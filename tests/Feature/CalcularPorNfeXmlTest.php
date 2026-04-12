<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Tests\Feature;

use Crdesign8\LaravelRtcCalculator\Actions\CalcularPorNfeXmlAction;
use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;
use Crdesign8\LaravelRtcCalculator\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class CalcularPorNfeXmlTest extends TestCase
{
    private array $saida;
    private string $xmlNfe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saida = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/saida-regime-geral.json'),
            associative: true,
        );
        $this->xmlNfe = file_get_contents(__DIR__ . '/../Fixtures/nfe-sem-rtc.xml');
    }

    private function action(): CalcularPorNfeXmlAction
    {
        return new CalcularPorNfeXmlAction($this->app->make(RtcClientContract::class));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Sucesso
    // ──────────────────────────────────────────────────────────────────────────

    public function test_calcula_a_partir_de_xml_nfe(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $result = $this->action()->handle(xmlNfe: $this->xmlNfe, rtcPorItem: [
            1 => ['cst' => '200', 'cClassTrib' => '200032'],
        ]);

        $this->assertInstanceOf(CalculoResult::class, $result);
    }

    public function test_extrai_municipio_uf_e_data_do_xml(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        // Verifica que a requisição enviada contém os valores extraídos do XML da NFe
        $this->action()->handle(xmlNfe: $this->xmlNfe, rtcPorItem: [1 => ['cst' => '200', 'cClassTrib' => '200032']]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return (
                $body['municipio'] === 2111300 // cMunFG da NFe fixture
                && $body['uf'] === 'MA' // UF do emitente
                && $body['dataHoraEmissao'] === '2025-09-01T00:00:00-03:00' // dhEmi
            );
        });
    }

    public function test_extrai_ncm_e_quantidade_do_item(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $this->action()->handle(xmlNfe: $this->xmlNfe, rtcPorItem: [1 => ['cst' => '200', 'cClassTrib' => '200032']]);

        Http::assertSent(function ($request) {
            $item = $request->data()['itens'][0] ?? null;

            return (
                $item !== null
                && $item['ncm'] === '76071120' // NCM do item 1 da fixture
                && $item['quantidade'] === 1.0
                && $item['baseCalculo'] === 2.0 // vProd da fixture
            );
        });
    }

    public function test_aplica_tributacao_regular_e_is_quando_informados(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $this->action()->handle(xmlNfe: $this->xmlNfe, rtcPorItem: [
            1 => [
                'cst' => '550',
                'cClassTrib' => '550020',
                'tributacaoRegular' => ['cst' => '200', 'cClassTrib' => '200032'],
            ],
        ]);

        Http::assertSent(function ($request) {
            $item = $request->data()['itens'][0] ?? null;

            return (
                $item !== null
                && $item['cst'] === '550'
                && isset($item['tributacaoRegular'])
                && $item['tributacaoRegular']['cst'] === '200'
            );
        });
    }

    public function test_resultado_contem_totais(): void
    {
        Http::fake([
            '*/api/calculadora/regime-geral' => Http::response($this->saida, 200),
        ]);

        $result = $this->action()->handle(xmlNfe: $this->xmlNfe, rtcPorItem: [1 => [
            'cst' => '200',
            'cClassTrib' => '200032',
        ]]);

        $this->assertSame('5984.03', $result->getTotal()->getVBcIbsCbs());
        $this->assertSame('0.00', $result->getTotal()->getVIsTot());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Erros de validação
    // ──────────────────────────────────────────────────────────────────────────

    public function test_lanca_excecao_se_xml_invalido(): void
    {
        $this->expectException(RtcValidationException::class);

        $this->action()->handle(xmlNfe: 'isto-nao-e-xml', rtcPorItem: [1 => [
            'cst' => '200',
            'cClassTrib' => '200032',
        ]]);
    }

    public function test_lanca_excecao_se_xml_sem_cMunFG(): void
    {
        $xmlSemMunicipio = preg_replace('/<cMunFG>[^<]+<\/cMunFG>/', '', $this->xmlNfe);

        $this->expectException(RtcValidationException::class);
        $this->expectExceptionMessageMatches('/municipio/');

        $this->action()->handle(xmlNfe: $xmlSemMunicipio, rtcPorItem: [1 => [
            'cst' => '200',
            'cClassTrib' => '200032',
        ]]);
    }

    public function test_lanca_excecao_se_item_nao_informado_em_rtcPorItem(): void
    {
        $this->expectException(RtcValidationException::class);
        $this->expectExceptionMessageMatches('/nItem=1/');

        $this->action()->handle(xmlNfe: $this->xmlNfe, rtcPorItem: []);
    }

    public function test_lanca_excecao_se_cst_ausente(): void
    {
        $this->expectException(RtcValidationException::class);
        $this->expectExceptionMessageMatches('/cst/');

        $this->action()->handle(xmlNfe: $this->xmlNfe, rtcPorItem: [1 => ['cClassTrib' => '200032']]); // cst ausente
    }

    public function test_lanca_excecao_se_cClassTrib_ausente(): void
    {
        $this->expectException(RtcValidationException::class);
        $this->expectExceptionMessageMatches('/cClassTrib/');

        $this->action()->handle(xmlNfe: $this->xmlNfe, rtcPorItem: [1 => ['cst' => '200']]); // cClassTrib ausente
    }
}
