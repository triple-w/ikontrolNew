@php
    $formItems = old('items', $items ?? []);
    if (empty($formItems)) {
        $formItems = [[
            'product_id' => null,
            'sku' => '',
            'snapshot_name' => '',
            'snapshot_description' => '',
            'snapshot_unit' => '',
            'quantity' => '1.000000',
            'unit_price' => '0.000000',
            'line_discount_amount' => '0.000000',
            'tax_name' => '',
            'tax_type' => 'traslado',
            'tax_rate' => '0.000000',
            'notes' => '',
        ]];
    }

    $issuedAt = old('issued_at', $quote->issued_at ? \Illuminate\Support\Carbon::parse($quote->issued_at)->format('Y-m-d') : now()->toDateString());
    $expiresAt = old('expires_at', $quote->expires_at ? \Illuminate\Support\Carbon::parse($quote->expires_at)->format('Y-m-d') : '');
    $clientId = old('commercial_client_id', $quote->commercial_client_id);
    $contactId = old('commercial_contact_id', $quote->commercial_contact_id);
    $fiscalId = old('fiscal_client_id', $quote->fiscal_client_id);
@endphp

@if ($errors->any())
    <div class="mb-6">
        <x-ikontrol.info-alert title="Revisa la cotizacion">{{ $errors->first() }}</x-ikontrol.info-alert>
    </div>
