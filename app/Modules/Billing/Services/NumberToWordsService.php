<?php

namespace App\Modules\Billing\Services;

/**
 * Convierte un monto en pesos enteros a letras en español colombiano.
 *
 * Ejemplo: 1_234_567 → "UN MILLÓN DOSCIENTOS TREINTA Y CUATRO MIL QUINIENTOS SESENTA Y SIETE PESOS M/CTE"
 *
 * Soporta hasta 999.999.999.999 (billones no requeridos).
 *
 * @see DOCUMENTO_RECTOR §5 BC-11 Documents
 */
final class NumberToWordsService
{
    private const UNITS = [
        '', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO',
        'SEIS', 'SIETE', 'OCHO', 'NUEVE',
    ];

    private const TENS_SPECIAL = [
        10 => 'DIEZ', 11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE',
        14 => 'CATORCE', 15 => 'QUINCE',
    ];

    private const TENS_PREFIX = [
        2 => 'VEINTI', 3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA',
        6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA',
    ];

    private const HUNDREDS = [
        1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS',
        5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS',
        9 => 'NOVECIENTOS',
    ];

    /**
     * @param  int  $pesos  Monto en pesos (entero, sin centavos)
     */
    public function convert(int $pesos): string
    {
        if ($pesos === 0) {
            return 'CERO PESOS M/CTE';
        }

        if ($pesos < 0) {
            return 'MENOS '.$this->convert(abs($pesos));
        }

        $result = $this->intToWords($pesos);

        return trim($result).' PESOS M/CTE';
    }

    private function intToWords(int $n): string
    {
        if ($n === 0) {
            return '';
        }

        if ($n === 1) {
            return 'UN';
        }

        if ($n < 10) {
            return self::UNITS[$n];
        }

        if ($n <= 15) {
            return self::TENS_SPECIAL[$n];
        }

        if ($n < 20) {
            return 'DIECI'.self::UNITS[$n - 10];
        }

        if ($n === 20) {
            return 'VEINTE';
        }

        if ($n < 30) {
            return 'VEINTI'.self::UNITS[$n - 20];
        }

        if ($n < 100) {
            $tens = intdiv($n, 10);
            $units = $n % 10;
            if ($units === 0) {
                return self::TENS_PREFIX[$tens];
            }

            return self::TENS_PREFIX[$tens].' Y '.self::UNITS[$units];
        }

        if ($n === 100) {
            return 'CIEN';
        }

        if ($n < 1000) {
            $hundreds = intdiv($n, 100);
            $remainder = $n % 100;

            return self::HUNDREDS[$hundreds].($remainder > 0 ? ' '.$this->intToWords($remainder) : '');
        }

        if ($n < 1_000_000) {
            $thousands = intdiv($n, 1000);
            $remainder = $n % 1000;

            $prefix = $thousands === 1 ? 'MIL' : $this->intToWords($thousands).' MIL';

            return $prefix.($remainder > 0 ? ' '.$this->intToWords($remainder) : '');
        }

        if ($n < 1_000_000_000) {
            $millions = intdiv($n, 1_000_000);
            $remainder = $n % 1_000_000;

            $prefix = $millions === 1 ? 'UN MILLÓN' : $this->intToWords($millions).' MILLONES';

            return $prefix.($remainder > 0 ? ' '.$this->intToWords($remainder) : '');
        }

        $billions = intdiv($n, 1_000_000_000);
        $remainder = $n % 1_000_000_000;

        $prefix = $billions === 1 ? 'MIL MILLONES' : $this->intToWords($billions).' MIL MILLONES';

        return $prefix.($remainder > 0 ? ' '.$this->intToWords($remainder) : '');
    }
}
