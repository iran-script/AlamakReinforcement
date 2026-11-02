<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RiserIndexTest extends TestCase
{
    use DatabaseTransactions;

    public function test_superadmin_can_search_filter_sort_and_paginate_risers(): void
    {
        $this->requireSchema();
        $admin = $this->user('superadmin');
        $this->insertRiser('R-ALPHA', 'G-001', 'default', 49.1, 34.1);
        $this->insertRiser('R-BETA', 'G-002', 'approved', 49.2, 34.2);

        $response = $this->actingAs($admin)->get(route('riserIndex', [
            'q' => 'BETA', 'status' => 'approved', 'sort' => 'id', 'direction' => 'asc', 'per_page' => 25,
        ]));

        $response->assertOk()->assertSee('R-BETA')->assertDontSee('R-ALPHA');
    }

    public function test_non_superadmin_cannot_see_a_riser_outside_their_zone(): void
    {
        $this->requireSchema();
        $zoneId = (int) DB::selectOne(
            'INSERT INTO zone (name, geom, created_at, updated_at) VALUES (?, ST_MakeEnvelope(49,34,50,35,4326), now(), now()) RETURNING id',
            ['Riser index scope '.uniqid()]
        )->id;
        $boss = $this->user('boss', $zoneId);
        $this->insertRiser('R-IN', 'G-IN', 'default', 49.5, 34.5);
        $this->insertRiser('R-OUT', 'G-OUT', 'default', 51.5, 36.5);

        $this->actingAs($boss)->get(route('riserIndex'))
            ->assertOk()
            ->assertSee('R-IN')
            ->assertDontSee('R-OUT');
    }

    private function requireSchema(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('riser') || ! Schema::hasTable('zone')) {
            $this->markTestSkipped('Riser index tests require PostgreSQL/PostGIS.');
        }
    }

    private function user(string $roleName, ?int $zoneId = null): User
    {
        $role = Role::findOrCreate($roleName, 'web');
        $user = User::query()->create([
            'name' => ucfirst($roleName), 'username' => $roleName.'-'.uniqid(),
            'email' => $roleName.'-'.uniqid().'@example.test', 'password' => 'StrongPass123',
            'role_id' => $role->id, 'zone_id' => $zoneId,
        ]);
        $user->assignRole($role);
        if ($roleName !== 'superadmin') {
            $user->givePermissionTo(Permission::findOrCreate('riser.view', 'web'));
        }

        return $user;
    }

    private function insertRiser(string $rGisCode, string $gisCode, string $status, float $longitude, float $latitude): void
    {
        DB::statement(
            'INSERT INTO riser (r_giscode, giscode, status, geom, geom_3857, created_at, updated_at)
             VALUES (?, ?, ?, ST_SetSRID(ST_Point(?, ?), 4326), ST_Transform(ST_SetSRID(ST_Point(?, ?), 4326), 3857), now(), now())',
            [$rGisCode, $gisCode, $status, $longitude, $latitude, $longitude, $latitude]
        );
    }
}
