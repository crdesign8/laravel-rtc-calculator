<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Actions;

use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\Enums\TipoDocumento;

class GerarXmlRtcAction
{
    public function __construct(
        private readonly RtcClientContract $client,
    ) {}

    public function handle(CalculoResult $result, TipoDocumento $tipo = TipoDocumento::NFe): string
    {
        return $this->client->gerarXml($result, $tipo);
    }
}
