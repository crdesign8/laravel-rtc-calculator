<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Tests\Feature;

use Crdesign8\LaravelRtcCalculator\Actions\InjetarXmlNfeAction;
use Crdesign8\LaravelRtcCalculator\Tests\TestCase;
use DOMDocument;
use DOMXPath;

class InjetarXmlTest extends TestCase
{
    private string $xmlRtc;
    private string $xmlNfe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->xmlRtc = file_get_contents(__DIR__ . '/../Fixtures/saida-gerar-xml.xml');
        $this->xmlNfe = file_get_contents(__DIR__ . '/../Fixtures/nfe-sem-rtc.xml');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // End-to-end com fixtures reais
    // ──────────────────────────────────────────────────────────────────────────

    public function test_resultado_e_xml_valido(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($result);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $this->assertTrue($loaded, 'O XML resultante deve ser sintáticamente válido');
    }

    public function test_resultado_contem_blocos_rtc_injetados(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $this->assertStringContainsString('<IS>', $result);
        $this->assertStringContainsString('<IBSCBS>', $result);
        $this->assertStringContainsString('<ISTot>', $result);
        $this->assertStringContainsString('<IBSCBSTot>', $result);
    }

    public function test_resultado_preserva_dados_originais_da_nfe(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $this->assertStringContainsString('EMPRESA EXEMPLO LTDA', $result);
        $this->assertStringContainsString('11111111000111', $result);
        $this->assertStringContainsString('22222222000122', $result);
    }

    public function test_valores_is_corretos_apos_injecao(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $xpath = $this->xpathFrom($result);

        $this->assertSame('000', $xpath->evaluate('string(//nfe:det[@nItem="1"]/nfe:imposto/nfe:IS/nfe:CSTIS)'));
        $this->assertSame('1111.00', $xpath->evaluate('string(//nfe:det[@nItem="1"]/nfe:imposto/nfe:IS/nfe:vBCIS)'));
        $this->assertSame('0.00', $xpath->evaluate('string(//nfe:det[@nItem="1"]/nfe:imposto/nfe:IS/nfe:vIS)'));
    }

    public function test_valores_ibscbs_corretos_apos_injecao(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $xpath = $this->xpathFrom($result);

        $this->assertSame('550', $xpath->evaluate('string(//nfe:det[@nItem="1"]/nfe:imposto/nfe:IBSCBS/nfe:CST)'));
        $this->assertSame(
            '5984.03',
            $xpath->evaluate('string(//nfe:det[@nItem="1"]/nfe:imposto/nfe:IBSCBS/nfe:gIBSCBS/nfe:vBC)'),
        );
    }

    public function test_valores_istot_corretos_apos_injecao(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $xpath = $this->xpathFrom($result);

        $this->assertSame('0.00', $xpath->evaluate('string(//nfe:total/nfe:ISTot/nfe:vIS)'));
    }

    public function test_valores_ibscbstot_corretos_apos_injecao(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $xpath = $this->xpathFrom($result);

        $this->assertSame('5984.03', $xpath->evaluate('string(//nfe:total/nfe:IBSCBSTot/nfe:vBCIBSCBS)'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────────────────────────

    private function xpathFrom(string $xml): DOMXPath
    {
        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

        return $xpath;
    }
}
