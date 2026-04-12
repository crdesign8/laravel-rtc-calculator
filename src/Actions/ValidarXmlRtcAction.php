<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Actions;

use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;

class ValidarXmlRtcAction
{
    public function __construct(
        private readonly RtcClientContract $client,
    ) {}

    public function handle(string $xml): bool
    {
        return $this->client->validarXml($xml);
    }
}
