<?php

namespace App\Modules\Affiliates\Controllers;

// RF-015 — credenciales de portales; almacenamiento en claro por defecto, cifrado vía config

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Enums\PortalCredentialPortalType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\PortalCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PortalCredentialController extends Controller
{
    public function index(Affiliate $affiliate): JsonResponse
    {
        $this->authorize('view', $affiliate);

        $rows = PortalCredential::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderBy('portal_type')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (PortalCredential $c): array => $this->toArray($c))->all(),
        ]);
    }

    public function store(Request $request, Affiliate $affiliate): JsonResponse
    {
        $this->authorize('update', $affiliate);

        $validated = $request->validate([
            'portal_type' => ['required', 'string', Rule::enum(PortalCredentialPortalType::class)],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:65535'],
        ]);

        $cred = PortalCredential::query()->firstOrNew([
            'affiliate_id' => $affiliate->id,
            'portal_type' => $validated['portal_type'],
        ]);

        foreach (['username', 'password', 'notes'] as $field) {
            if (array_key_exists($field, $validated)) {
                $cred->{$field} = $validated[$field];
            }
        }

        $wasNew = ! $cred->exists;
        $cred->save();

        return response()->json($this->toArray($cred->fresh()), $wasNew ? 201 : 200);
    }

    public function update(Request $request, Affiliate $affiliate, PortalCredential $portal_credential): JsonResponse
    {
        $this->authorize('update', $affiliate);
        $this->ensureBelongsToAffiliate($affiliate, $portal_credential);

        $validated = $request->validate([
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:65535'],
        ]);

        $portal_credential->fill($validated);
        $portal_credential->save();

        return response()->json($this->toArray($portal_credential->fresh()));
    }

    public function destroy(Affiliate $affiliate, PortalCredential $portal_credential): JsonResponse
    {
        $this->authorize('update', $affiliate);
        $this->ensureBelongsToAffiliate($affiliate, $portal_credential);

        $portal_credential->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function toArray(PortalCredential $c): array
    {
        $type = $c->portal_type;

        return [
            'id' => $c->id,
            'affiliateId' => $c->affiliate_id,
            'portalType' => $type instanceof PortalCredentialPortalType ? $type->value : (string) $type,
            'username' => $c->username,
            'password' => $c->password,
            'notes' => $c->notes,
            'updatedAt' => $c->updated_at?->toIso8601String(),
        ];
    }

    private function ensureBelongsToAffiliate(Affiliate $affiliate, PortalCredential $portal_credential): void
    {
        if ($portal_credential->affiliate_id !== $affiliate->id) {
            abort(404);
        }
    }
}