@endif

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
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <x-ikontrol.module-section title="Cliente comercial">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium mb-1">Cliente comercial *</label>
                        <select name="commercial_client_id" x-model="clientId" @change="loadClientOptions" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700" required>
                            <option value="">Selecciona cliente</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}{{ $client->business_name ? ' - '.$client->business_name : '' }}</option>
                            @endforeach
                        </select>
                        @error('commercial_client_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Contacto</label>
                        <select name="commercial_contact_id" x-model="contactId" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                            <option value="">Sin contacto</option>
                            <template x-for="contact in contacts" :key="contact.id">
                                <option :value="contact.id" x-text="contact.name"></option>
                            </template>
                        </select>
                        @error('commercial_contact_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Receptor fiscal sugerido</label>
                        <select name="fiscal_client_id" x-model="fiscalId" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                            <option value="">Sin receptor fiscal</option>
                            <template x-for="fiscal in fiscalClients" :key="fiscal.id">
                                <option :value="fiscal.id" x-text="`${fiscal.rfc} - ${fiscal.razon_social}`"></option>
                            </template>
                        </select>
                        @error('fiscal_client_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Responsable</label>
                        <select name="assigned_user_id" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                            <option value="">Sin responsable</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((string)old('assigned_user_id', $quote->assigned_user_id) === (string)$user->id)>{{ $user->username }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </x-ikontrol.module-section>

            <x-ikontrol.module-section title="Datos generales">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Fecha *</label>
                        <input type="date" name="issued_at" value="{{ $issuedAt }}" class="w-full rounded-md border-gray-300" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Vencimiento</label>
                        <input type="date" name="expires_at" value="{{ $expiresAt }}" class="w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Moneda *</label>
                        <input name="currency" value="{{ old('currency', $quote->currency ?: 'MXN') }}" maxlength="3" class="w-full rounded-md border-gray-300 uppercase" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Tipo de cambio</label>
                        <input name="exchange_rate" value="{{ old('exchange_rate', $quote->exchange_rate) }}" type="number" step="0.000001" min="0" class="w-full rounded-md border-gray-300">
                    </div>
                </div>
            </x-ikontrol.module-section>

            <x-ikontrol.module-section title="Partidas">
                <div class="space-y-4">
                    <template x-for="(item, index) in items" :key="item.uid">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <input type="hidden" :name="`items[${index}][product_id]`" x-model="item.product_id">
                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                                <div class="lg:col-span-3">
                                    <label class="block text-xs font-medium mb-1">Buscar producto</label>
                                    <input type="search" x-model="item.query" @input.debounce.350ms="searchProduct(index)" class="w-full rounded-md border-gray-300" placeholder="Clave o descripcion">
                                    <div x-show="item.results.length" x-cloak class="mt-1 max-h-40 overflow-auto rounded-md border border-gray-200 bg-white shadow-sm">
                                        <template x-for="product in item.results" :key="product.id">
                                            <button type="button" @click="chooseProduct(index, product)" class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50">
                                                <span class="font-medium" x-text="product.snapshot_name"></span>
                                                <span class="block text-xs text-gray-500" x-text="product.sku"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <div class="lg:col-span-3">
                                    <label class="block text-xs font-medium mb-1">Concepto *</label>
                                    <input :name="`items[${index}][snapshot_name]`" x-model="item.snapshot_name" class="w-full rounded-md border-gray-300" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">SKU</label>
                                    <input :name="`items[${index}][sku]`" x-model="item.sku" class="w-full rounded-md border-gray-300">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">Unidad</label>
                                    <input :name="`items[${index}][snapshot_unit]`" x-model="item.snapshot_unit" class="w-full rounded-md border-gray-300">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">Cantidad *</label>
                                    <input :name="`items[${index}][quantity]`" x-model="item.quantity" type="number" step="0.000001" min="0.000001" class="w-full rounded-md border-gray-300" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">Precio *</label>
                                    <input :name="`items[${index}][unit_price]`" x-model="item.unit_price" type="number" step="0.000001" min="0" class="w-full rounded-md border-gray-300" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">Descuento</label>
                                    <input :name="`items[${index}][line_discount_amount]`" x-model="item.line_discount_amount" type="number" step="0.000001" min="0" class="w-full rounded-md border-gray-300">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">Tasa</label>
                                    <input :name="`items[${index}][tax_rate]`" x-model="item.tax_rate" type="number" step="0.000001" min="0" class="w-full rounded-md border-gray-300" placeholder="0.160000">
                                </div>
                                <div class="lg:col-span-2">
                                    <label class="block text-xs font-medium mb-1">Impuesto</label>
                                    <input :name="`items[${index}][tax_name]`" x-model="item.tax_name" class="w-full rounded-md border-gray-300" placeholder="IVA">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">Tipo</label>
                                    <select :name="`items[${index}][tax_type]`" x-model="item.tax_type" class="w-full rounded-md border-gray-300">
                                        <option value="traslado">Traslado</option>
                                        <option value="retencion">Retencion</option>
                                    </select>
                                </div>
                                <div class="lg:col-span-12">
                                    <label class="block text-xs font-medium mb-1">Descripcion</label>
                                    <textarea :name="`items[${index}][snapshot_description]`" x-model="item.snapshot_description" rows="2" class="w-full rounded-md border-gray-300"></textarea>
                                </div>
                                <div class="lg:col-span-10">
                                    <label class="block text-xs font-medium mb-1">Notas</label>
                                    <input :name="`items[${index}][notes]`" x-model="item.notes" class="w-full rounded-md border-gray-300">
                                </div>
                                <div class="lg:col-span-2 flex items-end justify-end gap-2">
                                    <button type="button" @click="moveItem(index, -1)" class="rounded-md border px-3 py-2 text-sm">Subir</button>
                                    <button type="button" @click="removeItem(index)" class="rounded-md border border-red-200 px-3 py-2 text-sm text-red-600">Quitar</button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="addItem" class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Agregar partida</button>
                </div>
            </x-ikontrol.module-section>

            <x-ikontrol.module-section title="Condiciones y notas">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Condiciones comerciales</label>
                        <textarea name="commercial_terms" rows="4" class="w-full rounded-md border-gray-300">{{ old('commercial_terms', $quote->commercial_terms) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Notas visibles para cliente</label>
                        <textarea name="customer_notes" rows="4" class="w-full rounded-md border-gray-300">{{ old('customer_notes', $quote->customer_notes) }}</textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">Notas internas</label>
                        <textarea name="internal_notes" rows="3" class="w-full rounded-md border-gray-300">{{ old('internal_notes', $quote->internal_notes) }}</textarea>
                    </div>
                </div>
            </x-ikontrol.module-section>
        </div>

        <div class="space-y-6">
            <x-ikontrol.module-section title="Resumen">
                <div class="space-y-3 text-sm">
                    <div>
                        <label class="block text-sm font-medium mb-1">Descuento global fijo</label>
                        <input name="global_discount_amount" x-model="globalDiscount" type="number" step="0.000001" min="0" class="w-full rounded-md border-gray-300">
                    </div>
                    <dl class="space-y-2">
                        <div class="flex justify-between gap-4"><dt>Subtotal</dt><dd x-text="money(totals.subtotal)"></dd></div>
                        <div class="flex justify-between gap-4"><dt>Descuentos por partida</dt><dd x-text="money(totals.lineDiscount)"></dd></div>
                        <div class="flex justify-between gap-4"><dt>Descuento global</dt><dd x-text="money(toNumber(globalDiscount))"></dd></div>
                        <div class="flex justify-between gap-4"><dt>Impuestos</dt><dd x-text="money(totals.tax)"></dd></div>
                        <div class="border-t pt-3 flex justify-between gap-4 text-base font-semibold text-gray-900"><dt>Total</dt><dd x-text="money(totals.total)"></dd></div>
                    </dl>
                </div>
            </x-ikontrol.module-section>

            <x-ikontrol.module-section title="Acciones">
                <div class="space-y-3">
                    <button name="save_action" value="draft" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Guardar borrador</button>
                    <button name="save_action" value="send" class="w-full rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Guardar y enviar</button>
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
                        query: '',
                        results: [],
                    })),
                    init() {
                        if (this.clientId) this.loadClientOptions();
                    },
                    async loadClientOptions() {
                        this.contacts = [];
                        this.fiscalClients = [];
                        if (!this.clientId) return;

                        const url = this.clientOptionsUrl.replace('__CLIENT__', this.clientId);
                        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const payload = await response.json();
                        this.contacts = payload.contacts || [];
                        this.fiscalClients = payload.fiscal_clients || [];

                        if (!this.contacts.some((item) => Number(item.id) === Number(this.contactId))) this.contactId = '';
                        if (!this.fiscalClients.some((item) => Number(item.id) === Number(this.fiscalId))) this.fiscalId = '';
                        if (!this.contactId && this.contacts.length) this.contactId = this.contacts[0].id;
                        if (!this.fiscalId) {
                            const fiscalDefault = this.fiscalClients.find((item) => item.is_default);
                            if (fiscalDefault) this.fiscalId = fiscalDefault.id;
                        }
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
                            query: '',
                            results: [],
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
                    async searchProduct(index) {
                        const item = this.items[index];
                        if (!item || item.query.trim().length < 2) {
                            item.results = [];
                            return;
                        }
                        const url = `${this.productSearchUrl}?q=${encodeURIComponent(item.query.trim())}&commercial_client_id=${encodeURIComponent(this.clientId || '')}`;
                        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const payload = await response.json();
                        item.results = payload.data || [];
                    },
                    chooseProduct(index, product) {
                        const item = this.items[index];
                        Object.assign(item, product);
                        item.query = '';
                        item.results = [];
                    },
                    get totals() {
                        const subtotal = this.items.reduce((carry, item) => carry + this.toNumber(item.quantity) * this.toNumber(item.unit_price), 0);
                        const lineDiscount = this.items.reduce((carry, item) => carry + this.toNumber(item.line_discount_amount), 0);
                        const globalDiscount = this.toNumber(this.globalDiscount);
                        const base = Math.max(subtotal - lineDiscount - globalDiscount, 0);
                        const tax = this.items.reduce((carry, item) => {
                            const lineSubtotal = this.toNumber(item.quantity) * this.toNumber(item.unit_price);
                            const lineBase = Math.max(lineSubtotal - this.toNumber(item.line_discount_amount), 0);
                            const baseTotal = Math.max(subtotal - lineDiscount, 0);
                            const share = baseTotal > 0 ? globalDiscount * (lineBase / baseTotal) : 0;
                            const taxable = Math.max(lineBase - share, 0);
                            const amount = taxable * this.toNumber(item.tax_rate);
                            return carry + (item.tax_type === 'retencion' ? -amount : amount);
                        }, 0);
                        return { subtotal, lineDiscount, tax, total: Math.max(base + tax, 0) };
                    },
                    toNumber(value) {
                        const parsed = Number.parseFloat(value || 0);
                        return Number.isFinite(parsed) ? parsed : 0;
                    },
                    money(value) {
                        return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value || 0);
                    },
                }));
            });
        </script>
    @endpush
@endonce
