<?php

namespace Crdesign8\LaravelRtcCalculator\Tests\Feature;

use Crdesign8\LaravelRtcCalculator\Tests\TestCase;
use Crdesign8\LaravelRtcCalculator\Actions\GerarXmlRtcAction;
use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\Enums\TipoDocumento;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcCalculationException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;
use Illuminate\Support\Facades\Http;

class GerarXmlTest extends TestCase
{
    private array $saida;
    private string $xmlEsperado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saida       = json_decode(file_get_contents(__DIR__.'/../Fixtures/saida-regime-geral.json'), associative: true);
        $this->xmlEsperado = file_get_contents(__DIR__.'/../Fixtures/saida-gerar-xml.xml');
    }

    private function makeResult(): CalculoResult
    {
        return CalculoResult::fromArray($this->saida);
    }

    private function action(): GerarXmlRtcAction
    {
        return new GerarXmlRtcAction($this->app->make(RtcClientContract::class));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Sucesso
    // ──────────────────────────────────────────────────────────────────────────

    public function test_gera_xml_retorna_string(): void
    {
        Http::fake([
            '*/api/calculadora/xml/generate*' => Http::response($this->xmlEsperado, 200, ['Content-Type' => 'application/xml']),
        ]);

        $xml = $this->action()->handle($this->makeResult());

        $this->assertIsString($xml);
        $this->assertNotEmpty($xml);
    }

    public function test_xml_retornado_contem_bloco_is(): void
    {
        Http::fake([
            '*/api/calculadora/xml/generate*' => Http::response($this->xmlEsperado, 200, ['Content-Type' => 'application/xml']),
        ]);

        $xml = $this->action()->handle($this->makeResult());

        $this->assertStringContainsString('<IS>', $xml);
        $this->assertStringContainsString('</IS>', $xml);
    }

    public function test_xml_retornado_contem_bloco_ibscbs(): void
    {
        Http::fake([
            '*/api/calculadora/xml/generate*' => Http::response($this->xmlEsperado, 200, ['Content-Type' => 'application/xml']),
        ]);

        $xml = $this->action()->handle($this->makeResult());

        $this->assertStringContainsString('<IBSCBS>', $xml);
        $this->assertStringContainsString('</IBSCBS>', $xml);
    }

    public function test_tipo_nfe_enviado_como_query_param(): void
    {
        Http::fake([
            '*/api/calculadora/xml/generate*' => Http::response($this->xmlEsperado, 200, ['Content-Type' => 'application/xml']),
        ]);

        $this->action()->handle($this->makeResult(), TipoDocumento::NFe);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'tipo=NFe');
        });
    }

    public function test_tipo_cte_enviado_como_query_param(): void
    {
        Http::fake([
            '*/api/calculadora/xml/generate*' => Http::response($this->xmlEsperado, 200, ['Content-Type' => 'application/xml']),
        ]);

        $this->action()->handle($this->makeResult(), TipoDocumento::CTe);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'tipo=CTe');
        });
    }

    public function test_tipo_padrao_e_nfe(): void
    {
        Http::fake([
            '*/api/calculadora/xml/generate*' => Http::response($this->xmlEsperado, 200, ['Content-Type' => 'application/xml']),
        ]);

        // Sem especificar tipo → deve usar NFe
        $this->action()->handle($this->makeResult());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'tipo=NFe');
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tratamento de erros HTTP
    // ──────────────────────────────────────────────────────────────────────────

    public function test_resposta_422_lanca_rtc_validation_exception(): void
    {
        Http::fake([
            '*/api/calculadora/xml/generate*' => Http::response(['error' => 'estrutura inválida'], 422),
        ]);

        $this->expectException(RtcValidationException::class);

        $this->action()->handle($this->makeResult());
    }

    public function test_resposta_500_lanca_rtc_calculation_exception(): void
    {
        Http::fake([
            '*/api/calculadora/xml/generate*' => Http::response('erro interno', 500),
        ]);

        $this->expectException(RtcCalculationException::class);

        $this->action()->handle($this->makeResult());
    }
}
