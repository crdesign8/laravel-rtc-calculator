<?php

namespace Crdesign8\LaravelRtcCalculator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Crdesign8\LaravelRtcCalculator\Rtc make()
 * @method static \Crdesign8\LaravelRtcCalculator\Data\CalculoResult calcular(\Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO $dto)
 * @method static string gerarXml(\Crdesign8\LaravelRtcCalculator\Data\CalculoResult $result, \Crdesign8\LaravelRtcCalculator\Enums\TipoDocumento $tipo = \Crdesign8\LaravelRtcCalculator\Enums\TipoDocumento::NFe)
 * @method static bool validarXml(string $xml)
 * @method static string injetarNfe(string $xmlRtc, string $xmlNfe)
 * @method static \Crdesign8\LaravelRtcCalculator\Rtc paraFiscal(int $municipio, string $uf)
 * @method static \Crdesign8\LaravelRtcCalculator\Rtc emitidoEm(string $dataHoraEmissao)
 * @method static \Crdesign8\LaravelRtcCalculator\Rtc addItem(\Crdesign8\LaravelRtcCalculator\DTOs\ItemDTO $item)
 *
 * @see \Crdesign8\LaravelRtcCalculator\Rtc
 */
class Rtc extends Facade
{
    /**
     * Retorna o nome de registro do componente no container.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'rtc';
    }
}
