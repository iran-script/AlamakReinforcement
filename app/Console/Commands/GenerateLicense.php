<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateLicense extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'license:generate
                            {customer : License customer name}
                            {expire : Expiration date in YYYY-MM-DD format}
                            {--version=1.0 : License format version}
                            {--key-id= : Public-key identifier; defaults to the configured active key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate software license';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $keyId = $this->option('key-id') ?: config('license.active_key_id');
        if (! is_string($keyId) || ! preg_match('/^[A-Za-z0-9._-]+$/', $keyId)) {
            $this->error('Invalid key identifier.');
            return self::FAILURE;
        }

        $privateKeyPath = config('license.private_key_path');
        if (! is_file($privateKeyPath)) {
            $this->error('Configured signing key is unavailable outside the repository.');
            return self::FAILURE;
        }

        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));
        if ($privateKey === false) {
            $this->error('Configured signing key could not be loaded.');
            return self::FAILURE;
        }

        $data = [
            'customer' => $this->argument('customer'),
            'expire' => $this->argument('expire'),
            'version' => (string) $this->option('version'),
        ];
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (! openssl_sign($payload, $signature, $privateKey, config('license.algorithm'))) {
            $this->error('License signing failed.');
            return self::FAILURE;
        }

        $license = ['key_id' => $keyId, 'data' => $data, 'signature' => base64_encode($signature)];
        $path = config('license.license_path');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, json_encode($license, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->info('License created.');
        return self::SUCCESS;
    }
}
