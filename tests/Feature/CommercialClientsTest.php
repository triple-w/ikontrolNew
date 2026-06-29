<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommercialClientsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        config()->set('sat.regimenes_fiscales', ['601' => 'General de Ley Personas Morales']);

        $this->createSchema();
    }

    public function test_authenticated_user_creates_commercial_client(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('comercial.clientes.store'), [
            'name' => 'Acme Operativo',
            'client_type' => 'company',
            'email' => 'ventas@acme.test',
            'phone' => '5551234567',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('commercial_clients', [
            'users_id' => $user->id,
            'name' => 'Acme Operativo',
            'email' => 'ventas@acme.test',
        ]);
    }

    public function test_user_cannot_see_another_users_commercial_client(): void
    {
        $owner = $this->createUser('owner@example.test', 'OWNER');
        $other = $this->createUser('other@example.test', 'OTHER');
        $clientId = DB::table('commercial_clients')->insertGetId([
            'users_id' => $owner->id,
            'name' => 'Cliente ajeno',
            'client_type' => 'company',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($other)
            ->get(route('comercial.clientes.show', $clientId))
            ->assertForbidden();
    }

    public function test_contact_must_belong_to_client_in_url(): void
    {
        $user = $this->createUser();
        $clientA = $this->createCommercialClient($user->id, 'Cliente A');
        $clientB = $this->createCommercialClient($user->id, 'Cliente B');
        $contactId = DB::table('commercial_contacts')->insertGetId([
            'commercial_client_id' => $clientB,
            'name' => 'Contacto B',
            'is_primary' => 0,
            'receives_quotes' => 1,
            'receives_documents' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('comercial.contactos.edit', [$clientA, $contactId]))
            ->assertNotFound();
    }

    public function test_only_one_active_primary_contact_exists_per_client(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente');

        $this->actingAs($user)->post(route('comercial.contactos.store', $clientId), [
            'name' => 'Principal 1',
            'is_primary' => '1',
            'is_active' => '1',
        ]);

        $this->actingAs($user)->post(route('comercial.contactos.store', $clientId), [
            'name' => 'Principal 2',
            'is_primary' => '1',
            'is_active' => '1',
        ]);

        $this->assertSame(1, DB::table('commercial_contacts')->where('commercial_client_id', $clientId)->where('is_primary', 1)->count());
        $this->assertDatabaseHas('commercial_contacts', ['name' => 'Principal 2', 'is_primary' => 1]);
    }

    public function test_default_fiscal_client_is_unique_per_commercial_client(): void
    {
        $user = $this->createUser();
        $fiscalA = $this->createFiscalClient($user->id, 'AAA010101AAA');
        $fiscalB = $this->createFiscalClient($user->id, 'BBB010101BBB');

        $response = $this->actingAs($user)->post(route('comercial.clientes.store'), [
            'name' => 'Cliente con fiscales',
            'client_type' => 'company',
            'is_active' => '1',
            'fiscal_client_ids' => [$fiscalA, $fiscalB],
            'default_fiscal_client_id' => $fiscalA,
        ]);

        $clientId = DB::table('commercial_clients')->where('name', 'Cliente con fiscales')->value('id');
        $response->assertRedirect();

        $this->actingAs($user)->put(route('comercial.clientes.update', $clientId), [
            'name' => 'Cliente con fiscales',
            'client_type' => 'company',
            'is_active' => '1',
            'fiscal_client_ids' => [$fiscalA, $fiscalB],
            'default_fiscal_client_id' => $fiscalB,
        ]);

        $this->assertSame(1, DB::table('commercial_client_fiscal_client')->where('commercial_client_id', $clientId)->where('is_default', 1)->count());
        $this->assertDatabaseHas('commercial_client_fiscal_client', [
            'commercial_client_id' => $clientId,
            'fiscal_client_id' => $fiscalB,
            'is_default' => 1,
        ]);
    }

    public function test_commercial_client_can_be_created_from_existing_fiscal_client(): void
    {
        $user = $this->createUser();
        $fiscalId = $this->createFiscalClient($user->id, 'AAA010101AAA');

        $response = $this->actingAs($user)->post(route('comercial.clientes.store'), [
            'name' => 'Fiscal AAA010101AAA',
            'business_name' => 'Fiscal AAA010101AAA',
            'client_type' => 'company',
            'email' => 'fiscal@example.test',
            'phone' => '5551234567',
            'is_active' => '1',
            'fiscal_client_ids' => [$fiscalId],
        ]);

        $response->assertRedirect();

        $clientId = DB::table('commercial_clients')->where('name', 'Fiscal AAA010101AAA')->value('id');

        $this->assertDatabaseHas('commercial_client_fiscal_client', [
            'commercial_client_id' => $clientId,
            'fiscal_client_id' => $fiscalId,
            'is_default' => 1,
        ]);
    }

    public function test_fiscal_client_can_be_created_from_existing_commercial_client(): void
    {
        $user = $this->createUser();
        $commercialId = $this->createCommercialClient($user->id, 'Cliente Comercial');

        $response = $this->actingAs($user)->post(route('clientes.store'), [
            'rfc' => 'CCC010101CCC',
            'razon_social' => 'Cliente Comercial Fiscal',
            'regimen_fiscal' => '601',
            'email' => 'fiscal-comercial@example.test',
            'commercial_client_ids' => [$commercialId],
        ]);

        $response->assertRedirect();

        $fiscalId = DB::table('clientes')->where('rfc', 'CCC010101CCC')->value('id');

        $this->assertDatabaseHas('commercial_client_fiscal_client', [
            'commercial_client_id' => $commercialId,
            'fiscal_client_id' => $fiscalId,
            'is_default' => 1,
        ]);
    }

    public function test_existing_link_is_not_duplicated(): void
    {
        $user = $this->createUser();
        $commercialId = $this->createCommercialClient($user->id, 'Cliente Comercial');
        $fiscalId = $this->createFiscalClient($user->id, 'DDD010101DDD');

        DB::table('commercial_client_fiscal_client')->insert([
            'commercial_client_id' => $commercialId,
            'fiscal_client_id' => $fiscalId,
            'is_default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->put(route('clientes.update', $fiscalId), [
            'rfc' => 'DDD010101DDD',
            'razon_social' => 'Fiscal DDD010101DDD',
            'regimen_fiscal' => '601',
            'commercial_client_ids' => [$commercialId],
        ])->assertRedirect();

        $this->assertSame(1, DB::table('commercial_client_fiscal_client')
            ->where('commercial_client_id', $commercialId)
            ->where('fiscal_client_id', $fiscalId)
            ->count());
    }

    public function test_fiscal_client_cannot_link_to_other_users_commercial_client(): void
    {
        $owner = $this->createUser('owner2@example.test', 'OWNER2');
        $other = $this->createUser('other2@example.test', 'OTHER2');
        $foreignCommercialId = $this->createCommercialClient($other->id, 'Cliente Ajeno');

        $response = $this->actingAs($owner)->post(route('clientes.store'), [
            'rfc' => 'EEE010101EEE',
            'razon_social' => 'Fiscal Propio',
            'regimen_fiscal' => '601',
            'commercial_client_ids' => [$foreignCommercialId],
        ]);

        $response->assertSessionHasErrors('commercial_client_ids');
        $this->assertDatabaseMissing('commercial_client_fiscal_client', [
            'commercial_client_id' => $foreignCommercialId,
        ]);
    }

    public function test_fiscal_creation_does_not_replace_existing_default(): void
    {
        $user = $this->createUser();
        $commercialId = $this->createCommercialClient($user->id, 'Cliente con default');
        $defaultFiscalId = $this->createFiscalClient($user->id, 'FFF010101FFF');

        DB::table('commercial_client_fiscal_client')->insert([
            'commercial_client_id' => $commercialId,
            'fiscal_client_id' => $defaultFiscalId,
            'is_default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->post(route('clientes.store'), [
            'rfc' => 'GGG010101GGG',
            'razon_social' => 'Segundo Fiscal',
            'regimen_fiscal' => '601',
            'commercial_client_ids' => [$commercialId],
        ])->assertRedirect();

        $this->assertSame(1, DB::table('commercial_client_fiscal_client')
            ->where('commercial_client_id', $commercialId)
            ->where('is_default', 1)
            ->count());
        $this->assertDatabaseHas('commercial_client_fiscal_client', [
            'commercial_client_id' => $commercialId,
            'fiscal_client_id' => $defaultFiscalId,
            'is_default' => 1,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('email', 90);
            $table->string('username', 90)->unique();
            $table->boolean('verified');
            $table->boolean('active');
            $table->string('rol', 15)->default('');
            $table->integer('admin')->default(0);
            $table->string('password', 255);
            $table->integer('timbres_disponibles')->default(0);
            $table->rememberToken();
        });

        Schema::create('clientes', function ($table) {
            $table->id();
            $table->string('rfc', 30);
            $table->string('razon_social', 200);
            $table->string('email', 90)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('calle', 100)->nullable();
            $table->string('no_ext', 20)->nullable();
            $table->string('no_int', 20)->nullable();
            $table->string('colonia', 50)->nullable();
            $table->string('municipio', 50)->nullable();
            $table->string('localidad', 50)->nullable();
            $table->string('estado', 50)->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('pais', 30)->nullable();
            $table->string('nombre_contacto', 150)->nullable();
            $table->bigInteger('users_id');
            $table->string('regimen_fiscal', 5)->default('');
        });

        Schema::create('commercial_clients', function ($table) {
            $table->id();
            $table->bigInteger('users_id');
            $table->bigInteger('assigned_user_id')->nullable();
            $table->string('name', 200);
            $table->string('business_name', 200)->nullable();
            $table->string('client_type', 20);
            $table->string('email', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('street', 120)->nullable();
            $table->string('exterior_number', 30)->nullable();
            $table->string('interior_number', 30)->nullable();
            $table->string('neighborhood', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('category', 80)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('commercial_contacts', function ($table) {
            $table->id();
            $table->foreignId('commercial_client_id');
            $table->string('name', 160);
            $table->string('position', 120)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('receives_quotes')->default(true);
            $table->boolean('receives_documents')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('commercial_client_fiscal_client', function ($table) {
            $table->id();
            $table->foreignId('commercial_client_id');
            $table->bigInteger('fiscal_client_id');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    private function createUser(string $email = 'user@example.test', string $username = 'USER'): User
    {
        $id = DB::table('users')->insertGetId([
            'email' => $email,
            'username' => $username,
            'verified' => 1,
            'active' => 1,
            'rol' => 'ROLE_USUARIO',
            'admin' => 0,
            'password' => Hash::make('password'),
            'timbres_disponibles' => 0,
        ]);

        return User::query()->findOrFail($id);
    }

    private function createCommercialClient(int $userId, string $name): int
    {
        return (int) DB::table('commercial_clients')->insertGetId([
            'users_id' => $userId,
            'name' => $name,
            'client_type' => 'company',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createFiscalClient(int $userId, string $rfc): int
    {
        return (int) DB::table('clientes')->insertGetId([
            'users_id' => $userId,
            'rfc' => $rfc,
            'razon_social' => "Fiscal {$rfc}",
            'regimen_fiscal' => '601',
        ]);
    }
}
