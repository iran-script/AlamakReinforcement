<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\AuditLog;
use App\Models\DataAccessPolicy;
use App\Models\Organization;
use App\Models\SpatialScope;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformWorkOrderApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unauthenticated_api_is_rejected(): void
    {
        $this->getJson('/api/platform/work-orders')->assertUnauthorized();
    }

    public function test_policy_denies_user_without_policy(): void
    {
        $this->requirePostgisSchema();
        Sanctum::actingAs($this->user($this->organization('deny')));

        $this->getJson('/api/platform/work-orders')->assertForbidden();
    }

    public function test_organization_isolation_denies_foreign_work_order(): void
    {
        $this->requirePostgisSchema();
        $organization = $this->organization('one');
        $user = $this->user($organization);
        $this->allow($user, $organization, ['view']);
        $foreign = $this->workOrder($this->organization('two'));
        Sanctum::actingAs($user);

        $this->getJson("/api/platform/work-orders/{$foreign->id}")->assertForbidden();
    }

    public function test_create_work_order_writes_audit_log(): void
    {
        $this->requirePostgisSchema();
        $organization = $this->organization('create');
        $user = $this->user($organization);
        $this->allow($user, $organization, ['create']);
        $type = $this->type($organization);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/platform/work-orders', [
            'work_order_type_id' => $type->id,
            'title' => 'بازرسی ایمنی',
            'priority' => 'high',
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('work_orders', ['id' => $response->json('data.id'), 'organization_id' => $organization->id]);
        $this->assertTrue(AuditLog::query()->where('event', 'work_order.created')->where('actor_id', $user->id)->exists());
    }

    public function test_visible_asset_can_be_attached_once(): void
    {
        $this->requirePostgisSchema();
        $organization = $this->organization('asset');
        $user = $this->user($organization);
        $this->allow($user, $organization, ['attach_asset']);
        $workOrder = $this->workOrder($organization);
        $assetType = AssetType::query()->create(['organization_id' => $organization->id, 'key' => 'test', 'name' => 'Test']);
        $asset = Asset::query()->create(['organization_id' => $organization->id, 'asset_type_id' => $assetType->id, 'code' => 'A-1']);
        DB::statement('UPDATE assets SET geom = ST_SetSRID(ST_MakePoint(51, 35), 4326) WHERE id = ?', [$asset->id]);
        $scope = SpatialScope::query()->create(['organization_id' => $organization->id, 'name' => 'Test scope']);
        DB::statement("UPDATE spatial_scopes SET geom = ST_GeomFromText('MULTIPOLYGON(((50 34,52 34,52 36,50 36,50 34)))', 4326) WHERE id = ?", [$scope->id]);
        $user->spatialScopes()->attach($scope->id, ['access_level' => 'edit']);
        Sanctum::actingAs($user);

        $payload = ['asset_id' => $asset->id, 'relationship_type' => 'primary'];
        $this->postJson("/api/platform/work-orders/{$workOrder->id}/assets", $payload)->assertCreated();
        $this->postJson("/api/platform/work-orders/{$workOrder->id}/assets", $payload)->assertUnprocessable();
        $this->assertDatabaseHas('work_order_assets', ['work_order_id' => $workOrder->id, 'asset_id' => $asset->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'work_order.asset_attached', 'auditable_id' => $workOrder->id]);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $this->requirePostgisSchema();
        $organization = $this->organization('status');
        $user = $this->user($organization);
        $this->allow($user, $organization, ['change_status']);
        $workOrder = $this->workOrder($organization);
        Sanctum::actingAs($user);

        $this->postJson("/api/platform/work-orders/{$workOrder->id}/status", ['status' => 'completed'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id, 'status' => 'draft']);
    }

    private function requirePostgisSchema(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('work_orders')) {
            $this->markTestSkipped('PostgreSQL/PostGIS work-management schema is required.');
        }
    }

    private function organization(string $suffix): Organization
    {
        return Organization::query()->create(['code' => 'test-'.$suffix.'-'.uniqid(), 'name' => 'Test']);
    }

    private function user(Organization $organization): User
    {
        return User::query()->create([
            'organization_id' => $organization->id, 'name' => 'Tester', 'username' => 'tester-'.uniqid(),
            'password' => 'password', 'role_id' => 1,
        ]);
    }

    private function allow(User $user, Organization $organization, array $actions): void
    {
        DataAccessPolicy::query()->create([
            'organization_id' => $organization->id, 'name' => 'Test policy', 'principal_type' => 'user',
            'principal_id' => $user->id, 'resource_key' => 'work_orders', 'actions' => $actions,
            'effect' => 'allow', 'is_active' => true,
        ]);
    }

    private function type(Organization $organization): WorkOrderType
    {
        return WorkOrderType::query()->create(['organization_id' => $organization->id, 'key' => 'repair', 'name' => 'Repair']);
    }

    private function workOrder(Organization $organization): WorkOrder
    {
        return WorkOrder::query()->create([
            'organization_id' => $organization->id, 'work_order_type_id' => $this->type($organization)->id,
            'title' => 'Test work order', 'status' => 'draft', 'priority' => 'normal',
        ]);
    }
}
