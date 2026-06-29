@php
    $selectedFiscalIds = collect(old('fiscal_client_ids', $selectedFiscalIds ?? []))->map(fn($id) => (int) $id)->all();
    $defaultFiscalId = (int) old('default_fiscal_client_id', $defaultFiscalId ?? 0);
    $linkedFiscalClients = $linkedFiscalClients ?? [];
    $fiscalSearchUrl = $fiscalSearchUrl ?? route('comercial.clientes.search-fiscales');
@endphp

@if($errors->any())
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        <div class="font-semibold">Revisa la informacion capturada.</div>
        <ul class="mt-2 list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="space-y-6" x-data="commercialClientFiscalLinks({
    searchUrl: @js($fiscalSearchUrl),
    initial: @js($linkedFiscalClients),
    defaultId: @js($defaultFiscalId),
})">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <x-ikontrol.module-section title="Datos generales">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Nombre comercial *</label>
                <input name="name" value="{{ old('name', $commercialClient->name) }}" required class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Razon comercial</label>
                <input name="business_name" value="{{ old('business_name', $commercialClient->business_name) }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Tipo *</label>
                <select name="client_type" required class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                    <option value="company" @selected(old('client_type', $commercialClient->client_type) === 'company')>Empresa</option>
                    <option value="person" @selected(old('client_type', $commercialClient->client_type) === 'person')>Persona</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Categoria</label>
                <input name="category" value="{{ old('category', $commercialClient->category) }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Responsable</label>
                <select name="assigned_user_id" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                    <option value="">Sin responsable</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((string)old('assigned_user_id', $commercialClient->assigned_user_id) === (string)$user->id)>{{ $user->username }} - {{ $user->email }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2 pt-6">
                <input type="hidden" name="is_active" value="0">
                <input id="is_active" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $commercialClient->is_active ?? true)) class="rounded border-gray-300">
                <label for="is_active" class="text-sm font-medium">Cliente activo</label>
            </div>
        </div>
    </x-ikontrol.module-section>

    <x-ikontrol.module-section title="Datos de contacto comercial">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Correo principal</label>
                <input type="email" name="email" value="{{ old('email', $commercialClient->email) }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Telefono</label>
                <input name="phone" value="{{ old('phone', $commercialClient->phone) }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Celular</label>
                <input name="mobile" value="{{ old('mobile', $commercialClient->mobile) }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
        </div>
    </x-ikontrol.module-section>

    <x-ikontrol.module-section title="Direccion comercial">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Calle</label>
                <input name="street" value="{{ old('street', $commercialClient->street) }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">No. exterior</label>
                <input name="exterior_number" value="{{ old('exterior_number', $commercialClient->exterior_number) }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">No. interior</label>
                <input name="interior_number" value="{{ old('interior_number', $commercialClient->interior_number) }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Colonia</label>
                <input name="neighborhood" value="{{ old('neighborhood', $commercialClient->neighborhood) }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Ciudad</label>
                <input name="city" value="{{ old('city', $commercialClient->city) }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Estado</label>
                <input name="state" value="{{ old('state', $commercialClient->state) }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Pais</label>
                <input name="country" value="{{ old('country', $commercialClient->country ?? 'Mexico') }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Codigo postal</label>
                <input name="postal_code" value="{{ old('postal_code', $commercialClient->postal_code) }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            </div>
        </div>
    </x-ikontrol.module-section>

    <x-ikontrol.module-section title="Usar cliente fiscal existente" description="Opcional. Puedes reutilizar datos de contacto y direccion sin modificar el catalogo fiscal.">
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-[1fr_auto]">
                <input
                    type="search"
                    x-model="query"
                    @input.debounce.350ms="search"
                    class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700"
                    placeholder="Buscar por razon social, RFC, correo o telefono"
                >
                <button type="button" @click="search" class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">
                    Buscar
                </button>
            </div>

            <div x-show="results.length" x-cloak class="space-y-2">
                <template x-for="item in results" :key="item.id">
                    <button type="button" @click="addFiscal(item, true)" class="block w-full rounded-lg border border-gray-200 p-3 text-left transition hover:border-violet-300 hover:bg-violet-50/50">
                        <span class="block font-medium text-gray-800" x-text="item.razon_social || 'Sin razon social'"></span>
                        <span class="mt-1 block text-xs text-gray-500" x-text="`${item.rfc || 'Sin RFC'} - ${item.email || 'Sin correo'} - ${item.telefono || 'Sin telefono'}`"></span>
                    </button>
                </template>
            </div>

            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                Solo se copian campos compatibles: nombre, correo, telefono y direccion. No se modifica RFC, regimen fiscal ni ningun dato SAT.
            </div>

            <template x-if="linked.length === 0">
                <x-ikontrol.empty-state title="Sin receptores fiscales relacionados" message="Puedes guardar el cliente comercial sin relacion fiscal." />
            </template>

            <div class="space-y-3" x-show="linked.length" x-cloak>
                <template x-for="item in linked" :key="item.id">
                    <div class="rounded-lg border border-gray-200 p-3">
                        <input type="hidden" name="fiscal_client_ids[]" :value="item.id">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="font-medium text-gray-800" x-text="item.razon_social"></div>
                                <div class="text-xs text-gray-500" x-text="`${item.rfc || 'Sin RFC'} - ${item.email || 'Sin correo'}`"></div>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="default_fiscal_client_id" :value="item.id" x-model="defaultId" class="border-gray-300">
                                    Predeterminado
                                </label>
                                <button type="button" @click="removeFiscal(item.id)" class="text-red-600 hover:underline">Quitar</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <label class="flex items-start gap-2 text-sm text-gray-600">
                <input type="checkbox" name="confirm_without_default" value="1" class="mt-1 rounded border-gray-300">
                <span>Confirmo que puede quedar sin receptor fiscal predeterminado.</span>
            </label>
        </div>
    </x-ikontrol.module-section>

    <x-ikontrol.module-section title="Revision de duplicados">
        <label class="flex items-start gap-2 text-sm text-gray-600">
            <input type="checkbox" name="duplicate_confirmed" value="1" class="mt-1 rounded border-gray-300">
            <span>Confirmo que este cliente comercial es un registro distinto aunque exista uno parecido.</span>
        </label>
    </x-ikontrol.module-section>

    <x-ikontrol.module-section title="Notas internas">
        <textarea name="notes" rows="4" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">{{ old('notes', $commercialClient->notes) }}</textarea>
    </x-ikontrol.module-section>

    <div class="flex flex-wrap gap-3">
        <button class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Guardar cliente</button>
        <a href="{{ route('comercial.clientes.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Cancelar</a>
    </div>
