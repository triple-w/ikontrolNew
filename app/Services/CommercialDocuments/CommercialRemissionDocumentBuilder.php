<?php

namespace App\Services\CommercialDocuments;

use App\Models\Cliente;
use App\Models\CommercialClient;
use App\Models\CommercialContact;
use App\Models\CommercialDocumentTemplate;
use App\Models\CommercialRemission;
use App\Models\CommercialRemissionTax;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommercialRemissionDocumentBuilder
{
    public function __construct(private readonly TemplateVariableResolver $resolver)
    {
    }

    public function fromRemission(CommercialRemission $remission): array
    {
        $remission->loadMissing([
            'quote',
            'commercialClient',
            'commercialContact',
            'fiscalClient',
            'assignedUser',
            'documentTemplate',
            'items.taxes',
        ]);

        $template = $this->templateForRemission($remission);
        $items = $remission->items->map(fn ($item) => [
            'sku' => $item->sku,
            'name' => $item->snapshot_name,
            'description' => $item->snapshot_description,
            'unit' => $item->snapshot_unit,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'discount' => $item->line_discount_amount,
            'taxes' => $item->taxes->sortBy('sort_order')->map(fn (CommercialRemissionTax $tax) => [
                'tax_name' => $tax->tax_name,
                'tax_type' => $tax->tax_type,
                'tax_mode' => $tax->tax_mode,
                'rate' => $tax->rate,
                'base' => $tax->base,
                'amount' => $tax->amount,
            ])->values()->all(),
            'tax_amount' => $item->tax_amount,
            'line_subtotal' => $item->line_subtotal,
            'line_total' => $item->line_total,
            'notes' => $item->notes,
        ])->all();

        return $this->buildDocument(
            remission: $remission,
            template: $template,
            client: $remission->commercialClient,
            contact: $remission->commercialContact,
            fiscalClient: $remission->fiscalClient,
            items: $items,
            logoPath: $template['logo_path'] ?? null
        );
    }

    private function buildDocument(
        CommercialRemission $remission,
        array $template,
        ?CommercialClient $client,
        ?CommercialContact $contact,
        ?Cliente $fiscalClient,
        array $items,
        ?string $logoPath
    ): array {
        $company = $this->companyData((int) ($remission->users_id ?: auth()->id()));
        $totals = [
            'subtotal' => (string) $remission->subtotal,
            'line_discount_total' => (string) $remission->line_discount_total,
            'global_discount_amount' => (string) $remission->global_discount_amount,
            'discount_total' => (string) $remission->discount_total,
            'tax_transfers_total' => (string) $remission->transfers_total,
            'tax_retentions_total' => (string) $remission->withholdings_total,
            'tax_total' => (string) $remission->tax_total,
            'total' => (string) $remission->total,
        ];
        $context = [
            'empresa' => $company,
            'cliente' => $this->clientData($client),
            'contacto' => $this->contactData($contact),
            'cotizacion' => [
                'folio' => (string) ($remission->folio ?: 'Borrador'),
                'fecha' => optional($remission->issue_date)->format('Y-m-d') ?: (string) $remission->issue_date,
                'vencimiento' => '',
                'moneda' => (string) ($remission->currency ?: 'MXN'),
                'subtotal' => Decimal::format($totals['subtotal'] ?? '0'),
                'descuento' => Decimal::format($totals['discount_total'] ?? '0'),
                'impuestos' => Decimal::format($totals['tax_total'] ?? '0'),
                'total' => Decimal::format($totals['total'] ?? '0'),
                'responsable' => (string) ($remission->assignedUser?->username ?? ''),
            ],
        ];

        return [
            'remission' => $remission,
            'template' => $template,
            'company' => $company,
            'client' => $client,
            'contact' => $contact,
            'fiscalClient' => $fiscalClient,
            'items' => $items,
            'totals' => $totals,
            'logoDataUri' => $this->logoDataUri($logoPath),
            'resolved' => [
                'header_title' => $this->resolver->resolve($template['header_title'] ?? '', $context),
                'header_text' => $this->resolver->resolve($template['header_text'] ?? '', $context),
                'footer_text' => $this->resolver->resolve($template['footer_text'] ?? '', $context),
                'terms_text' => $this->resolver->resolve($template['terms_text'] ?? '', $context),
            ],
        ];
    }

    private function templateForRemission(CommercialRemission $remission): array
    {
        if ($remission->status !== CommercialRemission::STATUS_DRAFT && ($remission->template_name_snapshot || $remission->template_options_snapshot)) {
            $options = is_array($remission->template_options_snapshot)
                ? $remission->template_options_snapshot
                : (json_decode((string) $remission->template_options_snapshot, true) ?: []);

            return array_merge($this->defaultTemplateArray(), $options, [
                'name' => $remission->template_name_snapshot ?: 'Formato historico',
                'logo_path' => $remission->logo_path_snapshot,
                'header_title' => $remission->header_title_snapshot,
                'header_text' => $remission->header_text_snapshot,
                'footer_text' => $remission->footer_text_snapshot,
                'terms_text' => $remission->terms_text_snapshot,
            ]);
        }

        return $this->templateArray($remission->documentTemplate);
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
            'header_title' => 'Remision comercial',
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
        $perfil = Schema::hasTable('users_perfil') ? DB::table('users_perfil')->where('users_id', $userId)->first() : null;
        $info = Schema::hasTable('users_info_factura') ? DB::table('users_info_factura')->where('users_id', $userId)->first() : null;
        $user = Schema::hasTable('users') ? DB::table('users')->where('id', $userId)->first() : null;

        return [
            'nombre' => (string) ($perfil->razon_social ?? $info->razon_social ?? $info->nombre ?? $user->username ?? ''),
            'rfc' => (string) ($perfil->rfc ?? $info->rfc ?? ''),
            'telefono' => (string) ($perfil->telefono ?? $info->telefono ?? ''),
            'email' => (string) ($user->email ?? ''),
            'direccion' => '',
        ];
    }

    private function clientData(?CommercialClient $client): array
    {
        return [
            'nombre' => (string) ($client?->name ?? ''),
            'nombre_comercial' => (string) ($client?->business_name ?? ''),
            'email' => (string) ($client?->email ?? ''),
            'telefono' => (string) ($client?->phone ?? ''),
            'direccion' => '',
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
}
