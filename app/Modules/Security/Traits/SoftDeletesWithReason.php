<?php

namespace App\Modules\Security\Traits;

use Illuminate\Support\Facades\Auth;

/**
 * RF-112 — soft delete con motivo y usuario obligatorio.
 *
 * Extiende SoftDeletes para registrar quién eliminó y por qué.
 * El modelo DEBE usar `use SoftDeletes` además de este trait.
 *
 * @see DOCUMENTO_RECTOR §14.4
 */
trait SoftDeletesWithReason
{
    /**
     * @param  string  $reason  Motivo de la eliminación (causal + texto libre).
     * @param  int|null  $userId  ID del usuario que autoriza. Si null, usa Auth::id().
     */
    public function softDeleteWithReason(string $reason, ?int $userId = null): bool
    {
        $this->deleted_reason = $reason;
        $this->deleted_by = $userId ?? Auth::id();
        $this->save();

        return $this->delete() ?? false;
    }
}