</form>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('commercialClientFiscalLinks', (config) => ({
                    searchUrl: config.searchUrl,
                    query: '',
                    results: [],
                    linked: Array.isArray(config.initial) ? config.initial : [],
                    defaultId: config.defaultId ? String(config.defaultId) : '',
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
                    addFiscal(item, copy) {
                        if (!this.linked.some((current) => Number(current.id) === Number(item.id))) {
                            this.linked.push(item);
                        }

                        if (!this.defaultId) {
                            this.defaultId = String(item.id);
                        }

                        if (copy) {
                            this.fillCompatible(item);
                        }
                    },
                    removeFiscal(id) {
                        if (!confirm('Quitar relacion con este receptor fiscal? No se borrara el cliente fiscal.')) {
                            return;
                        }

                        this.linked = this.linked.filter((item) => Number(item.id) !== Number(id));
                        if (String(this.defaultId) === String(id)) {
                            this.defaultId = '';
                        }
                    },
                    fillCompatible(item) {
                        this.setField('name', item.razon_social);
                        this.setField('business_name', item.razon_social);
                        this.setField('email', item.email);
                        this.setField('phone', item.telefono);
                        this.setField('street', item.calle);
                        this.setField('exterior_number', item.no_ext);
                        this.setField('interior_number', item.no_int);
                        this.setField('neighborhood', item.colonia);
                        this.setField('city', item.municipio || item.localidad);
                        this.setField('state', item.estado);
                        this.setField('country', item.pais || 'Mexico');
                        this.setField('postal_code', item.codigo_postal);
                    },
                    setField(name, value) {
                        if (!value) return;
                        const field = this.$root.querySelector(`[name="${name}"]`);
                        if (field && !field.value) {
                            field.value = value;
                            field.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
