<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Events;

use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado após cada cálculo bem-sucedido de tributos RTC.
 *
 * Permite que a aplicação hosteante reaja ao resultado sem
 * acoplar lógica ao pacote (logging, auditoria, cache etc.).
 *
 * Exemplo de listener:
 *
 *   Event::listen(RtcCalculated::class, function (RtcCalculated $event) {
 *       Log::info('RTC calculado', [
 *           'municipio' => $event->dto->getMunicipio(),
 *           'itens'     => count($event->dto->getItens()),
 *           'vIsTot'    => $event->result->getTotal()->getVIsTot(),
 *           'vCbsTot'   => $event->result->getTotal()->getVCbsTot(),
 *       ]);
 *   });
 */
class RtcCalculated
{
    use Dispatchable;

    public function __construct(
        /**
         * DTO de entrada enviado à calculadora.
         */
        public readonly CalculoRequestDTO $dto,

        /**
         * Resultado completo retornado pela calculadora.
         */
        public readonly CalculoResult $result,
    ) {}
}
