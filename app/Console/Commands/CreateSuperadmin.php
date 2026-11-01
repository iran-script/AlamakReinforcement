<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateSuperadmin extends Command
{
    protected $signature = 'admin:create-superadmin';

    protected $description = 'Create the first Superadmin account on the local development database';

    public function handle(): int
    {
        if (config('app.env') !== 'local') {
            $this->error('This command can only run when APP_ENV=local.');

            return self::FAILURE;
        }

        $connection = config('database.default');
        $connectionConfig = config("database.connections.{$connection}", []);

        if ($connection !== 'pgsql'
            || ($connectionConfig['host'] ?? null) !== '127.0.0.1'
            || (string) ($connectionConfig['port'] ?? '') !== '5432'
            || ($connectionConfig['database'] ?? null) !== 'alamakreinforcement') {
            $this->error('Database target mismatch; expected local PostgreSQL alamakreinforcement at 127.0.0.1:5432. No changes made.');

            return self::FAILURE;
        }

        $identity = DB::connection($connection)->selectOne(
            'select current_database() as database, inet_server_addr()::text as host, inet_server_port() as port'
        );

        if (($identity->database ?? null) !== 'alamakreinforcement'
            || ! in_array($identity->host ?? null, ['127.0.0.1', '127.0.0.1/32'], true)
            || (int) ($identity->port ?? 0) !== 5432) {
            $this->error(sprintf(
                'Connected database identity mismatch (database=%s, server=%s, port=%s). No changes made.',
                $identity->database ?? 'unknown',
                $identity->host ?? 'unknown',
                $identity->port ?? 'unknown'
            ));

            return self::FAILURE;
        }

        if (! Schema::hasTable('users') || ! Schema::hasTable(config('permission.table_names.roles'))
            || ! Schema::hasTable(config('permission.table_names.model_has_roles'))) {
            $this->error('Required users or Spatie role tables are missing. Run the project migrations first.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('users', 'email')) {
            $this->error('The users table has no email column. Run php artisan migrate --database=pgsql, then retry.');

            return self::FAILURE;
        }

        $guard = config('auth.defaults.guard');
        if (! is_string($guard) || ! config("auth.guards.{$guard}")) {
            $this->error('The default authentication guard is not configured.');

            return self::FAILURE;
        }

        $role = Role::query()->where('name', 'superadmin')->where('guard_name', $guard)->first();
        $existingSuperadmin = $role ? User::role($role)->first() : null;
        if ($existingSuperadmin) {
            $this->info("A Superadmin already exists (username: {$existingSuperadmin->username}); no account was created.");
            $this->line("Role: {$role->name} / Guard: {$role->guard_name}");
            $this->line('isSuperAdmin(): '.($existingSuperadmin->isSuperAdmin() ? 'true' : 'false'));

            return self::SUCCESS;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Interactive input is required to collect the account details and hidden password.');

            return self::FAILURE;
        }

        $name = $this->validatedInput('Display name', 'name', ['required', 'string', 'max:255']);
        $username = $this->validatedInput('Username', 'username', [
            'required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username',
        ]);
        $email = $this->validatedInput('Email', 'email', ['required', 'email:rfc', 'max:255', 'unique:users,email']);

        $password = $this->secret('Password (minimum 8 characters)');
        $passwordConfirmation = $this->secret('Confirm password');
        if (! is_string($password) || strlen($password) < 8 || ! hash_equals($password, (string) $passwordConfirmation)) {
            $this->error('Password confirmation failed or password is shorter than 8 characters. No changes made.');

            return self::FAILURE;
        }

        try {
            [$user, $role] = DB::transaction(function () use ($name, $username, $email, $password, $guard): array {
                $role = Role::query()->firstOrCreate([
                    'name' => 'superadmin',
                    'guard_name' => $guard,
                ]);

                $user = User::query()->create([
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'role_id' => $role->getKey(),
                    // User's `hashed` cast hashes this plaintext exactly once.
                    'password' => $password,
                ]);

                $user->assignRole($role);

                return [$user, $role];
            });
        } catch (\Throwable) {
            $this->error('Account creation failed; the transaction was rolled back. No password was written to the application log.');

            return self::FAILURE;
        }

        $user->refresh();
        $authenticated = auth($guard)->once(['username' => $username, 'password' => $password]);
        $dashboardAllowed = $user->hasRole($role)
            && app(\App\Http\Middleware\CheckPermission::class)->handle(
                request()->setUserResolver(fn () => $user),
                fn () => response('allowed'),
                'dashboard.view'
            )->getContent() === 'allowed';
        $policyDetected = $user->isSuperAdmin()
            && \Illuminate\Support\Facades\Gate::forUser($user)->allows('viewAny', \App\Models\Operation::class);

        $this->info('Superadmin account created.');
        $this->line("Username: {$user->username}");
        $this->line("Role: {$role->name} / Guard: {$role->guard_name}");
        $this->line('isSuperAdmin(): '.($user->isSuperAdmin() ? 'true' : 'false'));
        $this->line('Login credential check: '.($authenticated ? 'passed' : 'failed'));
        $this->line('Dashboard permission check: '.($dashboardAllowed ? 'passed' : 'failed'));
        $this->line('Gate superadmin check: '.($policyDetected ? 'passed' : 'failed'));

        return $authenticated && $dashboardAllowed && $policyDetected ? self::SUCCESS : self::FAILURE;
    }

    private function validatedInput(string $question, string $field, array $rules): string
    {
        do {
            $value = trim((string) $this->ask($question));
            $validator = Validator::make([$field => $value], [$field => $rules]);
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }
            }
        } while ($validator->fails());

        return $value;
    }
}
