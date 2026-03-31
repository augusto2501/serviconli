<?php

namespace Tests\Unit\Modules\Billing;

use App\Modules\Billing\Services\NumberToWordsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NumberToWordsServiceTest extends TestCase
{
    private NumberToWordsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NumberToWordsService;
    }

    #[DataProvider('conversionCases')]
    public function test_converts_pesos_to_words(int $input, string $expected): void
    {
        $this->assertSame($expected, $this->service->convert($input));
    }

    public static function conversionCases(): array
    {
        return [
            'cero' => [0, 'CERO PESOS M/CTE'],
            'uno' => [1, 'UN PESOS M/CTE'],
            'quince' => [15, 'QUINCE PESOS M/CTE'],
            'veinte' => [20, 'VEINTE PESOS M/CTE'],
            'veintiuno' => [21, 'VEINTIUN PESOS M/CTE'],
            'cien' => [100, 'CIEN PESOS M/CTE'],
            'ciento uno' => [101, 'CIENTO UN PESOS M/CTE'],
            'quinientos' => [500, 'QUINIENTOS PESOS M/CTE'],
            'mil' => [1000, 'MIL PESOS M/CTE'],
            'dos mil trescientos' => [2_300, 'DOS MIL TRESCIENTOS PESOS M/CTE'],
            'diez mil' => [10_000, 'DIEZ MIL PESOS M/CTE'],
            'cien mil' => [100_000, 'CIEN MIL PESOS M/CTE'],
            'un millon' => [1_000_000, 'UN MILLÓN PESOS M/CTE'],
            'monto tipico aporte' => [
                1_234_567,
                'UN MILLÓN DOSCIENTOS TREINTA Y CUATRO MIL QUINIENTOS SESENTA Y SIETE PESOS M/CTE',
            ],
            'cinco millones' => [5_000_000, 'CINCO MILLONES PESOS M/CTE'],
        ];
    }
}
