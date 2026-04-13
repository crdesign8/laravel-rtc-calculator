<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Tests\Feature;

use Crdesign8\LaravelRtcCalculator\DTOs\ItemDTO;
use Crdesign8\LaravelRtcCalculator\Enums\UnidadeMedida;
use Crdesign8\LaravelRtcCalculator\Rtc;
use Crdesign8\LaravelRtcCalculator\Tests\TestCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class RtcBuilderStateTest extends TestCase
{
    private array $saida;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saida = json_decode(
            file_get_contents(__DIR__.'/../Fixtures/saida-regime-geral.json'),
            associative: true,
        );
    }

    public function test_builder_e_resetado_apos_calcular_em_singleton(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $rtc = app(Rtc::class);

        $rtc
            ->paraFiscal(4314902, 'RS')
            ->addItem($this->makeItem(numero: 1, ncm: '24021000'))
            ->calcular();

        try {
            $rtc->paraFiscal(4314902, 'RS')->calcular();
            $this->fail('Era esperado InvalidArgumentException sem itens após reset do builder.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        Http::assertSentCount(1);
    }

    public function test_segundo_calculo_nao_reaproveita_itens_do_primeiro(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $rtc = app(Rtc::class);

        $rtc
            ->paraFiscal(4314902, 'RS')
            ->addItem($this->makeItem(numero: 1, ncm: '24021000'))
            ->calcular();

        $rtc
            ->paraFiscal(4314902, 'RS')
            ->addItem($this->makeItem(numero: 2, ncm: '22030000'))
            ->calcular();

        /** @var Collection<int, array{0: \Illuminate\Http\Client\Request, 1: \Illuminate\Http\Client\Response|null}> $recorded */
        $recorded = Http::recorded();

        $payloads = $recorded->map(
            static fn (array $requestPair): array => $requestPair[0]->data(),
        )->values();

        $this->assertCount(2, $payloads);
        $this->assertSame('24021000', $payloads[0]['itens'][0]['ncm']);
        $this->assertCount(1, $payloads[1]['itens']);
        $this->assertSame('22030000', $payloads[1]['itens'][0]['ncm']);
    }

    private function makeItem(int $numero, string $ncm): ItemDTO
    {
        return ItemDTO::make($numero)
            ->ncm($ncm)
            ->quantidade(1)
            ->unidade(UnidadeMedida::VN)
            ->cst('550')
            ->baseCalculo(100.0)
            ->cClassTrib('550020');
    }
}
