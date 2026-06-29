<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\CommercialClient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ClientesController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $clientes = Cliente::query()
            ->forUser(auth()->id())
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('razon_social', 'like', "%{$q}%")
                       ->orWhere('rfc', 'like', "%{$q}%")
                       ->orWhere('email', 'like', "%{$q}%")
                       ->orWhere('telefono', 'like', "%{$q}%");
                });
            })
            ->orderBy('razon_social')
            ->paginate(20)
            ->withQueryString();

        return view('clientes.index', compact('clientes', 'q'));
    }

    public function create()
    {
        $cliente = new Cliente([
            'regimen_fiscal' => '',
            'pais' => 'MEX',
        ]);

        return view('clientes.create', [
            'cliente' => $cliente,
            'linkedCommercialClients' => [],
            'selectedCommercialIds' => [],
            'commercialSearchUrl' => route('comercial.search-clientes'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $commercialIds = $data['commercial_client_ids'] ?? [];
        unset(
            $data['commercial_client_ids'],
            $data['duplicate_confirmed'],
            $data['copy_postal_code_from_commercial'],
            $data['confirm_without_commercial_links']
        );

        $data['users_id'] = auth()->id();

        DB::transaction(function () use ($data, $request, $commercialIds) {
            $cliente = Cliente::create($data);
            $request->merge(['commercial_client_ids' => $commercialIds]);
            $this->syncCommercialClients($cliente, $request, true);
        });

        return redirect()
            ->route('clientes.index')
            ->with('status', 'Cliente creado correctamente.');
    }

    public function edit(Cliente $cliente)
    {
        $this->authorizeOwner($cliente);

        $linkedCommercialClients = $this->linkedCommercialClients($cliente);

        return view('clientes.edit', [
            'cliente' => $cliente,
            'linkedCommercialClients' => $linkedCommercialClients,
            'selectedCommercialIds' => collect($linkedCommercialClients)->pluck('id')->all(),
            'commercialSearchUrl' => route('comercial.search-clientes'),
        ]);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $this->authorizeOwner($cliente);

        $data = $this->validated($request);
        $commercialIds = $data['commercial_client_ids'] ?? [];

        // nunca permitir cambiar el dueño
        unset(
            $data['users_id'],
            $data['commercial_client_ids'],
            $data['duplicate_confirmed'],
            $data['copy_postal_code_from_commercial'],
            $data['confirm_without_commercial_links']
        );

        DB::transaction(function () use ($cliente, $data, $request, $commercialIds) {
            $cliente->update($data);
            $request->merge(['commercial_client_ids' => $commercialIds]);
            $this->syncCommercialClients($cliente, $request, true);
        });

        return redirect()
            ->route('clientes.index')
            ->with('status', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente)
    {
        $this->authorizeOwner($cliente);

        $cliente->delete();

        return redirect()
            ->route('clientes.index')
            ->with('status', 'Cliente eliminado correctamente.');
    }

    private function authorizeOwner(Cliente $cliente): void
    {
        abort_unless((int) $cliente->users_id === (int) auth()->id(), 403);
    }

    public function updateJson(Request $request, $clienteId)
    {
        $userId = auth()->id();

        // Solo permite editar clientes del usuario
        $cliente = DB::table('clientes')
            ->where('id', $clienteId)
            ->where('users_id', $userId)
            ->first();

        if (!$cliente) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        $data = $request->only([
            'rfc','razon_social','email','calle','no_ext','no_int','colonia',
            'localidad','estado','codigo_postal','pais'
        ]);

        DB::table('clientes')
            ->where('id', $clienteId)
            ->update($data);

        $updated = DB::table('clientes')->where('id', $clienteId)->first();

        return response()->json($updated);
    }

    public function searchCommercialClients(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $clients = CommercialClient::query()
            ->forUser((int) auth()->id())
            ->with(['primaryContact' => fn ($query) => $query->orderBy('name')])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('mobile', 'like', "%{$q}%")
                        ->orWhereHas('contacts', function ($contacts) use ($q) {
                            $contacts->where('name', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%")
                                ->orWhere('phone', 'like', "%{$q}%")
                                ->orWhere('mobile', 'like', "%{$q}%");
                        });
                });
            })
            ->orderBy('name')
            ->limit(12)
            ->get();

        return response()->json([
            'data' => $clients->map(fn (CommercialClient $client) => $this->commercialClientPayload($client))->values(),
        ]);
    }

    private function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'rfc'             => ['required', 'string', 'max:30'],
            'razon_social'    => ['required', 'string', 'max:200'],

            'calle'           => ['nullable', 'string', 'max:100'],
            'no_ext'          => ['nullable', 'string', 'max:20'],
            'no_int'          => ['nullable', 'string', 'max:20'],
            'colonia'         => ['nullable', 'string', 'max:50'],
            'municipio'       => ['nullable', 'string', 'max:50'],
            'localidad'       => ['nullable', 'string', 'max:50'],
            'estado'          => ['nullable', 'string', 'max:50'],
            'codigo_postal'   => ['nullable', 'string', 'max:10'],
            'pais'            => ['nullable', 'string', 'max:30'],

            'telefono'        => ['nullable', 'string', 'max:30'],
            'nombre_contacto' => ['nullable', 'string', 'max:150'],
            'email'           => ['nullable', 'email', 'max:90'],

            // en tu tabla es NOT NULL, default ''
            'regimen_fiscal'  => ['required',
            'string',
            'max:5',
            Rule::in(array_keys(config('sat.regimenes_fiscales'))),],
            'commercial_client_ids' => ['nullable', 'array'],
            'commercial_client_ids.*' => ['integer', Rule::exists('commercial_clients', 'id')],
            'copy_postal_code_from_commercial' => ['nullable', 'boolean'],
            'confirm_without_commercial_links' => ['nullable', 'boolean'],
            'duplicate_confirmed' => ['nullable', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->boolean('duplicate_confirmed') || $request->route('cliente')) {
                return;
            }

            $rfc = trim((string) $request->input('rfc'));
            $email = trim((string) $request->input('email'));
            $razonSocial = trim((string) $request->input('razon_social'));
            $telefono = trim((string) $request->input('telefono'));

            $duplicateQuery = Cliente::query()->forUser((int) auth()->id());
            $duplicateQuery->where(function ($query) use ($rfc, $email, $razonSocial, $telefono) {
                if ($rfc !== '') {
                    $query->orWhere('rfc', $rfc);
                }

                if ($email !== '') {
                    $query->orWhere('email', $email);
                }

                if ($razonSocial !== '' && $telefono !== '') {
                    $query->orWhere(function ($sub) use ($razonSocial, $telefono) {
                        $sub->where('razon_social', $razonSocial)->where('telefono', $telefono);
                    });
                }
            });

            if ($duplicateQuery->exists()) {
                $validator->errors()->add('duplicate_confirmed', 'Encontramos un cliente fiscal parecido. Confirma que deseas crear un registro distinto.');
            }
        });

        return $validator->validate();
    }

    private function syncCommercialClients(Cliente $cliente, Request $request, bool $keepExistingDefaults): void
    {
        $selected = collect($request->input('commercial_client_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $currentIds = DB::table('commercial_client_fiscal_client')
            ->join('commercial_clients', 'commercial_clients.id', '=', 'commercial_client_fiscal_client.commercial_client_id')
            ->where('commercial_client_fiscal_client.fiscal_client_id', $cliente->id)
            ->where('commercial_clients.users_id', $cliente->users_id)
            ->pluck('commercial_client_fiscal_client.commercial_client_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($selected->isEmpty()) {
            if ($currentIds->isNotEmpty() && !$request->boolean('confirm_without_commercial_links')) {
                throw ValidationException::withMessages([
                    'confirm_without_commercial_links' => 'Confirma que el cliente fiscal quedara sin clientes comerciales relacionados.',
                ]);
            }

            DB::table('commercial_client_fiscal_client')
                ->where('fiscal_client_id', $cliente->id)
                ->whereIn('commercial_client_id', $currentIds)
                ->delete();
            return;
        }

        $allowed = CommercialClient::query()
            ->forUser((int) $cliente->users_id)
            ->whereIn('id', $selected)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($allowed->count() !== $selected->count()) {
            throw ValidationException::withMessages([
                'commercial_client_ids' => 'Uno o mas clientes comerciales no pertenecen al usuario actual.',
            ]);
        }

        $removeIds = $currentIds->diff($allowed)->values();
        if ($removeIds->isNotEmpty()) {
            DB::table('commercial_client_fiscal_client')
                ->where('fiscal_client_id', $cliente->id)
                ->whereIn('commercial_client_id', $removeIds)
                ->delete();
        }

        foreach ($allowed as $commercialId) {
            $exists = DB::table('commercial_client_fiscal_client')
                ->where('commercial_client_id', $commercialId)
                ->where('fiscal_client_id', $cliente->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $hasDefault = DB::table('commercial_client_fiscal_client')
                ->where('commercial_client_id', $commercialId)
                ->where('is_default', true)
                ->exists();

            DB::table('commercial_client_fiscal_client')->insert([
                'commercial_client_id' => $commercialId,
                'fiscal_client_id' => $cliente->id,
                'is_default' => $keepExistingDefaults ? !$hasDefault : false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function linkedCommercialClients(Cliente $cliente): array
    {
        return CommercialClient::query()
            ->forUser((int) $cliente->users_id)
            ->whereHas('fiscalClients', fn ($query) => $query->where('clientes.id', $cliente->id))
            ->with(['primaryContact' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (CommercialClient $client) => $this->commercialClientPayload($client))
            ->values()
            ->all();
    }

    private function commercialClientPayload(CommercialClient $client): array
    {
        $primary = $client->primaryContact instanceof \Illuminate\Support\Collection
            ? $client->primaryContact->first()
            : null;

        return [
            'id' => (int) $client->id,
            'name' => (string) ($client->name ?? ''),
            'business_name' => (string) ($client->business_name ?? ''),
            'email' => (string) ($client->email ?? ''),
            'phone' => (string) ($client->phone ?: $client->mobile ?: ''),
            'street' => (string) ($client->street ?? ''),
            'exterior_number' => (string) ($client->exterior_number ?? ''),
            'interior_number' => (string) ($client->interior_number ?? ''),
            'neighborhood' => (string) ($client->neighborhood ?? ''),
            'city' => (string) ($client->city ?? ''),
            'state' => (string) ($client->state ?? ''),
            'country' => (string) ($client->country ?? ''),
            'postal_code' => (string) ($client->postal_code ?? ''),
            'primary_contact' => $primary ? [
                'name' => (string) ($primary->name ?? ''),
                'email' => (string) ($primary->email ?? ''),
                'phone' => (string) ($primary->phone ?: $primary->mobile ?: ''),
            ] : null,
        ];
    }
}
