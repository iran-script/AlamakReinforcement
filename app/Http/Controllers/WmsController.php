<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWmsServiceRequest;
use App\Http\Requests\UpdateWmsLayerRequest;
use App\Http\Requests\UpdateWmsServiceRequest;
use App\Models\WmsEndpointAllowlist;
use App\Models\WmsLayer;
use App\Models\WmsService;
use App\Services\AuditLogger;
use App\Services\WmsCapabilitiesService;
use App\Services\WmsErrorResponder;
use App\Services\WmsLayerCrsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class WmsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('wms.manage') || $request->user()->can('wms.endpoint-approve'), 403);
        $scope=$this->scope($request); $services=WmsService::with('layers')->where('organization_id',$scope)->orderBy('name')->get(); $endpoints=$request->user()->can('wms.endpoint-approve') ? WmsEndpointAllowlist::latest()->get() : collect();
        return view('settings.wms',compact('services','endpoints'));
    }

    public function store(StoreWmsServiceRequest $request, WmsCapabilitiesService $capabilities, AuditLogger $audit, WmsErrorResponder $errors): RedirectResponse
    {
        try { $data=$capabilities->fetch($request->validated('url'),$request->validated('version')); $scope=$this->scope($request); $service=DB::transaction(function() use($request,$scope,$data,$audit): WmsService { $service=WmsService::create(['organization_id'=>$scope,'name'=>$request->validated('name'),'url'=>$request->validated('url'),'version'=>$data['version'],'is_active'=>$request->boolean('is_active',true),'default_transparent'=>$request->boolean('default_transparent',true),'capabilities_metadata'=>['formats'=>$data['formats'],'layer_count'=>count($data['layers'])],'last_checked_at'=>now()]); $this->syncLayers($service,$data['layers']); $audit->record('wms.service_created',$service,null,$audit->attributesFor($service)); return $service; }); return back()->with('success',"سرویس {$service->name} با {$service->layers()->count()} لایه ثبت شد."); }
        catch (Throwable $e) { return $errors->flash($request,$e,$request->input('url')); }
    }

    public function test(StoreWmsServiceRequest $request, WmsCapabilitiesService $capabilities, WmsErrorResponder $errors): JsonResponse
    {
        try { $data=$capabilities->fetch($request->validated('url'),$request->validated('version')); return response()->json(['success'=>true,'version'=>$data['version'],'formats'=>$data['formats'],'layers'=>collect($data['layers'])->map(fn($layer)=>['name'=>$layer['layer_name'],'title'=>$layer['title'],'crs'=>$layer['supported_crs']])->values()]); }
        catch (Throwable $e) { return $errors->json($e,$request->input('url')); }
    }

    public function update(UpdateWmsServiceRequest $request, WmsService $wmsService, WmsCapabilitiesService $capabilities, AuditLogger $audit, WmsErrorResponder $errors): RedirectResponse
    {
        $this->assertOwn($request,$wmsService); $payload=$request->validated();
        try { if (WmsService::query()->where('organization_id',$wmsService->organization_id)->where('name',$payload['name'])->where('id','<>',$wmsService->id)->exists()) return back()->withInput()->withErrors(['name'=>'سرویسی با این نام قبلاً ثبت شده است.']); $data=$capabilities->fetch($payload['url'],$payload['version'] ?? $wmsService->version); $before=$audit->attributesFor($wmsService); DB::transaction(function() use($wmsService,$payload,$data): void { $wmsService->update(['name'=>$payload['name'],'url'=>$payload['url'],'version'=>$data['version'],'is_active'=>array_key_exists('is_active',$payload)?(bool)$payload['is_active']:$wmsService->is_active,'default_transparent'=>array_key_exists('default_transparent',$payload)?(bool)$payload['default_transparent']:$wmsService->default_transparent,'capabilities_metadata'=>['formats'=>$data['formats'],'layer_count'=>count($data['layers'])],'last_checked_at'=>now()]); $this->syncLayers($wmsService,$data['layers']); }); $fresh=$wmsService->fresh(); $audit->record('wms.service_updated',$fresh,$before,$audit->attributesFor($fresh)); return back()->with('success','URL سرویس با موفقیت بررسی و به‌روزرسانی شد.'); }
        catch (Throwable $e) { return $errors->flash($request,$e,$payload['url'] ?? $wmsService->url); }
    }

    public function refresh(Request $request, WmsService $wmsService, WmsCapabilitiesService $capabilities, WmsErrorResponder $errors): RedirectResponse
    {
        $this->assertOwn($request,$wmsService); try { $data=$capabilities->fetch($wmsService->url,$wmsService->version); DB::transaction(function() use($wmsService,$data): void { $wmsService->update(['version'=>$data['version'],'capabilities_metadata'=>['formats'=>$data['formats'],'layer_count'=>count($data['layers'])],'last_checked_at'=>now()]); $this->syncLayers($wmsService,$data['layers']); }); return back()->with('success','GetCapabilities دوباره دریافت شد.'); } catch(Throwable $e) { return $errors->flash($request,$e,$wmsService->url); }
    }

    public function destroy(Request $request, WmsService $wmsService, WmsErrorResponder $errors): RedirectResponse
    { $this->assertOwn($request,$wmsService); try { $wmsService->delete(); return back()->with('success','سرویس WMS حذف شد.'); } catch(Throwable $e) { return $errors->flash($request,$e,$wmsService->url,'service'); } }

    public function updateLayer(UpdateWmsLayerRequest $request, WmsLayer $wmsLayer, WmsErrorResponder $errors): JsonResponse|RedirectResponse
    { $this->assertOwn($request,$wmsLayer->service); try { $data=$request->validated(); $data['default_visible']=$request->boolean('default_visible'); if($request->has('is_enabled'))$data['is_enabled']=$request->boolean('is_enabled'); $wmsLayer->update($data); return $request->expectsJson()?response()->json(['success'=>true]):back()->with('success','تنظیمات لایه ذخیره شد.'); } catch(Throwable $e) { return $request->expectsJson()?$errors->json($e):$errors->flash($request,$e,$wmsLayer->service?->url,'layer'); } }

    public function mapLayers(Request $request, WmsLayerCrsResolver $crsResolver): JsonResponse
    {
        abort_unless($request->user()->can('map.view'), 403);
        $scope = $this->scope($request);
        $proxyRoute = $request->is('api/v1/supervisor/map/*')
            ? 'v1.supervisor.map.wms.proxy'
            : 'map.wms.proxy';
        $absoluteProxyUrl = $proxyRoute === 'map.wms.proxy';
        $services = WmsService::with('layers')
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $scope))
            ->where('is_active', true)->orderBy('name')->get();

        $payload = $services->map(function ($service) use ($crsResolver, $proxyRoute, $absoluteProxyUrl) {
            $crsByLayer = $crsResolver->forService($service);

            return [
                'id' => $service->id,
                'name' => $service->name,
                'version' => $service->version,
                'layers' => $service->layers->where('is_enabled', true)
                    ->map(function ($layer) use ($service, $crsResolver, $crsByLayer, $proxyRoute, $absoluteProxyUrl) {
                        $crs = $crsResolver->forLayer($layer, $crsByLayer);
                        $preferred = $crsResolver->preferred($crs);
                        if ($preferred === null) return null;

                        return [
                            'id' => $layer->id,
                            'title' => $layer->display_name ?: $layer->title,
                            'layer_name' => $layer->layer_name,
                            'version' => $service->version,
                            'sort_order' => $layer->sort_order,
                            'opacity' => (float) $layer->opacity,
                            'default_visible' => $layer->default_visible,
                            'legend_url' => $layer->legend_url,
                            'url' => route($proxyRoute, ['wmsService' => $service, 'wmsLayer' => $layer], $absoluteProxyUrl),
                            'preferred_crs' => $preferred,
                        ];
                    })->filter()->values(),
            ];
        });

        return response()->json([
            'success' => true,
            'services' => $payload->values(),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function syncLayers(WmsService $service,array $incoming): void
    { $existing=$service->layers()->get()->keyBy('layer_name'); $unused=$existing->keyBy('id'); foreach($incoming as $i=>$layer){$match=$existing->get($layer['layer_name']); if(!$match)$match=$unused->first(fn(WmsLayer $old)=>$old->title===$layer['title']); $preserved=$match?['display_name'=>$match->display_name,'is_enabled'=>$match->is_enabled,'default_visible'=>$match->default_visible,'opacity'=>$match->opacity,'sort_order'=>$match->sort_order]:['display_name'=>$layer['title'],'is_enabled'=>true,'default_visible'=>false,'opacity'=>1,'sort_order'=>$i]; if($match)$unused->forget($match->id); $service->layers()->updateOrCreate(['layer_name'=>$layer['layer_name']],$layer+$preserved);} foreach($unused as $old)$old->update(['is_enabled'=>false,'metadata'=>array_merge($old->metadata ?: [],['sync_status'=>'missing_from_last_capabilities','last_seen_at'=>now()->toIso8601String()])]); }
    private function scope(Request $request): ?int { return $request->user()->isSuperAdmin()?null:(int)$request->user()->organization_id; }
    private function assertOwn(Request $request,WmsService $service): void { abort_unless($request->user()->can('wms.manage'),403); abort_unless($service->organization_id===$this->scope($request),403); }
}
