<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BootstrapIKontrolAdmin extends Command
{
    protected $signature = 'ikontrol:bootstrap-admin';

    protected $description = 'Crea de forma segura el primer usuario administrador de iKontrol';

    private const LEGACY_ADMIN_FLAG = 1;

    private const LEGACY_BOOTSTRAP_ROLE = 'ROLE_USUARIO';

    public function handle(): int
    {
        if (!Schema::hasTable('users')) {
            $this->error('No existe la tabla users. No se puede inicializar el administrador.');
            return self::FAILURE;
        }

        $requiredColumns = ['email', 'username', 'password', 'verified', 'active'];
        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn('users', $column)) {
                $this->error("La tabla users no tiene la columna requerida {$column}.");
                return self::FAILURE;
            }
        }

        $username = trim((string) $this->ask('Nombre de usuario'));
        $email = trim((string) $this->ask('Correo electronico'));
        $password = (string) $this->secret('Contrasena');
        $passwordConfirmation = (string) $this->secret('Confirmacion de contrasena');

        try {
            Validator::make([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ], [
                'username' => ['required', 'string', 'max:90'],
                'email' => ['required', 'email', 'max:90'],
                'password' => ['required', 'string', 'min:12', 'confirmed'],
            ])->validate();
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existing = User::query()
            ->where('email', $email)
            ->orWhere('username', $username)
            ->first();

        if ($existing) {
            $this->warn('No se creo ningun usuario: ya existe un usuario con ese correo o username.');
            $this->line('ID existente: ' . $existing->id);
            $this->line('Username existente: ' . $existing->username);
            $this->line('Correo existente: ' . $existing->email);

            return self::SUCCESS;
        }

        try {
            $role = $this->resolveAdminRole();
            $payload = $this->buildUserPayload($username, $email, $password, $role);
            $this->assertPayloadCoversRequiredColumns($payload);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $userId = DB::table('users')->insertGetId($payload);
        $user = User::query()->find($userId);

        $this->info('Administrador inicial creado correctamente.');
        $this->line('ID: ' . $userId);
        $this->line('Username: ' . $username);
        $this->line('Correo: ' . $email);
        $this->line('Rol asignado: ' . $role);
        $this->line('URL esperada de login: ' . url('/login'));
        $this->line('Siguiente accion recomendada: iniciar sesion y completar Configuracion antes de timbrar.');

        return $user ? self::SUCCESS : self::FAILURE;
    }

    private function resolveAdminRole(): string
    {
        if (!Schema::hasColumn('users', 'rol')) {
            throw new \RuntimeException('No existe users.rol; falta confirmar donde se guarda el rol administrador.');
        }

        $query = DB::table('users')
            ->select('rol')
            ->whereNotNull('rol')
            ->where('rol', '!=', '');

        if (Schema::hasColumn('users', 'admin')) {
            $query->where('admin', self::LEGACY_ADMIN_FLAG);
        }

        if (Schema::hasColumn('users', 'active')) {
            $query->where('active', 1);
        }

        if (Schema::hasColumn('users', 'verified')) {
            $query->where('verified', 1);
        }

        $roles = $query->distinct()->pluck('rol')->map(fn ($role) => (string) $role)->values();

        if ($roles->count() === 1) {
            return $roles->first();
        }

        if ($roles->count() > 1) {
            throw new \RuntimeException(
                'Hay multiples valores de users.rol en administradores activos: ' . $roles->implode(', ') . '. Falta confirmar cual usar.'
            );
        }

        if (!Schema::hasColumn('users', 'admin')) {
            throw new \RuntimeException('No hay roles existentes y tampoco existe users.admin; falta confirmar el valor de rol administrador.');
        }

        return self::LEGACY_BOOTSTRAP_ROLE;
    }

    private function buildUserPayload(string $username, string $email, string $password, string $role): array
    {
        $now = now()->format('Y-m-d H:i:s');

        $values = [
            'email' => $email,
            'username' => $username,
            'verified' => 1,
            'active' => 1,
            'recovery' => 0,
            'must_change_password' => 0,
            'rol' => $role,
            'hash' => Str::random(60),
            'created_at' => $now,
            'password' => Hash::make($password),
            'remember_token' => null,
            'timbres_disponibles' => 0,
            'plantilla' => '',
            'api_credential' => Str::random(60),
            'api_env' => 'TEST',
            'correo_per' => null,
            'plantillasPDF' => null,
            'admin' => self::LEGACY_ADMIN_FLAG,
            'promociones' => 0,
            'nuevo' => 0,
            'descargas' => 0,
            'referencia' => 0,
        ];

        $columns = Schema::getColumnListing('users');

        return collect($values)
            ->filter(fn ($value, $column) => in_array($column, $columns, true))
            ->all();
    }

    private function assertPayloadCoversRequiredColumns(array $payload): void
    {
        $missing = collect($this->requiredColumnsWithoutDefaults())
            ->reject(fn ($column) => array_key_exists($column, $payload))
            ->values();

        if ($missing->isNotEmpty()) {
            throw new \RuntimeException(
                'La tabla users tiene columnas NOT NULL sin default que el bootstrap no puede llenar sin confirmar: ' . $missing->implode(', ')
            );
        }
    }

    private function requiredColumnsWithoutDefaults(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $database = DB::connection()->getDatabaseName();

            return collect(DB::select(
                "select column_name, is_nullable, column_default, extra
                 from information_schema.columns
                 where table_schema = ? and table_name = 'users'
                 order by ordinal_position",
                [$database]
            ))
                ->filter(function ($column) {
                    return $column->is_nullable === 'NO'
                        && $column->column_default === null
                        && !str_contains(strtolower((string) $column->extra), 'auto_increment');
                })
                ->pluck('column_name')
                ->map(fn ($column) => (string) $column)
                ->all();
        }

        if ($driver === 'sqlite') {
            return collect(DB::select('PRAGMA table_info(users)'))
                ->filter(function ($column) {
                    return (int) $column->notnull === 1
                        && $column->dflt_value === null
                        && (int) $column->pk !== 1;
                })
                ->pluck('name')
                ->map(fn ($column) => (string) $column)
                ->all();
        }

        return [];
    }
}
