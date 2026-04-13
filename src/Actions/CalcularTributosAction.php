<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Actions;

use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;

class CalcularTributosAction
{
    public function __construct(
        private readonly RtcClientContract $client,
    ) {}

    public function handle(CalculoRequestDTO $dto): CalculoResult
    {
        $dto->validate();

        return $this->client->calcularRegimeGeral($dto);
    }
}
