<?php

namespace App\Services;

use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ZoneBoundaryService
{
    public function hasValidBoundary(User $user): bool
    {
        return $this->boundaryQuery($user)->exists();
    }

    public function boundary(User $user): ?Zone
    {
        return $this->boundaryQuery($user)->first();
    }

    public function assertWithin(User $user, string $geometryJson): void
    {
        $boundary = $this->boundary($user);
        if (! $boundary) {
            throw ValidationException::withMessages([
                'geometry' => 'محدوده معتبر برای Zone اصلی Boss پیدا نشد.',
            ]);
        }

        $result = DB::selectOne(
            'SELECT ST_Covers(boundary.geom, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) AS contained
             FROM zone AS boundary
             WHERE boundary.id = ?',
            [$geometryJson, $boundary->id]
        );

        $contained = $result && in_array($result->contained, [true, 1, '1', 't', 'true'], true);
        if (! $contained) {
            throw ValidationException::withMessages([
                'geometry' => 'هندسه Zone باید کاملاً داخل محدوده Zone اصلی Boss باشد.',
            ]);
        }
    }

    private function boundaryQuery(User $user)
    {
        return Zone::query()
            ->whereKey($user->zone_id)
            ->where('organization_id', $user->organization_id)
            ->whereNotNull('geom')
            ->whereRaw('ST_IsValid(geom)');
    }
}
