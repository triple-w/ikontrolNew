<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommercialRemissionRequest;
use App\Http\Requests\UpdateCommercialRemissionRequest;
use App\Models\Cliente;
use App\Models\CommercialClient;
use App\Models\CommercialContact;
use App\Models\CommercialDocumentTemplate;
use App\Models\CommercialQuote;
use App\Models\CommercialQuoteItem;
use App\Models\CommercialQuoteTax;
use App\Models\CommercialRemission;
use App\Models\CommercialRemissionItem;
use App\Models\CommercialRemissionTax;
use App\Models\Producto;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialTemplateSnapshotter;
use App\Services\CommercialDocuments\CommercialRemissionDocumentBuilder;
use App\Services\CommercialDocuments\CommercialRemissionTemplateSnapshotter;
use App\Services\CommercialQuoteCalculator;
use App\Services\CommercialRemissionFolioService;
use App\Support\Decimal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommercialRemissionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CommercialRemission::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'commercial_client_id' => (string) $request->query('commercial_client_id', ''),
        ];

        $remissions = CommercialRemission::query()
            ->visibleTo($request->user())
            ->with(['commercialClient', 'quote', 'assignedUser'])
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $q = $filters['q'];
                $query->where(function ($sub) use ($q) {
                    $sub->where('folio', 'like', "%{$q}%")
                        ->orWhereHas('commercialClient', fn ($client) => $client->where('name', 'like', "%{$q}%")->orWhere('business_name', 'like', "%{$q}%"))
                        ->orWhereHas('quote', fn ($quote) => $quote->where('folio', 'like', "%{$q}%"));
                });
            })
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['commercial_client_id'] !== '', fn ($query) => $query->where('commercial_client_id', (int) $filters['commercial_client_id']))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('comercial.remisiones.index', [
            'remissions' => $remissions,
            'filters' => $filters,
            'statuses' => $this->statusLabels(),
            'statusTones' => $this->statusTones(),
            'clients' => $this->clientOptionsForFilter($request->user()),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', CommercialRemission::class);

        return view('comercial.remisiones.create', $this->formPayload($request->user(), new CommercialRemission([
            'issue_date' => now()->toDateString(),
            'currency' => 'MXN',
            'global_discount_amount' => '0.000000',
            'status' => CommercialRemission::STATUS_DRAFT,
        ]), []));
    }

    public function createFromQuote(Request $request, CommercialQuote $commercialQuote)
    {
        $this->authorize('view', $commercialQuote);
        $this->authorize('create', CommercialRemission::class);

        if (!$this->quoteCanBeRemissioned($commercialQuote)) {
            throw ValidationException::withMessages(['commercial_quote_id' => 'Esta cotizacion no puede remisionarse.']);
        }

        $commercialQuote->load(['items.taxes', 'commercialClient', 'commercialContact', 'fiscalClient']);
        $items = $this->itemsFromQuote($commercialQuote);

        return view('comercial.remisiones.create', $this->formPayload($request->user(), new CommercialRemission([
            'users_id' => $commercialQuote->users_id,
            'commercial_quote_id' => $commercialQuote->id,
            'commercial_client_id' => $commercialQuote->commercial_client_id,
            'commercial_contact_id' => $commercialQuote->commercial_contact_id,
            'fiscal_client_id' => $commercialQuote->fiscal_client_id,
            'commercial_document_template_id' => $commercialQuote->commercial_document_template_id,
            'issue_date' => now()->toDateString(),
            'currency' => $commercialQuote->currency ?: 'MXN',
            'global_discount_amount' => $commercialQuote->global_discount_amount ?: '0.000000',
            'conditions' => $commercialQuote->commercial_terms,
            'notes_visible' => $commercialQuote->customer_notes,
            'status' => CommercialRemission::STATUS_DRAFT,
        ]), $items, $commercialQuote));
    }

    public function store(StoreCommercialRemissionRequest $request, CommercialQuoteCalculator $calculator, CommercialRemissionFolioService $folioService, CommercialTemplateSnapshotter $quoteSnapshotter, CommercialRemissionTemplateSnapshotter $remissionSnapshotter)
    {
        $data = $request->validated();
        $routeQuote = $request->route('commercialQuote');
        if ($routeQuote instanceof CommercialQuote) {
            $data['commercial_quote_id'] = $routeQuote->id;
        }

        $client = $this->validatedClient((int) $data['commercial_client_id'], $request->user());
        $quote = $this->validatedQuote($data['commercial_quote_id'] ?? null, (int) $client->users_id);
        $this->validateRelatedIds($data, (int) $client->users_id, (int) $client->id, $quote);
        $this->validatedTemplate($data['commercial_document_template_id'] ?? null, (int) $client->users_id);
        $this->validatePendingQuantities($data['items'], $quote);

        $calculated = $calculator->calculate($this->payloadItems($data['items']), (string) ($data['global_discount_amount'] ?? '0'));

        $remission = DB::transaction(function () use ($data, $client, $calculated, $request, $folioService, $quote, $quoteSnapshotter, $remissionSnapshotter) {
            $folio = $folioService->reserve((int) $client->users_id);
            $status = ($data['save_action'] ?? 'draft') === 'issue'
                ? CommercialRemission::STATUS_ISSUED
                : CommercialRemission::STATUS_DRAFT;
            $remission = CommercialRemission::create(array_merge(
                $this->remissionPayload($data, $client, $calculated, $status),
                $folio,
                [
                    'users_id' => (int) $client->users_id,
                    'created_by_id' => (int) $request->user()->id,
                ]
            ));

            $this->storeItems($remission, $calculated['items']);
            if ($status !== CommercialRemission::STATUS_DRAFT) {
                $remissionSnapshotter->ensureSnapshot($remission->fresh(['documentTemplate']));
            }
            $this->recordStatus($remission, null, $status, (int) $request->user()->id, $quote ? 'Remision creada desde cotizacion ' . $quote->folio . '.' : 'Remision creada.');
            $this->acceptQuoteIfRequested($quote, $data, (int) $request->user()->id, $quoteSnapshotter);

            return $remission;
        });

        return redirect()->route('comercial.remisiones.show', $remission)->with('status', 'Remision creada correctamente.');
    }

    public function show(CommercialRemission $commercialRemission)
    {
        $this->authorize('view', $commercialRemission);
        $commercialRemission->load(['quote', 'commercialClient', 'commercialContact', 'fiscalClient', 'items.taxes', 'statusHistory.user']);

        return view('comercial.remisiones.show', [
            'remission' => $commercialRemission,
            'statuses' => $this->statusLabels(),
            'statusTones' => $this->statusTones(),
        ]);
    }

    public function edit(Request $request, CommercialRemission $commercialRemission)
    {
        $this->authorize('update', $commercialRemission);
        $this->ensureEditable($commercialRemission);
        $commercialRemission->load(['items.taxes', 'quote.items']);

        return view('comercial.remisiones.edit', $this->formPayload(
            $request->user(),
            $commercialRemission,
            $commercialRemission->items->map(fn ($item) => $this->itemPayloadForForm($item))->values()->all(),
            $commercialRemission->quote
        ));
    }

    public function update(UpdateCommercialRemissionRequest $request, CommercialRemission $commercialRemission, CommercialQuoteCalculator $calculator)
    {
        $this->authorize('update', $commercialRemission);
        $this->ensureEditable($commercialRemission);

        $data = $request->validated();
        $client = $this->validatedClient((int) $data['commercial_client_id'], $request->user(), (int) $commercialRemission->users_id);
        $quote = $this->validatedQuote($data['commercial_quote_id'] ?? null, (int) $commercialRemission->users_id);
        $this->validateRelatedIds($data, (int) $commercialRemission->users_id, (int) $client->id, $quote);
        $this->validatedTemplate($data['commercial_document_template_id'] ?? null, (int) $commercialRemission->users_id);
        $this->validatePendingQuantities($data['items'], $quote, $commercialRemission);
        $calculated = $calculator->calculate($this->payloadItems($data['items']), (string) ($data['global_discount_amount'] ?? '0'));

        DB::transaction(function () use ($commercialRemission, $data, $client, $calculated) {
            $commercialRemission->update($this->remissionPayload($data, $client, $calculated, $commercialRemission->status));
            $commercialRemission->taxes()->delete();
            $commercialRemission->items()->delete();
            $this->storeItems($commercialRemission, $calculated['items']);
        });

        return redirect()->route('comercial.remisiones.show', $commercialRemission)->with('status', 'Remision actualizada correctamente.');
    }

    public function issue(Request $request, CommercialRemission $commercialRemission, CommercialRemissionTemplateSnapshotter $snapshotter)
    {
        $this->authorize('update', $commercialRemission);
        if ($commercialRemission->status !== CommercialRemission::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Solo una remision en borrador puede emitirse.']);
        }

        DB::transaction(function () use ($request, $commercialRemission, $snapshotter) {
            $old = $commercialRemission->status;
            $commercialRemission->update(['status' => CommercialRemission::STATUS_ISSUED]);
            $snapshotter->ensureSnapshot($commercialRemission->fresh(['documentTemplate']));
            $this->recordStatus($commercialRemission, $old, CommercialRemission::STATUS_ISSUED, (int) $request->user()->id, 'Remision emitida.');
        });

        return back()->with('status', 'Remision emitida correctamente.');
    }

    public function cancel(Request $request, CommercialRemission $commercialRemission, CommercialRemissionTemplateSnapshotter $snapshotter)
    {
        $this->authorize('update', $commercialRemission);
        if (!in_array($commercialRemission->status, [CommercialRemission::STATUS_DRAFT, CommercialRemission::STATUS_ISSUED], true)) {
            throw ValidationException::withMessages(['status' => 'Esta remision no puede cancelarse.']);
        }

        DB::transaction(function () use ($request, $commercialRemission, $snapshotter) {
            $old = $commercialRemission->status;
            $commercialRemission->update(['status' => CommercialRemission::STATUS_CANCELLED]);
            $snapshotter->ensureSnapshot($commercialRemission->fresh(['documentTemplate']));
            $this->recordStatus($commercialRemission, $old, CommercialRemission::STATUS_CANCELLED, (int) $request->user()->id, 'Remision cancelada.');
        });

        return back()->with('status', 'Remision cancelada correctamente.');
    }

    public function preview(CommercialRemission $commercialRemission, CommercialRemissionDocumentBuilder $builder)
    {
        $this->authorize('view', $commercialRemission);

        return view('comercial.remisiones.preview', [
            'document' => $builder->fromRemission($commercialRemission),
            'backUrl' => route('comercial.remisiones.show', $commercialRemission),
        ]);
    }

    public function previewDraft(StoreCommercialRemissionRequest $request, CommercialRemissionDocumentBuilder $builder)
    {
        $data = $request->validated();
        $client = $this->validatedClient((int) $data['commercial_client_id'], $request->user());
        $quote = $this->validatedQuote($data['commercial_quote_id'] ?? null, (int) $client->users_id);
        $this->validateRelatedIds($data, (int) $client->users_id, (int) $client->id, $quote);
        $template = $this->validatedTemplate($data['commercial_document_template_id'] ?? null, (int) $client->users_id);
        $this->validatePendingQuantities($data['items'], $quote);
        $remissionId = $request->filled('preview_remission_id') ? (int) $request->input('preview_remission_id') : null;
        $token = $this->storePreviewSession($request, $data, $remissionId);

        return view('comercial.remisiones.preview', [
            'document' => $builder->fromPayload($data, (int) $client->users_id, $template),
            'backUrl' => route('comercial.remisiones.preview-draft.edit', $token),
            'previewToken' => $token,
        ]);
    }

    public function editPreviewDraft(Request $request, string $token)
    {
        $payload = $this->previewSessionPayload($request, $token);
        if (!empty($payload['used'])) {
            abort(409);
        }
        $request->session()->put($this->previewSessionKey($token), array_merge($payload, ['used' => true]));
        $data = $payload['data'];
        $quote = !empty($data['commercial_quote_id'])
            ? CommercialQuote::query()->visibleTo($request->user())->with(['items.taxes'])->find((int) $data['commercial_quote_id'])
            : null;
        $remission = !empty($payload['remission_id'])
            ? CommercialRemission::query()->visibleTo($request->user())->find((int) $payload['remission_id'])
            : new CommercialRemission();

        if (!$remission) {
            abort(404);
        }

        $remission->forceFill([
            'commercial_quote_id' => $data['commercial_quote_id'] ?? null,
            'commercial_client_id' => $data['commercial_client_id'],
            'commercial_contact_id' => $data['commercial_contact_id'] ?? null,
            'fiscal_client_id' => $data['fiscal_client_id'] ?? null,
            'commercial_document_template_id' => $data['commercial_document_template_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'issue_date' => $data['issue_date'],
            'currency' => $data['currency'] ?? 'MXN',
            'exchange_rate' => $data['exchange_rate'] ?? null,
            'global_discount_amount' => $data['global_discount_amount'] ?? '0.000000',
            'conditions' => $data['conditions'] ?? null,
            'notes_visible' => $data['notes_visible'] ?? null,
            'notes_internal' => $data['notes_internal'] ?? null,
        ]);

        return view($remission->exists ? 'comercial.remisiones.edit' : 'comercial.remisiones.create', $this->formPayload($request->user(), $remission, $data['items'] ?? [], $quote));
    }

    public function storePreviewDraft(Request $request, string $token, CommercialQuoteCalculator $calculator, CommercialRemissionFolioService $folioService, CommercialTemplateSnapshotter $quoteSnapshotter, CommercialRemissionTemplateSnapshotter $remissionSnapshotter)
    {
        $payload = $this->previewSessionPayload($request, $token);
        $data = $payload['data'];
        if ($request->input('save_action') === 'issue') {
            $data['save_action'] = 'issue';
        }

        $client = $this->validatedClient((int) $data['commercial_client_id'], $request->user());
        $quote = $this->validatedQuote($data['commercial_quote_id'] ?? null, (int) $client->users_id);
        $this->validateRelatedIds($data, (int) $client->users_id, (int) $client->id, $quote);
        $this->validatedTemplate($data['commercial_document_template_id'] ?? null, (int) $client->users_id);
        $this->validatePendingQuantities($data['items'], $quote);
        $calculated = $calculator->calculate($this->payloadItems($data['items']), (string) ($data['global_discount_amount'] ?? '0'));

        if (!empty($payload['remission_id'])) {
            $remission = CommercialRemission::query()->visibleTo($request->user())->findOrFail((int) $payload['remission_id']);
            $this->authorize('update', $remission);
            $this->ensureEditable($remission);
            DB::transaction(function () use ($remission, $data, $client, $calculated, $quote, $request, $quoteSnapshotter, $remissionSnapshotter) {
                $status = ($data['save_action'] ?? 'draft') === 'issue' ? CommercialRemission::STATUS_ISSUED : $remission->status;
                $oldStatus = $remission->status;
                $remission->update($this->remissionPayload($data, $client, $calculated, $status));
                $remission->taxes()->delete();
                $remission->items()->delete();
                $this->storeItems($remission, $calculated['items']);
                if ($status !== $oldStatus) {
                    $remissionSnapshotter->ensureSnapshot($remission->fresh(['documentTemplate']));
                    $this->recordStatus($remission, $oldStatus, $status, (int) $request->user()->id, 'Remision emitida desde previsualizacion.');
                }
                $this->acceptQuoteIfRequested($quote, $data, (int) $request->user()->id, $quoteSnapshotter);
            });
        } else {
            $remission = DB::transaction(function () use ($data, $client, $calculated, $request, $folioService, $quote, $quoteSnapshotter, $remissionSnapshotter) {
                $folio = $folioService->reserve((int) $client->users_id);
                $status = ($data['save_action'] ?? 'draft') === 'issue' ? CommercialRemission::STATUS_ISSUED : CommercialRemission::STATUS_DRAFT;
                $remission = CommercialRemission::create(array_merge(
                    $this->remissionPayload($data, $client, $calculated, $status),
                    $folio,
                    ['users_id' => (int) $client->users_id, 'created_by_id' => (int) $request->user()->id]
                ));
                $this->storeItems($remission, $calculated['items']);
                if ($status !== CommercialRemission::STATUS_DRAFT) {
                    $remissionSnapshotter->ensureSnapshot($remission->fresh(['documentTemplate']));
                }
                $this->recordStatus($remission, null, $status, (int) $request->user()->id, $status === CommercialRemission::STATUS_ISSUED ? 'Remision creada y emitida.' : 'Remision creada.');
                $this->acceptQuoteIfRequested($quote, $data, (int) $request->user()->id, $quoteSnapshotter);

                return $remission;
            });
        }

        $request->session()->forget($this->previewSessionKey($token));

        return redirect()->route('comercial.remisiones.show', $remission)->with('status', 'Remision guardada desde previsualizacion.');
    }

    public function pdf(CommercialRemission $commercialRemission, CommercialRemissionDocumentBuilder $builder)
    {
        $this->authorize('view', $commercialRemission);

        return Pdf::loadView('comercial.remisiones.pdf', ['document' => $builder->fromRemission($commercialRemission)])
            ->setPaper('letter')
            ->stream(($commercialRemission->folio ?: 'remision') . '.pdf');
    }

    public function searchQuotes(Request $request)
    {
        $this->authorize('create', CommercialRemission::class);
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $quotes = CommercialQuote::query()
            ->visibleTo($request->user())
            ->with(['commercialClient', 'items'])
            ->whereIn('status', [CommercialQuote::STATUS_DRAFT, CommercialQuote::STATUS_SENT, CommercialQuote::STATUS_ACCEPTED])
            ->where(function ($query) use ($q) {
                $query->where('folio', 'like', "%{$q}%")
                    ->orWhere('status', 'like', "%{$q}%")
                    ->orWhereHas('commercialClient', fn ($client) => $client->where('name', 'like', "%{$q}%")->orWhere('business_name', 'like', "%{$q}%"));
            })
            ->orderByDesc('issued_at')
            ->limit(20)
            ->get()
            ->filter(fn (CommercialQuote $quote) => collect($this->pendingByQuoteItem($quote))->contains(fn ($qty) => Decimal::cmp($qty, '0') > 0))
            ->values();

        return response()->json(['data' => $quotes->map(fn (CommercialQuote $quote) => [
            'id' => $quote->id,
            'folio' => $quote->folio,
            'status' => $quote->status,
            'client' => $quote->commercialClient?->name,
            'total' => $quote->total,
        ])->all()]);
    }

    public function quotePayload(Request $request, CommercialQuote $commercialQuote)
    {
        $this->authorize('view', $commercialQuote);
        if (!$this->quoteCanBeRemissioned($commercialQuote)) {
            throw ValidationException::withMessages(['commercial_quote_id' => 'Esta cotizacion no puede remisionarse.']);
        }

        $commercialQuote->load(['items.taxes', 'commercialClient', 'commercialContact', 'fiscalClient']);

        return response()->json([
            'quote' => [
                'id' => $commercialQuote->id,
                'folio' => $commercialQuote->folio,
                'status' => $commercialQuote->status,
                'commercial_client_id' => $commercialQuote->commercial_client_id,
                'commercial_contact_id' => $commercialQuote->commercial_contact_id,
                'fiscal_client_id' => $commercialQuote->fiscal_client_id,
                'commercial_document_template_id' => $commercialQuote->commercial_document_template_id,
                'currency' => $commercialQuote->currency ?: 'MXN',
                'exchange_rate' => $commercialQuote->exchange_rate,
                'global_discount_amount' => $commercialQuote->global_discount_amount ?: '0.000000',
                'conditions' => $commercialQuote->commercial_terms,
                'notes_visible' => $commercialQuote->customer_notes,
            ],
            'items' => $this->itemsFromQuote($commercialQuote),
        ]);
    }

    private function formPayload(User $user, CommercialRemission $remission, array $items, ?CommercialQuote $quote = null): array
    {
        return [
            'remission' => $remission,
            'quote' => $quote,
            'clients' => $this->clientOptionsForFilter($user),
            'users' => $this->userOptions(),
            'items' => $items,
            'clientOptionsUrl' => route('comercial.cotizaciones.client-options', ['commercialClient' => '__CLIENT__']),
            'productSearchUrl' => route('comercial.cotizaciones.search-productos'),
            'quoteSearchUrl' => route('comercial.remisiones.cotizaciones.search'),
            'quotePayloadUrl' => route('comercial.remisiones.cotizaciones.payload', ['commercialQuote' => '__QUOTE__']),
            'previewDraftUrl' => route('comercial.remisiones.preview-draft'),
            'templates' => $this->templateOptions((int) ($remission->users_id ?: $user->id), (int) $remission->commercial_document_template_id),
            'defaultTemplateId' => $this->defaultTemplateId((int) ($remission->users_id ?: $user->id)),
            'pendingByQuoteItem' => $quote ? $this->pendingByQuoteItem($quote, $remission->exists ? $remission : null) : [],
        ];
    }

    private function remissionPayload(array $data, CommercialClient $client, array $calculated, string $status): array
    {
        $totals = $calculated['totals'];

        return [
            'commercial_quote_id' => $data['commercial_quote_id'] ?? null,
            'commercial_client_id' => (int) $client->id,
            'commercial_contact_id' => $data['commercial_contact_id'] ?? null,
            'fiscal_client_id' => $data['fiscal_client_id'] ?? null,
            'commercial_document_template_id' => $data['commercial_document_template_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'issue_date' => $data['issue_date'],
            'currency' => strtoupper((string) ($data['currency'] ?? 'MXN')),
            'exchange_rate' => $data['exchange_rate'] ?? null,
            'status' => $status,
            'global_discount_amount' => $totals['global_discount_amount'],
            'subtotal' => $totals['subtotal'],
            'line_discount_total' => $totals['line_discount_total'],
            'discount_total' => $totals['discount_total'],
            'transfers_total' => $totals['tax_transfers_total'] ?? '0.000000',
            'withholdings_total' => $totals['tax_retentions_total'] ?? '0.000000',
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'conditions' => $data['conditions'] ?? null,
            'notes_visible' => $data['notes_visible'] ?? null,
            'notes_internal' => $data['notes_internal'] ?? null,
        ];
    }

    private function storeItems(CommercialRemission $remission, array $items): void
    {
        foreach ($items as $index => $item) {
            $created = $remission->items()->create([
                'commercial_quote_item_id' => $item['commercial_quote_item_id'] ?? null,
                'product_id' => $item['product_id'] ?? null,
                'sku' => $item['sku'] ?? null,
                'snapshot_name' => $item['snapshot_name'],
                'snapshot_description' => $item['snapshot_description'] ?? null,
                'snapshot_unit' => $item['snapshot_unit'] ?? null,
                'snapshot_unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_discount_amount' => $item['line_discount_amount'],
                'line_subtotal' => $item['line_subtotal'],
                'line_base_before_global' => $item['line_base_before_global'],
                'global_discount_share' => $item['global_discount_share'],
                'taxable_base' => $item['taxable_base'],
                'tax_amount' => $item['tax_amount'],
                'line_total' => $item['line_total'],
                'sort_order' => $index + 1,
                'notes' => $item['notes'] ?? null,
            ]);

            foreach ($item['taxes'] ?? [] as $tax) {
                $remission->taxes()->create([
                    'commercial_remission_item_id' => $created->id,
                    'tax_name' => $tax['tax_name'],
                    'tax_type' => $tax['tax_type'],
                    'tax_mode' => $tax['tax_mode'],
                    'rate' => $tax['rate'],
                    'base' => $tax['base'],
                    'amount' => $tax['amount'],
                    'sort_order' => $tax['sort_order'],
                ]);
            }
        }
    }

    private function payloadItems(array $items): array
    {
        return collect($items)->values()->map(fn (array $item) => [
            'commercial_quote_item_id' => $item['commercial_quote_item_id'] ?? null,
            'product_id' => $item['product_id'] ?? null,
            'sku' => $item['sku'] ?? null,
            'snapshot_name' => trim((string) $item['snapshot_name']),
            'snapshot_description' => $item['snapshot_description'] ?? null,
            'snapshot_unit' => $item['snapshot_unit'] ?? null,
            'quantity' => (string) ($item['quantity'] ?? '0'),
            'unit_price' => (string) ($item['unit_price'] ?? '0'),
            'line_discount_amount' => (string) ($item['line_discount_amount'] ?? '0'),
            'taxes' => $this->taxPayloads($item),
            'notes' => $item['notes'] ?? null,
        ])->all();
    }

    private function taxPayloads(array $item): array
    {
        return collect($item['taxes'] ?? [])
            ->filter(fn ($tax) => is_array($tax) && trim((string) ($tax['tax_name'] ?? '')) !== '')
            ->map(fn (array $tax) => [
                'tax_name' => trim((string) ($tax['tax_name'] ?? '')),
                'tax_type' => (string) ($tax['tax_type'] ?? CommercialQuoteTax::TYPE_TRASLADO),
                'tax_mode' => (string) ($tax['tax_mode'] ?? CommercialQuoteTax::MODE_RATE),
                'rate' => (string) ($tax['rate'] ?? '0'),
            ])
            ->values()
            ->all();
    }

    private function validatedClient(int $clientId, User $user, ?int $ownerId = null): CommercialClient
    {
        $client = CommercialClient::query()
            ->visibleToQuoteUser($user)
            ->when($ownerId !== null, fn ($query) => $query->where('users_id', $ownerId))
            ->find($clientId);

        if (!$client) {
            throw ValidationException::withMessages(['commercial_client_id' => 'El cliente comercial no pertenece al usuario autorizado.']);
        }

        return $client;
    }

    private function validatedQuote(null|int|string $quoteId, int $ownerId): ?CommercialQuote
    {
        if (empty($quoteId)) {
            return null;
        }

        $quote = CommercialQuote::query()->where('users_id', $ownerId)->find((int) $quoteId);
        if (!$quote || !$this->quoteCanBeRemissioned($quote)) {
            throw ValidationException::withMessages(['commercial_quote_id' => 'La cotizacion no pertenece al usuario autorizado o no puede remisionarse.']);
        }

        return $quote;
    }

    private function validateRelatedIds(array $data, int $ownerId, int $clientId, ?CommercialQuote $quote): void
    {
        if ($quote && (int) $quote->commercial_client_id !== $clientId) {
            throw ValidationException::withMessages(['commercial_client_id' => 'El cliente no coincide con la cotizacion.']);
        }

        if (!empty($data['commercial_contact_id'])) {
            $valid = CommercialContact::query()->where('commercial_client_id', $clientId)->where('id', (int) $data['commercial_contact_id'])->exists();
            if (!$valid) {
                throw ValidationException::withMessages(['commercial_contact_id' => 'El contacto no pertenece al cliente comercial seleccionado.']);
            }
        }

        if (!empty($data['fiscal_client_id'])) {
            $valid = Cliente::query()->forUser($ownerId)->where('id', (int) $data['fiscal_client_id'])->exists();
            if (!$valid) {
                throw ValidationException::withMessages(['fiscal_client_id' => 'El receptor fiscal sugerido no pertenece al usuario autorizado.']);
            }
        }

        $productIds = collect($data['items'] ?? [])->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($productIds->isNotEmpty()) {
            $allowed = Producto::query()->forUser($ownerId)->whereIn('id', $productIds)->count();
            if ($allowed !== $productIds->count()) {
                throw ValidationException::withMessages(['items' => 'Una o mas partidas usan productos de otro usuario.']);
            }
        }
    }

    private function validatePendingQuantities(array $items, ?CommercialQuote $quote, ?CommercialRemission $current = null): void
    {
        if (!$quote) {
            return;
        }

        $pending = $this->pendingByQuoteItem($quote, $current);
        foreach ($items as $index => $item) {
            $quoteItemId = (int) ($item['commercial_quote_item_id'] ?? 0);
            if ($quoteItemId <= 0 || !array_key_exists($quoteItemId, $pending)) {
                throw ValidationException::withMessages(["items.{$index}.commercial_quote_item_id" => 'La partida no pertenece a la cotizacion.']);
            }
            if (Decimal::cmp((string) ($item['quantity'] ?? '0'), $pending[$quoteItemId]) > 0) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => 'La cantidad remitada excede el pendiente de la cotizacion.']);
            }
        }
    }

    private function pendingByQuoteItem(CommercialQuote $quote, ?CommercialRemission $current = null): array
    {
        $quote->loadMissing('items');
        $sent = CommercialRemissionItem::query()
            ->select('commercial_quote_item_id', DB::raw('SUM(quantity) as sent_qty'))
            ->whereNotNull('commercial_quote_item_id')
            ->whereHas('remission', function ($query) use ($quote, $current) {
                $query->where('commercial_quote_id', $quote->id)
                    ->where('status', '!=', CommercialRemission::STATUS_CANCELLED)
                    ->when($current?->exists, fn ($sub) => $sub->where('id', '!=', $current->id));
            })
            ->groupBy('commercial_quote_item_id')
            ->pluck('sent_qty', 'commercial_quote_item_id');

        return $quote->items->mapWithKeys(function (CommercialQuoteItem $item) use ($sent) {
            $pending = Decimal::max(Decimal::sub((string) $item->quantity, (string) ($sent[$item->id] ?? '0')), '0');
            return [$item->id => $pending];
        })->all();
    }

    private function itemsFromQuote(CommercialQuote $quote): array
    {
        $pending = $this->pendingByQuoteItem($quote);

        return $quote->items->filter(fn ($item) => Decimal::cmp($pending[$item->id] ?? '0', '0') > 0)->map(fn ($item) => [
            'commercial_quote_item_id' => $item->id,
            'product_id' => $item->product_id,
            'sku' => $item->sku,
            'snapshot_name' => $item->snapshot_name,
            'snapshot_description' => $item->snapshot_description,
            'snapshot_unit' => $item->snapshot_unit,
            'quantity' => $pending[$item->id],
            'quoted_quantity' => (string) $item->quantity,
            'previously_remitted_quantity' => Decimal::sub((string) $item->quantity, $pending[$item->id]),
            'pending_quantity' => $pending[$item->id],
            'unit_price' => $item->unit_price,
            'line_discount_amount' => $item->line_discount_amount ?: '0.000000',
            'taxes' => $item->taxes->map(fn (CommercialQuoteTax $tax) => [
                'tax_name' => $tax->tax_name,
                'tax_type' => $tax->tax_type,
                'tax_mode' => $tax->tax_mode,
                'rate' => $tax->rate,
            ])->values()->all(),
            'notes' => $item->notes,
        ])->values()->all();
    }

    private function itemPayloadForForm(CommercialRemissionItem $item): array
    {
        return [
            'commercial_quote_item_id' => $item->commercial_quote_item_id,
            'product_id' => $item->product_id,
            'sku' => $item->sku,
            'snapshot_name' => $item->snapshot_name,
            'snapshot_description' => $item->snapshot_description,
            'snapshot_unit' => $item->snapshot_unit,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_discount_amount' => $item->line_discount_amount,
            'taxes' => $item->taxes->map(fn (CommercialRemissionTax $tax) => [
                'tax_name' => $tax->tax_name,
                'tax_type' => $tax->tax_type,
                'tax_mode' => $tax->tax_mode,
                'rate' => $tax->rate,
            ])->values()->all(),
            'notes' => $item->notes,
        ];
    }

    private function ensureEditable(CommercialRemission $remission): void
    {
        if (!$remission->canBeEdited()) {
            throw ValidationException::withMessages(['status' => 'Una remision emitida, cancelada o facturada no puede editarse.']);
        }
    }

    private function recordStatus(CommercialRemission $remission, ?string $old, string $new, int $userId, ?string $note = null): void
    {
        $remission->statusHistory()->create([
            'old_status' => $old,
            'new_status' => $new,
            'user_id' => $userId,
            'note' => $note,
            'changed_at' => now(),
        ]);
    }

    private function quoteCanBeRemissioned(CommercialQuote $quote): bool
    {
        return in_array($quote->status, [
            CommercialQuote::STATUS_DRAFT,
            CommercialQuote::STATUS_SENT,
            CommercialQuote::STATUS_ACCEPTED,
        ], true);
    }

    private function acceptQuoteIfRequested(?CommercialQuote $quote, array $data, int $userId, CommercialTemplateSnapshotter $snapshotter): void
    {
        if (!$quote || empty($data['accept_quote_on_save']) || $quote->status === CommercialQuote::STATUS_ACCEPTED) {
            return;
        }

        if (!in_array($quote->status, [CommercialQuote::STATUS_DRAFT, CommercialQuote::STATUS_SENT], true)) {
            return;
        }

        $old = $quote->status;
        $quote->update(['status' => CommercialQuote::STATUS_ACCEPTED]);
        $snapshotter->ensureSnapshot($quote->fresh(['documentTemplate']));
        $quote->statusHistory()->create([
            'old_status' => $old,
            'new_status' => CommercialQuote::STATUS_ACCEPTED,
            'user_id' => $userId,
            'note' => 'Cotizacion aceptada al crear remision.',
            'changed_at' => now(),
        ]);
    }

    private function storePreviewSession(Request $request, array $data, ?int $remissionId = null): string
    {
        $token = (string) Str::uuid();
        $request->session()->put($this->previewSessionKey($token), [
            'user_id' => (int) $request->user()->id,
            'remission_id' => $remissionId,
            'data' => $data,
            'created_at' => now()->timestamp,
        ]);

        return $token;
    }

    private function previewSessionPayload(Request $request, string $token): array
    {
        $payload = $request->session()->get($this->previewSessionKey($token));
        if (!$payload || (int) ($payload['user_id'] ?? 0) !== (int) $request->user()->id) {
            abort(404);
        }

        if ((int) ($payload['created_at'] ?? 0) < now()->subHours(2)->timestamp) {
            $request->session()->forget($this->previewSessionKey($token));
            abort(404);
        }

        return $payload;
    }

    private function previewSessionKey(string $token): string
    {
        return 'commercial_remission_preview.' . $token;
    }

    private function validatedTemplate(null|int|string $templateId, int $ownerId): ?CommercialDocumentTemplate
    {
        if (empty($templateId)) {
            return null;
        }

        $template = CommercialDocumentTemplate::query()
            ->forUser($ownerId)
            ->whereIn('document_type', [CommercialDocumentTemplate::TYPE_REMISSION, CommercialDocumentTemplate::TYPE_GENERAL])
            ->where('is_active', true)
            ->find((int) $templateId);

        if (!$template) {
            throw ValidationException::withMessages(['commercial_document_template_id' => 'El formato comercial no pertenece al usuario autorizado o no esta activo.']);
        }

        return $template;
    }

    private function templateOptions(int $ownerId, ?int $includeId = null)
    {
        return CommercialDocumentTemplate::query()
            ->forUser($ownerId)
            ->whereIn('document_type', [CommercialDocumentTemplate::TYPE_REMISSION, CommercialDocumentTemplate::TYPE_GENERAL])
            ->where(function ($query) use ($includeId) {
                $query->where('is_active', true)->when($includeId, fn ($sub) => $sub->orWhere('id', $includeId));
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function defaultTemplateId(int $ownerId): ?int
    {
        return CommercialDocumentTemplate::query()
            ->forUser($ownerId)
            ->whereIn('document_type', [CommercialDocumentTemplate::TYPE_REMISSION, CommercialDocumentTemplate::TYPE_GENERAL])
            ->where('is_active', true)
            ->where('is_default', true)
            ->value('id');
    }

    private function userOptions()
    {
        return User::query()->where('active', 1)->orderBy('username')->get(['id', 'username', 'email']);
    }

    private function clientOptionsForFilter(User $user)
    {
        return CommercialClient::query()->visibleToQuoteUser($user)->orderBy('name')->get(['id', 'users_id', 'name', 'business_name']);
    }

    private function statusLabels(): array
    {
        return [
            CommercialRemission::STATUS_DRAFT => 'Borrador',
            CommercialRemission::STATUS_ISSUED => 'Emitida',
            CommercialRemission::STATUS_CANCELLED => 'Cancelada',
            CommercialRemission::STATUS_INVOICED => 'Facturada',
        ];
    }

    private function statusTones(): array
    {
        return [
            CommercialRemission::STATUS_DRAFT => 'gray',
            CommercialRemission::STATUS_ISSUED => 'green',
            CommercialRemission::STATUS_CANCELLED => 'red',
            CommercialRemission::STATUS_INVOICED => 'sky',
        ];
    }
}
