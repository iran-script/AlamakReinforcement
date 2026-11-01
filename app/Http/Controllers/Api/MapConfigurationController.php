<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MapProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapConfigurationController extends Controller
{
    public function show(Request $request, MapProfileService $profiles): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        if ($request->is('api/v1/supervisor/*')) {
            abort_unless(! $user->isSuperAdmin() && $user->hasRole('supervisor', 'web') && $user->can('map.view'), 403);
        } else {
            abort_unless($user->isSuperAdmin() || $user->can('map.view'), 403);
        }

        $profile = $profiles->activeFor($user);
        if ($profile && ! $user->isSuperAdmin()) {
            $sameScope = $profile->organization_id === null || (int) $profile->organization_id === (int) $user->organization_id;
            abort_unless($sameScope, 403);
        }

        return response()->json([
            'schema_version' => 1,
            'map_profile' => $profiles->payload($profile),
        ])->header('Cache-Control', 'private, no-store');
    }
}
