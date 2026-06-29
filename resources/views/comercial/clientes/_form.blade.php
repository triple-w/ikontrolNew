@php
    $selectedFiscalIds = collect(old('fiscal_client_ids', $selectedFiscalIds ?? []))->map(fn($id) => (int) $id)->all();
    $defaultFiscalId = (int) old('default_fiscal_client_id', $defaultFiscalId ?? 0);
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

<form method="POST" action="{{ $action }}" class="space-y-6">
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

    <x-ikontrol.module-section title="Receptores fiscales relacionados" description="Opcional. No modifica el catalogo fiscal existente.">
        @if($fiscalClients->isEmpty())
            <x-ikontrol.empty-state title="Sin clientes fiscales disponibles" message="Puedes guardar el cliente comercial y relacionarlo despues." />
        @else
            <div class="space-y-3">
                @foreach($fiscalClients as $fiscal)
                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 dark:border-gray-700/60 p-3">
                        <input type="checkbox" name="fiscal_client_ids[]" value="{{ $fiscal->id }}" @checked(in_array((int)$fiscal->id, $selectedFiscalIds, true)) class="mt-1 rounded border-gray-300">
                        <span class="grow">
                            <span class="block font-medium text-gray-800 dark:text-gray-100">{{ $fiscal->razon_social }}</span>
                            <span class="block text-xs text-gray-500">{{ $fiscal->rfc }}</span>
                        </span>
                        <span class="flex items-center gap-2 text-xs text-gray-500">
                            <input type="radio" name="default_fiscal_client_id" value="{{ $fiscal->id }}" @checked($defaultFiscalId === (int)$fiscal->id) class="border-gray-300">
                            Predeterminado
                        </span>
                    </label>
                @endforeach
            </div>
        @endif
    </x-ikontrol.module-section>

    <x-ikontrol.module-section title="Notas internas">
        <textarea name="notes" rows="4" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">{{ old('notes', $commercialClient->notes) }}</textarea>
    </x-ikontrol.module-section>

    <div class="flex flex-wrap gap-3">
        <button class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Guardar cliente</button>
        <a href="{{ route('comercial.clientes.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Cancelar</a>
    </div>
</form>
