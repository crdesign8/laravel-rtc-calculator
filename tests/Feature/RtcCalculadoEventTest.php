<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Tests\Feature;

use Crdesign8\LaravelRtcCalculator\Events\RtcCalculated;
use Crdesign8\LaravelRtcCalculator\Rtc;
use Crdesign8\LaravelRtcCalculator\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class RtcCalculadoEventTest extends TestCase
{
    private array $saida;
    private array $entrada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saida = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/saida-regime-geral.json'),
            associative: true,
        );
        $this->entrada = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/entrada-regime-geral.json'),
            associative: true,
        );
    }

    public function test_evento_rtc_calculated_e_disparado_apos_calcular(): void
    {
        Event::fake();
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $dto = \Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO::fromArray($this->entrada);
        app(Rtc::class)->executarCalculo($dto);

        Event::assertDispatched(RtcCalculated::class);
    }

    public function test_evento_contem_dto_e_result_corretos(): void
    {
        Event::fake();
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $dto = \Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO::fromArray($this->entrada);
        app(Rtc::class)->executarCalculo($dto);

        Event::assertDispatched(RtcCalculated::class, function (RtcCalculated $event) use ($dto) {
            return (
                $event->dto->getMunicipio() === $dto->getMunicipio()
                && $event->result->getTotal()->getVBcIbsCbs() === '5984.03'
            );
        });
    }

    public function test_evento_rtc_calculated_e_disparado_apos_calcular_fluente(): void
    {
        Event::fake();
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $dto = \Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO::fromArray($this->entrada);
        // calcular() usa o builder fluente internamente
        app(Rtc::class)->executarCalculo($dto);

        Event::assertDispatched(RtcCalculated::class, 1);
    }
}
