<?php

namespace Tests\Feature;

use App\Http\Controllers\TileController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class MvtRiserTileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_postgis_generates_mvt_and_zone_user_does_not_receive_outside_riser(): void
    {
        if (DB::getDriverName() !== 'pgsql'
            || ! Schema::hasTable('riser')
            || ! Schema::hasTable('zone')
            || ! DB::selectOne("select exists(select 1 from pg_extension where extname = 'postgis') as installed")->installed) {
            $this->markTestSkipped('PostgreSQL/PostGIS riser and zone schema is required.');
        }

        $zone = DB::selectOne(
            "insert into zone (name, geom, created_at, updated_at)
             values (?, ST_MakeEnvelope(49.49, 33.99, 49.51, 34.01, 4326), now(), now()) returning id",
            ['MVT-TEST-ZONE-'.uniqid()]
        );
        $this->insertRiser('MVT-INSIDE-'.uniqid(), 49.5, 34.0);
        $this->insertRiser('MVT-OUTSIDE-'.uniqid(), 49.5, 34.05);

        $zonedTile = $this->tileFor($this->user((int) $zone->id, false));
        $superadminTile = $this->tileFor($this->user(null, true));

        $this->assertSame(200, $zonedTile->getStatusCode());
        $this->assertSame('application/x-protobuf', $zonedTile->headers->get('Content-Type'));
        $this->assertNotEmpty($zonedTile->getContent());
        $this->assertGreaterThan(strlen($zonedTile->getContent()), strlen($superadminTile->getContent()));
    }

    private function insertRiser(string $code, float $longitude, float $latitude): void
    {
        DB::insert(
            "insert into riser (r_giscode, giscode, status, geom, geom_3857, created_at, updated_at)
             values (?, ?, 'success', ST_SetSRID(ST_MakePoint(?, ?), 4326),
                     ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857), now(), now())",
            [$code, $code, $longitude, $latitude, $longitude, $latitude]
        );
    }

    private function user(?int $zoneId, bool $superadmin): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes(['id' => 1, 'zone_id' => $zoneId]);
        $user->shouldReceive('isSuperAdmin')->andReturn($superadmin);
        $user->shouldReceive('hasRole')->with('superadmin')->andReturn($superadmin);
        $user->shouldReceive('hasPermission')->with('map.view')->andReturn(true);
        $user->shouldReceive('hasPermission')->with('maintenance.case.view')->andReturn(false);

        return $user;
    }

    private function tileFor(User $user)
    {
        $request = Request::create('/tiles/riser/0/0/0.pbf');
        $request->setUserResolver(fn () => $user);

        return app(TileController::class)->tile($request, 'riser', 0, 0, 0);
    }
}
