@php
    $formItems = old('items', $items ?? []);
    if (empty($formItems)) {
        $formItems = [[
            'product_id' => null,
            'sku' => '',
            'snapshot_name' => '',
            'snapshot_description' => '',
            'snapshot_unit' => '',
            'quantity' => '1',
            'unit_price' => '0',
            'line_discount_amount' => '0',
            'taxes' => [],
            'notes' => '',
        ]];
    }

    $clientId = old('commercial_client_id', $quote->commercial_client_id);
    $contactId = old('commercial_contact_id', $quote->commercial_contact_id);
    $fiscalId = old('fiscal_client_id', $quote->fiscal_client_id);
    $selectedTemplateId = old('commercial_document_template_id', $quote->commercial_document_template_id ?: $defaultTemplateId);
@endphp

<form
    method="POST"
    action="{{ $action }}"
    class="space-y-6"
    x-data="commercialQuoteForm({
        clientOptionsUrl: @js($clientOptionsUrl),
        productSearchUrl: @js($productSearchUrl),
        clientId: @js((string) $clientId),
        contactId: @js((string) $contactId),
        fiscalId: @js((string) $fiscalId),
        initialItems: @js($formItems),
        globalDiscount: @js(old('global_discount_amount', $quote->global_discount_amount ?? '0.000000')),
    })"
    x-init="init()"
