<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWmsEndpointRequest;
use App\Models\WmsEndpointAllowlist;
use App\Services\AuditLogger;
use App\Services\WmsEndpointPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WmsEndpointController extends Controller
{
    public function store(StoreWmsEndpointRequest $request, WmsEndpointPolicy $policy, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $host = strtolower(rtrim($data['host'], '.'));
        if (in_array($host, ['localhost', 'metadata.google.internal', '169.254.169.254'], true)) return back()->withInput()->withErrors(['host'=>'این مقصد حساس قابل ثبت نیست.']);
        if (filter_var($host, FILTER_VALIDATE_IP) && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return back()->withInput()->withErrors(['host'=>'فرمت IP معتبر نیست.']);
        $path = rtrim($data['path_prefix'], '/');
        if (app()->environment('local') && $host === '127.0.0.1' && ((int) $data['port'] !== 8080 || $data['scheme'] !== 'http' || $path !== '/geoserver/tamirat/wms')) return back()->withInput()->withErrors(['path_prefix'=>'در محیط توسعه فقط مقصد دقیق GeoServer محلی قابل ثبت است.']);
        $ips = $policy->resolve($host);
        if ($ips === []) return back()->withErrors(['host' => 'میزبان قابل resolve نیست.'])->withInput();
        $endpoint = WmsEndpointAllowlist::query()->firstOrNew(['scheme'=>$data['scheme'], 'host'=>$host, 'port'=>(int)$data['port'], 'path_prefix'=>$path]);
        if ($endpoint->exists && $endpoint->status === 'approved') return back()->with('success', 'این مقصد قبلاً تأیید شده است.');
        $endpoint->fill(['resolved_ips'=>$ips, 'status'=>'pending', 'requested_by'=>$request->user()->id, 'rejected_at'=>null, 'decision_note'=>null]);
        $endpoint->save();
        $audit->record('wms.endpoint_requested', $endpoint, null, ['scheme'=>$endpoint->scheme, 'host'=>$endpoint->host, 'port'=>$endpoint->port, 'path_prefix'=>$endpoint->path_prefix, 'status'=>$endpoint->status]);
        return back()->with('success', 'درخواست مقصد ثبت شد و پس از تأیید مدیر امنیتی قابل استفاده است.');
    }

    public function approve(Request $request, WmsEndpointAllowlist $endpoint, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('wms.endpoint-approve'), 403);
        if ($endpoint->host === '127.0.0.1' && (! app()->environment('local') || $endpoint->scheme !== 'http' || $endpoint->port !== 8080 || $endpoint->path_prefix !== '/geoserver/tamirat/wms')) return back()->withErrors(['endpoint'=>'این مقصد محلی مجاز نیست.']);
        $before = $audit->attributesFor($endpoint);
        $endpoint->update(['status'=>'approved', 'approved_by'=>$request->user()->id, 'approved_at'=>now(), 'rejected_at'=>null, 'decision_note'=>$request->string('decision_note')->toString() ?: null]);
        $audit->record('wms.endpoint_approved', $endpoint, $before, $audit->attributesFor($endpoint));
        return back()->with('success', 'مقصد WMS تأیید شد.');
    }

    public function reject(Request $request, WmsEndpointAllowlist $endpoint, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('wms.endpoint-approve'), 403);
        $before = $audit->attributesFor($endpoint);
        $endpoint->update(['status'=>'rejected', 'rejected_at'=>now(), 'approved_by'=>null, 'approved_at'=>null, 'decision_note'=>$request->string('decision_note')->toString() ?: null]);
        $audit->record('wms.endpoint_rejected', $endpoint, $before, $audit->attributesFor($endpoint));
        return back()->with('success', 'مقصد WMS رد شد.');
    }
}
