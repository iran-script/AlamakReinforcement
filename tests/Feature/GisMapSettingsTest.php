<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DataAccessPolicy;
use App\Models\GisMapLayer;
use App\Models\GisMapSetting;
use App\Models\Organization;
use App\Models\User;
use App\Services\MapServiceUrlValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GisMapSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/settings/gis-map')->assertRedirect('/login');
    }

    public function test_user_without_configure_access_is_forbidden(): void
    {
        $this->requireSchema();
        [, $user] = $this->actor('denied');

        $this->actingAs($user)->get('/settings/gis-map')->assertForbidden();
    }

    public function test_authorized_user_can_store_only_their_organization_setting_and_action_is_audited(): void
    {
        $this->requireSchema();
        [$organization, $user] = $this->actor('allowed');
        $this->allow($organization, $user, ['view', 'configure']);

        $this->actingAs($user)->put('/settings/gis-map', [
            'base_tile_url' => 'https://tiles.example.test/{z}/{x}/{y}.png',
            'base_tile_attribution' => 'Example Maps',
            'is_active' => true,
        ])->assertRedirect();

        $setting = GisMapSetting::query()->sole();
        $this->assertSame($organization->id, $setting->organization_id);
        $this->assertSame($user->id, $setting->updated_by);
        $this->assertTrue(AuditLog::query()->where('event', 'gis.settings_created')->where('actor_id', $user->id)->exists());
    }

    public function test_unsafe_or_incomplete_tile_url_is_rejected(): void
    {
        $this->requireSchema();
        [$organization, $user] = $this->actor('invalid');
        $this->allow($organization, $user, ['configure']);

        $this->actingAs($user)->from('/settings/gis-map')->put('/settings/gis-map', [
            'base_tile_url' => 'javascript:alert(1)',
        ])->assertRedirect('/settings/gis-map')->assertSessionHasErrors('base_tile_url');
    }

    public function test_authorized_user_can_add_an_organization_wms_layer(): void
    {
        $this->requireSchema();
        [$organization, $user] = $this->actor('wms-form');
        $this->allow($organization, $user, ['view', 'configure']);

        $this->actingAs($user)->get('/settings/gis-map')
            ->assertOk()
            ->assertSee('افزودن لایه');

        $this->actingAs($user)->post('/settings/gis-map/layers', [
            'key' => 'service_zones',
            'title' => 'محدوده‌های خدماتی',
            'type' => 'wms',
            'endpoint' => 'https://maps.example.test/geoserver/wms',
            'wms_layers' => 'workspace:service_zones',
            'format' => 'image/png',
            'version' => '1.3.0',
            'server_type' => 'geoserver',
            'transparent' => true,
            'min_zoom' => 0,
            'max_zoom' => 22,
            'is_active' => true,
        ])->assertRedirect();

        $layer = GisMapLayer::query()->sole();
        $this->assertSame($organization->id, $layer->organization_id);
        $this->assertSame('workspace:service_zones', $layer->wms_layers);
        $this->assertTrue(AuditLog::query()->where('event', 'gis.layer_created')->where('actor_id', $user->id)->exists());
    }

    public function test_map_prefers_the_authenticated_users_organization_setting(): void
    {
        $this->requireSchema();
        [$organization, $user] = $this->actor('map');
        $globalUrl = 'https://global.example/{z}/{x}/{y}.png';
        $organizationUrl = 'https://organization.example/{z}/{x}/{y}.png';
        GisMapSetting::create(['base_tile_url' => $globalUrl]);
        GisMapSetting::create(['organization_id' => $organization->id, 'base_tile_url' => $organizationUrl]);

        $this->actingAs($user)->get('/map')
            ->assertOk()
            ->assertSee(json_encode($organizationUrl), false)
            ->assertDontSee(json_encode($globalUrl), false);
    }

    public function test_superadmin_can_create_and_activate_multiple_profiles_and_mobile_api_returns_only_the_active_profile(): void
    {
        $this->requireSchema();
        [, $admin] = $this->actor('profile-admin');
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('superadmin', 'web'));

        $this->actingAs($admin)->post('/settings/gis-map/profiles', [
            'name' => 'Internal Raster', 'service_type' => 'xyz_raster',
            'base_tile_url' => '/maps/raster/{z}/{x}/{y}.png', 'style_url' => '',
            'base_tile_attribution' => 'Internal test tiles', 'min_zoom' => 2, 'max_zoom' => 19,
        ])->assertRedirect();
        $raster = GisMapSetting::query()->sole();
        $this->assertTrue($raster->is_active);

        $this->actingAs($admin)->post('/settings/gis-map/profiles', [
            'name' => 'Internal Vector', 'service_type' => 'vector_mvt',
            'base_tile_url' => '/maps/vector/{z}/{x}/{y}.pbf', 'style_url' => '/maps/styles/base/style.json',
            'base_tile_attribution' => 'Vector style', 'min_zoom' => 4, 'max_zoom' => 21,
        ])->assertRedirect();
        $vector = GisMapSetting::query()->where('name', 'Internal Vector')->firstOrFail();
        $this->assertFalse($vector->is_active);

        $this->actingAs($admin)->post(route('settings.gis.profiles.activate', $vector))->assertRedirect();
        $this->assertFalse($raster->fresh()->is_active);
        $this->assertTrue($vector->fresh()->is_active);
        $this->assertSame(1, GisMapSetting::query()->where('is_active', true)->count());
        $this->assertTrue(AuditLog::query()->where('event', 'gis.map_profile_created')->where('actor_id', $admin->id)->exists());
        $this->assertTrue(AuditLog::query()->where('event', 'gis.map_profile_activated')->where('actor_id', $admin->id)->exists());

        $this->actingAs($admin)->get('/api/map/config')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('schema_version', 1)
            ->assertJsonPath('map_profile.service_type', 'vector_mvt')
            ->assertJsonPath('map_profile.tile_url', '/maps/vector/{z}/{x}/{y}.pbf')
            ->assertJsonPath('map_profile.style_url', '/maps/styles/base/style.json')
            ->assertJsonMissingPath('backend_auth_url');
    }

    public function test_profile_service_url_policy_rejects_tokens_and_public_production_hosts(): void
    {
        $validator = app(MapServiceUrlValidator::class);
        config()->set('app.env', 'local');
        $this->assertNull($validator->error('https://tile.openstreetmap.org/{z}/{x}/{y}.png', true));
        $this->assertNotNull($validator->error('https://tiles.example.test/{z}/{x}/{y}.png?token=secret', true));
        $this->assertNotNull($validator->error('//tiles.example.test/{z}/{x}/{y}.png', true));

        config()->set('app.env', 'production');
        config()->set('gis.allowed_internal_hosts', ['tiles.internal.example']);
        $this->assertNotNull($validator->error('https://tiles.example.test/{z}/{x}/{y}.png', true));
        $this->assertNull($validator->error('https://tiles.internal.example/{z}/{x}/{y}.png', true));
        $this->assertNotNull($validator->error('https://tiles.internal.example:8443/{z}/{x}/{y}.png', true));
    }

    public function test_connection_test_endpoint_applies_environment_url_policy_without_fetching_url(): void
    {
        $this->requireSchema();
        [$organization, $user] = $this->actor('map-test-connection');
        $this->allow($organization, $user, ['configure']);
        config()->set('app.env', 'production');
        config()->set('gis.allowed_internal_hosts', ['tiles.internal.example']);

        $this->actingAs($user)->postJson('/settings/gis-map/test-service', [
            'service_type' => 'xyz_raster',
            'base_tile_url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        ])->assertUnprocessable()->assertJsonPath('valid', false);

        $this->postJson('/settings/gis-map/test-service', [
            'service_type' => 'xyz_raster',
            'base_tile_url' => 'https://tiles.internal.example/{z}/{x}/{y}.png',
        ])->assertOk()->assertJsonPath('valid', true);
    }

    public function test_mobile_map_configuration_requires_map_permission(): void
    {
        $this->requireSchema();
        [, $user] = $this->actor('map-config-denied');
        $user->revokePermissionTo('map.view');

        $this->actingAs($user)->getJson('/api/map/config')->assertForbidden();
    }

    public function test_map_exposes_an_allowlisted_wms_layer_configuration(): void
    {
        $this->requireSchema();
        [, $user] = $this->actor('wms');
        config()->set('gis.layers', [
            'service_zones' => [
                'title' => 'محدوده خدماتی',
                'type' => 'wms',
                'endpoint' => 'https://maps.example.test/geoserver/wms',
                'layers' => 'workspace:service_zones',
                'format' => 'image/png',
                'transparent' => true,
                'version' => '1.3.0',
                'server_type' => 'geoserver',
            ],
        ]);

        $this->actingAs($user)->get('/map')
            ->assertOk()
            ->assertSee('محدوده خدماتی')
            ->assertSee('workspace:service_zones', false)
            ->assertSee('ol.source.TileWMS', false);
    }

    public function test_local_map_offers_the_demo_riser_extent_control(): void
    {
        $this->requireSchema();
        [, $user] = $this->actor('demo-map');
        config()->set('app.env', 'local');

        $this->actingAs($user)->get('/map')
            ->assertOk()
            ->assertSee('نمایش علمک‌های آزمایشی')
            ->assertSee('ol.layer.VectorTile', false)
            ->assertSee('ol.format.MVT', false)
            ->assertSee('demo=1', false)
            ->assertDontSee('map/geojson', false)
            ->assertDontSee('ol.source.Cluster', false);
    }

    public function test_demo_geojson_is_unavailable_outside_local_environment(): void
    {
        $this->requireSchema();
        [, $user] = $this->actor('demo-map-production');
        config()->set('app.env', 'production');

        $this->actingAs($user)->get('/map/geojson?demo=1')->assertNotFound();
    }

    public function test_local_demo_extent_uses_the_zone_scoped_postgis_extent_query(): void
    {
        $this->requireSchema();
        if (! Schema::hasTable('riser') || ! Schema::hasTable('zone')) {
            $this->markTestSkipped('Riser and PostGIS zone schema is required.');
        }
        [, $user] = $this->actor('demo-extent');
        config()->set('app.env', 'local');

        $this->actingAs($user)->get('/map/tools/extent?demo=1')
            ->assertOk()
            ->assertJsonPath('is_demo', true)
            ->assertJsonPath('extent', null);
    }

    private function requireSchema(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('gis_map_settings') || ! Schema::hasTable('gis_map_layers')) {
            $this->markTestSkipped('PostgreSQL GIS map settings schema is required.');
        }
    }

    private function actor(string $key): array
    {
        $organization = Organization::create(['code' => $key.'-'.uniqid(), 'name' => 'Test']);
        $user = User::create([
            'organization_id' => $organization->id,
            'name' => 'Tester',
            'username' => uniqid('gis-'),
            'password' => 'password',
            'role_id' => 1,
        ]);
        $user->givePermissionTo(Permission::findOrCreate('map.view', 'web'));

        return [$organization, $user];
    }

    private function allow(Organization $organization, User $user, array $actions): void
    {
        DataAccessPolicy::create([
            'organization_id' => $organization->id,
            'name' => uniqid('policy-'),
            'principal_type' => 'user',
            'principal_id' => $user->id,
            'resource_key' => 'gis_settings',
            'actions' => $actions,
            'effect' => 'allow',
            'is_active' => true,
        ]);
    }
}