>
    @csrf
    @if(($method ?? 'POST') !== 'POST')
        <input x-ref="methodOverride" type="hidden" name="_method" value="{{ $method }}">
    @endif
    @if($quote->exists)
        <input type="hidden" name="preview_quote_id" value="{{ $quote->id }}">
    @endif

    @if($errors->any())
        <x-ikontrol.info-alert title="Revisa la cotizacion">{{ $errors->first() }}</x-ikontrol.info-alert>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <x-ikontrol.module-section title="Cliente y documento">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Cliente comercial *</label>
                        <select name="commercial_client_id" x-model="clientId" @change="loadClientOptions" required class="w-full rounded-md border-gray-300">
                            <option value="">Selecciona cliente</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}{{ $client->business_name ? ' / '.$client->business_name : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Contacto</label>
                        <select name="commercial_contact_id" x-model="contactId" class="w-full rounded-md border-gray-300">
                            <option value="">Sin contacto</option>
                            <template x-for="contact in contacts" :key="contact.id">
                                <option :value="contact.id" x-text="`${contact.name}${contact.email ? ' / ' + contact.email : ''}`"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Receptor fiscal sugerido</label>
                        <select name="fiscal_client_id" x-model="fiscalId" class="w-full rounded-md border-gray-300">
                            <option value="">Sin receptor sugerido</option>
                            <template x-for="fiscal in fiscalClients" :key="fiscal.id">
                                <option :value="fiscal.id" x-text="`${fiscal.rfc || ''} ${fiscal.razon_social || ''}${fiscal.is_default ? ' / predeterminado' : ''}`"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Formato comercial</label>
                        <select name="commercial_document_template_id" class="w-full rounded-md border-gray-300">
                            <option value="">Formato simple del sistema</option>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}" @selected((string) $selectedTemplateId === (string) $template->id)>
                                    {{ $template->name }}{{ $template->is_default ? ' / predeterminado' : '' }}{{ !$template->is_active ? ' / inactivo' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </x-ikontrol.module-section>

            <x-ikontrol.module-section title="Datos generales">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Emision *</label>
                        <input type="date" name="issued_at" value="{{ old('issued_at', optional($quote->issued_at)->format('Y-m-d') ?: now()->toDateString()) }}" required class="w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Vencimiento</label>
                        <input type="date" name="expires_at" value="{{ old('expires_at', optional($quote->expires_at)->format('Y-m-d')) }}" class="w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Moneda *</label>
                        <input name="currency" maxlength="3" value="{{ old('currency', $quote->currency ?: 'MXN') }}" required class="w-full rounded-md border-gray-300 uppercase">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tipo de cambio</label>
                        <input type="number" step="0.000001" min="0" name="exchange_rate" value="{{ old('exchange_rate', $quote->exchange_rate) }}" class="w-full rounded-md border-gray-300">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Responsable</label>
                        <select name="assigned_user_id" class="w-full rounded-md border-gray-300">
                            <option value="">Sin responsable</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((string) old('assigned_user_id', $quote->assigned_user_id) === (string) $user->id)>{{ $user->username }} / {{ $user->email }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </x-ikontrol.module-section>

            <x-ikontrol.module-section title="Partidas" description="Busca productos existentes o agrega conceptos libres. La cotizacion guardara un snapshot de cada partida.">
                <div class="mb-4 grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-3">
                    <div class="relative">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Buscar producto</label>
                        <input type="search" x-model="productSearch.query" @input.debounce.300ms="searchProducts" @focus="productSearch.open = true; searchProducts()" class="w-full rounded-md border-gray-300" placeholder="Clave, descripcion, unidad o clave SAT">
                        <div x-show="productSearch.open && productSearch.results.length" x-cloak class="absolute z-20 mt-1 max-h-72 w-full overflow-auto rounded-md border border-gray-200 bg-white shadow-lg">
                            <template x-for="product in productSearch.results" :key="product.id">
                                <button type="button" @click="addProduct(product)" class="block w-full px-4 py-3 text-left text-sm hover:bg-gray-50">
                                    <span class="font-medium text-gray-900" x-text="product.snapshot_name"></span>
                                    <span class="mt-1 block text-xs text-gray-500" x-text="`${product.sku || 'Sin clave'} / ${product.snapshot_unit || 'Sin unidad'} / $${money(product.unit_price)}`"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="button" @click="addItem" class="h-10 rounded-md border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700">Concepto libre</button>
                    </div>
                </div>

                <div
                    x-show="taxFeedback"
                    x-transition
                    x-cloak
                    class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
                    x-text="taxFeedback"
                ></div>

                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-[980px] w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2 w-[28%]">Producto / descripcion</th>
                                <th class="px-3 py-2">SKU</th>
                                <th class="px-3 py-2">Cant.</th>
                                <th class="px-3 py-2">Unidad</th>
                                <th class="px-3 py-2">Precio</th>
                                <th class="px-3 py-2">Descuento</th>
                                <th class="px-3 py-2">Impuestos</th>
                                <th class="px-3 py-2 text-right">Importe</th>
                                <th class="px-3 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(item, index) in items" :key="item.uid">
                                <tr class="align-top">
                                    <td class="px-3 py-3">
                                        <input type="hidden" :name="`items[${index}][product_id]`" x-model="item.product_id">
                                        <input :name="`items[${index}][snapshot_name]`" x-model="item.snapshot_name" required class="w-full rounded-md border-gray-300 text-sm" placeholder="Concepto">
                                        <textarea :name="`items[${index}][snapshot_description]`" x-model="item.snapshot_description" rows="2" class="mt-2 w-full rounded-md border-gray-300 text-sm" placeholder="Descripcion"></textarea>
                                        <input :name="`items[${index}][notes]`" x-model="item.notes" class="mt-2 w-full rounded-md border-gray-300 text-xs" placeholder="Notas de partida">
                                    </td>
                                    <td class="px-3 py-3"><input :name="`items[${index}][sku]`" x-model="item.sku" class="w-24 rounded-md border-gray-300 text-sm"></td>
                                    <td class="px-3 py-3"><input type="number" step="0.000001" min="0.000001" :name="`items[${index}][quantity]`" x-model="item.quantity" required class="w-24 rounded-md border-gray-300 text-sm"></td>
                                    <td class="px-3 py-3"><input :name="`items[${index}][snapshot_unit]`" x-model="item.snapshot_unit" class="w-24 rounded-md border-gray-300 text-sm"></td>
                                    <td class="px-3 py-3"><input type="number" step="0.000001" min="0" :name="`items[${index}][unit_price]`" x-model="item.unit_price" required class="w-28 rounded-md border-gray-300 text-sm"></td>
                                    <td class="px-3 py-3"><input type="number" step="0.000001" min="0" :name="`items[${index}][line_discount_amount]`" x-model="item.line_discount_amount" class="w-28 rounded-md border-gray-300 text-sm"></td>
                                    <td class="px-3 py-3">
                                        <div class="space-y-2 min-w-40">
                                            <template x-for="(tax, taxIndex) in item.taxes" :key="`${item.uid}-tax-${taxIndex}`">
                                                <div>
                                                    <input type="hidden" :name="`items[${index}][taxes][${taxIndex}][tax_name]`" :value="tax.tax_name">
                                                    <input type="hidden" :name="`items[${index}][taxes][${taxIndex}][tax_type]`" :value="tax.tax_type">
                                                    <input type="hidden" :name="`items[${index}][taxes][${taxIndex}][tax_mode]`" :value="tax.tax_mode">
                                                    <input type="hidden" :name="`items[${index}][taxes][${taxIndex}][rate]`" :value="tax.rate">
                                                    <input type="hidden" :name="`items[${index}][taxes][${taxIndex}][sort_order]`" :value="taxIndex + 1">
                                                </div>
                                            </template>
                                            <button type="button" @click="openTaxes(index)" class="inline-flex items-center rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                                Impuestos
                                                <span class="ml-2 rounded-full bg-teal-50 px-2 py-0.5 text-[11px] text-teal-700" x-text="item.taxes.length"></span>
                                            </button>
                                            <div class="text-xs text-gray-500" x-text="taxSummary(item)"></div>
                                            <div class="text-xs font-medium text-gray-700" x-text="`Neto imp.: $${money(itemTaxTotal(item))}`"></div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-right font-medium" x-text="`$${money(lineTotal(item))}`"></td>
                                    <td class="px-3 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" @click="moveItem(index, -1)" class="rounded-md border border-gray-200 px-2 py-1 text-xs">Subir</button>
                                            <button type="button" @click="moveItem(index, 1)" class="rounded-md border border-gray-200 px-2 py-1 text-xs">Bajar</button>
                                            <button type="button" @click="removeItem(index)" class="rounded-md border border-red-200 px-2 py-1 text-xs text-red-600">Quitar</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </x-ikontrol.module-section>
            @include('comercial.cotizaciones.partials._tax-drawer')

            <x-ikontrol.module-section title="Condiciones y notas">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Condiciones comerciales</label>
                        <textarea name="commercial_terms" rows="5" class="w-full rounded-md border-gray-300">{{ old('commercial_terms', $quote->commercial_terms) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Notas visibles para cliente</label>
                        <textarea name="customer_notes" rows="5" class="w-full rounded-md border-gray-300">{{ old('customer_notes', $quote->customer_notes) }}</textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Notas internas</label>
                        <textarea name="internal_notes" rows="4" class="w-full rounded-md border-gray-300">{{ old('internal_notes', $quote->internal_notes) }}</textarea>
                    </div>
                </div>
            </x-ikontrol.module-section>
        </div>

        <div class="space-y-6">
            <x-ikontrol.module-section title="Resumen">
                <div class="space-y-3 text-sm">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Descuento global fijo</label>
                        <input type="number" step="0.000001" min="0" name="global_discount_amount" x-model="globalDiscount" class="w-full rounded-md border-gray-300">
                    </div>
                    <div class="border-t border-gray-100 pt-3 space-y-2">
                        <div class="flex justify-between"><span>Subtotal</span><span x-text="`$${money(totals.subtotal)}`"></span></div>
                        <div class="flex justify-between"><span>Descuentos por partida</span><span x-text="`$${money(totals.lineDiscount)}`"></span></div>
                        <div class="flex justify-between"><span>Descuento global</span><span x-text="`$${money(totals.globalDiscount)}`"></span></div>
                        <div class="flex justify-between"><span>Impuestos estimados</span><span x-text="`$${money(totals.tax)}`"></span></div>
                        <div class="border-t pt-3 flex justify-between text-lg font-semibold text-gray-900"><span>Total estimado</span><span x-text="`$${money(totals.total)}`"></span></div>
                    </div>
                    <p class="text-xs text-gray-500">El backend recalcula y valida los importes al guardar o previsualizar.</p>
                </div>
            </x-ikontrol.module-section>

            <x-ikontrol.module-section title="Acciones">
                <div class="space-y-3">
                    <button
                        type="submit"
                        formaction="{{ $previewDraftUrl }}"
                        formmethod="POST"
                        @click="if ($refs.methodOverride) { $refs.methodOverride.disabled = true; setTimeout(() => $refs.methodOverride.disabled = false, 1000); }"
                        class="block w-full rounded-md border border-gray-200 bg-white px-4 py-2 text-center text-sm font-medium text-gray-700"
                    >Previsualizar cotizacion</button>
                    <button name="save_action" value="draft" class="block w-full rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white">Guardar borrador</button>
                    <button name="save_action" value="send" class="block w-full rounded-md border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-medium text-violet-700">Guardar y enviar</button>
                    <a href="{{ route('comercial.cotizaciones.index') }}" class="block w-full rounded-md border border-gray-200 bg-white px-4 py-2 text-center text-sm font-medium text-gray-700">Cancelar</a>
                </div>
            </x-ikontrol.module-section>
        </div>
    </div>
</form>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('commercialQuoteForm', (config) => ({
                    clientOptionsUrl: config.clientOptionsUrl,
                    productSearchUrl: config.productSearchUrl,
                    clientId: config.clientId || '',
                    contactId: config.contactId || '',
                    fiscalId: config.fiscalId || '',
                    contacts: [],
                    fiscalClients: [],
                    globalDiscount: config.globalDiscount || '0',
                    productSearch: { query: '', open: false, results: [] },
                    activeTaxRowIndex: -1,
                    taxDrawer: { open: false, error: '' },
                    taxesDraft: [],
                    taxFeedback: '',
                    items: (config.initialItems || []).map((item, index) => ({
                        uid: `${Date.now()}-${index}`,
                        product_id: item.product_id || '',
                        sku: item.sku || '',
                        snapshot_name: item.snapshot_name || '',
                        snapshot_description: item.snapshot_description || '',
                        snapshot_unit: item.snapshot_unit || '',
                        quantity: item.quantity || '1',
                        unit_price: item.unit_price || '0',
                        line_discount_amount: item.line_discount_amount || '0',
                        taxes: Array.isArray(item.taxes) && item.taxes.length
                            ? item.taxes.map((tax) => ({
                                tax_name: tax.tax_name || '',
                                tax_type: tax.tax_type === 'retencion' ? 'retencion' : 'traslado',
                                tax_mode: ['rate', 'zero', 'exempt'].includes(tax.tax_mode) ? tax.tax_mode : 'rate',
                                rate: ['zero', 'exempt'].includes(tax.tax_mode) ? '0' : (tax.rate || '0'),
                            })).filter((tax) => tax.tax_name)
                            : ((item.tax_name || '').trim() || Number.parseFloat(item.tax_rate || '0') > 0
                                ? [{
                                    tax_name: item.tax_name || 'IVA',
                                    tax_type: item.tax_type === 'retencion' ? 'retencion' : 'traslado',
                                    tax_mode: Number.parseFloat(item.tax_rate || '0') > 0 ? 'rate' : 'zero',
                                    rate: item.tax_rate || '0',
                                }]
                                : []),
                        notes: item.notes || '',
                    })),
                    init() {
                        if (this.clientId) {
                            this.loadClientOptions();
                        }
                    },
                    async loadClientOptions() {
                        this.contacts = [];
                        this.fiscalClients = [];
                        if (!this.clientId) return;
                        const url = this.clientOptionsUrl.replace('__CLIENT__', encodeURIComponent(this.clientId));
                        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const payload = await response.json();
                        this.contacts = payload.contacts || [];
                        this.fiscalClients = payload.fiscal_clients || [];
                        if (!this.contacts.some((item) => String(item.id) === String(this.contactId))) this.contactId = '';
                        if (!this.fiscalClients.some((item) => String(item.id) === String(this.fiscalId))) {
                            const def = this.fiscalClients.find((item) => item.is_default);
                            this.fiscalId = def ? String(def.id) : '';
                        }
                    },
                    async searchProducts() {
                        const q = (this.productSearch.query || '').trim();
                        if (q.length < 2) {
                            this.productSearch.results = [];
                            return;
                        }
                        const url = `${this.productSearchUrl}?q=${encodeURIComponent(q)}&commercial_client_id=${encodeURIComponent(this.clientId || '')}`;
                        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const payload = await response.json();
                        this.productSearch.results = payload.data || [];
                    },
                    addProduct(product) {
                        this.items.push({
                            uid: `${Date.now()}-${this.items.length}`,
                            product_id: product.id || '',
                            sku: product.sku || '',
                            snapshot_name: product.snapshot_name || '',
                            snapshot_description: product.snapshot_description || '',
                            snapshot_unit: product.snapshot_unit || '',
                            quantity: '1',
                            unit_price: product.unit_price || '0',
                            line_discount_amount: '0',
                            taxes: this.normalizeTaxes(product),
                            notes: '',
                        });
                        this.productSearch.query = '';
                        this.productSearch.results = [];
                        this.productSearch.open = false;
                    },
                    addItem() {
                        this.items.push({
                            uid: `${Date.now()}-${this.items.length}`,
                            product_id: '',
                            sku: '',
                            snapshot_name: '',
                            snapshot_description: '',
                            snapshot_unit: '',
                            quantity: '1',
                            unit_price: '0',
                            line_discount_amount: '0',
                            taxes: [],
                            notes: '',
                        });
                    },
                    removeItem(index) {
                        if (this.items.length === 1) return;
                        this.items.splice(index, 1);
                    },
                    moveItem(index, direction) {
                        const target = index + direction;
                        if (target < 0 || target >= this.items.length) return;
                        const item = this.items.splice(index, 1)[0];
                        this.items.splice(target, 0, item);
                    },
                    lineTotal(item) {
                        return Math.max(this.lineTaxableBase(item) + this.itemTaxTotal(item), 0);
                    },
                    lineSubtotal(item) {
                        return this.toNumber(item.quantity) * this.toNumber(item.unit_price);
                    },
                    lineBaseBeforeGlobal(item) {
                        return Math.max(this.lineSubtotal(item) - this.toNumber(item.line_discount_amount), 0);
                    },
                    lineGlobalShare(item) {
                        const baseTotal = this.items.reduce((carry, row) => carry + this.lineBaseBeforeGlobal(row), 0);
                        if (baseTotal <= 0) return 0;
                        return Math.min(this.toNumber(this.globalDiscount) * (this.lineBaseBeforeGlobal(item) / baseTotal), this.lineBaseBeforeGlobal(item));
                    },
                    lineTaxableBase(item) {
                        return Math.max(this.lineBaseBeforeGlobal(item) - this.lineGlobalShare(item), 0);
                    },
                    taxAmountFor(item, tax) {
                        if ((tax.tax_mode || 'rate') !== 'rate') return 0;
                        const amount = this.lineTaxableBase(item) * this.toNumber(tax.rate);
                        return tax.tax_type === 'retencion' ? -amount : amount;
                    },
                    itemTaxTotal(item) {
                        return (item.taxes || []).reduce((carry, tax) => carry + this.taxAmountFor(item, tax), 0);
                    },
                    taxTotalsFor(taxes, item) {
                        return (taxes || []).reduce((carry, tax) => {
                            const amount = this.taxAmountFor(item, tax);
                            if (tax.tax_type === 'retencion') {
                                carry.retentions += Math.abs(amount);
                            } else {
                                carry.transfers += amount;
                            }
                            carry.net += amount;
                            return carry;
                        }, { transfers: 0, retentions: 0, net: 0 });
                    },
                    taxSummary(item) {
                        if (!(item.taxes || []).length) return 'Sin impuestos';
                        return item.taxes.map((tax) => this.taxLabel(tax)).join(' + ');
                    },
                    taxLabel(tax) {
                        const name = tax.tax_name || 'Impuesto';
                        if (tax.tax_mode === 'exempt') return `${name} exento`;
                        if (tax.tax_mode === 'zero') return `${name} tasa cero`;
                        const pct = this.toNumber(tax.rate) * 100;
                        return `${name} ${pct.toLocaleString('es-MX', { maximumFractionDigits: 4 })}%`;
                    },
                    normalizeTaxes(item) {
                        const taxes = Array.isArray(item.taxes) ? item.taxes : [];
                        if (taxes.length) {
                            return taxes.map((tax) => this.normalizeTax(tax)).filter((tax) => tax.tax_name);
                        }
                        if ((item.tax_name || '').trim() || this.toNumber(item.tax_rate) > 0) {
                            return [this.normalizeTax({
                                tax_name: item.tax_name || 'IVA',
                                tax_type: item.tax_type || 'traslado',
                                tax_mode: this.toNumber(item.tax_rate) > 0 ? 'rate' : 'zero',
                                rate: item.tax_rate || '0',
                            })];
                        }
                        return [];
                    },
                    cloneTaxes(taxes) {
                        return (Array.isArray(taxes) ? taxes : [])
                            .map((tax) => this.normalizeTax({
                                tax_name: tax.tax_name,
                                tax_type: tax.tax_type,
                                tax_mode: tax.tax_mode,
                                rate: tax.rate,
                            }));
                    },
                    normalizeTax(tax) {
                        const mode = ['rate', 'zero', 'exempt'].includes(tax.tax_mode) ? tax.tax_mode : 'rate';
                        return {
                            tax_name: tax.tax_name || '',
                            tax_type: tax.tax_type === 'retencion' ? 'retencion' : 'traslado',
                            tax_mode: mode,
                            rate: mode === 'rate' ? (tax.rate || '0') : '0',
                        };
                    },
                    openTaxes(index) {
                        this.activeTaxRowIndex = index;
                        this.taxDrawer = { open: true, error: '' };
                        this.taxesDraft = this.cloneTaxes(this.items[index]?.taxes || []);
                    },
                    addTax() {
                        this.taxesDraft.push(this.normalizeTax({
                            tax_name: 'IVA',
                            tax_type: 'traslado',
                            tax_mode: 'rate',
                            rate: '0.160000',
                        }));
                    },
                    removeTax(index) {
                        this.taxesDraft.splice(index, 1);
                    },
                    applyTaxes() {
                        if (this.activeTaxRowIndex < 0 || !this.items[this.activeTaxRowIndex]) return;
                        const draftWithValues = this.taxesDraft
                            .filter((tax) => (tax.tax_name || '').trim() !== '' || this.toNumber(tax.rate) > 0);
                        const normalized = this.cloneTaxes(draftWithValues);
                        const invalid = normalized.find((tax) => {
                            if (tax.tax_name.trim() === '') return true;
                            if (!['traslado', 'retencion'].includes(tax.tax_type)) return true;
                            if (!['rate', 'zero', 'exempt'].includes(tax.tax_mode)) return true;
                            if (tax.tax_mode === 'rate' && this.toNumber(tax.rate) < 0) return true;
                            return false;
                        });

                        if (invalid) {
                            this.taxDrawer.error = 'Revisa nombre, tipo, modo y tasa de cada impuesto.';
                            return;
                        }

                        this.items[this.activeTaxRowIndex].taxes = JSON.parse(JSON.stringify(normalized));
                        this.taxFeedback = 'Impuestos aplicados a la partida.';
                        window.setTimeout(() => { this.taxFeedback = ''; }, 2500);
                        this.cancelTaxes();
                    },
                    cancelTaxes() {
                        this.taxDrawer = { open: false, error: '' };
                        this.activeTaxRowIndex = -1;
                        this.taxesDraft = [];
                    },
                    activeTaxItem() {
                        return this.items[this.activeTaxRowIndex] || null;
                    },
                    get totals() {
                        const subtotal = this.items.reduce((carry, item) => carry + this.lineSubtotal(item), 0);
                        const lineDiscount = this.items.reduce((carry, item) => carry + this.toNumber(item.line_discount_amount), 0);
                        const globalDiscount = this.toNumber(this.globalDiscount);
                        const tax = this.items.reduce((carry, item) => carry + this.itemTaxTotal(item), 0);
                        return {
                            subtotal,
                            lineDiscount,
                            globalDiscount,
                            tax,
                            total: Math.max(subtotal - lineDiscount - globalDiscount + tax, 0),
                        };
                    },
                    toNumber(value) {
                        const parsed = Number.parseFloat(value || '0');
                        return Number.isFinite(parsed) ? parsed : 0;
                    },
                    money(value) {
                        return this.toNumber(value).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                }));
            });
        </script>
    @endpush
@endonce
