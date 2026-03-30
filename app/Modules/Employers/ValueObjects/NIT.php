<?php

namespace App\Modules\Employers\ValueObjects;

// DOCUMENTO_RECTOR §2.3 — algoritmo módulo 11 (pesos equivalentes a la secuencia 71…3 de izquierda a derecha)

final readonly class NIT
{
    /** @var list<int> Aplicados de derecha a izquierda sobre el cuerpo sin DV. */
    private const WEIGHTS_RIGHT_TO_LEFT = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

    public static function calcularDigito(string $nitSinDigitoVerificacion): string
    {
        $digitsOnly = preg_replace('/\D+/', '', $nitSinDigitoVerificacion) ?? '';
        $reversed = str_split(strrev($digitsOnly));
        $sum = 0;
        foreach ($reversed as $i => $ch) {
            $sum += (int) $ch * (self::WEIGHTS_RIGHT_TO_LEFT[$i] ?? 0);
        }
        $r = $sum % 11;
        $dv = $r < 2 ? $r : 11 - $r;

        return (string) $dv;
    }

    public static function validarCuerpoYDigito(string $nitCompleto): bool
    {
        $clean = preg_replace('/\D+/', '', $nitCompleto) ?? '';
        if (strlen($clean) < 2) {
            return false;
        }
        $cuerpo = substr($clean, 0, -1);
        $dv = substr($clean, -1);

        return self::calcularDigito($cuerpo) === $dv;
    }
}
