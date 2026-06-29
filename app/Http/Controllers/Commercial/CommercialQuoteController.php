<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeCommercialQuoteStatusRequest;
use App\Http\Requests\StoreCommercialQuoteRequest;
use App\Http\Requests\UpdateCommercialQuoteRequest;
use App\Models\Cliente;
use App\Models\CommercialClient;
use App\Models\CommercialContact;
use App\Models\CommercialDocumentTemplate;
use App\Models\CommercialQuote;
use App\Models\CommercialQuoteTax;
use App\Models\Producto;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialQuoteDocumentBuilder;
use App\Services\CommercialDocuments\CommercialTemplateSnapshotter;
use App\Services\CommercialQuoteCalculator;
use App\Services\CommercialQuoteFolioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialQuoteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CommercialQuote::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'assigned_user_id' => (string) $request->query('assigned_user_id', ''),
            'commercial_client_id' => (string) $request->query('commercial_client_id', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];

        $quotes = CommercialQuote::query()
            ->visibleTo($request->user())
            ->with(['commercialClient', 'commercialContact', 'assignedUser'])
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $q = $filters['q'];
                $query->where(function ($sub) use ($q) {
                    $sub->where('folio', 'like', "%{$q}%")
                        ->orWhereHas('commercialClient', function ($client) use ($q) {
                            $client->where('name', 'like', "%{$q}%")
                                ->orWhere('business_name', 'like', "%{$q}%");
                        })
                        ->orWhereHas('commercialContact', fn ($contact) => $contact->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('assignedUser', fn ($user) => $user->where('username', 'like', "%{$q}%"))
                        ->orWhereHas('items', function ($items) use ($q) {
                            $items->where('snapshot_name', 'like', "%{$q}%")
                                ->orWhere('snapshot_description', 'like', "%{$q}%")
                                ->orWhere('sku', 'like', "%{$q}%");
                        });
                });
            })
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['assigned_user_id'] !== '', fn ($query) => $query->where('assigned_user_id', (int) $filters['assigned_user_id']))
            ->when($filters['commercial_client_id'] !== '', fn ($query) => $query->where('commercial_client_id', (int) $filters['commercial_client_id']))
            ->when($filters['date_from'] !== '', fn ($query) => $query->whereDate('issued_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($query) => $query->whereDate('issued_at', '<=', $filters['date_to']))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('comercial.cotizaciones.index', [
            'quotes' => $quotes,
            'filters' => $filters,
            'statuses' => $this->statusLabels(),
            'users' => $this->userOptions(),
            'clients' => $this->clientOptionsForFilter($request->user()),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', CommercialQuote::class);

        return view('comercial.cotizaciones.create', [
            'quote' => new CommercialQuote([
                'issued_at' => now()->toDateString(),
                'currency' => 'MXN',
                'global_discount_amount' => '0.000000',
                'status' => CommercialQuote::STATUS_DRAFT,
            ]),
            'clients' => $this->clientOptionsForFilter($request->user()),
            'users' => $this->userOptions(),
            'items' => [],
            'clientOptionsUrl' => route('comercial.cotizaciones.client-options', ['commercialClient' => '__CLIENT__']),
            'productSearchUrl' => route('comercial.cotizaciones.search-productos'),
            'templates' => $this->templateOptions((int) $request->user()->id),
            'defaultTemplateId' => $this->defaultTemplateId((int) $request->user()->id),
            'previewDraftUrl' => route('comercial.cotizaciones.preview-draft'),
        ]);
    }

    public function store(StoreCommercialQuoteRequest $request, CommercialQuoteCalculator $calculator, CommercialQuoteFolioService $folioService, CommercialTemplateSnapshotter $snapshotter)
    {
        $data = $request->validated();
        $client = $this->validatedClient($data['commercial_client_id'], $request->user());
        $this->validateRelatedIds($data, (int) $client->users_id, (int) $client->id);
        $this->validatedTemplate($data['commercial_document_template_id'] ?? null, (int) $client->users_id);
        $payloadItems = $this->payloadItems($data['items']);
        $calculated = $calculator->calculate($payloadItems, (string) ($data['global_discount_amount'] ?? '0'));

        $quote = $this->createWithRetry($data, $client, $calculated, $request, $folioService, $snapshotter);

        return redirect()
            ->route('comercial.cotizaciones.show', $quote)
            ->with('status', 'Cotizacion creada correctamente.');
    }

    public function show(CommercialQuote $commercialQuote)
    {
        $this->authorize('view', $commercialQuote);

        $commercialQuote->load([
            'commercialClient',
            'commercialContact',
            'fiscalClient',
            'creator',
            'assignedUser',
            'documentTemplate',
            'items.taxes',
            'taxes',
            'statusHistory.user',
        ]);

        return view('comercial.cotizaciones.show', [
            'quote' => $commercialQuote,
            'statuses' => $this->statusLabels(),
            'statusTones' => $this->statusTones(),
        ]);
    }

    public function edit(Request $request, CommercialQuote $commercialQuote)
    {
        $this->authorize('update', $commercialQuote);
        $this->ensureEditable($commercialQuote);

        $commercialQuote->load(['items', 'commercialClient.contacts', 'commercialClient.fiscalClients']);

        return view('comercial.cotizaciones.edit', [
            'quote' => $commercialQuote,
            'clients' => $this->clientOptionsForFilter($request->user()),
            'users' => $this->userOptions(),
            'items' => $commercialQuote->items->map(fn ($item) => $this->itemPayloadForForm($item))->values()->all(),
            'clientOptionsUrl' => route('comercial.cotizaciones.client-options', ['commercialClient' => '__CLIENT__']),
            'productSearchUrl' => route('comercial.cotizaciones.search-productos'),
            'templates' => $this->templateOptions((int) $commercialQuote->users_id, (int) $commercialQuote->commercial_document_template_id),
            'defaultTemplateId' => $this->defaultTemplateId((int) $commercialQuote->users_id),
            'previewDraftUrl' => route('comercial.cotizaciones.preview-draft'),
        ]);
    }

    public function update(UpdateCommercialQuoteRequest $request, CommercialQuote $commercialQuote, CommercialQuoteCalculator $calculator, CommercialTemplateSnapshotter $snapshotter)
    {
        $this->authorize('update', $commercialQuote);
        $this->ensureEditable($commercialQuote);

        $data = $request->validated();
        $client = $this->validatedClient($data['commercial_client_id'], $request->user(), (int) $commercialQuote->users_id);
        $this->validateRelatedIds($data, (int) $commercialQuote->users_id, (int) $client->id);
        $this->validatedTemplate($data['commercial_document_template_id'] ?? null, (int) $commercialQuote->users_id);
        $calculated = $calculator->calculate($this->payloadItems($data['items']), (string) ($data['global_discount_amount'] ?? '0'));

        DB::transaction(function () use ($commercialQuote, $data, $client, $calculated, $request, $snapshotter) {
            $oldStatus = $commercialQuote->status;
            $status = $oldStatus;
            if (($data['save_action'] ?? 'draft') === 'send' && $oldStatus === CommercialQuote::STATUS_DRAFT) {
                $status = CommercialQuote::STATUS_SENT;
            }

            $commercialQuote->update($this->quotePayload($data, $client, $calculated, $status));
            $commercialQuote->items()->delete();
            $this->storeItems($commercialQuote, $calculated['items']);

            if ($status !== $oldStatus) {
                $snapshotter->ensureSnapshot($commercialQuote->fresh(['documentTemplate']));
                $this->recordStatus($commercialQuote, $oldStatus, $status, (int) $request->user()->id, 'Guardada y enviada.');
            }
        });

        return redirect()
            ->route('comercial.cotizaciones.show', $commercialQuote)
            ->with('status', 'Cotizacion actualizada correctamente.');
    }

    public function send(ChangeCommercialQuoteStatusRequest $request, CommercialQuote $commercialQuote, CommercialTemplateSnapshotter $snapshotter)
    {
        $this->changeStatus($request, $commercialQuote, [CommercialQuote::STATUS_DRAFT], CommercialQuote::STATUS_SENT, 'Cotizacion enviada.', $snapshotter);

        return back()->with('status', 'Cotizacion enviada correctamente.');
    }

    public function accept(ChangeCommercialQuoteStatusRequest $request, CommercialQuote $commercialQuote, CommercialTemplateSnapshotter $snapshotter)
    {
        $this->changeStatus($request, $commercialQuote, [CommercialQuote::STATUS_SENT], CommercialQuote::STATUS_ACCEPTED, 'Cotizacion aceptada.', $snapshotter);

        return back()->with('status', 'Cotizacion aceptada correctamente.');
    }

    public function reject(ChangeCommercialQuoteStatusRequest $request, CommercialQuote $commercialQuote, CommercialTemplateSnapshotter $snapshotter)
    {
        $this->changeStatus($request, $commercialQuote, [CommercialQuote::STATUS_SENT], CommercialQuote::STATUS_REJECTED, 'Cotizacion rechazada.', $snapshotter);

        return back()->with('status', 'Cotizacion rechazada correctamente.');
    }

    public function cancel(ChangeCommercialQuoteStatusRequest $request, CommercialQuote $commercialQuote, CommercialTemplateSnapshotter $snapshotter)
    {
        $this->changeStatus($request, $commercialQuote, [CommercialQuote::STATUS_DRAFT, CommercialQuote::STATUS_SENT], CommercialQuote::STATUS_CANCELLED, 'Cotizacion cancelada.', $snapshotter);

        return back()->with('status', 'Cotizacion cancelada correctamente.');
    }

    public function previewDraft(StoreCommercialQuoteRequest $request, CommercialQuoteDocumentBuilder $builder)
    {
        $data = $request->validated();
        $client = $this->validatedClient($data['commercial_client_id'], $request->user());
        $this->validateRelatedIds($data, (int) $client->users_id, (int) $client->id);
        $template = $this->validatedTemplate($data['commercial_document_template_id'] ?? null, (int) $client->users_id);

        return view('comercial.cotizaciones.preview', [
            'document' => $builder->fromPayload($data, (int) $client->users_id, $template),
            'backUrl' => url()->previous(),
        ]);
    }

    public function preview(CommercialQuote $commercialQuote, CommercialQuoteDocumentBuilder $builder)
    {
        $this->authorize('view', $commercialQuote);

        return view('comercial.cotizaciones.preview', [
            'document' => $builder->fromQuote($commercialQuote),
            'backUrl' => route('comercial.cotizaciones.show', $commercialQuote),
        ]);
    }

    public function pdf(CommercialQuote $commercialQuote, CommercialQuoteDocumentBuilder $builder)
    {
        $this->authorize('view', $commercialQuote);

        $document = $builder->fromQuote($commercialQuote);

        return Pdf::loadView('comercial.cotizaciones.pdf', ['document' => $document])
            ->setPaper('letter')
            ->stream(($commercialQuote->folio ?: 'cotizacion') . '.pdf');
    }

    public function print(CommercialQuote $commercialQuote, CommercialQuoteDocumentBuilder $builder)
    {
        $this->authorize('view', $commercialQuote);

        return view('comercial.cotizaciones.print', [
            'document' => $builder->fromQuote($commercialQuote),
        ]);
    }

    public function searchProducts(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $ownerId = (int) $request->user()->id;
        if ($request->filled('commercial_client_id')) {
            $client = $this->validatedClient((int) $request->query('commercial_client_id'), $request->user());
            $ownerId = (int) $client->users_id;
        }

        $products = Producto::query()
            ->forUser($ownerId)
            ->with(['prodServ:id,clave,descripcion', 'unidadSat:id,clave,descripcion'])
            ->where(function ($query) use ($q) {
                $query->where('clave', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%")
                    ->orWhere('unidad', 'like', "%{$q}%")
                    ->orWhere('observaciones', 'like', "%{$q}%")
                    ->orWhereHas('prodServ', fn ($sat) => $sat->where('clave', 'like', "%{$q}%")->orWhere('descripcion', 'like', "%{$q}%"))
                    ->orWhereHas('unidadSat', fn ($sat) => $sat->where('clave', 'like', "%{$q}%")->orWhere('descripcion', 'like', "%{$q}%"));
            })
            ->orderBy('descripcion')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $products->map(fn (Producto $product) => [
                'id' => (int) $product->id,
                'sku' => (string) ($product->clave ?? ''),
                'snapshot_name' => (string) ($product->descripcion ?? ''),
                'snapshot_description' => (string) ($product->observaciones ?: $product->descripcion ?: ''),
                'snapshot_unit' => (string) ($product->unidad ?: $product->unidadSat?->clave ?: ''),
                'unit_price' => (string) ($product->precio ?? '0.000000'),
                'tax_name' => '',
                'tax_type' => CommercialQuoteTax::TYPE_TRASLADO,
                'tax_rate' => '0.000000',
            ])->values(),
        ]);
    }

    public function clientOptions(Request $request, CommercialClient $commercialClient)
    {
        $this->authorize('view', $commercialClient);

        $commercialClient->load([
            'contacts' => fn ($query) => $query->active()->where('receives_quotes', true)->orderByDesc('is_primary')->orderBy('name'),
            'fiscalClients' => fn ($query) => $query->orderBy('razon_social'),
        ]);

        return response()->json([
            'contacts' => $commercialClient->contacts->map(fn (CommercialContact $contact) => [
                'id' => (int) $contact->id,
                'name' => (string) $contact->name,
                'email' => (string) ($contact->email ?? ''),
                'phone' => (string) ($contact->phone ?: $contact->mobile ?: ''),
            ])->values(),
            'fiscal_clients' => $commercialClient->fiscalClients->map(fn (Cliente $cliente) => [
                'id' => (int) $cliente->id,
                'rfc' => (string) ($cliente->rfc ?? ''),
                'razon_social' => (string) ($cliente->razon_social ?? ''),
                'is_default' => (bool) ($cliente->pivot?->is_default ?? false),
            ])->values(),
        ]);
    }

    private function createWithRetry(array $data, CommercialClient $client, array $calculated, Request $request, CommercialQuoteFolioService $folioService, CommercialTemplateSnapshotter $snapshotter): CommercialQuote
    {
        $lastException = null;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $client, $calculated, $request, $folioService, $snapshotter) {
                    $folio = $folioService->reserve((int) $client->users_id);
                    $status = ($data['save_action'] ?? 'draft') === 'send'
                        ? CommercialQuote::STATUS_SENT
                        : CommercialQuote::STATUS_DRAFT;

                    $quote = CommercialQuote::create(array_merge(
                        $this->quotePayload($data, $client, $calculated, $status),
                        $folio,
                        [
                            'users_id' => (int) $client->users_id,
                            'created_by_id' => (int) $request->user()->id,
                        ]
                    ));

                    $this->storeItems($quote, $calculated['items']);
                    if ($status !== CommercialQuote::STATUS_DRAFT) {
                        $snapshotter->ensureSnapshot($quote->fresh(['documentTemplate']));
                    }
                    $this->recordStatus($quote, null, $status, (int) $request->user()->id, 'Cotizacion creada.');

                    return $quote;
                });
            } catch (QueryException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?? new \RuntimeException('No fue posible crear la cotizacion.');
    }

    private function quotePayload(array $data, CommercialClient $client, array $calculated, string $status): array
    {
        $totals = $calculated['totals'];

        return [
            'commercial_client_id' => (int) $client->id,
            'commercial_contact_id' => $data['commercial_contact_id'] ?? null,
            'fiscal_client_id' => $data['fiscal_client_id'] ?? null,
            'commercial_document_template_id' => $data['commercial_document_template_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'issued_at' => $data['issued_at'],
            'expires_at' => $data['expires_at'] ?? null,
            'currency' => strtoupper((string) ($data['currency'] ?? 'MXN')),
            'exchange_rate' => $data['exchange_rate'] ?? null,
            'status' => $status,
            'commercial_terms' => $data['commercial_terms'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
            'global_discount_amount' => $totals['global_discount_amount'],
            'subtotal' => $totals['subtotal'],
            'line_discount_total' => $totals['line_discount_total'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
        ];
    }

    private function storeItems(CommercialQuote $quote, array $items): void
    {
        foreach ($items as $index => $item) {
            $created = $quote->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'sku' => $item['sku'] ?? null,
                'snapshot_name' => $item['snapshot_name'],
                'snapshot_description' => $item['snapshot_description'] ?? null,
                'snapshot_unit' => $item['snapshot_unit'] ?? null,
                'snapshot_unit_price' => $item['unit_price'],
                'snapshot_tax_name' => $item['tax_name'] ?: null,
                'snapshot_tax_type' => $item['tax_type'] ?: null,
                'snapshot_tax_rate' => $item['tax_rate'],
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

            if ($item['tax_name'] !== '' && $item['tax_rate'] !== '0.000000') {
                $quote->taxes()->create([
                    'commercial_quote_item_id' => $created->id,
                    'tax_name' => $item['tax_name'],
                    'tax_type' => $item['tax_type'],
                    'rate' => $item['tax_rate'],
                    'base' => $item['taxable_base'],
                    'amount' => $item['tax_amount'],
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }

    private function payloadItems(array $items): array
    {
        return collect($items)->values()->map(function (array $item) {
            return [
                'product_id' => $item['product_id'] ?? null,
                'sku' => $item['sku'] ?? null,
                'snapshot_name' => trim((string) $item['snapshot_name']),
                'snapshot_description' => $item['snapshot_description'] ?? null,
                'snapshot_unit' => $item['snapshot_unit'] ?? null,
                'quantity' => (string) ($item['quantity'] ?? '0'),
                'unit_price' => (string) ($item['unit_price'] ?? '0'),
                'line_discount_amount' => (string) ($item['line_discount_amount'] ?? '0'),
                'tax_name' => trim((string) ($item['tax_name'] ?? '')),
                'tax_type' => (string) ($item['tax_type'] ?? CommercialQuoteTax::TYPE_TRASLADO),
                'tax_rate' => (string) ($item['tax_rate'] ?? '0'),
                'notes' => $item['notes'] ?? null,
            ];
        })->all();
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

    private function validateRelatedIds(array $data, int $ownerId, int $clientId): void
    {
        if (!empty($data['commercial_contact_id'])) {
            $valid = CommercialContact::query()
                ->where('commercial_client_id', $clientId)
                ->where('id', (int) $data['commercial_contact_id'])
                ->exists();
            if (!$valid) {
                throw ValidationException::withMessages(['commercial_contact_id' => 'El contacto no pertenece al cliente comercial seleccionado.']);
            }
        }

        if (!empty($data['fiscal_client_id'])) {
            $valid = Cliente::query()
                ->forUser($ownerId)
                ->where('id', (int) $data['fiscal_client_id'])
                ->exists();
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

    private function ensureEditable(CommercialQuote $quote): void
    {
        if (!$quote->canBeEdited()) {
            throw ValidationException::withMessages([
                'status' => 'Una cotizacion aceptada, cancelada, rechazada, expirada o convertida no puede editarse.',
            ]);
        }
    }

    private function changeStatus(ChangeCommercialQuoteStatusRequest $request, CommercialQuote $quote, array $from, string $to, string $defaultNote, CommercialTemplateSnapshotter $snapshotter): void
    {
        $this->authorize('update', $quote);

        if (!in_array($quote->status, $from, true)) {
            throw ValidationException::withMessages(['status' => 'La cotizacion no permite esa transicion de estado.']);
        }

        DB::transaction(function () use ($request, $quote, $to, $defaultNote, $snapshotter) {
            $old = $quote->status;
            $quote->update(['status' => $to]);
            $snapshotter->ensureSnapshot($quote->fresh(['documentTemplate']));
            $this->recordStatus($quote, $old, $to, (int) $request->user()->id, $request->input('note') ?: $defaultNote);
        });
    }

    private function recordStatus(CommercialQuote $quote, ?string $old, string $new, int $userId, ?string $note = null): void
    {
        $quote->statusHistory()->create([
            'old_status' => $old,
            'new_status' => $new,
            'user_id' => $userId,
            'note' => $note,
            'changed_at' => now(),
        ]);
    }

    private function itemPayloadForForm($item): array
    {
        return [
            'product_id' => $item->product_id,
            'sku' => $item->sku,
            'snapshot_name' => $item->snapshot_name,
            'snapshot_description' => $item->snapshot_description,
            'snapshot_unit' => $item->snapshot_unit,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_discount_amount' => $item->line_discount_amount,
            'tax_name' => $item->snapshot_tax_name,
            'tax_type' => $item->snapshot_tax_type ?: CommercialQuoteTax::TYPE_TRASLADO,
            'tax_rate' => $item->snapshot_tax_rate,
            'notes' => $item->notes,
        ];
    }

    private function userOptions()
    {
        return User::query()->where('active', 1)->orderBy('username')->get(['id', 'username', 'email']);
    }

    private function validatedTemplate(null|int|string $templateId, int $ownerId): ?CommercialDocumentTemplate
    {
        if (empty($templateId)) {
            return null;
        }

        $template = CommercialDocumentTemplate::query()
            ->forUser($ownerId)
            ->forType(CommercialDocumentTemplate::TYPE_QUOTE)
            ->where('is_active', true)
            ->find((int) $templateId);

        if (!$template) {
            throw ValidationException::withMessages([
                'commercial_document_template_id' => 'El formato comercial no pertenece al usuario autorizado o no esta activo.',
            ]);
        }

        return $template;
    }

    private function templateOptions(int $ownerId, ?int $includeId = null)
    {
        return CommercialDocumentTemplate::query()
            ->forUser($ownerId)
            ->forType(CommercialDocumentTemplate::TYPE_QUOTE)
            ->where(function ($query) use ($includeId) {
                $query->where('is_active', true)
                    ->when($includeId, fn ($sub) => $sub->orWhere('id', $includeId));
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function defaultTemplateId(int $ownerId): ?int
    {
        return CommercialDocumentTemplate::query()
            ->forUser($ownerId)
            ->forType(CommercialDocumentTemplate::TYPE_QUOTE)
            ->where('is_active', true)
            ->where('is_default', true)
            ->value('id');
    }

    private function clientOptionsForFilter(User $user)
    {
        return CommercialClient::query()
            ->visibleToQuoteUser($user)
            ->orderBy('name')
            ->get(['id', 'users_id', 'name', 'business_name']);
    }

    private function statusLabels(): array
    {
        return [
            CommercialQuote::STATUS_DRAFT => 'Borrador',
            CommercialQuote::STATUS_SENT => 'Enviada',
            CommercialQuote::STATUS_ACCEPTED => 'Aceptada',
            CommercialQuote::STATUS_REJECTED => 'Rechazada',
            CommercialQuote::STATUS_EXPIRED => 'Expirada',
            CommercialQuote::STATUS_CANCELLED => 'Cancelada',
            CommercialQuote::STATUS_CONVERTED_TO_REMISSION => 'Convertida a remision',
        ];
    }

    private function statusTones(): array
    {
        return [
            CommercialQuote::STATUS_DRAFT => 'gray',
            CommercialQuote::STATUS_SENT => 'sky',
            CommercialQuote::STATUS_ACCEPTED => 'green',
            CommercialQuote::STATUS_REJECTED => 'red',
            CommercialQuote::STATUS_EXPIRED => 'amber',
            CommercialQuote::STATUS_CANCELLED => 'red',
            CommercialQuote::STATUS_CONVERTED_TO_REMISSION => 'green',
        ];
    }
}
