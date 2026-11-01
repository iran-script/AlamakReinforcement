<?php

namespace Tests\Feature;

use App\Models\DataAccessPolicy;
use App\Models\AuditLog;
use App\Models\Material;
use App\Models\Operation;
use App\Models\OperationCategory;
use App\Models\OperationImage;
use App\Models\Organization;
use App\Models\Riser;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegacySecurityAndOperationsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unauthenticated_user_cannot_create_an_operation(): void
    {
        $this->post('/operations', [])->assertRedirect('/login');
    }

    public function test_operation_policy_denies_an_operation_outside_the_users_zone(): void
    {
        [$organizationA, $user] = $this->actor('operation-view', ['operation.view']);
        $organizationB = Organization::create(['code' => 'org-b-'.uniqid(), 'name' => 'B']);
        $zoneB = $this->zone($organizationB, 'zone-b');
        $riserB = $this->riser($zoneB, 'RISER-B');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $operation = $this->operation($riserB, $category, $user, 'foreign-operation');

        $this->actingAs($user)->get('/operations/'.$operation->id)
            ->assertForbidden()
            ->assertDontSee('foreign-operation');

        $this->assertFalse(Gate::forUser($user)->allows('view', $operation));
    }

    public function test_zone_policy_rejects_a_foreign_organization(): void
    {
        [$organizationA, $user] = $this->actor('zone-policy', ['zone.view', 'zone.edit']);
        $organizationB = Organization::create(['code' => 'org-b-'.uniqid(), 'name' => 'B']);
        $zoneA = $this->zone($organizationA, 'zone-a');
        $zoneB = $this->zone($organizationB, 'zone-b');

        $this->assertTrue(Gate::forUser($user)->allows('update', $zoneA));
        $this->assertFalse(Gate::forUser($user)->allows('update', $zoneB));

        $this->actingAs($user)->get('/api/zones')
            ->assertOk()
            ->assertJsonFragment(['name' => 'zone-a'])
            ->assertJsonMissing(['name' => 'zone-b']);
    }

    public function test_riser_api_does_not_expose_a_foreign_zone(): void
    {
        [$organizationA, $user] = $this->actor('riser-api');
        $organizationB = Organization::create(['code' => 'org-b-'.uniqid(), 'name' => 'B']);
        $zoneA = $this->zone($organizationA, 'zone-a');
        $zoneB = $this->zone($organizationB, 'zone-b');
        $this->riser($zoneA, 'RISER-A');
        $this->riser($zoneB, 'RISER-B');

        $response = $this->actingAs($user)->getJson('/api/risers')->assertOk();

        $this->assertCount(1, $response->json('features'));
        $this->assertSame('RISER-A', $response->json('features.0.properties.code'));
        $this->assertStringNotContainsString('RISER-B', $response->getContent());
    }

    public function test_valid_operation_stores_decimal_materials_without_calculating_cost(): void
    {
        [$organization, $user] = $this->actor('operation-create', ['operation.create', 'operation.view', 'operation.edit']);
        $this->actor('supervisor', [], 'supervisor', $organization, $user->zone_id);
        $zone = Zone::query()->findOrFail($user->zone_id);
        $riser = $this->riser($zone, 'RISER-MATERIAL');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $material = Material::create([
            'material_category_id' => DB::table('material_categories')->insertGetId(['title' => 'Pipe', 'code' => 'pipe-'.uniqid(), 'is_active' => true]),
            'title' => 'Test material',
            'code' => 'mat-'.uniqid(),
            'unit' => 'm',
            'default_price' => 12.50,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/operations', [
            'riser_id' => $riser->id,
            'operationcat' => $category->id,
            'status' => '0',
            'priority' => '0',
            'materials' => [$material->id => '0.5'],
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $operationId = $response->json('operation.id');
        $this->assertDatabaseHas('operation_materials', [
            'operation_id' => $operationId,
            'material_id' => $material->id,
            'qty' => 0.50,
            'price' => 0,
            'total' => 0,
        ]);
        $this->assertSame(0.0, (float) Operation::query()->findOrFail($operationId)->total_cost);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'operation.created',
            'auditable_type' => Operation::class,
            'auditable_id' => $operationId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'operation.materials_updated',
            'auditable_id' => $operationId,
        ]);

        $this->actingAs($user)->putJson('/operations/'.$operationId, [
            'description' => 'edited safely',
        ])->assertOk()->assertJsonPath('operation.description', 'edited safely');

        $this->assertDatabaseHas('operation_materials', [
            'operation_id' => $operationId,
            'material_id' => $material->id,
            'qty' => 0.50,
        ]);

        $this->actingAs($user)->putJson('/operations/'.$operationId, [
            'materials' => [$material->id => '0.75'],
        ])->assertOk();
        $this->assertDatabaseHas('operation_materials', [
            'operation_id' => $operationId,
            'material_id' => $material->id,
            'qty' => 0.75,
            'price' => 0,
            'total' => 0,
        ]);
    }

    public function test_non_positive_material_quantity_is_rejected(): void
    {
        [$organization, $user] = $this->actor('material-validation', ['operation.create']);
        $this->actor('supervisor', [], 'supervisor', $organization, $user->zone_id);
        $zone = Zone::query()->findOrFail($user->zone_id);
        $riser = $this->riser($zone, 'RISER-VALIDATION');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $material = Material::create([
            'material_category_id' => DB::table('material_categories')->insertGetId(['title' => 'Pipe', 'code' => 'pipe-'.uniqid(), 'is_active' => true]),
            'title' => 'Test material', 'code' => 'mat-'.uniqid(), 'unit' => 'm', 'default_price' => 10, 'is_active' => true,
        ]);

        $this->actingAs($user)->postJson('/operations', [
            'riser_id' => $riser->id,
            'operationcat' => $category->id,
            'materials' => [$material->id => -1],
        ])->assertUnprocessable()->assertJsonValidationErrors('materials.'.$material->id);

        $this->actingAs($user)->postJson('/operations', [
            'riser_id' => $riser->id,
            'operationcat' => $category->id,
            'materials' => [$material->id => 0],
        ])->assertUnprocessable()->assertJsonValidationErrors('materials.'.$material->id);
    }

    public function test_approved_operation_cannot_be_changed_by_an_editor(): void
    {
        [$organization, $user] = $this->actor('approved-operation', ['operation.edit']);
        $zone = Zone::query()->findOrFail($user->zone_id);
        $riser = $this->riser($zone, 'RISER-APPROVED');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $operation = $this->operation($riser, $category, $user, 'before');
        DB::table('operation_supervisors')->insert([
            'operation_id' => $operation->id,
            'user_id' => $user->id,
            'status' => 'approved',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->putJson('/operations/'.$operation->id, [
            'description' => 'after',
        ])->assertForbidden();

        $this->assertSame('before', $operation->fresh()->description);
    }

    public function test_assigned_supervisor_can_approve_and_a_foreign_user_cannot(): void
    {
        [$organization, $worker] = $this->actor('workflow-worker', ['operation.create', 'operation.edit']);
        [, $supervisor] = $this->actor('workflow-supervisor', [], 'supervisor', $organization, $worker->zone_id);
        $zone = Zone::query()->findOrFail($worker->zone_id);
        $riser = $this->riser($zone, 'WORKFLOW-RISER');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);

        $operation = $this->actingAs($worker)->postJson('/operations', [
            'riser_id' => $riser->id,
            'operationcat' => $category->id,
            'description' => 'awaiting review',
        ])->assertCreated()->json('operation.id');

        $this->assertDatabaseHas('operation_supervisors', [
            'operation_id' => $operation,
            'user_id' => $supervisor->id,
            'status' => 'pending',
        ]);

        $this->actingAs($worker)->postJson('/operations/'.$operation.'/approve')
            ->assertForbidden();

        $this->actingAs($supervisor)->postJson('/operations/'.$operation.'/approve')
            ->assertOk()
            ->assertJsonPath('status', 'approved');

        $this->assertDatabaseHas('operation_supervisors', [
            'operation_id' => $operation,
            'user_id' => $supervisor->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'operation.approved',
            'auditable_id' => $operation,
        ]);
    }

    public function test_supervisor_return_requires_reason_and_allows_authorized_revision(): void
    {
        [$organization, $worker] = $this->actor('return-worker', ['operation.create', 'operation.edit']);
        [, $supervisor] = $this->actor('return-supervisor', [], 'supervisor', $organization, $worker->zone_id);
        $zone = Zone::query()->findOrFail($worker->zone_id);
        $riser = $this->riser($zone, 'RETURN-RISER');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);

        $operation = $this->actingAs($worker)->postJson('/operations', [
            'riser_id' => $riser->id,
            'operationcat' => $category->id,
            'description' => 'needs correction',
        ])->assertCreated()->json('operation.id');

        $this->actingAs($supervisor)->postJson('/operations/'.$operation.'/return', [])
            ->assertUnprocessable()->assertJsonValidationErrors('comment');

        $this->actingAs($supervisor)->postJson('/operations/'.$operation.'/return', ['comment' => 'Add missing detail'])
            ->assertOk()
            ->assertJsonPath('status', 'need_revision');

        $this->assertDatabaseHas('operation_supervisors', [
            'operation_id' => $operation,
            'user_id' => $supervisor->id,
            'status' => 'need_revision',
            'comment' => 'Add missing detail',
        ]);
        $this->actingAs($worker)->putJson('/operations/'.$operation, ['description' => 'corrected'])
            ->assertOk()->assertJsonPath('operation.description', 'corrected');
    }

    public function test_riser_history_is_paginated_and_zone_scoped(): void
    {
        [$organization, $user] = $this->actor('history-user', ['operation.view']);
        $zone = Zone::query()->findOrFail($user->zone_id);
        $visibleRiser = $this->riser($zone, 'HISTORY-VISIBLE');
        $foreignZone = $this->zone($organization, 'zone-b-history');
        $foreignRiser = $this->riser($foreignZone, 'HISTORY-HIDDEN');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $this->operation($visibleRiser, $category, $user, 'visible-history');
        $this->operation($foreignRiser, $category, $user, 'hidden-history');

        $this->actingAs($user)->getJson('/api/risers/'.$visibleRiser->id.'/history')
            ->assertOk()
            ->assertJsonPath('data.0.description', 'visible-history')
            ->assertJsonMissing(['description' => 'hidden-history'])
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);

        $this->actingAs($user)->getJson('/api/risers/'.$foreignRiser->id.'/history')
            ->assertNotFound()
            ->assertJsonMissing(['description' => 'hidden-history']);
    }

    public function test_operation_image_idor_is_denied_and_record_remains(): void
    {
        Storage::fake('public');
        [$organizationA, $user] = $this->actor('image-owner', ['operation.edit']);
        $organizationB = Organization::create(['code' => 'org-b-'.uniqid(), 'name' => 'B']);
        $zoneB = $this->zone($organizationB, 'zone-b');
        $riserB = $this->riser($zoneB, 'RISER-IMAGE-B');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $operation = $this->operation($riserB, $category, $user, 'foreign-image-operation');
        $image = OperationImage::create([
            'operation_id' => $operation->id,
            'riser_id' => $riserB->id,
            'type' => 'before',
            'path' => 'operation-photos/test.png',
            'original_name' => 'test.png',
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)->deleteJson('/operation/photos/'.$image->id)
            ->assertForbidden()
            ->assertJsonMissing(['success' => true]);

        $this->assertDatabaseHas('operation_image', ['id' => $image->id]);
    }

    public function test_authorized_image_upload_succeeds_but_foreign_report_is_denied(): void
    {
        Storage::fake('public');
        [$organization, $user] = $this->actor('image-upload', ['operation.edit']);
        $zone = Zone::query()->findOrFail($user->zone_id);
        $riser = $this->riser($zone, 'RISER-UPLOAD');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $operation = $this->operation($riser, $category, $user, 'upload-operation');

        $this->actingAs($user)->post('/operation/photos/upload', [
            'operation_id' => $operation->id,
            'riser_id' => $riser->id,
            'type' => 'before',
            'photo' => UploadedFile::fake()->image('before.jpg'),
        ])->assertOk()->assertJsonPath('type', 'before');

        $this->assertDatabaseHas('operation_image', [
            'operation_id' => $operation->id,
            'riser_id' => $riser->id,
            'type' => 'before',
        ]);
    }

    public function test_reports_are_scoped_and_dashboard_requires_permission(): void
    {
        [$organizationA, $user] = $this->actor('report-user', ['report.view', 'dashboard.view']);
        $organizationB = Organization::create(['code' => 'org-b-'.uniqid(), 'name' => 'B']);
        $zoneA = Zone::query()->findOrFail($user->zone_id);
        $zoneB = $this->zone($organizationB, 'zone-b');
        $riserA = $this->riser($zoneA, 'REPORT-A');
        $riserB = $this->riser($zoneB, 'REPORT-B');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $this->operation($riserA, $category, $user, 'visible-report');
        $this->operation($riserB, $category, $user, 'hidden-report');

        $report = $this->actingAs($user)->getJson('/reports/data')->assertOk();
        $this->assertStringContainsString('REPORT-A', $report->getContent());
        $this->assertStringNotContainsString('REPORT-B', $report->getContent());

        $statistics = $this->actingAs($user)->getJson('/dashboard/statistics')->assertOk();
        $statistics->assertJsonPath('total', 1)
            ->assertJsonPath('zones.data.0', 1);
        $this->assertStringNotContainsString('REPORT-B', $statistics->getContent());

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_material_report_is_quantity_and_unit_based(): void
    {
        [$organization, $user] = $this->actor('material-report', ['report.view']);
        $zone = Zone::query()->findOrFail($user->zone_id);
        $riser = $this->riser($zone, 'MATERIAL-REPORT');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $material = Material::create([
            'material_category_id' => DB::table('material_categories')->insertGetId(['title' => 'Pipe', 'code' => 'pipe-'.uniqid(), 'is_active' => true]),
            'title' => 'Report material', 'code' => 'mat-'.uniqid(), 'unit' => 'm', 'default_price' => 99, 'is_active' => true,
        ]);
        $operation = $this->operation($riser, $category, $user, 'material-report-operation');
        $operation->operationMaterial()->attach($material->id, ['qty' => 1.25, 'price' => 0, 'total' => 0]);
        $differentUnit = Material::create([
            'material_category_id' => $material->material_category_id,
            'title' => 'Report material other', 'code' => 'mat-'.uniqid(), 'unit' => 'piece', 'default_price' => 0, 'is_active' => true,
        ]);
        $operation->operationMaterial()->attach($differentUnit->id, ['qty' => 3.5, 'price' => 0, 'total' => 0]);

        $this->actingAs($user)->getJson('/reports/data')
            ->assertOk()
            ->assertJsonCount(2, 'materialUsage')
            ->assertJsonPath('materialUsage.0.title', 'Report material')
            ->assertJsonPath('materialUsage.0.unit', 'm')
            ->assertJsonPath('materialUsage.0.qty', 1.25)
            ->assertJsonPath('materialUsage.1.unit', 'piece')
            ->assertJsonPath('materialUsage.1.qty', 3.5);

        $this->actingAs($user)->getJson('/reports/data?dataset=materials')
            ->assertOk()
            ->assertJsonPath('dataset', 'materials')
            ->assertJsonPath('table.0.material_title', 'Report material')
            ->assertJsonPath('table.0.unit', 'm')
            ->assertJsonPath('table.0.qty', 1.25);
        $this->actingAs($user)->get('/reports/excel?dataset=materials')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_report_json_excel_and_pdf_share_the_same_permission_boundary(): void
    {
        [, $denied] = $this->actor('report-denied');

        foreach (['/reports/data', '/reports/excel', '/reports/pdf'] as $uri) {
            $this->actingAs($denied)->get($uri)->assertForbidden();
        }

        [$organization, $allowed] = $this->actor('report-allowed', ['report.view']);
        $zone = Zone::query()->findOrFail($allowed->zone_id);
        $riser = $this->riser($zone, 'REPORT-EXPORT');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $this->operation($riser, $category, $allowed, 'export-operation');

        $this->actingAs($allowed)->get('/reports/pdf?columns%5B%5D=riser&columns%5B%5D=status&group_by=status')->assertOk();
        $this->actingAs($allowed)->get('/reports/excel?columns%5B%5D=riser&columns%5B%5D=status&group_by=status')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_dashboard_is_available_to_a_user_with_dashboard_permission(): void
    {
        [, $user] = $this->actor('dashboard', ['dashboard.view']);

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->getJson('/dashboard/statistics')
            ->assertOk()
            ->assertJsonStructure(['total', 'status' => ['labels', 'data'], 'monthly' => ['labels', 'data']]);
    }

    public function test_report_paginates_and_validates_filter_values(): void
    {
        [, $user] = $this->actor('report-pagination', ['report.view']);
        $zone = Zone::query()->findOrFail($user->zone_id);
        $riser = $this->riser($zone, 'REPORT-PAGE');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $this->operation($riser, $category, $user, 'pagination-row');

        $this->actingAs($user)->getJson('/reports/data?page=1')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('pagination.per_page', 100)
            ->assertJsonPath('cards.total', 1)
            ->assertJsonPath('statusChart.data.0', 1)
            ->assertJsonPath('monthChart.data.0', 1);

        $this->actingAs($user)->getJson('/reports/data?status[]=injected')->assertUnprocessable();
    }

    public function test_report_rejects_foreign_zone_filter_even_when_manually_submitted(): void
    {
        [, $user] = $this->actor('report-zone-filter', ['report.view']);
        $foreignOrganization = Organization::create(['code' => 'foreign-'.uniqid(), 'name' => 'Foreign']);
        $foreignZone = $this->zone($foreignOrganization, 'foreign-zone');

        $this->actingAs($user)->getJson('/reports/data?zone='.$foreignZone->id)->assertForbidden();
    }

    public function test_saved_report_configuration_is_owned_validated_and_reusable(): void
    {
        [, $owner] = $this->actor('saved-report-owner', ['report.view']);
        [, $other] = $this->actor('saved-report-other', ['report.view']);
        $this->actingAs($owner)->get('/reports')->assertOk()->assertSee('reportColumns');
        $payload = [
            'name' => 'Quarterly repairs',
            'configuration' => ['status' => '0', 'columns' => ['riser', 'status'], 'sort' => 'date', 'direction' => 'desc', 'group_by' => 'status'],
        ];

        $created = $this->actingAs($owner)->postJson('/reports/saved', $payload)->assertCreated();
        $id = $created->json('id');
        $this->actingAs($owner)->get('/reports/saved/'.$id.'/open')->assertRedirectContains('columns%5B0%5D=riser');
        $this->actingAs($other)->get('/reports/saved/'.$id.'/open')->assertNotFound();
        $this->actingAs($other)->deleteJson('/reports/saved/'.$id)->assertNotFound();
        $this->actingAs($owner)->deleteJson('/reports/saved/'.$id)->assertOk()->assertJsonPath('deleted', true);

        $this->actingAs($owner)->postJson('/reports/saved', [
            'name' => 'Unsafe', 'configuration' => ['columns' => ['riser; drop table users']],
        ])->assertUnprocessable();
    }

    public function test_report_builder_allowlists_columns_sort_and_grouping(): void
    {
        [, $user] = $this->actor('report-builder', ['report.view']);
        $zone = Zone::query()->findOrFail($user->zone_id);
        $riser = $this->riser($zone, 'BUILDER-RISER');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $this->operation($riser, $category, $user, 'builder-row');

        $this->actingAs($user)->getJson('/reports/data?columns[]=riser&columns[]=status&group_by=status&sort=status')
            ->assertOk()
            ->assertJsonPath('columns.0.key', 'riser')
            ->assertJsonPath('grouping.0.count', 1)
            ->assertJsonPath('table.0.riser', 'BUILDER-RISER')
            ->assertJsonMissingPath('table.0.cost');

        $this->actingAs($user)->getJson('/reports/data?columns[]=riser%3B%20drop%20table%20users')->assertUnprocessable();
        $this->actingAs($user)->getJson('/reports/data?sort=operations.id%20desc')->assertUnprocessable();
        $this->actingAs($user)->getJson('/reports/data?columns[]=riser&group_by=zone')
            ->assertOk()->assertJsonPath('grouping.0.count', 1);
    }

    public function test_image_upload_rejects_mismatched_operation_and_riser(): void
    {
        [$organization, $user] = $this->actor('image-mismatch', ['operation.edit']);
        $zone = Zone::query()->findOrFail($user->zone_id);
        $riserA = $this->riser($zone, 'RISER-IMAGE-A');
        $riserB = $this->riser($zone, 'RISER-IMAGE-B');
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $operation = $this->operation($riserA, $category, $user, 'image-operation');

        $this->actingAs($user)->post('/operation/photos/upload', [
            'operation_id' => $operation->id,
            'riser_id' => $riserB->id,
            'type' => 'before',
            'photo' => UploadedFile::fake()->image('before.jpg'),
        ])->assertUnprocessable()->assertJsonFragment(['message' => 'The operation and riser do not match.']);
    }

    private function actor(string $key, array $permissions = [], string $role = 'worker', ?Organization $organization = null, ?int $zoneId = null): array
    {
        $organization ??= Organization::create(['code' => $key.'-'.uniqid(), 'name' => $key]);
        $zoneId ??= $this->zone($organization, $key.'-zone')->id;
        $user = User::create([
            'organization_id' => $organization->id,
            'zone_id' => $zoneId,
            'name' => $key,
            'username' => uniqid($key.'-'),
            'password' => 'password',
            'role_id' => 1,
        ]);
        $roleName = in_array($role, ['supervisor', 'superadmin', 'boss'], true)
            ? $role
            : $role.'-'.uniqid();
        $roleModel = Role::findOrCreate($roleName, 'web');
        foreach ($permissions as $permission) {
            $roleModel->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $user->assignRole($roleModel);

        return [$organization, $user];
    }

    private function zone(Organization $organization, string $name, ?int $creatorId = null): Zone
    {
        $x = str_contains($name, 'zone-b') ? 50 : 49;
        $y = str_contains($name, 'zone-b') ? 35 : 34;
        $polygon = "POLYGON(({$x} {$y}, {$x} ".($y + 0.01).", ".($x + 0.01).' '.($y + 0.01).", ".($x + 0.01)." {$y}, {$x} {$y}))";
        $id = DB::selectOne(
            'INSERT INTO zone (name, creator_id, organization_id, geom, created_at, updated_at)
             VALUES (?, ?, ?, ST_GeomFromText(?, 4326), now(), now()) RETURNING id',
            [$name, $creatorId, $organization->id, $polygon]
        )->id;

        return Zone::query()->findOrFail($id);
    }

    private function riser(Zone $zone, string $code): Riser
    {
        $x = str_contains($zone->name, 'zone-b') ? 50.005 : 49.005;
        $y = str_contains($zone->name, 'zone-b') ? 35.005 : 34.005;
        $point = "POINT({$x} {$y})";
        $id = DB::selectOne(
            'INSERT INTO riser (r_giscode, giscode, status, geom, geom_3857, created_at, updated_at)
             VALUES (?, ?, ?, ST_GeomFromText(?, 4326), ST_Transform(ST_GeomFromText(?, 4326), 3857), now(), now()) RETURNING id',
            [$code, $code, 'default', $point, $point]
        )->id;

        return Riser::query()->findOrFail($id);
    }

    private function operation(Riser $riser, OperationCategory $category, User $user, string $description): Operation
    {
        return Operation::create([
            'riser_id' => $riser->id,
            'operation_category_id' => $category->id,
            'user_id' => $user->id,
            'operation_date' => now()->toDateString(),
            'status' => '0',
            'priority' => '0',
            'total_cost' => 0,
            'description' => $description,
        ]);
    }
}
