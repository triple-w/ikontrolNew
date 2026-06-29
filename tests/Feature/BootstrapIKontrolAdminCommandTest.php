<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BootstrapIKontrolAdminCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('users');
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('email', 90);
            $table->string('username', 90)->unique();
            $table->boolean('verified');
            $table->boolean('active');
            $table->boolean('recovery');
            $table->boolean('must_change_password');
            $table->string('rol', 15);
            $table->string('hash', 70)->unique();
            $table->dateTime('last_login')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->boolean('completar_perfil')->nullable();
            $table->string('password', 255);
            $table->string('remember_token', 255)->nullable();
            $table->integer('timbres_disponibles');
            $table->string('plantilla', 5);
            $table->string('api_credential', 70);
            $table->string('api_env', 5);
            $table->bigInteger('correo_per')->nullable();
            $table->integer('plantillasPDF')->nullable();
            $table->bigInteger('admin');
            $table->integer('promociones')->nullable()->default(0);
            $table->integer('nuevo')->default(0);
            $table->integer('descargas')->default(0);
            $table->integer('referencia')->default(0);
        });
    }

    public function test_it_creates_a_legacy_admin_user(): void
    {
        $this->artisan('ikontrol:bootstrap-admin')
            ->expectsQuestion('Nombre de usuario', 'ADMINRFC')
            ->expectsQuestion('Correo electronico', 'admin@example.test')
            ->expectsQuestion('Contrasena', 'very-secure-password')
            ->expectsQuestion('Confirmacion de contrasena', 'very-secure-password')
            ->assertExitCode(0);

        $user = DB::table('users')->where('username', 'ADMINRFC')->first();

        $this->assertNotNull($user);
        $this->assertSame('admin@example.test', $user->email);
        $this->assertSame('ROLE_USUARIO', $user->rol);
        $this->assertSame(1, (int) $user->admin);
        $this->assertSame(1, (int) $user->active);
        $this->assertSame(1, (int) $user->verified);
        $this->assertTrue(Hash::check('very-secure-password', $user->password));
    }

    public function test_it_does_not_modify_an_existing_user(): void
    {
        DB::table('users')->insert([
            'email' => 'admin@example.test',
            'username' => 'ADMINRFC',
            'verified' => 1,
            'active' => 1,
            'recovery' => 0,
            'must_change_password' => 0,
            'rol' => 'ROLE_USUARIO',
            'hash' => 'existing-hash',
            'created_at' => now()->format('Y-m-d H:i:s'),
            'password' => Hash::make('original-password'),
            'timbres_disponibles' => 0,
            'plantilla' => '',
            'api_credential' => 'existing-api-credential',
            'api_env' => 'TEST',
            'admin' => 1,
            'nuevo' => 0,
            'descargas' => 0,
            'referencia' => 0,
        ]);

        $this->artisan('ikontrol:bootstrap-admin')
            ->expectsQuestion('Nombre de usuario', 'ADMINRFC')
            ->expectsQuestion('Correo electronico', 'admin@example.test')
            ->expectsQuestion('Contrasena', 'different-secure-password')
            ->expectsQuestion('Confirmacion de contrasena', 'different-secure-password')
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('users')->count());
        $user = DB::table('users')->where('username', 'ADMINRFC')->first();
        $this->assertTrue(Hash::check('original-password', $user->password));
    }
}
