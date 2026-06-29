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
            'tax_name' => '',
            'tax_type' => 'traslado',
            'tax_rate' => '0',
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
                                <th class="px-3 py-2">Impuesto</th>
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
                                        <div class="space-y-2">
                                            <input :name="`items[${index}][tax_name]`" x-model="item.tax_name" class="w-28 rounded-md border-gray-300 text-sm" placeholder="IVA">
                                            <input type="number" step="0.000001" min="0" :name="`items[${index}][tax_rate]`" x-model="item.tax_rate" class="w-28 rounded-md border-gray-300 text-sm" placeholder="0.160000">
                                            <select :name="`items[${index}][tax_type]`" x-model="item.tax_type" class="w-28 rounded-md border-gray-300 text-xs">
                                                <option value="traslado">Traslado</option>
                                                <option value="retencion">Retencion</option>
                                            </select>
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
                        formtarget="_blank"
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
                        tax_name: item.tax_name || '',
                        tax_type: item.tax_type || 'traslado',
                        tax_rate: item.tax_rate || '0',
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
                            tax_name: product.tax_name || '',
                            tax_type: product.tax_type || 'traslado',
                            tax_rate: product.tax_rate || '0',
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
                            tax_name: '',
                            tax_type: 'traslado',
                            tax_rate: '0',
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
                        const subtotal = this.toNumber(item.quantity) * this.toNumber(item.unit_price);
                        const base = Math.max(subtotal - this.toNumber(item.line_discount_amount), 0);
                        const tax = base * this.toNumber(item.tax_rate) * (item.tax_type === 'retencion' ? -1 : 1);
                        return Math.max(base + tax, 0);
                    },
                    get totals() {
                        const subtotal = this.items.reduce((carry, item) => carry + this.toNumber(item.quantity) * this.toNumber(item.unit_price), 0);
                        const lineDiscount = this.items.reduce((carry, item) => carry + this.toNumber(item.line_discount_amount), 0);
                        const globalDiscount = this.toNumber(this.globalDiscount);
                        const baseTotal = Math.max(subtotal - lineDiscount, 0);
                        const tax = this.items.reduce((carry, item) => {
                            const lineSubtotal = this.toNumber(item.quantity) * this.toNumber(item.unit_price);
                            const lineBase = Math.max(lineSubtotal - this.toNumber(item.line_discount_amount), 0);
                            const share = baseTotal > 0 ? globalDiscount * (lineBase / baseTotal) : 0;
                            const taxable = Math.max(lineBase - share, 0);
                            const sign = item.tax_type === 'retencion' ? -1 : 1;
                            return carry + taxable * this.toNumber(item.tax_rate) * sign;
                        }, 0);
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
