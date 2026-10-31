<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGisMapLayerRequest;
use App\Http\Requests\UpdateGisMapSettingRequest;
use App\Models\GisMapLayer;
use App\Models\GisMapSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GisSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        Gate::authorize('create', GisMapSetting::class);
        $organizationId = $request->user()->hasRole('superadmin') ? null : $request->user()->organization_id;
        abort_if($organizationId === null && ! $request->user()->hasRole('superadmin'), 403);
        $setting = GisMapSetting::query()->where('organization_id', $organizationId)->first();
        $layers = GisMapLayer::query()->where('organization_id', $organizationId)->latest()->get();

        return view('settings.gis-map', compact('setting', 'layers'));
    }

    public function update(UpdateGisMapSettingRequest $request, AuditLogger $audit): RedirectResponse
    {
        $organizationId = $request->user()->hasRole('superadmin') ? null : $request->user()->organization_id;
        abort_if($organizationId === null && ! $request->user()->hasRole('superadmin'), 403);

        DB::transaction(function () use ($request, $audit, $organizationId): void {
            $setting = GisMapSetting::query()->where('organization_id', $organizationId)->lockForUpdate()->first();
            $before = $setting ? $audit->attributesFor($setting) : null;
            $setting ??= new GisMapSetting(['organization_id' => $organizationId]);
            $setting->fill($request->validated());
            $setting->is_active = $request->boolean('is_active');
            $setting->updated_by = $request->user()->id;
            $setting->save();

            $audit->record($before ? 'gis.settings_updated' : 'gis.settings_created', $setting, $before, $audit->attributesFor($setting));
        });

        return back()->with('success', 'تنظیمات نقشه با موفقیت ذخیره شد.');
    }

    public function storeLayer(StoreGisMapLayerRequest $request, AuditLogger $audit): RedirectResponse
    {
        $organizationId = $request->user()->hasRole('superadmin') ? null : $request->user()->organization_id;
        abort_if($organizationId === null && ! $request->user()->hasRole('superadmin'), 403);

        $layer = DB::transaction(function () use ($request, $organizationId, $audit): GisMapLayer {
            $layer = GisMapLayer::create([
                ...$request->validated(),
                'organization_id' => $organizationId,
                'transparent' => $request->boolean('transparent'),
                'is_active' => $request->boolean('is_active'),
                'updated_by' => $request->user()->id,
            ]);
            $audit->record('gis.layer_created', $layer, null, $audit->attributesFor($layer));

            return $layer;
        });

        return back()->with('success', "لایه «{$layer->title}» اضافه شد.");
    }

    public function destroyLayer(Request $request, GisMapLayer $gisMapLayer, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('create', GisMapSetting::class);
        $organizationId = $request->user()->hasRole('superadmin') ? null : $request->user()->organization_id;
        $sameScope = $organizationId === null
            ? $gisMapLayer->organization_id === null
            : (int) $gisMapLayer->organization_id === (int) $organizationId;
        abort_unless($sameScope, 403);

        DB::transaction(function () use ($gisMapLayer, $audit): void {
            $before = $audit->attributesFor($gisMapLayer);
            $audit->record('gis.layer_deleted', $gisMapLayer, $before, null);
            $gisMapLayer->delete();
        });

        return back()->with('success', 'لایه حذف شد.');
    }
}
