<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Tests\Integration;

use Crdesign8\LaravelRtcCalculator\Actions\CalcularTributosAction;
use Crdesign8\LaravelRtcCalculator\Actions\GerarXmlRtcAction;
use Crdesign8\LaravelRtcCalculator\Actions\InjetarXmlNfeAction;
use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\Enums\TipoDocumento;
use Crdesign8\LaravelRtcCalculator\Tests\TestCase;

/**
 * Testes de integração reais contra a Calculadora RTC Java.
 *
 * Requer a calculadora rodando em http://localhost:8080:
 *   docker run -d --name calculadora-api -p 8080:8080 -w /calculadora \
 *     calculadora-rtc /bin/sh -c "JAVA_HOME=/opt/java/openjdk; \
 *     export PATH=\$JAVA_HOME/bin:\$PATH; \
 *     java -jar /calculadora/api-regime-geral.jar --spring.profiles.active=offline"
 *
 * Executar apenas esta suite:
 *   vendor/bin/phpunit --testsuite Integration
 */
class CalculadoraRtcIntegrationTest extends TestCase
{
    private RtcClientContract $client;

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->calculadoraEstaRodando()) {
            $this->markTestSkipped(
                'Calculadora RTC não está acessível em http://localhost:8080. '
                . 'Inicie o container e rode: vendor/bin/phpunit --testsuite Integration',
            );
        }

        $this->client = $this->app->make(RtcClientContract::class);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/calculadora/regime-geral
    // ──────────────────────────────────────────────────────────────────────────

    public function test_calcula_regime_geral_e_retorna_calculo_result(): void
    {
        $dto = $this->dtoDoFixture();
        $result = (new CalcularTributosAction($this->client))->handle($dto);

        $this->assertInstanceOf(CalculoResult::class, $result);
        $this->assertNotEmpty($result->getObjetos());
    }

    public function test_resultado_contem_item_1(): void
    {
        $result = (new CalcularTributosAction($this->client))->handle($this->dtoDoFixture());

        $item = $result->getItem(1);

        $this->assertNotNull($item, 'Deve retornar o item nObj=1');
        $this->assertSame(1, $item->getNObj());
    }

    public function test_is_retorna_cst_000_para_cigarro(): void
    {
        $result = (new CalcularTributosAction($this->client))->handle($this->dtoDoFixture());

        $this->assertSame('000', $result->getItem(1)->getCstIs());
    }

    public function test_base_calculo_is_bate_com_valor_enviado(): void
    {
        $result = (new CalcularTributosAction($this->client))->handle($this->dtoDoFixture());

        // O fixture envia baseCalculo = 1111, a API deve confirmar vBCIS = "1111.00"
        $this->assertSame('1111.00', $result->getItem(1)->getVBcIs());
    }

    public function test_totais_sao_calculados(): void
    {
        $result = (new CalcularTributosAction($this->client))->handle($this->dtoDoFixture());
        $total = $result->getTotal();

        // Resultado deve ter estrutura de totais (valores podem variar entre versões da API)
        $this->assertIsString($total->getVIsTot());
        $this->assertIsString($total->getVBcIbsCbs());
        $this->assertIsString($total->getVIbsTot());
        $this->assertIsString($total->getVCbsTot());
    }

    public function test_resultado_bate_com_fixture_de_saida(): void
    {
        $esperado = json_decode(file_get_contents(__DIR__ . '/../Fixtures/saida-regime-geral.json'), associative: true);

        $result = (new CalcularTributosAction($this->client))->handle($this->dtoDoFixture());
        $real = $result->toArray();

        // Compara estrutura nObj e valores tributários (não memoriaCalculo que pode mudar)
        $this->assertSame($esperado['objetos'][0]['nObj'], $real['objetos'][0]['nObj']);
        $this->assertSame(
            $esperado['objetos'][0]['tribCalc']['IS']['CSTIS'],
            $real['objetos'][0]['tribCalc']['IS']['CSTIS'],
        );
        $this->assertSame(
            $esperado['objetos'][0]['tribCalc']['IS']['vBCIS'],
            $real['objetos'][0]['tribCalc']['IS']['vBCIS'],
        );
        $this->assertSame($esperado['total']['tribCalc']['ISTot']['vIS'], $real['total']['tribCalc']['ISTot']['vIS']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/calculadora/xml/generate
    // ──────────────────────────────────────────────────────────────────────────

    public function test_gera_xml_rtc_para_nfe(): void
    {
        $result = (new CalcularTributosAction($this->client))->handle($this->dtoDoFixture());
        $xml = (new GerarXmlRtcAction($this->client))->handle($result, TipoDocumento::NFe);

        $this->assertIsString($xml);
        $this->assertStringContainsString('<?xml', $xml);
        $this->assertStringContainsString('<IS>', $xml);
        $this->assertStringContainsString('<IBSCBS>', $xml);
    }

    public function test_xml_gerado_contem_totalizadores(): void
    {
        $result = (new CalcularTributosAction($this->client))->handle($this->dtoDoFixture());
        $xml = (new GerarXmlRtcAction($this->client))->handle($result, TipoDocumento::NFe);

        $this->assertStringContainsString('<ISTot>', $xml);
        $this->assertStringContainsString('<IBSCBSTot>', $xml);
    }

    public function test_xml_gerado_e_xml_valido(): void
    {
        $result = (new CalcularTributosAction($this->client))->handle($this->dtoDoFixture());
        $xml = (new GerarXmlRtcAction($this->client))->handle($result, TipoDocumento::NFe);

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $this->assertTrue($loaded, "O XML gerado pela API deve ser sintáticamente válido.\nConteúdo: {$xml}");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Fluxo completo: calcular → gerar XML → injetar NFe
    // ──────────────────────────────────────────────────────────────────────────

    public function test_fluxo_completo_calculo_geracao_e_injecao(): void
    {
        $xmlNfe = file_get_contents(__DIR__ . '/../Fixtures/nfe-sem-rtc.xml');

        // 1. Calcula
        $result = (new CalcularTributosAction($this->client))->handle($this->dtoDoFixture());
        $this->assertNotEmpty($result->getObjetos(), 'Cálculo deve retornar objetos');

        // 2. Gera XML RTC
        $xmlRtc = (new GerarXmlRtcAction($this->client))->handle($result, TipoDocumento::NFe);
        $this->assertStringContainsString('<IS>', $xmlRtc, 'XML RTC deve conter bloco IS');

        // 3. Injeta na NFe
        $nfeComRtc = (new InjetarXmlNfeAction())->handle($xmlRtc, $xmlNfe);

        $doc = new \DOMDocument();
        $doc->loadXML($nfeComRtc);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

        $this->assertSame(1, $xpath->query('//nfe:det[@nItem="1"]/nfe:imposto/nfe:IS')->length);
        $this->assertSame(1, $xpath->query('//nfe:det[@nItem="1"]/nfe:imposto/nfe:IBSCBS')->length);
        $this->assertSame(1, $xpath->query('//nfe:total/nfe:ISTot')->length);
        $this->assertSame(1, $xpath->query('//nfe:total/nfe:IBSCBSTot')->length);
        $this->assertStringContainsString('EMPRESA EXEMPLO LTDA', $nfeComRtc);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function dtoDoFixture(): CalculoRequestDTO
    {
        $data = json_decode(file_get_contents(__DIR__ . '/../Fixtures/entrada-regime-geral.json'), associative: true);

        return CalculoRequestDTO::fromArray($data);
    }

    private function calculadoraEstaRodando(): bool
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(3)->get('http://localhost:8080/');

            return $response->status() < 500;
        } catch (\Throwable) {
            return false;
        }
    }
}
