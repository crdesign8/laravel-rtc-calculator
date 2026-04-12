<?php

namespace Crdesign8\LaravelRtcCalculator\Tests\Feature;

use Crdesign8\LaravelRtcCalculator\Tests\TestCase;
use Crdesign8\LaravelRtcCalculator\Actions\CalcularTributosAction;
use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcCalculationException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;
use Illuminate\Support\Facades\Http;

class CalcularTributosTest extends TestCase
{
    private array $saida;
    private array $entrada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saida   = json_decode(file_get_contents(__DIR__.'/../Fixtures/saida-regime-geral.json'), associative: true);
        $this->entrada = json_decode(file_get_contents(__DIR__.'/../Fixtures/entrada-regime-geral.json'), associative: true);
    }

    private function makeDto(): CalculoRequestDTO
    {
        return CalculoRequestDTO::fromArray($this->entrada);
    }

    private function action(): CalcularTributosAction
    {
        return new CalcularTributosAction($this->app->make(RtcClientContract::class));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Sucesso
    // ──────────────────────────────────────────────────────────────────────────

    public function test_calculo_bem_sucedido_retorna_calculo_result(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $result = $this->action()->handle($this->makeDto());

        $this->assertInstanceOf(CalculoResult::class, $result);
    }

    public function test_resultado_contem_um_objeto(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $result = $this->action()->handle($this->makeDto());

        $this->assertCount(1, $result->getObjetos());
        $this->assertSame(1, $result->getObjetos()[0]->getNObj());
    }

    public function test_resultado_contem_totais_corretos(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $totais = $this->action()->handle($this->makeDto())->getTotal();

        $this->assertSame('0.00', $totais->getVIsTot());
        $this->assertSame('5984.03', $totais->getVBcIbsCbs());
        $this->assertSame('0.00', $totais->getVIbsTot());
        $this->assertSame('0.00', $totais->getVCbsTot());
    }

    public function test_get_item_retorna_item_por_n_obj(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $item = $this->action()->handle($this->makeDto())->getItem(1);

        $this->assertNotNull($item);
        $this->assertSame('000', $item->getCstIs());
        $this->assertSame('1111.00', $item->getVBcIs());
        $this->assertSame('0.00', $item->getVIs());
    }

    public function test_get_item_retorna_null_para_n_obj_inexistente(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $item = $this->action()->handle($this->makeDto())->getItem(99);

        $this->assertNull($item);
    }

    public function test_to_json_retorna_string_json_valida(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $json = $this->action()->handle($this->makeDto())->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, associative: true);
        $this->assertArrayHasKey('objetos', $decoded);
        $this->assertArrayHasKey('total', $decoded);
    }

    public function test_to_array_roundtrip_preserva_dados_brutos(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $result = $this->action()->handle($this->makeDto());

        $this->assertSame($this->saida, $result->toArray());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tratamento de erros HTTP
    // ──────────────────────────────────────────────────────────────────────────

    public function test_resposta_422_lanca_rtc_validation_exception(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response(['error' => 'campo inválido'], 422),
        ]);

        $this->expectException(RtcValidationException::class);

        $this->action()->handle($this->makeDto());
    }

    public function test_resposta_400_lanca_rtc_validation_exception(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response(['error' => 'requisição inválida'], 400),
        ]);

        $this->expectException(RtcValidationException::class);

        $this->action()->handle($this->makeDto());
    }

    public function test_resposta_500_lanca_rtc_calculation_exception(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response(['error' => 'erro interno'], 500),
        ]);

        $this->expectException(RtcCalculationException::class);

        $this->action()->handle($this->makeDto());
    }
}
