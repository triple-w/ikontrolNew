@php
    $isEdit = isset($cliente) && $cliente->exists;
    $linkedCommercialClients = $linkedCommercialClients ?? [];
    $commercialSearchUrl = $commercialSearchUrl ?? route('comercial.search-clientes');
@endphp

<div class="mb-6" x-data="fiscalClientCommercialLinks({
    searchUrl: @js($commercialSearchUrl),
    initial: @js($linkedCommercialClients),
})">
    <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="mb-4">
            <h3 class="text-base font-semibold text-gray-800">Usar cliente comercial existente</h3>
            <p class="mt-1 text-sm text-gray-500">Opcional. Copia solo datos de contacto y direccion. RFC y regimen fiscal deben capturarse manualmente.</p>
        </div>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-[1fr_auto]">
            <input
                type="search"
                x-model="query"
                @input.debounce.350ms="search"
                class="w-full rounded-md border-gray-300"
                placeholder="Buscar por nombre comercial, correo, telefono o contacto"
            >
            <button type="button" @click="search" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">
                Buscar
            </button>
        </div>

        <div x-show="results.length" x-cloak class="mt-3 space-y-2">
            <template x-for="item in results" :key="item.id">
                <button type="button" @click="addCommercial(item, true)" class="block w-full rounded-lg border border-gray-200 p-3 text-left transition hover:border-violet-300 hover:bg-violet-50/50">
                    <span class="block font-medium text-gray-800" x-text="item.name || item.business_name || 'Sin nombre'"></span>
                    <span class="mt-1 block text-xs text-gray-500" x-text="commercialSummary(item)"></span>
                </button>
            </template>
        </div>

        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            No se copia RFC, regimen fiscal, uso CFDI ni datos SAT. El codigo postal solo se copia si marcas la confirmacion.
        </div>

        <label class="mt-3 flex items-start gap-2 text-sm text-gray-600">
            <input type="checkbox" name="copy_postal_code_from_commercial" value="1" x-model="copyPostalCode" class="mt-1 rounded border-gray-300">
            <span>Confirmo que deseo copiar el codigo postal comercial al campo fiscal.</span>
        </label>

        <div class="mt-4 space-y-3" x-show="linked.length" x-cloak>
            <template x-for="item in linked" :key="item.id">
                <div class="rounded-lg border border-gray-200 p-3">
                    <input type="hidden" name="commercial_client_ids[]" :value="item.id">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="font-medium text-gray-800" x-text="item.name || item.business_name"></div>
                            <div class="text-xs text-gray-500" x-text="commercialSummary(item)"></div>
                        </div>
                        <button type="button" @click="removeCommercial(item.id)" class="text-sm text-red-600 hover:underline">Quitar relacion</button>
                    </div>
                </div>
            </template>
        </div>

        <label class="mt-3 flex items-start gap-2 text-sm text-gray-600">
            <input type="checkbox" name="confirm_without_commercial_links" value="1" class="mt-1 rounded border-gray-300">
            <span>Confirmo que este cliente fiscal puede quedar sin clientes comerciales relacionados.</span>
        </label>

        <label class="mt-3 flex items-start gap-2 text-sm text-gray-600">
            <input type="checkbox" name="duplicate_confirmed" value="1" class="mt-1 rounded border-gray-300">
            <span>Confirmo que este cliente fiscal es un registro distinto aunque exista uno parecido.</span>
        </label>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">RFC *</label>
        <input name="rfc" value="{{ old('rfc', $cliente->rfc) }}"
               class="w-full rounded-md border-gray-300" maxlength="30" required>
        @error('rfc') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
       <label class="block text-sm font-medium mb-1">Régimen Fiscal *</label>
        <select name="regimen_fiscal" class="w-full rounded-md border-gray-300" required>
            <option value="">Selecciona un régimen...</option>
            @foreach (config('sat.regimenes_fiscales') as $clave => $nombre)
                <option value="{{ $clave }}" @selected(old('regimen_fiscal', $cliente->regimen_fiscal) == $clave)>
                    {{ $clave }} - {{ $nombre }}
                </option>
            @endforeach
        </select>
        @error('regimen_fiscal') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Razón Social *</label>
        <input name="razon_social" value="{{ old('razon_social', $cliente->razon_social) }}"
               class="w-full rounded-md border-gray-300" maxlength="200" required>
        @error('razon_social') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input name="email" value="{{ old('email', $cliente->email) }}"
               class="w-full rounded-md border-gray-300" maxlength="90" type="email">
        @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Teléfono</label>
        <input name="telefono" value="{{ old('telefono', $cliente->telefono) }}"
               class="w-full rounded-md border-gray-300" maxlength="30">
        @error('telefono') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Nombre de contacto</label>
        <input name="nombre_contacto" value="{{ old('nombre_contacto', $cliente->nombre_contacto) }}"
               class="w-full rounded-md border-gray-300" maxlength="150">
        @error('nombre_contacto') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('fiscalClientCommercialLinks', (config) => ({
                    searchUrl: config.searchUrl,
                    query: '',
                    results: [],
                    linked: Array.isArray(config.initial) ? config.initial : [],
                    copyPostalCode: false,
                    async search() {
                        if (this.query.trim().length < 2) {
                            this.results = [];
                            return;
                        }

                        const response = await fetch(`${this.searchUrl}?q=${encodeURIComponent(this.query.trim())}`, {
                            headers: { 'Accept': 'application/json' },
                        });
                        const payload = await response.json();
                        this.results = Array.isArray(payload.data) ? payload.data : [];
                    },
                    addCommercial(item, copy) {
                        if (!this.linked.some((current) => Number(current.id) === Number(item.id))) {
                            this.linked.push(item);
                        }

                        if (copy) {
                            this.fillCompatible(item);
                        }
                    },
                    removeCommercial(id) {
                        if (!confirm('Quitar relacion con este cliente comercial? No se borrara ningun cliente.')) {
                            return;
                        }

                        this.linked = this.linked.filter((item) => Number(item.id) !== Number(id));
                    },
                    fillCompatible(item) {
                        this.setField('razon_social', item.business_name || item.name);
                        this.setField('email', item.email || (item.primary_contact ? item.primary_contact.email : ''));
                        this.setField('telefono', item.phone || (item.primary_contact ? item.primary_contact.phone : ''));
                        this.setField('calle', item.street);
                        this.setField('no_ext', item.exterior_number);
                        this.setField('no_int', item.interior_number);
                        this.setField('colonia', item.neighborhood);
                        this.setField('municipio', item.city);
                        this.setField('localidad', item.city);
                        this.setField('estado', item.state);
                        this.setField('pais', item.country || 'MEX');
                        if (this.copyPostalCode) {
                            this.setField('codigo_postal', item.postal_code);
                        }
                    },
                    setField(name, value) {
                        if (!value) return;
                        const field = this.$root.closest('form').querySelector(`[name="${name}"]`);
                        if (field && !field.value) {
                            field.value = value;
                            field.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    },
                    commercialSummary(item) {
                        const contact = item.primary_contact ? item.primary_contact.name : '';
                        return `${item.business_name || 'Sin razon comercial'} - ${contact || 'Sin contacto'} - ${item.email || 'Sin correo'} - ${item.phone || 'Sin telefono'}`;
                    },
                }));
            });
        </script>
    @endpush
