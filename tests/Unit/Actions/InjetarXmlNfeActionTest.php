<?php

namespace Crdesign8\LaravelRtcCalculator\Tests\Unit\Actions;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Crdesign8\LaravelRtcCalculator\Actions\InjetarXmlNfeAction;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;

class InjetarXmlNfeActionTest extends TestCase
{
    private string $xmlRtc;
    private string $xmlNfe;

    protected function setUp(): void
    {
        $this->xmlRtc = file_get_contents(__DIR__.'/../../Fixtures/saida-gerar-xml.xml');
        $this->xmlNfe = file_get_contents(__DIR__.'/../../Fixtures/nfe-sem-rtc.xml');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Injeção em <imposto> de cada <det>
    // ──────────────────────────────────────────────────────────────────────────

    public function test_injeta_is_no_imposto_do_det(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $xpath = $this->xpathFrom($result);
        $nodes = $xpath->query('//nfe:det[@nItem="1"]/nfe:imposto/nfe:IS');

        $this->assertSame(1, $nodes->length, 'Deve ter exatamente um <IS> dentro de <imposto> do det nItem=1');
    }

    public function test_injeta_ibscbs_no_imposto_do_det(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $xpath = $this->xpathFrom($result);
        $nodes = $xpath->query('//nfe:det[@nItem="1"]/nfe:imposto/nfe:IBSCBS');

        $this->assertSame(1, $nodes->length, 'Deve ter exatamente um <IBSCBS> dentro de <imposto> do det nItem=1');
    }

    public function test_valores_is_preservados_apos_injecao(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $xpath  = $this->xpathFrom($result);
        $cstIs  = $xpath->evaluate('string(//nfe:det[@nItem="1"]/nfe:imposto/nfe:IS/nfe:CSTIS)');
        $vBcIs  = $xpath->evaluate('string(//nfe:det[@nItem="1"]/nfe:imposto/nfe:IS/nfe:vBCIS)');
        $vIs    = $xpath->evaluate('string(//nfe:det[@nItem="1"]/nfe:imposto/nfe:IS/nfe:vIS)');

        $this->assertSame('000', $cstIs);
        $this->assertSame('1111.00', $vBcIs);
        $this->assertSame('0.00', $vIs);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Injeção em <total>
    // ──────────────────────────────────────────────────────────────────────────

    public function test_injeta_istot_em_total(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $xpath = $this->xpathFrom($result);
        $nodes = $xpath->query('//nfe:total/nfe:ISTot');

        $this->assertSame(1, $nodes->length, 'Deve ter exatamente um <ISTot> dentro de <total>');
    }

    public function test_injeta_ibscbstot_em_total(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $xpath = $this->xpathFrom($result);
        $nodes = $xpath->query('//nfe:total/nfe:IBSCBSTot');

        $this->assertSame(1, $nodes->length, 'Deve ter exatamente um <IBSCBSTot> dentro de <total>');
    }

    public function test_istot_inserido_antes_de_vnftot(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $doc = new DOMDocument();
        $doc->loadXML($result);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

        $total = $xpath->query('//nfe:total')->item(0);
        $this->assertNotNull($total, '<total> deve existir no resultado');

        $children = [];
        foreach ($total->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $children[] = $child->localName;
            }
        }

        $isTotPos  = array_search('ISTot', $children);
        $vNfTotPos = array_search('vNFTot', $children);

        $this->assertNotFalse($isTotPos, '<ISTot> deve estar presente em <total>');

        if ($vNfTotPos !== false) {
            $this->assertLessThan($vNfTotPos, $isTotPos, '<ISTot> deve vir antes de <vNFTot>');
        }
    }

    public function test_ibscbstot_inserido_antes_de_vnftot(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $doc = new DOMDocument();
        $doc->loadXML($result);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

        $total = $xpath->query('//nfe:total')->item(0);
        $children = [];
        foreach ($total->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $children[] = $child->localName;
            }
        }

        $ibscbsTotPos = array_search('IBSCBSTot', $children);
        $vNfTotPos    = array_search('vNFTot', $children);

        $this->assertNotFalse($ibscbsTotPos, '<IBSCBSTot> deve estar presente em <total>');

        if ($vNfTotPos !== false) {
            $this->assertLessThan($vNfTotPos, $ibscbsTotPos, '<IBSCBSTot> deve vir antes de <vNFTot>');
        }
    }

    public function test_resultado_e_xml_valido(): void
    {
        $result = (new InjetarXmlNfeAction())->handle($this->xmlRtc, $this->xmlNfe);

        $doc = new DOMDocument();
        $loaded = @$doc->loadXML($result);

        $this->assertTrue($loaded, 'Resultado deve ser XML sintáticamente válido');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tratamento de erros
    // ──────────────────────────────────────────────────────────────────────────

    public function test_xml_rtc_invalido_lanca_rtc_validation_exception(): void
    {
        $this->expectException(RtcValidationException::class);

        (new InjetarXmlNfeAction())->handle('xml inválido', $this->xmlNfe);
    }

    public function test_xml_nfe_invalido_lanca_rtc_validation_exception(): void
    {
        $this->expectException(RtcValidationException::class);

        (new InjetarXmlNfeAction())->handle($this->xmlRtc, 'xml inválido');
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
