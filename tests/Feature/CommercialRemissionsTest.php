<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommercialRemissionsTest extends TestCase
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

    public function test_accepted_quote_can_create_partial_remission_with_taxes(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id);
        [$quoteId, $quoteItemId] = $this->createAcceptedQuote($user->id, $clientId, '10.000000');

        $this->actingAs($user)
            ->post(route('comercial.cotizaciones.remisiones.store', $quoteId), $this->remissionPayload($clientId, [
                [
                    'commercial_quote_item_id' => $quoteItemId,
                    'snapshot_name' => 'Servicio parcial',
                    'quantity' => '4',
                    'unit_price' => '100',
                    'taxes' => [
                        ['tax_name' => 'IVA', 'tax_type' => 'traslado', 'tax_mode' => 'rate', 'rate' => '0.160000', 'base' => '9999', 'amount' => '9999'],
                    ],
                ],
            ], ['commercial_quote_id' => $quoteId]))
            ->assertRedirect();

        $remission = DB::table('commercial_remissions')->first();
        $item = DB::table('commercial_remission_items')->where('commercial_remission_id', $remission->id)->first();
        $tax = DB::table('commercial_remission_taxes')->where('commercial_remission_item_id', $item->id)->first();

        $this->assertSame('REM-000001', $remission->folio);
        $this->assertSame($quoteId, (int) $remission->commercial_quote_id);
        $this->assertSame($quoteItemId, (int) $item->commercial_quote_item_id);
        $this->assertSame('64.000000', Decimal::normalize((string) $tax->amount));
        $this->assertSame('400.000000', Decimal::normalize((string) $tax->base));
        $this->assertSame('464.000000', Decimal::normalize((string) $remission->total));
    }

    public function test_quote_remission_cannot_exceed_pending_quantity(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id);
        [$quoteId, $quoteItemId] = $this->createAcceptedQuote($user->id, $clientId, '10.000000');
        $remissionId = $this->createStoredRemission($user->id, $clientId, $quoteId);
        $this->createStoredRemissionItem($remissionId, $quoteItemId, '6.000000');

        $this->actingAs($user)
            ->post(route('comercial.cotizaciones.remisiones.store', $quoteId), $this->remissionPayload($clientId, [
                [
                    'commercial_quote_item_id' => $quoteItemId,
                    'snapshot_name' => 'Exceso',
                    'quantity' => '5',
                    'unit_price' => '100',
                ],
            ], ['commercial_quote_id' => $quoteId]))
            ->assertSessionHasErrors('items.0.quantity');
    }

    public function test_manual_remission_can_be_created_without_quote(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id);

        $this->actingAs($user)
            ->post(route('comercial.remisiones.store'), $this->remissionPayload($clientId, [
                [
                    'snapshot_name' => 'Entrega manual',
                    'quantity' => '2',
                    'unit_price' => '50',
                    'taxes' => [
                        ['tax_name' => 'IVA', 'tax_type' => 'traslado', 'tax_mode' => 'zero', 'rate' => '0'],
                    ],
                ],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('commercial_remissions', [
            'commercial_client_id' => $clientId,
            'commercial_quote_id' => null,
            'folio' => 'REM-000001',
        ]);
        $this->assertSame(1, DB::table('commercial_remission_taxes')->count());
    }

    public function test_remission_can_accept_draft_quote_only_after_successful_save(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id);
        [$quoteId, $quoteItemId] = $this->createAcceptedQuote($user->id, $clientId, '5.000000', 'draft');

        $this->actingAs($user)
            ->post(route('comercial.cotizaciones.remisiones.store', $quoteId), $this->remissionPayload($clientId, [
                [
                    'commercial_quote_item_id' => $quoteItemId,
                    'snapshot_name' => 'Servicio parcial',
                    'quantity' => '2',
                    'unit_price' => '100',
                ],
            ], [
                'commercial_quote_id' => $quoteId,
                'accept_quote_on_save' => '1',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('commercial_quotes', ['id' => $quoteId, 'status' => 'accepted']);
        $this->assertDatabaseHas('commercial_quote_status_history', [
            'commercial_quote_id' => $quoteId,
            'old_status' => 'draft',
            'new_status' => 'accepted',
        ]);
    }

    public function test_failed_remission_does_not_accept_quote(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id);
        [$quoteId, $quoteItemId] = $this->createAcceptedQuote($user->id, $clientId, '3.000000', 'sent');

        $this->actingAs($user)
            ->post(route('comercial.cotizaciones.remisiones.store', $quoteId), $this->remissionPayload($clientId, [
                [
                    'commercial_quote_item_id' => $quoteItemId,
                    'snapshot_name' => 'Exceso',
                    'quantity' => '4',
                    'unit_price' => '100',
                ],
            ], [
                'commercial_quote_id' => $quoteId,
                'accept_quote_on_save' => '1',
            ]))
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertDatabaseHas('commercial_quotes', ['id' => $quoteId, 'status' => 'sent']);
        $this->assertSame(0, DB::table('commercial_quote_status_history')->count());
    }

    private function remissionPayload(int $clientId, array $items, array $overrides = []): array
    {
        return array_merge([
            'commercial_client_id' => $clientId,
            'issue_date' => '2026-06-30',
            'currency' => 'MXN',
            'global_discount_amount' => '0',
            'items' => $items,
        ], $overrides);
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
            $table->bigInteger('commercial_client_id');
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
        });

        Schema::create('commercial_document_templates', function ($table) {
            $table->id();
            $table->bigInteger('users_id');
            $table->string('name', 120);
            $table->string('document_type', 30)->default('remission');
            $table->boolean('is_default')->default(false);
            $table->string('logo_path')->nullable();
            $table->string('header_title')->nullable();
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->text('terms_text')->nullable();
            $table->string('accent_style', 40)->nullable();
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_contact_info')->default(true);
            $table->boolean('show_fiscal_info')->default(false);
            $table->boolean('show_item_tax')->default(true);
            $table->boolean('show_item_sku')->default(true);
            $table->boolean('show_notes')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('commercial_quotes', function ($table) {
            $table->id();
            $table->bigInteger('users_id');
            $table->bigInteger('commercial_client_id');
            $table->bigInteger('commercial_contact_id')->nullable();
            $table->bigInteger('fiscal_client_id')->nullable();
            $table->bigInteger('commercial_document_template_id')->nullable();
            $table->bigInteger('created_by_id');
            $table->bigInteger('assigned_user_id')->nullable();
            $table->string('folio_prefix', 20)->default('COT');
            $table->bigInteger('folio_number');
            $table->string('folio', 40);
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->string('currency', 3)->default('MXN');
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->string('status', 40)->default('accepted');
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
            $table->string('tax_mode', 20)->default('rate');
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

        Schema::create('commercial_remissions', function ($table) {
            $table->id();
            $table->bigInteger('users_id');
            $table->bigInteger('commercial_quote_id')->nullable();
            $table->bigInteger('commercial_client_id');
            $table->bigInteger('commercial_contact_id')->nullable();
            $table->bigInteger('fiscal_client_id')->nullable();
            $table->bigInteger('commercial_document_template_id')->nullable();
            $table->bigInteger('created_by_id');
            $table->bigInteger('assigned_user_id')->nullable();
            $table->string('folio_prefix', 20)->default('REM');
            $table->bigInteger('folio_number');
            $table->string('folio', 40);
            $table->date('issue_date');
            $table->string('currency', 3)->default('MXN');
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->string('status', 40)->default('draft');
            $table->decimal('global_discount_amount', 18, 6)->default(0);
            $table->decimal('subtotal', 18, 6)->default(0);
            $table->decimal('line_discount_total', 18, 6)->default(0);
            $table->decimal('discount_total', 18, 6)->default(0);
            $table->decimal('transfers_total', 18, 6)->default(0);
            $table->decimal('withholdings_total', 18, 6)->default(0);
            $table->decimal('tax_total', 18, 6)->default(0);
            $table->decimal('total', 18, 6)->default(0);
            $table->text('notes_visible')->nullable();
            $table->text('notes_internal')->nullable();
            $table->text('conditions')->nullable();
            $table->string('template_name_snapshot')->nullable();
            $table->string('logo_path_snapshot')->nullable();
            $table->string('header_title_snapshot')->nullable();
            $table->text('header_text_snapshot')->nullable();
            $table->text('footer_text_snapshot')->nullable();
            $table->text('terms_text_snapshot')->nullable();
            $table->text('template_options_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('commercial_remission_items', function ($table) {
            $table->id();
            $table->bigInteger('commercial_remission_id');
            $table->bigInteger('commercial_quote_item_id')->nullable();
            $table->bigInteger('product_id')->nullable();
            $table->string('sku', 80)->nullable();
            $table->string('snapshot_name', 200);
            $table->text('snapshot_description')->nullable();
            $table->string('snapshot_unit', 80)->nullable();
            $table->decimal('snapshot_unit_price', 18, 6)->default(0);
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

        Schema::create('commercial_remission_taxes', function ($table) {
            $table->id();
            $table->bigInteger('commercial_remission_id');
            $table->bigInteger('commercial_remission_item_id')->nullable();
            $table->string('tax_name', 80);
            $table->string('tax_type', 20);
            $table->string('tax_mode', 20)->default('rate');
            $table->decimal('rate', 18, 6)->default(0);
            $table->decimal('base', 18, 6)->default(0);
            $table->decimal('amount', 18, 6)->default(0);
            $table->integer('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('commercial_remission_status_history', function ($table) {
            $table->id();
            $table->bigInteger('commercial_remission_id');
            $table->string('old_status', 40)->nullable();
            $table->string('new_status', 40);
            $table->bigInteger('user_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    private function createUser(): User
    {
        $id = DB::table('users')->insertGetId([
            'email' => 'user@example.test',
            'username' => 'USER',
            'verified' => 1,
            'active' => 1,
            'rol' => 'ROLE_USUARIO',
            'admin' => 0,
            'password' => Hash::make('password'),
            'timbres_disponibles' => 0,
        ]);

        return User::query()->findOrFail($id);
    }

    private function createCommercialClient(int $userId): int
    {
        return (int) DB::table('commercial_clients')->insertGetId([
            'users_id' => $userId,
            'name' => 'Cliente Remision',
            'client_type' => 'company',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAcceptedQuote(int $userId, int $clientId, string $quantity, string $status = 'accepted'): array
    {
        $quoteId = (int) DB::table('commercial_quotes')->insertGetId([
            'users_id' => $userId,
            'commercial_client_id' => $clientId,
            'created_by_id' => $userId,
            'folio_prefix' => 'COT',
            'folio_number' => 1,
            'folio' => 'COT-000001',
            'issued_at' => '2026-06-30',
            'currency' => 'MXN',
            'status' => $status,
            'subtotal' => '1000.000000',
            'discount_total' => '0.000000',
            'tax_total' => '160.000000',
            'total' => '1160.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = (int) DB::table('commercial_quote_items')->insertGetId([
            'commercial_quote_id' => $quoteId,
            'snapshot_name' => 'Servicio parcial',
            'snapshot_unit' => 'SERV',
            'snapshot_unit_price' => '100.000000',
            'quantity' => $quantity,
            'unit_price' => '100.000000',
            'line_subtotal' => Decimal::mul($quantity, '100'),
            'line_base_before_global' => Decimal::mul($quantity, '100'),
            'taxable_base' => Decimal::mul($quantity, '100'),
            'tax_amount' => Decimal::mul(Decimal::mul($quantity, '100'), '0.160000'),
            'line_total' => Decimal::add(Decimal::mul($quantity, '100'), Decimal::mul(Decimal::mul($quantity, '100'), '0.160000')),
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commercial_quote_taxes')->insert([
            'commercial_quote_id' => $quoteId,
            'commercial_quote_item_id' => $itemId,
            'tax_name' => 'IVA',
            'tax_type' => 'traslado',
            'tax_mode' => 'rate',
            'rate' => '0.160000',
            'base' => Decimal::mul($quantity, '100'),
            'amount' => Decimal::mul(Decimal::mul($quantity, '100'), '0.160000'),
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$quoteId, $itemId];
    }

    private function createStoredRemission(int $userId, int $clientId, int $quoteId): int
    {
        return (int) DB::table('commercial_remissions')->insertGetId([
            'users_id' => $userId,
            'commercial_quote_id' => $quoteId,
            'commercial_client_id' => $clientId,
            'created_by_id' => $userId,
            'folio_prefix' => 'REM',
            'folio_number' => 1,
            'folio' => 'REM-000001',
            'issue_date' => '2026-06-30',
            'currency' => 'MXN',
            'status' => 'issued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStoredRemissionItem(int $remissionId, int $quoteItemId, string $quantity): void
    {
        DB::table('commercial_remission_items')->insert([
            'commercial_remission_id' => $remissionId,
            'commercial_quote_item_id' => $quoteItemId,
            'snapshot_name' => 'Ya remitido',
            'quantity' => $quantity,
            'unit_price' => '100.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
