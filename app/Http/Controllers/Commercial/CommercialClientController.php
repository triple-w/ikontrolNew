<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommercialClientRequest;
use App\Http\Requests\UpdateCommercialClientRequest;
use App\Models\Cliente;
use App\Models\CommercialClient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialClientController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CommercialClient::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'active'),
            'type' => (string) $request->query('type', ''),
            'assigned_user_id' => (string) $request->query('assigned_user_id', ''),
            'category' => trim((string) $request->query('category', '')),
        ];

        $clients = CommercialClient::query()
            ->with(['primaryContact', 'defaultFiscalClient', 'assignedUser'])
            ->when(!$this->isAdmin($request->user()), function ($query) use ($request) {
                $query->where(function ($visible) use ($request) {
                    $visible->where('users_id', $request->user()->id)
                        ->orWhere('assigned_user_id', $request->user()->id);
                });
            })
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $q = $filters['q'];
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhereHas('contacts', function ($contacts) use ($q) {
                            $contacts->where('name', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%")
                                ->orWhere('phone', 'like', "%{$q}%")
                                ->orWhere('mobile', 'like', "%{$q}%");
                        })
                        ->orWhereHas('fiscalClients', function ($fiscal) use ($q) {
                            $fiscal->where('rfc', 'like', "%{$q}%")
                                ->orWhere('razon_social', 'like', "%{$q}%");
                        });
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['type'] !== '', fn ($query) => $query->where('client_type', $filters['type']))
            ->when($filters['assigned_user_id'] !== '', fn ($query) => $query->where('assigned_user_id', (int) $filters['assigned_user_id']))
            ->when($filters['category'] !== '', fn ($query) => $query->where('category', 'like', "%{$filters['category']}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('comercial.clientes.index', [
            'clients' => $clients,
            'filters' => $filters,
            'users' => $this->userOptions(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', CommercialClient::class);

        return view('comercial.clientes.create', [
            'commercialClient' => new CommercialClient([
                'client_type' => 'company',
                'country' => 'Mexico',
                'is_active' => true,
            ]),
            'users' => $this->userOptions(),
            'fiscalClients' => $this->fiscalClientOptions(),
            'selectedFiscalIds' => [],
            'defaultFiscalId' => null,
        ]);
    }

    public function store(StoreCommercialClientRequest $request)
    {
        $data = $this->clientPayload($request->validated());
        $data['users_id'] = (int) $request->user()->id;

        $commercialClient = DB::transaction(function () use ($data, $request) {
            $client = CommercialClient::create($data);
            $this->syncFiscalClients($client, $request);

            return $client;
        });

        return redirect()
            ->route('comercial.clientes.show', $commercialClient)
            ->with('status', 'Cliente comercial creado correctamente.');
    }

    public function show(CommercialClient $commercialClient)
    {
        $this->authorize('view', $commercialClient);

        $commercialClient->load([
            'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('name'),
            'fiscalClients',
            'defaultFiscalClient',
            'assignedUser',
            'owner',
        ]);

        return view('comercial.clientes.show', compact('commercialClient'));
    }

    public function edit(CommercialClient $commercialClient)
    {
        $this->authorize('update', $commercialClient);

        $commercialClient->load('fiscalClients');

        return view('comercial.clientes.edit', [
            'commercialClient' => $commercialClient,
            'users' => $this->userOptions(),
            'fiscalClients' => $this->fiscalClientOptions((int) $commercialClient->users_id),
            'selectedFiscalIds' => $commercialClient->fiscalClients->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'defaultFiscalId' => optional($commercialClient->defaultFiscalClient()->first())->id,
        ]);
    }

    public function update(UpdateCommercialClientRequest $request, CommercialClient $commercialClient)
    {
        $this->authorize('update', $commercialClient);

        DB::transaction(function () use ($request, $commercialClient) {
            $commercialClient->update($this->clientPayload($request->validated()));
            $this->syncFiscalClients($commercialClient, $request);
        });

        return redirect()
            ->route('comercial.clientes.show', $commercialClient)
            ->with('status', 'Cliente comercial actualizado correctamente.');
    }

    public function destroy(CommercialClient $commercialClient)
    {
        $this->authorize('delete', $commercialClient);

        $commercialClient->delete();

        return redirect()
            ->route('comercial.clientes.index')
            ->with('status', 'Cliente comercial eliminado correctamente.');
    }

    private function clientPayload(array $data): array
    {
        return [
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'name' => $data['name'],
            'business_name' => $data['business_name'] ?? null,
            'client_type' => $data['client_type'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'street' => $data['street'] ?? null,
            'exterior_number' => $data['exterior_number'] ?? null,
            'interior_number' => $data['interior_number'] ?? null,
            'neighborhood' => $data['neighborhood'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => $data['country'] ?? 'Mexico',
            'postal_code' => $data['postal_code'] ?? null,
            'category' => $data['category'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ];
    }

    private function syncFiscalClients(CommercialClient $commercialClient, Request $request): void
    {
        $selected = collect($request->input('fiscal_client_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selected->isEmpty()) {
            $commercialClient->fiscalClients()->sync([]);
            return;
        }

        $allowed = $this->fiscalClientOptions((int) $commercialClient->users_id)
            ->whereIn('id', $selected)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($allowed->count() !== $selected->count()) {
            throw ValidationException::withMessages([
                'fiscal_client_ids' => 'Uno o mas receptores fiscales no pertenecen al usuario actual.',
            ]);
        }

        $defaultId = (int) $request->input('default_fiscal_client_id', 0);
        $sync = [];
        foreach ($allowed as $fiscalId) {
            $sync[$fiscalId] = ['is_default' => $defaultId > 0 && $defaultId === (int) $fiscalId];
        }

        $commercialClient->fiscalClients()->sync($sync);
    }

    private function userOptions()
    {
        return User::query()
            ->where('active', 1)
            ->orderBy('username')
            ->get(['id', 'username', 'email']);
    }

    private function fiscalClientOptions(?int $userId = null)
    {
        return Cliente::query()
            ->forUser($userId ?? (int) auth()->id())
            ->orderBy('razon_social')
            ->get(['id', 'rfc', 'razon_social']);
    }

    private function isAdmin(User $user): bool
    {
        $role = strtoupper((string) ($user->rol ?? ''));

        return (int) ($user->admin ?? 0) === 1 || str_contains($role, 'ADMIN');
    }
}
