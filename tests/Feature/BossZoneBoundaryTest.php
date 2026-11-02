<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BossZoneBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_boss_can_create_a_zone_inside_or_on_the_boundary(): void
    {
        $this->requireSchema();
        [$boss, $boundary] = $this->bossWithBoundary();
        $geometry = ['type' => 'Polygon', 'coordinates' => [[
            [49.0, 34.2], [49.8, 34.2], [49.8, 34.8], [49.0, 34.8], [49.0, 34.2],
        ]]];

        $this->actingAs($boss)->postJson(route('zones.store'), ['name' => 'داخل محدوده', 'geometry' => $geometry])
            ->assertOk();
        $this->assertDatabaseHas('zone', ['name' => 'داخل محدوده', 'organization_id' => $boundary->organization_id]);
    }

    public function test_boss_cannot_create_a_zone_outside_the_boundary(): void
    {
        $this->requireSchema();
        [$boss] = $this->bossWithBoundary();
        $geometry = ['type' => 'Polygon', 'coordinates' => [[
            [48.0, 33.0], [48.5, 33.0], [48.5, 33.5], [48.0, 33.5], [48.0, 33.0],
        ]]];

        $this->actingAs($boss)->postJson(route('zones.store'), ['name' => 'خارج محدوده', 'geometry' => $geometry])
            ->assertUnprocessable()->assertJsonValidationErrors('geometry');
        $this->assertDatabaseMissing('zone', ['name' => 'خارج محدوده']);
    }

    public function test_boss_without_a_valid_assigned_boundary_cannot_create_a_zone(): void
    {
        $this->requireSchema();
        $organization = Organization::query()->create(['code' => 'boss-no-boundary-'.uniqid(), 'name' => 'No Boundary', 'status' => 'active']);
        $boss = $this->boss($organization, null);

        $this->actingAs($boss)->get(route('zones.manage'))->assertOk()->assertSee('محدودهٔ جغرافیایی');
        $this->actingAs($boss)->postJson(route('zones.store'), [
            'name' => 'بدون مرز', 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[49, 34], [49.1, 34], [49.1, 34.1], [49, 34.1], [49, 34]]]],
        ])->assertForbidden();
    }

    public function test_boss_cannot_update_or_delete_the_assigned_boundary(): void
    {
        $this->requireSchema();
        [$boss, $boundary] = $this->bossWithBoundary();

        $this->actingAs($boss)->putJson(route('zones.update', ['zone' => $boundary->id]), [
            'name' => 'تغییر غیرمجاز',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[49, 34], [50, 34], [50, 35], [49, 35], [49, 34]]]],
        ])->assertForbidden();

        $this->actingAs($boss)->deleteJson(route('zones.destroy', ['zone' => $boundary->id]))
            ->assertForbidden();
    }

    private function requireSchema(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('organizations') || ! Schema::hasTable('zone')) {
            $this->markTestSkipped('Boss zone boundary tests require PostgreSQL/PostGIS.');
        }
    }

    /** @return array{0: User, 1: object} */
    private function bossWithBoundary(): array
    {
        $organization = Organization::query()->create(['code' => 'boss-boundary-'.uniqid(), 'name' => 'Boundary Org', 'status' => 'active']);
        $boundary = DB::selectOne(
            "INSERT INTO zone (name, organization_id, geom, created_at, updated_at) VALUES (?, ?, ST_MakeEnvelope(49,34,50,35,4326), now(), now()) RETURNING id, organization_id",
            ['Boss boundary '.uniqid(), $organization->id]
        );
        $boss = $this->boss($organization, (int) $boundary->id);

        return [$boss, $boundary];
    }

    private function boss(Organization $organization, ?int $zoneId): User
    {
        $role = Role::findOrCreate('boss', 'web');
        $user = User::query()->create([
            'name' => 'Boss', 'username' => 'boss-'.uniqid(), 'email' => 'boss-'.uniqid().'@example.test',
            'password' => 'StrongPass123', 'role_id' => $role->id,
            'organization_id' => $organization->id, 'zone_id' => $zoneId,
        ]);
        $user->assignRole($role);
        $user->givePermissionTo(Permission::findOrCreate('zone.view', 'web'));

        return $user;
    }
}
