<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DataAccessPolicy;
use App\Models\GisMapLayer;
use App\Models\GisMapSetting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
