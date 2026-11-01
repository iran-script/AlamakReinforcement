<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGisMapLayerRequest;
use App\Http\Requests\SaveGisMapProfileRequest;
use App\Http\Requests\UpdateGisMapSettingRequest;
use App\Models\GisMapLayer;
use App\Models\GisMapSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class GisSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        Gate::authorize('create', GisMapSetting::class);
        $organizationId = $request->user()->hasRole('superadmin') ? null : $request->user()->organization_id;
        abort_if($organizationId === null && ! $request->user()->hasRole('superadmin'), 403);
        $profiles = GisMapSetting::query()->where('organization_id', $organizationId)->orderByDesc('is_active')->orderBy('name')->get();
        $setting = $profiles->firstWhere('is_active', true) ?? $profiles->first();
        $layers = GisMapLayer::query()->where('organization_id', $organizationId)->latest()->get();

        return view('settings.gis-map', compact('setting', 'profiles', 'layers'));
    }

    public function testService(Request $request, \App\Services\MapServiceUrlValidator $urls)
    {
        Gate::authorize('create', GisMapSetting::class);
        $validator = Validator::make($request->all(), [
            'service_type' => ['required', 'in:xyz_raster,vector_mvt'],
            'base_tile_url' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail) use ($urls): void {
                if ($error = $urls->error((string) $value, true)) $fail($error);
            }],
        ]);
        if ($validator->fails()) return response()->json(['valid' => false, 'errors' => $validator->errors()], 422);

        // The browser performs the tile request after this policy-only check. Laravel never fetches
        // a user-supplied URL, so this endpoint cannot be used as an SSRF proxy.
        return response()->json(['valid' => true]);
    }

    public function update(UpdateGisMapSettingRequest $request, AuditLogger $audit): RedirectResponse
    {
        $organizationId = $request->user()->hasRole('superadmin') ? null : $request->user()->organization_id;
        abort_if($organizationId === null && ! $request->user()->hasRole('superadmin'), 403);

        DB::transaction(function () use ($request, $audit, $organizationId): void {
            $setting = GisMapSetting::query()->where('organization_id', $organizationId)->where('is_active', true)->lockForUpdate()->first()
                ?? GisMapSetting::query()->where('organization_id', $organizationId)->orderBy('id')->lockForUpdate()->first();
            $before = $setting ? $audit->attributesFor($setting) : null;
            $setting ??= new GisMapSetting([
                'organization_id' => $organizationId, 'name' => 'Default', 'service_type' => 'xyz_raster',
                'min_zoom' => 0, 'max_zoom' => 22,
            ]);
            $setting->fill($request->validated());
            $setting->is_active = $request->boolean('is_active');
            $setting->updated_by = $request->user()->id;
            $setting->save();

            $audit->record($before ? 'gis.settings_updated' : 'gis.settings_created', $setting, $before, $audit->attributesFor($setting));
        });

        return back()->with('success', 'تنظیمات نقشه با موفقیت ذخیره شد.');
    }

    public function storeProfile(SaveGisMapProfileRequest $request, AuditLogger $audit): RedirectResponse
    {
        $organizationId = $this->scopeId($request);
        $profile = DB::transaction(function () use ($request, $audit, $organizationId): GisMapSetting {
            $isFirst = ! GisMapSetting::query()->where('organization_id', $organizationId)->exists();
            $profile = GisMapSetting::query()->create([
                ...$request->validated(),
                'organization_id' => $organizationId,
                'is_active' => $isFirst,
                'updated_by' => $request->user()->id,
            ]);
            $audit->record('gis.map_profile_created', $profile, null, $audit->attributesFor($profile));
            return $profile;
        });

        return back()->with('success', "پروفایل «{$profile->name}» ثبت شد.");
    }

    public function updateProfile(SaveGisMapProfileRequest $request, GisMapSetting $gisMapSetting, AuditLogger $audit): RedirectResponse
    {
        $this->assertOwnProfile($request, $gisMapSetting);
        DB::transaction(function () use ($request, $gisMapSetting, $audit): void {
            $profile = GisMapSetting::query()->lockForUpdate()->findOrFail($gisMapSetting->id);
            $before = $audit->attributesFor($profile);
            $profile->fill($request->validated());
            $profile->updated_by = $request->user()->id;
            $profile->save();
            $audit->record('gis.map_profile_updated', $profile, $before, $audit->attributesFor($profile));
        });
        return back()->with('success', 'پروفایل نقشه به‌روزرسانی شد.');
    }

    public function activateProfile(Request $request, GisMapSetting $gisMapSetting, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('create', GisMapSetting::class);
        $this->assertOwnProfile($request, $gisMapSetting);
        DB::transaction(function () use ($request, $gisMapSetting, $audit): void {
            $scope = $this->scopeId($request);
            $previous = GisMapSetting::query()->where('organization_id', $scope)->where('is_active', true)->lockForUpdate()->first();
            GisMapSetting::query()->where('organization_id', $scope)->where('is_active', true)
                ->update(['is_active' => false, 'updated_by' => $request->user()->id, 'updated_at' => now()]);
            $profile = GisMapSetting::query()->lockForUpdate()->findOrFail($gisMapSetting->id);
            $profile->is_active = true;
            $profile->updated_by = $request->user()->id;
            $profile->save();
            $audit->record('gis.map_profile_activated', $profile, ['active_profile_id' => $previous?->id], ['active_profile_id' => $profile->id]);
        });
        return back()->with('success', "پروفایل «{$gisMapSetting->name}» فعال شد.");
    }

    public function destroyProfile(Request $request, GisMapSetting $gisMapSetting, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('create', GisMapSetting::class);
        $this->assertOwnProfile($request, $gisMapSetting);
        abort_if($gisMapSetting->is_active, 422, 'ابتدا پروفایل دیگری را فعال کنید.');
        DB::transaction(function () use ($gisMapSetting, $audit): void {
            $before = $audit->attributesFor($gisMapSetting);
            $audit->record('gis.map_profile_deleted', $gisMapSetting, $before, null);
            $gisMapSetting->delete();
        });
        return back()->with('success', 'پروفایل غیرفعال حذف شد.');
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

    private function scopeId(Request $request): ?int
    {
        $organizationId = $request->user()->hasRole('superadmin') ? null : $request->user()->organization_id;
        abort_if($organizationId === null && ! $request->user()->hasRole('superadmin'), 403);
        return $organizationId === null ? null : (int) $organizationId;
    }

    private function assertOwnProfile(Request $request, GisMapSetting $profile): void
    {
        Gate::authorize('create', GisMapSetting::class);
        abort_unless($profile->organization_id === $this->scopeId($request), 403);
    }
}
