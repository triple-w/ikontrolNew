<?php

namespace App\Services\CommercialDocuments;

use App\Models\CommercialDocumentTemplate;
use App\Models\CommercialQuote;
use Illuminate\Support\Facades\File;

class CommercialTemplateSnapshotter
{
    public function ensureSnapshot(CommercialQuote $quote): void
    {
        if (!empty($quote->template_name_snapshot) || !empty($quote->template_options_snapshot)) {
            return;
        }

        $template = $quote->documentTemplate;
        $this->capture($quote, $template);
    }

    public function capture(CommercialQuote $quote, ?CommercialDocumentTemplate $template): void
    {
        $logoSnapshot = null;

        if ($template && $template->show_logo && $template->logo_path) {
            $logoSnapshot = $this->copyLogoSnapshot($quote, $template->logo_path);
        }

        $quote->forceFill([
            'template_name_snapshot' => $template?->name,
            'logo_path_snapshot' => $logoSnapshot,
            'header_title_snapshot' => $template?->header_title,
            'header_text_snapshot' => $template?->header_text,
            'footer_text_snapshot' => $template?->footer_text,
            'terms_text_snapshot' => $template?->terms_text,
            'template_options_snapshot' => $template ? [
                'accent_style' => $template->accent_style,
                'show_logo' => (bool) $template->show_logo,
                'show_contact_info' => (bool) $template->show_contact_info,
                'show_fiscal_info' => (bool) $template->show_fiscal_info,
                'show_item_tax' => (bool) $template->show_item_tax,
                'show_item_sku' => (bool) $template->show_item_sku,
                'show_notes' => (bool) $template->show_notes,
            ] : null,
        ])->save();
    }

    private function copyLogoSnapshot(CommercialQuote $quote, string $logoPath): ?string
    {
        $source = storage_path('app/' . ltrim($logoPath, '/\\'));
        if (!is_file($source)) {
            return null;
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'png';
        $relativeDir = 'private/commercial-documents/quotes/' . $quote->id;
        $targetDir = storage_path('app/' . $relativeDir);
        File::ensureDirectoryExists($targetDir);

        $relativePath = $relativeDir . '/logo.' . $extension;
        File::copy($source, storage_path('app/' . $relativePath));

        return $relativePath;
    }
}