@endonce

<hr class="my-6">

<h3 class="text-base font-semibold mb-3">Dirección</h3>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Calle</label>
        <input name="calle" value="{{ old('calle', $cliente->calle) }}"
               class="w-full rounded-md border-gray-300" maxlength="100">
        @error('calle') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">No. Ext</label>
        <input name="no_ext" value="{{ old('no_ext', $cliente->no_ext) }}"
               class="w-full rounded-md border-gray-300" maxlength="20">
        @error('no_ext') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">No. Int</label>
        <input name="no_int" value="{{ old('no_int', $cliente->no_int) }}"
               class="w-full rounded-md border-gray-300" maxlength="20">
        @error('no_int') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Colonia</label>
        <input name="colonia" value="{{ old('colonia', $cliente->colonia) }}"
               class="w-full rounded-md border-gray-300" maxlength="50">
        @error('colonia') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Municipio</label>
        <input name="municipio" value="{{ old('municipio', $cliente->municipio) }}"
               class="w-full rounded-md border-gray-300" maxlength="50">
        @error('municipio') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Localidad</label>
        <input name="localidad" value="{{ old('localidad', $cliente->localidad) }}"
               class="w-full rounded-md border-gray-300" maxlength="50">
        @error('localidad') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Estado</label>
        <input name="estado" value="{{ old('estado', $cliente->estado) }}"
               class="w-full rounded-md border-gray-300" maxlength="50">
        @error('estado') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Código Postal</label>
        <input name="codigo_postal" value="{{ old('codigo_postal', $cliente->codigo_postal) }}"
               class="w-full rounded-md border-gray-300" maxlength="10">
        @error('codigo_postal') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">País</label>
        <input name="pais" value="{{ old('pais', $cliente->pais) }}"
               class="w-full rounded-md border-gray-300" maxlength="30">
        @error('pais') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
</div>
