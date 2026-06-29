@php
    $bool = fn (string $field, bool $default = false) => (bool) old($field, $template->{$field} ?? $default);
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if(($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    @if($errors->any())
        <x-ikontrol.info-alert title="Revisa el formulario">{{ $errors->first() }}</x-ikontrol.info-alert>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <x-ikontrol.module-section title="Datos del formato">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nombre *</label>
                        <input name="name" value="{{ old('name', $template->name) }}" required class="w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tipo *</label>
                        <select name="document_type" class="w-full rounded-md border-gray-300" required>
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('document_type', $template->document_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Estilo</label>
                        <select name="accent_style" class="w-full rounded-md border-gray-300">
                            @foreach(['teal' => 'Teal iKontrol', 'violet' => 'Violeta', 'slate' => 'Gris', 'emerald' => 'Verde'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('accent_style', $template->accent_style ?: 'teal') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Logo del formato</label>
                        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @if($template->logo_path)
                            <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300">
                                Quitar logo actual
                            </label>
                        @endif
                    </div>
                </div>
            </x-ikontrol.module-section>

            <x-ikontrol.module-section title="Textos del documento" description="Texto plano con variables controladas. No se guarda HTML.">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Titulo de encabezado</label>
                        <input name="header_title" value="{{ old('header_title', $template->header_title) }}" class="w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Texto de encabezado</label>
                        <textarea name="header_text" rows="4" class="w-full rounded-md border-gray-300">{{ old('header_text', $template->header_text) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Terminos y condiciones</label>
                        <textarea name="terms_text" rows="5" class="w-full rounded-md border-gray-300">{{ old('terms_text', $template->terms_text) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Pie del documento</label>
                        <textarea name="footer_text" rows="4" class="w-full rounded-md border-gray-300">{{ old('footer_text', $template->footer_text) }}</textarea>
                    </div>
                </div>
            </x-ikontrol.module-section>
        </div>

        <div class="space-y-6">
            <x-ikontrol.module-section title="Opciones">
                <div class="space-y-3 text-sm">
                    @foreach([
                        'is_active' => 'Formato activo',
                        'is_default' => 'Predeterminado para su tipo',
                        'show_logo' => 'Mostrar logo',
                        'show_contact_info' => 'Mostrar contacto',
                        'show_fiscal_info' => 'Mostrar receptor fiscal sugerido',
                        'show_item_tax' => 'Mostrar impuesto por partida',
                        'show_item_sku' => 'Mostrar SKU/clave',
                        'show_notes' => 'Mostrar notas visibles',
                    ] as $field => $label)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="{{ $field }}" value="1" @checked($bool($field, in_array($field, ['is_active', 'show_logo', 'show_contact_info', 'show_item_tax', 'show_item_sku', 'show_notes'], true))) class="rounded border-gray-300">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </x-ikontrol.module-section>

            @include('configuracion.formatos-documentos._variables', ['variables' => $variables])
        </div>
    </div>

    <div class="flex flex-col sm:flex-row justify-end gap-3">
        <a href="{{ route('configuracion.formatos-documentos.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Cancelar</a>
        <button class="inline-flex items-center justify-center rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-violet-700">Guardar formato</button>
    </div>
</form>
