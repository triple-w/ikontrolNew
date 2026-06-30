<div
    x-show="taxDrawer.open"
    x-cloak
    class="fixed inset-0 z-50"
    aria-modal="true"
    role="dialog"
    @keydown.escape.window="cancelTaxes()"
>
    <div class="absolute inset-0 bg-gray-900/40" @click="cancelTaxes()"></div>
    <aside class="absolute right-0 top-0 flex h-full w-full max-w-xl flex-col bg-white shadow-2xl">
        <div class="border-b border-gray-200 px-5 py-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500">Impuestos comerciales</p>
                    <h3 class="text-lg font-semibold text-gray-900" x-text="activeTaxItem()?.snapshot_name || 'Partida'"></h3>
                    <p class="mt-1 text-sm text-gray-500">La base se calcula automaticamente; no se captura manualmente.</p>
                </div>
                <button type="button" @click="cancelTaxes()" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50" aria-label="Cerrar drawer">X</button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4">
            <div class="mb-4 grid grid-cols-3 gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm">
                <div>
                    <div class="text-xs text-gray-500">Subtotal/base</div>
                    <div class="font-semibold text-gray-900" x-text="`$${money(lineBaseBeforeGlobal(activeTaxItem() || {}))}`"></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Desc. global prop.</div>
                    <div class="font-semibold text-gray-900" x-text="`$${money(lineGlobalShare(activeTaxItem() || {}))}`"></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Base gravable</div>
                    <div class="font-semibold text-gray-900" x-text="`$${money(lineTaxableBase(activeTaxItem() || {}))}`"></div>
                </div>
            </div>

            <div class="space-y-3">
                <template x-for="(tax, index) in taxesDraft" :key="`tax-draft-${index}`">
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Nombre</label>
                                <input x-model="tax.tax_name" class="w-full rounded-md border-gray-300 text-sm" placeholder="IVA, ISR, retencion">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Tipo</label>
                                <select x-model="tax.tax_type" class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="traslado">Traslado</option>
                                    <option value="retencion">Retencion</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Modo</label>
                                <select x-model="tax.tax_mode" @change="if (tax.tax_mode !== 'rate') tax.rate = '0'" class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="rate">Con tasa</option>
                                    <option value="zero">Tasa cero</option>
                                    <option value="exempt">Exento</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Tasa</label>
                                <input type="number" step="0.000001" min="0" x-model="tax.rate" :disabled="tax.tax_mode !== 'rate'" class="w-full rounded-md border-gray-300 text-sm disabled:bg-gray-100">
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3 text-sm">
                            <div class="text-gray-500">
                                <span x-text="taxLabel(tax)"></span>
                                <span class="ml-2" x-text="`Base $${money(lineTaxableBase(activeTaxItem() || {}))}`"></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-semibold text-gray-900" x-text="`$${money(taxAmountFor(activeTaxItem() || {}, tax))}`"></span>
                                <button type="button" @click="removeTax(index)" class="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600">Eliminar</button>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="!taxesDraft.length" class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                    Esta partida no tiene impuestos comerciales.
                </div>
            </div>
        </div>

        <div class="border-t border-gray-200 px-5 py-4">
            <div x-show="taxDrawer.error" class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="taxDrawer.error"></div>
            <div class="mb-3 grid grid-cols-1 gap-2 rounded-lg bg-gray-50 p-3 text-sm sm:grid-cols-3">
                <div class="flex justify-between gap-2 sm:block">
                    <span class="text-gray-500">Traslados</span>
                    <span class="font-semibold text-gray-900" x-text="`$${money(taxTotalsFor(taxesDraft, activeTaxItem() || {}).transfers)}`"></span>
                </div>
                <div class="flex justify-between gap-2 sm:block">
                    <span class="text-gray-500">Retenciones</span>
                    <span class="font-semibold text-gray-900" x-text="`$${money(taxTotalsFor(taxesDraft, activeTaxItem() || {}).retentions)}`"></span>
                </div>
                <div class="flex justify-between gap-2 sm:block">
                    <span class="text-gray-500">Neto</span>
                    <span class="font-semibold text-gray-900" x-text="`$${money(taxTotalsFor(taxesDraft, activeTaxItem() || {}).net)}`"></span>
                </div>
            </div>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                <button type="button" @click="addTax()" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Agregar impuesto</button>
                <div class="flex gap-2">
                    <button type="button" @click="cancelTaxes()" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Cancelar</button>
                    <button type="button" @click="applyTaxes()" class="rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white">Aplicar impuestos</button>
                </div>
            </div>
        </div>
    </aside>
</div>
