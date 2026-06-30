<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CommercialDocuments\TemplateVariableResolver;
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
        $this->assertNull($item->snapshot_tax_name);
        $this->assertSame('32.000000', Decimal::normalize((string) $item->tax_amount));
        $this->assertDatabaseHas('commercial_quote_taxes', [
            'commercial_quote_item_id' => $item->id,
            'tax_name' => 'IVA',
            'tax_type' => 'traslado',
            'tax_mode' => 'rate',
        ]);
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

    public function test_quote_item_can_store_two_transfer_taxes(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente Dos Traslados');

        $this->actingAs($user)->post(route('comercial.cotizaciones.store'), $this->quotePayload($clientId, [[
            'snapshot_name' => 'Servicio gravado',
            'quantity' => '1',
            'unit_price' => '100',
            'taxes' => [
                ['tax_name' => 'IVA', 'tax_type' => 'traslado', 'tax_mode' => 'rate', 'rate' => '0.160000'],
                ['tax_name' => 'ISH', 'tax_type' => 'traslado', 'tax_mode' => 'rate', 'rate' => '0.020000'],
            ],
        ]]))->assertRedirect();

        $quote = DB::table('commercial_quotes')->first();

        $this->assertSame('18.000000', Decimal::normalize((string) $quote->tax_total));
        $this->assertSame('118.000000', Decimal::normalize((string) $quote->total));
        $this->assertSame(2, DB::table('commercial_quote_taxes')->where('commercial_quote_id', $quote->id)->count());
    }

    public function test_quote_item_can_mix_transfer_and_retention(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente Retencion');

        $this->actingAs($user)->post(route('comercial.cotizaciones.store'), $this->quotePayload($clientId, [[
            'snapshot_name' => 'Servicio profesional',
            'quantity' => '1',
            'unit_price' => '100',
            'taxes' => [
                ['tax_name' => 'IVA', 'tax_type' => 'traslado', 'tax_mode' => 'rate', 'rate' => '0.160000'],
                ['tax_name' => 'ISR', 'tax_type' => 'retencion', 'tax_mode' => 'rate', 'rate' => '0.100000'],
            ],
        ]]))->assertRedirect();

        $quote = DB::table('commercial_quotes')->first();
        $retention = DB::table('commercial_quote_taxes')->where('tax_type', 'retencion')->first();

        $this->assertSame('6.000000', Decimal::normalize((string) $quote->tax_total));
        $this->assertSame('106.000000', Decimal::normalize((string) $quote->total));
        $this->assertSame('-10.000000', Decimal::normalize((string) $retention->amount));
    }

    public function test_zero_rate_and_exempt_taxes_are_distinct(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente Cero Exento');

        $this->actingAs($user)->post(route('comercial.cotizaciones.store'), $this->quotePayload($clientId, [[
            'snapshot_name' => 'Servicio mixto',
            'quantity' => '1',
            'unit_price' => '100',
            'taxes' => [
                ['tax_name' => 'IVA', 'tax_type' => 'traslado', 'tax_mode' => 'zero', 'rate' => '0'],
                ['tax_name' => 'IEPS', 'tax_type' => 'traslado', 'tax_mode' => 'exempt', 'rate' => '0.160000'],
            ],
        ]]))->assertRedirect();

        $zero = DB::table('commercial_quote_taxes')->where('tax_name', 'IVA')->first();
        $exempt = DB::table('commercial_quote_taxes')->where('tax_name', 'IEPS')->first();

        $this->assertSame('zero', $zero->tax_mode);
        $this->assertSame('0.000000', Decimal::normalize((string) $zero->rate));
        $this->assertSame('0.000000', Decimal::normalize((string) $zero->amount));
        $this->assertSame('exempt', $exempt->tax_mode);
        $this->assertSame('0.000000', Decimal::normalize((string) $exempt->rate));
        $this->assertSame('0.000000', Decimal::normalize((string) $exempt->amount));
    }

    public function test_frontend_tax_base_and_amount_are_recalculated_by_backend(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente Manipulado');

        $this->actingAs($user)->post(route('comercial.cotizaciones.store'), $this->quotePayload($clientId, [[
            'snapshot_name' => 'Servicio seguro',
            'quantity' => '1',
            'unit_price' => '100',
            'taxes' => [[
                'tax_name' => 'IVA',
                'tax_type' => 'traslado',
                'tax_mode' => 'rate',
                'rate' => '0.160000',
                'base' => '999999',
                'amount' => '999999',
            ]],
        ]]))->assertRedirect();

        $tax = DB::table('commercial_quote_taxes')->first();

        $this->assertSame('100.000000', Decimal::normalize((string) $tax->base));
        $this->assertSame('16.000000', Decimal::normalize((string) $tax->amount));
    }

    public function test_preview_shows_transfers_and_retentions_without_fiscal_modules(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente Preview Impuestos');

        $this->actingAs($user)
            ->post(route('comercial.cotizaciones.preview-draft'), $this->quotePayload($clientId, [[
                'snapshot_name' => 'Servicio temporal',
                'quantity' => '1',
                'unit_price' => '100',
                'taxes' => [
                    ['tax_name' => 'IVA', 'tax_type' => 'traslado', 'tax_mode' => 'rate', 'rate' => '0.160000'],
                    ['tax_name' => 'ISR', 'tax_type' => 'retencion', 'tax_mode' => 'rate', 'rate' => '0.100000'],
                ],
            ]]))
            ->assertOk()
            ->assertSee('Traslados')
            ->assertSee('Retenciones')
            ->assertSee('Documento comercial no fiscal');
    }

    public function test_quote_tax_drawer_applies_only_draft_taxes_to_item_payload(): void
    {
        $form = file_get_contents(resource_path('views/comercial/cotizaciones/_form.blade.php'));
        $drawer = file_get_contents(resource_path('views/comercial/cotizaciones/partials/_tax-drawer.blade.php'));

        $this->assertStringContainsString('activeTaxRowIndex', $form);
        $this->assertStringContainsString('taxesDraft = this.cloneTaxes(this.items[index]?.taxes || [])', $form);
        $this->assertStringContainsString('this.items[this.activeTaxRowIndex].taxes = normalized', $form);
        $this->assertStringContainsString('.map((tax) => ({ ...tax }))', $form);
        $this->assertStringContainsString('items[${index}][taxes][${taxIndex}][tax_name]', $form);
        $this->assertStringContainsString('items[${index}][taxes][${taxIndex}][tax_type]', $form);
        $this->assertStringContainsString('items[${index}][taxes][${taxIndex}][tax_mode]', $form);
        $this->assertStringContainsString('items[${index}][taxes][${taxIndex}][rate]', $form);
        $this->assertStringNotContainsString('items[${index}][taxes][${taxIndex}][base]', $form);
        $this->assertStringNotContainsString('items[${index}][taxes][${taxIndex}][amount]', $form);

        $this->assertStringContainsString('Cancelar', $drawer);
        $this->assertStringContainsString('Aplicar impuestos', $drawer);
        $this->assertStringContainsString('@click="cancelTaxes()"', $drawer);
        $this->assertStringContainsString('@click="applyTaxes()"', $drawer);
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

    public function test_user_can_create_quote_template_and_keep_one_default(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->post(route('configuracion.formatos-documentos.store'), [
            'name' => 'Formato A',
            'document_type' => 'quote',
            'is_default' => '1',
            'is_active' => '1',
            'show_logo' => '1',
            'show_contact_info' => '1',
            'show_item_tax' => '1',
            'show_item_sku' => '1',
            'show_notes' => '1',
            'header_text' => 'Hola {{ cliente.nombre }}',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('configuracion.formatos-documentos.store'), [
            'name' => 'Formato B',
            'document_type' => 'quote',
            'is_default' => '1',
            'is_active' => '1',
            'show_logo' => '1',
            'show_contact_info' => '1',
            'show_item_tax' => '1',
            'show_item_sku' => '1',
            'show_notes' => '1',
        ])->assertRedirect();

        $this->assertSame(1, DB::table('commercial_document_templates')->where('users_id', $user->id)->where('document_type', 'quote')->where('is_default', 1)->count());
        $this->assertDatabaseHas('commercial_document_templates', ['name' => 'Formato B', 'is_default' => 1]);
        $this->assertDatabaseHas('commercial_document_templates', ['name' => 'Formato A', 'is_default' => 0]);
    }

    public function test_template_variables_resolve_allowed_values_without_executing_unknown_text(): void
    {
        $resolver = new TemplateVariableResolver();

        $text = $resolver->resolve('Cotizacion para {{ cliente.nombre }} {{ php.info }} <script>alert(1)</script>', [
            'cliente' => ['nombre' => 'Cliente Seguro'],
        ]);

        $this->assertSame('Cotizacion para Cliente Seguro {{ php.info }} <script>alert(1)</script>', $text);
    }

    public function test_draft_preview_does_not_create_quote_or_consume_folio(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente Preview');
        $templateId = $this->createTemplate($user->id, 'Formato Preview');

        $this->actingAs($user)
            ->post(route('comercial.cotizaciones.preview-draft'), $this->quotePayload($clientId, [
                ['snapshot_name' => 'Servicio temporal', 'quantity' => '1', 'unit_price' => '100'],
            ], ['commercial_document_template_id' => $templateId]))
            ->assertOk()
            ->assertSee('Vista temporal sin guardar', false)
            ->assertSee('Borrador');

        $this->assertSame(0, DB::table('commercial_quotes')->count());
    }

    public function test_user_cannot_use_template_from_another_user(): void
    {
        $owner = $this->createUser('owner-template@example.test', 'OWNERT');
        $other = $this->createUser('other-template@example.test', 'OTHERT');
        $clientId = $this->createCommercialClient($owner->id, 'Cliente Propio');
        $foreignTemplateId = $this->createTemplate($other->id, 'Formato Ajeno');

        $this->actingAs($owner)
            ->post(route('comercial.cotizaciones.store'), $this->quotePayload($clientId, [
                ['snapshot_name' => 'Servicio', 'quantity' => '1', 'unit_price' => '100'],
            ], ['commercial_document_template_id' => $foreignTemplateId]))
            ->assertSessionHasErrors('commercial_document_template_id');
    }

    public function test_sent_quote_keeps_template_snapshot_after_template_changes(): void
    {
        $user = $this->createUser();
        $clientId = $this->createCommercialClient($user->id, 'Cliente Historico');
        $templateId = $this->createTemplate($user->id, 'Formato Historico', ['header_text' => 'Texto original']);

        $quoteId = $this->createQuote($user, $clientId, [
            'save_action' => 'send',
            'commercial_document_template_id' => $templateId,
        ]);

        DB::table('commercial_document_templates')->where('id', $templateId)->update(['header_text' => 'Texto cambiado']);

        $quote = DB::table('commercial_quotes')->where('id', $quoteId)->first();

        $this->assertSame('Formato Historico', $quote->template_name_snapshot);
        $this->assertSame('Texto original', $quote->header_text_snapshot);
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
            $table->string('template_name_snapshot')->nullable();
            $table->string('logo_path_snapshot')->nullable();
            $table->string('header_title_snapshot')->nullable();
            $table->text('header_text_snapshot')->nullable();
            $table->text('footer_text_snapshot')->nullable();
            $table->text('terms_text_snapshot')->nullable();
            $table->text('template_options_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('commercial_document_templates', function ($table) {
            $table->id();
            $table->bigInteger('users_id');
            $table->string('name', 120);
            $table->string('document_type', 30)->default('quote');
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

    private function createTemplate(int $userId, string $name, array $overrides = []): int
    {
        return (int) DB::table('commercial_document_templates')->insertGetId(array_merge([
            'users_id' => $userId,
            'name' => $name,
            'document_type' => 'quote',
            'is_default' => 0,
            'logo_path' => null,
            'header_title' => 'Cotizacion',
            'header_text' => null,
            'footer_text' => null,
            'terms_text' => null,
            'accent_style' => 'teal',
            'show_logo' => 1,
            'show_contact_info' => 1,
            'show_fiscal_info' => 0,
            'show_item_tax' => 1,
            'show_item_sku' => 1,
            'show_notes' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
