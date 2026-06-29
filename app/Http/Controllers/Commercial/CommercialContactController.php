<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommercialContactRequest;
use App\Http\Requests\UpdateCommercialContactRequest;
use App\Models\CommercialClient;
use App\Models\CommercialContact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommercialContactController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $contacts = CommercialContact::query()
            ->with('commercialClient')
            ->whereHas('commercialClient', function ($query) use ($request) {
                if (!$this->isAdmin($request->user())) {
                    $query->where('users_id', $request->user()->id)
                        ->orWhere('assigned_user_id', $request->user()->id);
                }
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('mobile', 'like', "%{$q}%")
                        ->orWhereHas('commercialClient', fn ($client) => $client->where('name', 'like', "%{$q}%"));
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('comercial.contactos.index', compact('contacts', 'q'));
    }

    public function store(StoreCommercialContactRequest $request, CommercialClient $commercialClient)
    {
        $this->authorize('update', $commercialClient);

        DB::transaction(function () use ($request, $commercialClient) {
            $payload = $this->contactPayload($request->validated());
            if ($payload['is_primary'] && $payload['is_active']) {
                $commercialClient->contacts()->where('is_primary', true)->update(['is_primary' => false]);
            }

            $commercialClient->contacts()->create($payload);
        });

        return redirect()
            ->route('comercial.clientes.show', $commercialClient)
            ->with('status', 'Contacto creado correctamente.');
    }

    public function edit(CommercialClient $commercialClient, CommercialContact $commercialContact)
    {
        $this->authorizeContact($commercialClient, $commercialContact);

        return view('comercial.contactos.edit', compact('commercialClient', 'commercialContact'));
    }

    public function update(UpdateCommercialContactRequest $request, CommercialClient $commercialClient, CommercialContact $commercialContact)
    {
        $this->authorizeContact($commercialClient, $commercialContact);

        DB::transaction(function () use ($request, $commercialClient, $commercialContact) {
            $payload = $this->contactPayload($request->validated());
            if ($payload['is_primary'] && $payload['is_active']) {
                $commercialClient->contacts()
                    ->where('id', '!=', $commercialContact->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $commercialContact->update($payload);
        });

        return redirect()
            ->route('comercial.clientes.show', $commercialClient)
            ->with('status', 'Contacto actualizado correctamente.');
    }

    public function destroy(CommercialClient $commercialClient, CommercialContact $commercialContact)
    {
        $this->authorizeContact($commercialClient, $commercialContact);

        $commercialContact->delete();

        return redirect()
            ->route('comercial.clientes.show', $commercialClient)
            ->with('status', 'Contacto eliminado correctamente.');
    }

    private function authorizeContact(CommercialClient $commercialClient, CommercialContact $commercialContact): void
    {
        abort_unless((int) $commercialContact->commercial_client_id === (int) $commercialClient->id, 404);
        $this->authorize('update', $commercialClient);
    }

    private function contactPayload(array $data): array
    {
        return [
            'name' => $data['name'],
            'position' => $data['position'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'receives_quotes' => array_key_exists('receives_quotes', $data) ? (bool) $data['receives_quotes'] : true,
            'receives_documents' => (bool) ($data['receives_documents'] ?? false),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function isAdmin(User $user): bool
    {
        $role = strtoupper((string) ($user->rol ?? ''));

        return (int) ($user->admin ?? 0) === 1 || str_contains($role, 'ADMIN');
    }
}
