<?php

namespace App\Modules\PILALiquidation\Services;

/**
 * Normalización de caracteres para archivo PILA — RN-21.
 *
 * Ñ→N, tildes→sin tilde, ANSI encoding.
 * Portado de Access Convertidor_ARUS NormalizarTexto.
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 8 paso 6
 */
final class PILACharNormalizer
{
    private const REPLACEMENTS = [
        'Ñ' => 'N', 'ñ' => 'N',
        'Á' => 'A', 'á' => 'A', 'À' => 'A', 'à' => 'A', 'Ä' => 'A', 'ä' => 'A',
        'É' => 'E', 'é' => 'E', 'È' => 'E', 'è' => 'E', 'Ë' => 'E', 'ë' => 'E',
        'Í' => 'I', 'í' => 'I', 'Ì' => 'I', 'ì' => 'I', 'Ï' => 'I', 'ï' => 'I',
        'Ó' => 'O', 'ó' => 'O', 'Ò' => 'O', 'ò' => 'O', 'Ö' => 'O', 'ö' => 'O',
        'Ú' => 'U', 'ú' => 'U', 'Ù' => 'U', 'ù' => 'U', 'Ü' => 'U', 'ü' => 'U',
    ];

    public function normalize(string $text): string
    {
        $text = strtr($text, self::REPLACEMENTS);
        $text = strtoupper($text);

        return preg_replace('/[^\x20-\x7E]/', '', $text) ?? $text;
    }

    /** Pad derecho con espacios hasta $length. */
    public function padRight(string $value, int $length): string
    {
        return str_pad(mb_substr($this->normalize($value), 0, $length), $length);
    }

    /** Pad izquierdo con ceros hasta $length. */
    public function padZero(int|string $value, int $length): string
    {
        return str_pad((string) $value, $length, '0', STR_PAD_LEFT);
    }

    /** Convierte string a ANSI (Windows-1252). */
    public function toAnsi(string $content): string
    {
        return mb_convert_encoding($content, 'Windows-1252', 'UTF-8');
    }
}
