<?php

namespace App\Services\CommercialDocuments;

use App\Models\Cliente;
use App\Models\CommercialClient;
use App\Models\CommercialContact;
use App\Models\CommercialDocumentTemplate;
use App\Models\CommercialQuote;
use App\Models\CommercialQuoteTax;
use App\Services\CommercialQuoteCalculator;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommercialQuoteDocumentBuilder
{
    public function __construct(
        private readonly CommercialQuoteCalculator $calculator,
        private readonly TemplateVariableResolver $resolver
    ) {
    }

    public function fromQuote(CommercialQuote $quote): array
    {
        $quote->loadMissing([
            'commercialClient',
            'commercialContact',
            'fiscalClient',
            'assignedUser',
            'documentTemplate',
            'items.taxes',
        ]);

        $template = $this->templateForQuote($quote);
        $items = $quote->items->map(fn ($item) => [
            'sku' => $item->sku,
            'name' => $item->snapshot_name,
            'description' => $item->snapshot_description,
            'unit' => $item->snapshot_unit,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'discount' => $item->line_discount_amount,
            'taxes' => $this->taxesForItem($item),
            'tax_amount' => $item->tax_amount,
            'line_subtotal' => $item->line_subtotal,
            'line_total' => $item->line_total,
            'notes' => $item->notes,
        ])->all();

        return $this->buildDocument(
            quote: $quote,
            template: $template,
            client: $quote->commercialClient,
            contact: $quote->commercialContact,
            fiscalClient: $quote->fiscalClient,
            items: $items,
            totals: [
                'subtotal' => (string) $quote->subtotal,
                'line_discount_total' => (string) $quote->line_discount_total,
                'global_discount_amount' => (string) $quote->global_discount_amount,
                'discount_total' => (string) $quote->discount_total,
                ...$this->taxTotals($items),
                'tax_total' => (string) $quote->tax_total,
                'total' => (string) $quote->total,
            ],
            logoPath: $template['logo_path'] ?? null,
            isTemporary: false
        );
    }

    public function fromPayload(array $data, int $ownerId, ?CommercialDocumentTemplate $template): array
    {
        $client = CommercialClient::query()->find($data['commercial_client_id']);
        $contact = !empty($data['commercial_contact_id'])
            ? CommercialContact::query()->find($data['commercial_contact_id'])
            : null;
        $fiscalClient = !empty($data['fiscal_client_id'])
            ? Cliente::query()->find($data['fiscal_client_id'])
            : null;

        $calculated = $this->calculator->calculate($this->payloadItems($data['items']), (string) ($data['global_discount_amount'] ?? '0'));

        $quote = new CommercialQuote([
            'users_id' => $ownerId,
            'commercial_client_id' => $data['commercial_client_id'],
            'commercial_contact_id' => $data['commercial_contact_id'] ?? null,
            'fiscal_client_id' => $data['fiscal_client_id'] ?? null,
            'commercial_document_template_id' => $template?->id,
            'folio' => 'Borrador',
            'issued_at' => $data['issued_at'],
            'expires_at' => $data['expires_at'] ?? null,
            'currency' => strtoupper((string) ($data['currency'] ?? 'MXN')),
            'exchange_rate' => $data['exchange_rate'] ?? null,
            'status' => CommercialQuote::STATUS_DRAFT,
            'commercial_terms' => $data['commercial_terms'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
        ]);
        $quote->forceFill($calculated['totals']);

        $items = collect($calculated['items'])->map(fn (array $item) => [
            'sku' => $item['sku'] ?? null,
            'name' => $item['snapshot_name'],
            'description' => $item['snapshot_description'] ?? null,
            'unit' => $item['snapshot_unit'] ?? null,
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'discount' => $item['line_discount_amount'],
            'taxes' => $item['taxes'] ?? [],
            'tax_amount' => $item['tax_amount'],
            'line_subtotal' => $item['line_subtotal'],
            'line_total' => $item['line_total'],
            'notes' => $item['notes'] ?? null,
        ])->all();

        return $this->buildDocument(
            quote: $quote,
            template: $this->templateArray($template),
            client: $client,
            contact: $contact,
            fiscalClient: $fiscalClient,
            items: $items,
            totals: array_merge($calculated['totals'], $this->taxTotals($items)),
            logoPath: $template?->logo_path,
            isTemporary: true
        );
    }

    private function buildDocument(
        CommercialQuote $quote,
        array $template,
        ?CommercialClient $client,
        ?CommercialContact $contact,
        ?Cliente $fiscalClient,
        array $items,
        array $totals,
        ?string $logoPath,
        bool $isTemporary
    ): array {
        $company = $this->companyData((int) ($quote->users_id ?: auth()->id()));
        $context = [
            'empresa' => $company,
            'cliente' => $this->clientData($client),
            'contacto' => $this->contactData($contact),
            'cotizacion' => [
                'folio' => (string) ($quote->folio ?: 'Borrador'),
                'fecha' => optional($quote->issued_at)->format('Y-m-d') ?: (string) $quote->issued_at,
                'vencimiento' => optional($quote->expires_at)->format('Y-m-d') ?: '',
                'moneda' => (string) ($quote->currency ?: 'MXN'),
                'subtotal' => Decimal::format($totals['subtotal'] ?? '0'),
                'descuento' => Decimal::format($totals['discount_total'] ?? '0'),
                'impuestos' => Decimal::format($totals['tax_total'] ?? '0'),
                'total' => Decimal::format($totals['total'] ?? '0'),
                'responsable' => (string) ($quote->assignedUser?->username ?? ''),
            ],
        ];

        return [
            'quote' => $quote,
            'template' => $template,
            'company' => $company,
            'client' => $client,
            'contact' => $contact,
            'fiscalClient' => $fiscalClient,
            'items' => $items,
            'totals' => $totals,
            'isTemporary' => $isTemporary,
            'logoDataUri' => $this->logoDataUri($logoPath),
            'resolved' => [
                'header_title' => $this->resolver->resolve($template['header_title'] ?? '', $context),
                'header_text' => $this->resolver->resolve($template['header_text'] ?? '', $context),
                'footer_text' => $this->resolver->resolve($template['footer_text'] ?? '', $context),
                'terms_text' => $this->resolver->resolve($template['terms_text'] ?? '', $context),
            ],
        ];
    }

    private function templateForQuote(CommercialQuote $quote): array
    {
        if ($quote->status !== CommercialQuote::STATUS_DRAFT && ($quote->template_name_snapshot || $quote->template_options_snapshot)) {
            $options = is_array($quote->template_options_snapshot)
                ? $quote->template_options_snapshot
                : (json_decode((string) $quote->template_options_snapshot, true) ?: []);

            return array_merge($this->defaultTemplateArray(), $options, [
                'name' => $quote->template_name_snapshot ?: 'Formato historico',
                'logo_path' => $quote->logo_path_snapshot,
                'header_title' => $quote->header_title_snapshot,
                'header_text' => $quote->header_text_snapshot,
                'footer_text' => $quote->footer_text_snapshot,
                'terms_text' => $quote->terms_text_snapshot,
            ]);
        }

        return $this->templateArray($quote->documentTemplate);
    }

    private function templateArray(?CommercialDocumentTemplate $template): array
    {
        if (!$template) {
            return $this->defaultTemplateArray();
        }

        return [
            'name' => $template->name,
            'logo_path' => $template->logo_path,
            'header_title' => $template->header_title,
            'header_text' => $template->header_text,
            'footer_text' => $template->footer_text,
            'terms_text' => $template->terms_text,
            'accent_style' => $template->accent_style ?: 'teal',
            'show_logo' => (bool) $template->show_logo,
            'show_contact_info' => (bool) $template->show_contact_info,
            'show_fiscal_info' => (bool) $template->show_fiscal_info,
            'show_item_tax' => (bool) $template->show_item_tax,
            'show_item_sku' => (bool) $template->show_item_sku,
            'show_notes' => (bool) $template->show_notes,
        ];
    }

    private function defaultTemplateArray(): array
    {
        return [
            'name' => 'Formato comercial',
            'logo_path' => null,
            'header_title' => 'Cotizacion comercial',
            'header_text' => '',
            'footer_text' => '',
            'terms_text' => '',
            'accent_style' => 'teal',
            'show_logo' => true,
            'show_contact_info' => true,
            'show_fiscal_info' => false,
            'show_item_tax' => true,
            'show_item_sku' => true,
            'show_notes' => true,
        ];
    }

    private function companyData(int $userId): array
    {
        $perfil = Schema::hasTable('users_perfil')
            ? DB::table('users_perfil')->where('users_id', $userId)->first()
            : null;

        $info = Schema::hasTable('users_info_factura')
            ? DB::table('users_info_factura')->where('users_id', $userId)->first()
            : null;

        $user = Schema::hasTable('users')
            ? DB::table('users')->where('id', $userId)->first()
            : null;

        $address = $this->addressFrom($perfil ?: $info);

        return [
            'nombre' => (string) ($perfil->razon_social ?? $info->razon_social ?? $info->nombre ?? $user->username ?? ''),
            'rfc' => (string) ($perfil->rfc ?? $info->rfc ?? ''),
            'telefono' => (string) ($perfil->telefono ?? $info->telefono ?? ''),
            'email' => (string) ($user->email ?? ''),
            'direccion' => $address,
        ];
    }

    private function clientData(?CommercialClient $client): array
    {
        return [
            'nombre' => (string) ($client?->name ?? ''),
            'nombre_comercial' => (string) ($client?->business_name ?? ''),
            'email' => (string) ($client?->email ?? ''),
            'telefono' => (string) ($client?->phone ?? ''),
            'direccion' => $this->addressFrom($client),
        ];
    }

    private function contactData(?CommercialContact $contact): array
    {
        return [
            'nombre' => (string) ($contact?->name ?? ''),
            'puesto' => (string) ($contact?->position ?? ''),
            'email' => (string) ($contact?->email ?? ''),
            'telefono' => (string) ($contact?->phone ?: $contact?->mobile ?: ''),
        ];
    }

    private function addressFrom(?object $row): string
    {
        if (!$row) {
            return '';
        }

        $street = trim((string) (($row->street ?? $row->calle ?? '') . ' ' . ($row->exterior_number ?? $row->no_ext ?? '')));
        $interior = (string) ($row->interior_number ?? $row->no_int ?? '');

        return trim(implode(', ', array_filter([
            $street . ($interior !== '' ? ' Int ' . $interior : ''),
            $row->neighborhood ?? $row->colonia ?? null,
            $row->city ?? $row->municipio ?? null,
            $row->state ?? $row->estado ?? null,
            ($row->postal_code ?? $row->codigo_postal ?? $row->cp ?? null) ? 'CP ' . ($row->postal_code ?? $row->codigo_postal ?? $row->cp) : null,
            $row->country ?? $row->pais ?? null,
        ])));
    }

    private function logoDataUri(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $absolute = storage_path('app/' . ltrim($path, '/\\'));
        if (!is_file($absolute)) {
            return null;
        }

        $mime = mime_content_type($absolute) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($absolute));
    }

    private function payloadItems(array $items): array
    {
        return collect($items)->values()->map(fn (array $item) => [
            'product_id' => $item['product_id'] ?? null,
            'sku' => $item['sku'] ?? null,
            'snapshot_name' => trim((string) $item['snapshot_name']),
            'snapshot_description' => $item['snapshot_description'] ?? null,
            'snapshot_unit' => $item['snapshot_unit'] ?? null,
            'quantity' => (string) ($item['quantity'] ?? '0'),
            'unit_price' => (string) ($item['unit_price'] ?? '0'),
            'line_discount_amount' => (string) ($item['line_discount_amount'] ?? '0'),
            'taxes' => $this->taxPayloads($item),
            'tax_name' => trim((string) ($item['tax_name'] ?? '')),
            'tax_type' => (string) ($item['tax_type'] ?? CommercialQuoteTax::TYPE_TRASLADO),
            'tax_rate' => (string) ($item['tax_rate'] ?? '0'),
            'notes' => $item['notes'] ?? null,
        ])->all();
    }

    private function taxPayloads(array $item): array
    {
        $taxes = collect($item['taxes'] ?? [])
            ->filter(fn ($tax) => is_array($tax) && trim((string) ($tax['tax_name'] ?? '')) !== '')
            ->map(fn (array $tax) => [
                'tax_name' => trim((string) ($tax['tax_name'] ?? '')),
                'tax_type' => (string) ($tax['tax_type'] ?? CommercialQuoteTax::TYPE_TRASLADO),
                'tax_mode' => (string) ($tax['tax_mode'] ?? CommercialQuoteTax::MODE_RATE),
                'rate' => (string) ($tax['rate'] ?? '0'),
            ])
            ->values()
            ->all();

        if (!empty($taxes)) {
            return $taxes;
        }

        $legacyName = trim((string) ($item['tax_name'] ?? ''));
        $legacyRate = (string) ($item['tax_rate'] ?? '0');
        if ($legacyName === '' && Decimal::cmp($legacyRate, '0') <= 0) {
            return [];
        }

        return [[
            'tax_name' => $legacyName !== '' ? $legacyName : 'IVA',
            'tax_type' => (string) ($item['tax_type'] ?? CommercialQuoteTax::TYPE_TRASLADO),
            'tax_mode' => Decimal::cmp($legacyRate, '0') > 0 ? CommercialQuoteTax::MODE_RATE : CommercialQuoteTax::MODE_ZERO,
            'rate' => $legacyRate,
        ]];
    }

    private function taxesForItem($item): array
    {
        $taxes = $item->taxes->sortBy('sort_order')->map(fn (CommercialQuoteTax $tax) => [
            'tax_name' => $tax->tax_name,
            'tax_type' => $tax->tax_type,
            'tax_mode' => $tax->tax_mode ?: CommercialQuoteTax::MODE_RATE,
            'rate' => $tax->rate,
            'base' => $tax->base,
            'amount' => $tax->amount,
        ])->values()->all();

        if (!empty($taxes) || !$item->snapshot_tax_name) {
            return $taxes;
        }

        return [[
            'tax_name' => $item->snapshot_tax_name,
            'tax_type' => $item->snapshot_tax_type ?: CommercialQuoteTax::TYPE_TRASLADO,
            'tax_mode' => Decimal::cmp((string) $item->snapshot_tax_rate, '0') > 0 ? CommercialQuoteTax::MODE_RATE : CommercialQuoteTax::MODE_ZERO,
            'rate' => (string) $item->snapshot_tax_rate,
            'base' => (string) $item->taxable_base,
            'amount' => (string) $item->tax_amount,
        ]];
    }

    private function taxTotals(array $items): array
    {
        $transfers = Decimal::zero();
        $retentions = Decimal::zero();

        foreach ($items as $item) {
            foreach (($item['taxes'] ?? []) as $tax) {
                $amount = Decimal::normalize($tax['amount'] ?? '0');
                if (($tax['tax_type'] ?? CommercialQuoteTax::TYPE_TRASLADO) === CommercialQuoteTax::TYPE_RETENCION) {
                    $retentions = Decimal::add($retentions, ltrim($amount, '-'));
                } else {
                    $transfers = Decimal::add($transfers, $amount);
                }
            }
        }

        return [
            'tax_transfers_total' => $transfers,
            'tax_retentions_total' => $retentions,
        ];
    }
}
