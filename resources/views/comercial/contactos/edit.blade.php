<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-3xl mx-auto">
        <x-ikontrol.page-header
            title="Editar contacto"
            :description="$commercialClient->name"
            :breadcrumbs="['iKontrol', 'Comercial', 'Clientes', 'Contacto']"
        />

        @if($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-ikontrol.module-section title="Datos del contacto">
            <form method="POST" action="{{ route('comercial.contactos.update', [$commercialClient, $commercialContact]) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Nombre *</label>
                        <input name="name" value="{{ old('name', $commercialContact->name) }}" required class="w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Puesto</label>
                        <input name="position" value="{{ old('position', $commercialContact->position) }}" class="w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Correo</label>
                        <input type="email" name="email" value="{{ old('email', $commercialContact->email) }}" class="w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Telefono</label>
                        <input name="phone" value="{{ old('phone', $commercialContact->phone) }}" class="w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Celular</label>
                        <input name="mobile" value="{{ old('mobile', $commercialContact->mobile) }}" class="w-full rounded-md border-gray-300">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $commercialContact->is_primary)) class="rounded"> Principal</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="receives_quotes" value="1" @checked(old('receives_quotes', $commercialContact->receives_quotes)) class="rounded"> Cotizaciones</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="receives_documents" value="1" @checked(old('receives_documents', $commercialContact->receives_documents)) class="rounded"> Documentos</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $commercialContact->is_active)) class="rounded"> Activo</label>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Notas</label>
                    <textarea name="notes" rows="4" class="w-full rounded-md border-gray-300">{{ old('notes', $commercialContact->notes) }}</textarea>
                </div>
                <div class="flex gap-3">
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Guardar contacto</button>
                    <a href="{{ route('comercial.clientes.show', $commercialClient) }}" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Cancelar</a>
                </div>
            </form>
        </x-ikontrol.module-section>
    </div>
</x-app-layout>
