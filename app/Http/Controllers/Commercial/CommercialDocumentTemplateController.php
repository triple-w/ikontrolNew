<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommercialDocuments\StoreCommercialDocumentTemplateRequest;
use App\Http\Requests\CommercialDocuments\UpdateCommercialDocumentTemplateRequest;
use App\Models\CommercialDocumentTemplate;
use App\Services\CommercialDocuments\TemplateVariableResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CommercialDocumentTemplateController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CommercialDocumentTemplate::class);

        $templates = CommercialDocumentTemplate::query()
            ->forUser((int) $request->user()->id)
            ->orderBy('document_type')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(20);

        return view('configuracion.formatos-documentos.index', [
            'templates' => $templates,
            'types' => $this->typeLabels(),
        ]);
    }

    public function create(TemplateVariableResolver $resolver)
    {
        $this->authorize('create', CommercialDocumentTemplate::class);

        return view('configuracion.formatos-documentos.create', [
            'template' => new CommercialDocumentTemplate([
                'document_type' => CommercialDocumentTemplate::TYPE_QUOTE,
                'accent_style' => 'teal',
                'show_logo' => true,
                'show_contact_info' => true,
                'show_fiscal_info' => false,
                'show_item_tax' => true,
                'show_item_sku' => true,
                'show_notes' => true,
                'is_active' => true,
            ]),
            'types' => $this->typeLabels(),
            'variables' => $resolver->allowedVariables(),
        ]);
    }

    public function store(StoreCommercialDocumentTemplateRequest $request)
    {
        $data = $request->validated();
        $data = $this->payload($data, (int) $request->user()->id);

        DB::transaction(function () use ($request, $data) {
            if ($data['is_default']) {
                $this->clearDefault((int) $request->user()->id, $data['document_type']);
            }

            CommercialDocumentTemplate::create($data);
        });

        return redirect()
            ->route('configuracion.formatos-documentos.index')
            ->with('status', 'Formato comercial creado correctamente.');
    }

    public function edit(CommercialDocumentTemplate $template, TemplateVariableResolver $resolver)
    {
        $this->authorize('update', $template);

        return view('configuracion.formatos-documentos.edit', [
            'template' => $template,
            'types' => $this->typeLabels(),
            'variables' => $resolver->allowedVariables(),
        ]);
    }

    public function update(UpdateCommercialDocumentTemplateRequest $request, CommercialDocumentTemplate $template)
    {
        $data = $request->validated();
        $payload = $this->payload($data, (int) $template->users_id, $template);

        DB::transaction(function () use ($template, $payload) {
            if ($payload['is_default'] && $payload['is_active']) {
                $this->clearDefault((int) $template->users_id, $payload['document_type'], (int) $template->id);
            }

            if (!$payload['is_active']) {
                $payload['is_default'] = false;
            }

            $template->update($payload);
        });

        return redirect()
            ->route('configuracion.formatos-documentos.index')
            ->with('status', 'Formato comercial actualizado correctamente.');
    }

    public function setDefault(Request $request, CommercialDocumentTemplate $template)
    {
        $this->authorize('update', $template);

        abort_unless($template->is_active, 422, 'Solo un formato activo puede ser predeterminado.');

        DB::transaction(function () use ($template) {
            $this->clearDefault((int) $template->users_id, (string) $template->document_type, (int) $template->id);
            $template->update(['is_default' => true]);
        });

        return back()->with('status', 'Formato predeterminado actualizado.');
    }

    public function destroy(CommercialDocumentTemplate $template)
    {
        $this->authorize('delete', $template);

        DB::transaction(function () use ($template) {
            $logoPath = $template->logo_path;
            $template->update(['is_default' => false, 'is_active' => false]);
            $template->delete();

            if ($logoPath) {
                Storage::disk('local')->delete($logoPath);
            }
        });

        return redirect()
            ->route('configuracion.formatos-documentos.index')
            ->with('status', 'Formato comercial eliminado correctamente.');
    }

    private function payload(array $data, int $userId, ?CommercialDocumentTemplate $template = null): array
    {
        $logoPath = $template?->logo_path;
        if (($data['remove_logo'] ?? false) && $logoPath) {
            Storage::disk('local')->delete($logoPath);
            $logoPath = null;
        }

        if (request()->hasFile('logo')) {
            if ($logoPath) {
                Storage::disk('local')->delete($logoPath);
            }
            $logoPath = $this->storeLogo(request()->file('logo'), $userId);
        }

        return [
            'users_id' => $userId,
            'name' => trim((string) $data['name']),
            'document_type' => $data['document_type'],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'logo_path' => $logoPath,
            'header_title' => $this->plain($data['header_title'] ?? null),
            'header_text' => $this->plain($data['header_text'] ?? null),
            'footer_text' => $this->plain($data['footer_text'] ?? null),
            'terms_text' => $this->plain($data['terms_text'] ?? null),
            'accent_style' => $data['accent_style'] ?? 'teal',
            'show_logo' => (bool) ($data['show_logo'] ?? false),
            'show_contact_info' => (bool) ($data['show_contact_info'] ?? false),
            'show_fiscal_info' => (bool) ($data['show_fiscal_info'] ?? false),
            'show_item_tax' => (bool) ($data['show_item_tax'] ?? false),
            'show_item_sku' => (bool) ($data['show_item_sku'] ?? false),
            'show_notes' => (bool) ($data['show_notes'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function storeLogo($file, int $userId): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = Str::uuid()->toString() . '.' . $extension;
        $dir = "private/commercial-documents/templates/{$userId}";

        return $file->storeAs($dir, $name, 'local');
    }

    private function clearDefault(int $userId, string $type, ?int $exceptId = null): void
    {
        CommercialDocumentTemplate::query()
            ->forUser($userId)
            ->forType($type)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function plain(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : strip_tags($value);
    }

    private function typeLabels(): array
    {
        return [
            CommercialDocumentTemplate::TYPE_QUOTE => 'Cotizacion',
            CommercialDocumentTemplate::TYPE_REMISSION => 'Remision futura',
            CommercialDocumentTemplate::TYPE_GENERAL => 'General',
        ];
    }
}
