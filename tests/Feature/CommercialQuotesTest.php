<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommercialQuotesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_quote_can_be_created_with_commercial_client_and_items(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente Cotizable');

        $this->actingAs($user)->post(route('comercial.cotizaciones.store'), $this->quotePayload($clientId, [
            [
                'snapshot_name' => 'Servicio comercial',
                'quantity' => '2',
                'unit_price' => '100',
                'line_discount_amount' => '0',
                'tax_name' => 'IVA',
                'tax_type' => 'traslado',
                'tax_rate' => '0.160000',
            ],
        ]))->assertRedirect();

        $quote = DB::table('commercial_quotes')->first();

        $this->assertSame('COT-000001', $quote->folio);
        $item = DB::table('commercial_quote_items')->where('commercial_quote_id', $quote->id)->first();

        $this->assertSame('232.000000', Decimal::normalize((string) $quote->total));
        $this->assertSame('Servicio comercial', $item->snapshot_name);
        $this->assertSame('32.000000', Decimal::normalize((string) $item->tax_amount));
    }

    public function test_global_discount_is_distributed_before_tax_calculation(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente Descuento');

        $this->actingAs($user)->post(route('comercial.cotizaciones.store'), $this->quotePayload($clientId, [
            ['snapshot_name' => 'Linea 100', 'quantity' => '1', 'unit_price' => '100', 'line_discount_amount' => '0', 'tax_name' => 'IVA', 'tax_type' => 'traslado', 'tax_rate' => '0.160000'],
            ['snapshot_name' => 'Linea 300', 'quantity' => '1', 'unit_price' => '300', 'line_discount_amount' => '0', 'tax_name' => 'IVA', 'tax_type' => 'traslado', 'tax_rate' => '0.160000'],
        ], ['global_discount_amount' => '40']))->assertRedirect();

        $quote = DB::table('commercial_quotes')->first();
        $items = DB::table('commercial_quote_items')->orderBy('sort_order')->get();

        $this->assertSame('400.000000', Decimal::normalize((string) $quote->subtotal));
        $this->assertSame('40.000000', Decimal::normalize((string) $quote->discount_total));
        $this->assertSame('57.600000', Decimal::normalize((string) $quote->tax_total));
        $this->assertSame('417.600000', Decimal::normalize((string) $quote->total));
        $this->assertSame('10.000000', Decimal::normalize((string) $items[0]->global_discount_share));
        $this->assertSame('90.000000', Decimal::normalize((string) $items[0]->taxable_base));
        $this->assertSame('14.400000', Decimal::normalize((string) $items[0]->tax_amount));
        $this->assertSame('30.000000', Decimal::normalize((string) $items[1]->global_discount_share));
        $this->assertSame('270.000000', Decimal::normalize((string) $items[1]->taxable_base));
        $this->assertSame('43.200000', Decimal::normalize((string) $items[1]->tax_amount));
    }

    public function test_user_cannot_view_another_users_quote(): void
    {
        $owner = $this->createUser('owner@example.test', 'OWNER');
        $other = $this->createUser('other@example.test', 'OTHER');
        $clientId = $this->createCommercialClient($owner->id, 'Cliente Ajeno');
        $quoteId = $this->createQuote($owner, $clientId);

        $this->actingAs($other)
            ->get(route('comercial.cotizaciones.show', $quoteId))
            ->assertForbidden();
    }

    public function test_sent_quote_can_be_accepted_and_then_not_edited(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente Estado');
        $quoteId = $this->createQuote($user, $clientId, ['save_action' => 'send']);

        $this->actingAs($user)
            ->post(route('comercial.cotizaciones.accept', $quoteId))
            ->assertRedirect();

        $this->assertDatabaseHas('commercial_quotes', [
            'id' => $quoteId,
            'status' => 'accepted',
        ]);

        $this->actingAs($user)
            ->put(route('comercial.cotizaciones.update', $quoteId), $this->quotePayload($clientId, [
                ['snapshot_name' => 'Cambio bloqueado', 'quantity' => '1', 'unit_price' => '10'],
            ]))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('commercial_quote_items', ['snapshot_name' => 'Cambio bloqueado']);
    }

    public function test_repeated_quote_creation_uses_unique_commercial_folios(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente Folios');

        $this->createQuote($user, $clientId);
        $this->createQuote($user, $clientId);

        $folios = DB::table('commercial_quotes')->orderBy('id')->pluck('folio')->all();

        $this->assertSame(['COT-000001', 'COT-000002'], $folios);
        $this->assertSame(2, count(array_unique($folios)));
    }

    public function test_item_snapshot_does_not_change_when_original_product_changes(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente Snapshot');
        $productId = $this->createProduct($user->id, 'SKU-1', 'Producto Original', '100');

        $quoteId = $this->createQuote($user, $clientId, [], [
            'product_id' => $productId,
            'sku' => 'SKU-1',
            'snapshot_name' => 'Producto Original',
            'snapshot_description' => 'Descripcion Original',
            'snapshot_unit' => 'SERV',
            'quantity' => '1',
            'unit_price' => '100',
        ]);

        DB::table('productos')->where('id', $productId)->update([
            'descripcion' => 'Producto Modificado',
            'precio' => '250',
        ]);

        $item = DB::table('commercial_quote_items')
            ->where('commercial_quote_id', $quoteId)
            ->where('product_id', $productId)
            ->first();

        $this->assertSame('Producto Original', $item->snapshot_name);
        $this->assertSame('100.000000', Decimal::normalize((string) $item->snapshot_unit_price));
    }

    public function test_fiscal_client_must_belong_to_authorized_user(): void
    {
        $owner = $this->createUser('owner-fiscal@example.test', 'OWNERF');
        $other = $this->createUser('other-fiscal@example.test', 'OTHERF');
        $clientId = $this->createCommercialClient($owner->id, 'Cliente Propio');
        $foreignFiscalId = $this->createFiscalClient($other->id, 'AAA010101AAA');

        $this->actingAs($owner)
            ->post(route('comercial.cotizaciones.store'), $this->quotePayload($clientId, [
                ['snapshot_name' => 'Servicio', 'quantity' => '1', 'unit_price' => '100'],
            ], ['fiscal_client_id' => $foreignFiscalId]))
            ->assertSessionHasErrors('fiscal_client_id');
    }

    private function quotePayload(int $clientId, array $items, array $overrides = []): array
    {
        return array_merge([
            'commercial_client_id' => $clientId,
            'issued_at' => '2026-06-29',
            'currency' => 'MXN',
            'global_discount_amount' => '0',
            'save_action' => 'draft',
            'items' => $items,
        ], $overrides);
    }

    private function createQuote(User $user, int $clientId, array $overrides = [], ?array $item = null): int
    {
        $item ??= ['snapshot_name' => 'Servicio', 'quantity' => '1', 'unit_price' => '100', 'tax_name' => 'IVA', 'tax_type' => 'traslado', 'tax_rate' => '0.160000'];

        $this->actingAs($user)
            ->post(route('comercial.cotizaciones.store'), $this->quotePayload($clientId, [$item], $overrides))
            ->assertRedirect();

        return (int) DB::table('commercial_quotes')->latest('id')->value('id');
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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('commercial_contacts', function ($table) {
            $table->id();
            $table->foreignId('commercial_client_id');
            $table->string('name', 160);
            $table->string('email', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('receives_quotes')->default(true);
            $table->boolean('receives_documents')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('productos', function ($table) {
            $table->id();
            $table->bigInteger('users_id');
            $table->string('clave', 30);
            $table->string('unidad', 30);
            $table->decimal('precio', 18, 6)->default(0);
            $table->string('descripcion', 150);
            $table->string('observaciones', 150)->nullable();
            $table->bigInteger('clave_prod_serv_id')->nullable();
            $table->bigInteger('clave_unidad_id')->nullable();
        });

        Schema::create('commercial_quotes', function ($table) {
            $table->id();
            $table->bigInteger('users_id');
            $table->bigInteger('commercial_client_id');
            $table->bigInteger('commercial_contact_id')->nullable();
            $table->bigInteger('fiscal_client_id')->nullable();
            $table->bigInteger('created_by_id');
            $table->bigInteger('assigned_user_id')->nullable();
            $table->string('folio_prefix', 20)->default('COT');
            $table->bigInteger('folio_number');
            $table->string('folio', 40);
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->string('currency', 3)->default('MXN');
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->string('status', 40)->default('draft');
            $table->text('commercial_terms')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->decimal('global_discount_amount', 18, 6)->default(0);
            $table->decimal('subtotal', 18, 6)->default(0);
            $table->decimal('line_discount_total', 18, 6)->default(0);
            $table->decimal('discount_total', 18, 6)->default(0);
            $table->decimal('tax_total', 18, 6)->default(0);
            $table->decimal('total', 18, 6)->default(0);
            $table->timestamps();
        });

        Schema::create('commercial_quote_items', function ($table) {
            $table->id();
            $table->bigInteger('commercial_quote_id');
            $table->bigInteger('product_id')->nullable();
            $table->string('sku', 80)->nullable();
            $table->string('snapshot_name', 200);
            $table->text('snapshot_description')->nullable();
            $table->string('snapshot_unit', 80)->nullable();
            $table->decimal('snapshot_unit_price', 18, 6)->default(0);
            $table->string('snapshot_tax_name', 80)->nullable();
            $table->string('snapshot_tax_type', 20)->nullable();
            $table->decimal('snapshot_tax_rate', 18, 6)->default(0);
            $table->decimal('quantity', 18, 6)->default(1);
            $table->decimal('unit_price', 18, 6)->default(0);
            $table->decimal('line_discount_amount', 18, 6)->default(0);
            $table->decimal('line_subtotal', 18, 6)->default(0);
            $table->decimal('line_base_before_global', 18, 6)->default(0);
            $table->decimal('global_discount_share', 18, 6)->default(0);
            $table->decimal('taxable_base', 18, 6)->default(0);
            $table->decimal('tax_amount', 18, 6)->default(0);
            $table->decimal('line_total', 18, 6)->default(0);
            $table->integer('sort_order')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('commercial_quote_taxes', function ($table) {
            $table->id();
            $table->bigInteger('commercial_quote_id');
            $table->bigInteger('commercial_quote_item_id')->nullable();
            $table->string('tax_name', 80);
            $table->string('tax_type', 20);
            $table->decimal('rate', 18, 6)->default(0);
            $table->decimal('base', 18, 6)->default(0);
            $table->decimal('amount', 18, 6)->default(0);
            $table->integer('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('commercial_quote_status_history', function ($table) {
            $table->id();
            $table->bigInteger('commercial_quote_id');
            $table->string('old_status', 40)->nullable();
            $table->string('new_status', 40);
            $table->bigInteger('user_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('changed_at');
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

    private function createProduct(int $userId, string $sku, string $description, string $price): int
    {
        return (int) DB::table('productos')->insertGetId([
            'users_id' => $userId,
            'clave' => $sku,
            'unidad' => 'SERV',
            'precio' => $price,
            'descripcion' => $description,
        ]);
    }
}
