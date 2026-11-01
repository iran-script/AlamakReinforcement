<?php

namespace Tests\Feature;

use Database\Seeders\DemoMaintenanceDataSeeder;
use RuntimeException;
use Tests\TestCase;

class DemoMaintenanceDataSeederTest extends TestCase
{
    public function test_demo_maintenance_seeder_refuses_testing_and_production_environments(): void
    {
        foreach (['testing', 'production'] as $environment) {
            config(['app.env' => $environment]);

            try {
                app(DemoMaintenanceDataSeeder::class)->run();
                $this->fail("Seeder should refuse APP_ENV={$environment}.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('allowed only when APP_ENV=local', $exception->getMessage());
            }
        }
    }
}
