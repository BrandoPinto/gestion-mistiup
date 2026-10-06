<?php

namespace Tests\Unit\Clients;

use App\Rules\ValidRuc;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidRucTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function rucs(): array
    {
        return [
            'empresa válida (SUNAT)' => ['20131312955', true],
            'persona con negocio válida' => ['10467793549', true],
            'dígito verificador incorrecto' => ['20131312956', false],
            'prefijo inexistente' => ['30131312955', false],
            'menos de 11 dígitos' => ['2013131295', false],
            'con letras' => ['2013131295A', false],
            'vacío' => ['', false],
        ];
    }

    #[DataProvider('rucs')]
    public function test_validates_ruc_format_and_check_digit(string $ruc, bool $expected): void
    {
        $this->assertSame($expected, ValidRuc::passes($ruc));
    }
}
