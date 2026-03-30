<?php

namespace Tests\Unit\Modules\Employers;

use App\Modules\Employers\ValueObjects\NIT;
use PHPUnit\Framework\TestCase;

class NITTest extends TestCase
{
    public function test_calcular_digito_ejemplo_documento_rector(): void
    {
        $this->assertSame('4', NIT::calcularDigito('900966567'));
    }

    public function test_validar_cuerpo_y_digito(): void
    {
        $this->assertTrue(NIT::validarCuerpoYDigito('9009665674'));
        $this->assertTrue(NIT::validarCuerpoYDigito('900966567-4'));
        $this->assertFalse(NIT::validarCuerpoYDigito('9009665675'));
    }
}
