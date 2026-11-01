<?php

namespace Tests\Feature;

use App\Http\Middleware\LicenseMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Services\MenuService;
use Tests\TestCase;

class LicenseRotationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/license-'.uniqid());
        File::ensureDirectoryExists($this->directory);
        $menuService = \Mockery::mock(MenuService::class);
        $menuService->shouldReceive('get')->andReturn(collect());
        $this->app->instance(MenuService::class, $menuService);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_production_ignores_enabled_development_bypass(): void
    {
        config([
            'app.env' => 'production',
            'license.dev_bypass' => true,
            'license.license_path' => $this->directory.'/missing-license.dat',
        ]);

        $response = app(LicenseMiddleware::class)->handle(Request::create('/'), fn () => response('passed'));

        $this->assertInstanceOf(\Illuminate\View\View::class, $response->getOriginalContent());
    }

    public function test_local_environment_with_bypass_disabled_still_validates_license(): void
    {
        config([
            'app.env' => 'local',
            'license.dev_bypass' => false,
            'license.license_path' => $this->directory.'/missing-license.dat',
        ]);

        $response = app(LicenseMiddleware::class)->handle(Request::create('/'), fn () => response('passed'));

        $this->assertInstanceOf(\Illuminate\View\View::class, $response->getOriginalContent());
    }

    public function test_local_bypass_only_applies_to_loopback_connections(): void
    {
        config([
            'app.env' => 'local',
            'license.dev_bypass' => true,
            'license.license_path' => $this->directory.'/missing-license.dat',
        ]);
        $middleware = app(LicenseMiddleware::class);
        $next = fn () => response('passed');
        $loopbackRequest = Request::create('/');
        $loopbackRequest->server->set('REMOTE_ADDR', '127.0.0.1');
        $remoteRequest = Request::create('/');
        $remoteRequest->server->set('REMOTE_ADDR', '192.168.1.20');

        $this->assertSame('passed', $middleware->handle($loopbackRequest, $next)->getContent());
        $this->assertInstanceOf(\Illuminate\View\View::class, $middleware->handle($remoteRequest, $next)->getOriginalContent());
    }

    public function test_legacy_license_without_key_id_remains_valid(): void
    {
        config([
            'license.license_path' => storage_path('license/license.dat'),
            'license.legacy_public_key_path' => storage_path('license/public.pem'),
        ]);

        $response = app(LicenseMiddleware::class)->handle(Request::create('/'), fn () => response('passed'));

        $this->assertSame('passed', $response->getContent());
    }

    public function test_new_license_is_valid_and_tampering_or_unknown_key_is_rejected(): void
    {
        $keyOptions = ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048];
        $exportOptions = [];
        $opensslConfig = getenv('OPENSSL_CONF') ?: dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';
        if (is_file($opensslConfig)) {
            $keyOptions['config'] = $opensslConfig;
            $exportOptions['config'] = $opensslConfig;
        }

        $key = openssl_pkey_new($keyOptions);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privatePem, null, $exportOptions));
        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);

        $keyDirectory = $this->directory.'/keys';
        File::ensureDirectoryExists($keyDirectory);
        $publicPath = $keyDirectory.'/synthetic-2026.pem';
        File::put($publicPath, $details['key']);
        $licensePath = $this->directory.'/license.dat';
        $data = ['customer' => 'synthetic', 'expire' => now()->addYear()->toDateString(), 'version' => '2.0'];
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertTrue(openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256));
        File::put($licensePath, json_encode([
            'key_id' => 'synthetic-2026',
            'data' => $data,
            'signature' => base64_encode($signature),
        ], JSON_THROW_ON_ERROR));

        config([
            'license.license_path' => $licensePath,
            'license.public_keys_path' => $keyDirectory,
            'license.legacy_public_key_path' => $publicPath,
        ]);

        $middleware = app(LicenseMiddleware::class);
        $next = fn () => response('passed');
        $this->assertSame('passed', $middleware->handle(Request::create('/'), $next)->getContent());

        File::put($licensePath, json_encode([
            'key_id' => 'synthetic-2026',
            'data' => [...$data, 'customer' => 'tampered'],
            'signature' => base64_encode($signature),
        ], JSON_THROW_ON_ERROR));
        $this->assertNotSame('passed', $middleware->handle(Request::create('/'), $next)->getContent());

        File::put($licensePath, json_encode([
            'key_id' => 'unknown-key',
            'data' => $data,
            'signature' => base64_encode($signature),
        ], JSON_THROW_ON_ERROR));
        $this->assertNotSame('passed', $middleware->handle(Request::create('/'), $next)->getContent());
    }
}
