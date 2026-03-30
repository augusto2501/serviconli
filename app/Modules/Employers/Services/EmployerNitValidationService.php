<?php

namespace App\Modules\Employers\Services;

// RF-024–027

use App\Modules\Employers\ValueObjects\NIT;
use InvalidArgumentException;

final class EmployerNitValidationService
{
    /**
     * @param  array{nit_body: string, digito_verificacion: int|string, razon_social: string}  $data
     * @return array{nit_body: string, digito_verificacion: int, razon_social: string}
     */
    public function assertValid(array $data): array
    {
        $body = preg_replace('/\D+/', '', (string) $data['nit_body']) ?? '';
        $dv = (int) $data['digito_verificacion'];
        $expected = (int) NIT::calcularDigito($body);
        if ($expected !== $dv) {
            throw new InvalidArgumentException('NIT inválido: dígito de verificación no coincide con módulo 11.');
        }

        return [
            'nit_body' => $body,
            'digito_verificacion' => $dv,
            'razon_social' => trim((string) $data['razon_social']),
        ];
    }
}
