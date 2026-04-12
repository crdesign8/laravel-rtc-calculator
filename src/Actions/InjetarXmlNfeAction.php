<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Actions;

use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;
use DOMDocument;
use DOMXPath;

/**
 * Injeta os grupos RTC (IS, IBSCBS, ISTot, IBSCBSTot) em uma NFe existente.
 *
 * O XML RTC gerado pelo endpoint /xml/generate tem a estrutura:
 *   <infNFe xmlns="...">
 *     <det nItem="N"><imposto><IS>...</IS><IBSCBS>...</IBSCBS></imposto></det>
 *     <total><ISTot>...</ISTot><IBSCBSTot>...</IBSCBSTot></total>
 *   </infNFe>
 *
 * A NFe de destino segue o mesmo namespace (http://www.portalfiscal.inf.br/nfe).
 * - IS e IBSCBS são injetados dentro de cada <imposto> do <det> correspondente.
 * - ISTot e IBSCBSTot são injetados dentro de <total>, antes de <vNFTot> (se
 *   existir) ou ao final do bloco.
 */
class InjetarXmlNfeAction
{
    private const NS = 'http://www.portalfiscal.inf.br/nfe';

    /**
     * @param  string  $xmlRtc  XML gerado pelo endpoint /xml/generate
     * @param  string  $xmlNfe  XML da NFe sem os grupos RTC
     * @return string           XML da NFe com os grupos RTC injetados
     *
     * @throws RtcValidationException Se o XML RTC ou da NFe forem inválidos
     */
    public function handle(string $xmlRtc, string $xmlNfe): string
    {
        $rtcDoc = $this->parseXml($xmlRtc, 'XML RTC');
        $nfeDoc = $this->parseXml($xmlNfe, 'XML NFe');

        $rtcXpath = new DOMXPath($rtcDoc);
        $nfeXpath = new DOMXPath($nfeDoc);

        $rtcXpath->registerNamespace('nfe', self::NS);
        $nfeXpath->registerNamespace('nfe', self::NS);

        $this->injetarBlocosImposto($rtcXpath, $nfeXpath, $nfeDoc);
        $this->injetarBlocosTotal($rtcXpath, $nfeXpath, $nfeDoc);

        return $this->exportXml($nfeDoc);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Injeção em <imposto> de cada <det>
    // ──────────────────────────────────────────────────────────────────────────

    private function injetarBlocosImposto(DOMXPath $rtcXpath, DOMXPath $nfeXpath, DOMDocument $nfeDoc): void
    {
        // Itera sobre cada <det> presente no XML RTC
        $dets = $rtcXpath->query('//nfe:det');

        foreach ($dets as $detRtc) {
            /** @var \DOMElement $detRtc */
            $nItem = $detRtc->getAttribute('nItem');

            // Localiza o <imposto> correspondente na NFe
            $impostoNfe = $nfeXpath->query("//nfe:det[@nItem='{$nItem}']/nfe:imposto")->item(0);

            if ($impostoNfe === null) {
                continue;
            }

            // IS
            $isRtc = $rtcXpath->query('nfe:imposto/nfe:IS', $detRtc)->item(0);
            if ($isRtc !== null) {
                $impostoNfe->appendChild($nfeDoc->importNode($isRtc, deep: true));
            }

            // IBSCBS
            $ibsRtc = $rtcXpath->query('nfe:imposto/nfe:IBSCBS', $detRtc)->item(0);
            if ($ibsRtc !== null) {
                $impostoNfe->appendChild($nfeDoc->importNode($ibsRtc, deep: true));
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Injeção em <total>
    // ──────────────────────────────────────────────────────────────────────────

    private function injetarBlocosTotal(DOMXPath $rtcXpath, DOMXPath $nfeXpath, DOMDocument $nfeDoc): void
    {
        $totalNfe = $nfeXpath->query('//nfe:total')->item(0);

        if ($totalNfe === null) {
            return;
        }

        // Ponto de inserção: antes de <vNFTot> (se existir) ou ao final
        $vNfTot = $nfeXpath->query('nfe:vNFTot', $totalNfe)->item(0);

        // ISTot
        $isTot = $rtcXpath->query('//nfe:total/nfe:ISTot')->item(0);
        if ($isTot !== null) {
            $node = $nfeDoc->importNode($isTot, deep: true);
            $vNfTot !== null ? $totalNfe->insertBefore($node, $vNfTot) : $totalNfe->appendChild($node);
        }

        // Atualiza referência pois o DOM mudou
        $vNfTot = $nfeXpath->query('nfe:vNFTot', $totalNfe)->item(0);

        // IBSCBSTot
        $ibsCbsTot = $rtcXpath->query('//nfe:total/nfe:IBSCBSTot')->item(0);
        if ($ibsCbsTot !== null) {
            $node = $nfeDoc->importNode($ibsCbsTot, deep: true);
            $vNfTot !== null ? $totalNfe->insertBefore($node, $vNfTot) : $totalNfe->appendChild($node);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function parseXml(string $xml, string $label): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $previous = libxml_use_internal_errors(true);

        $loaded = $doc->loadXML($xml);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || $errors !== []) {
            $messages = array_map(static fn($e) => trim($e->message), $errors);
            throw new RtcValidationException("{$label} inválido: " . implode('; ', $messages), errors: $messages);
        }

        return $doc;
    }

    private function exportXml(DOMDocument $doc): string
    {
        $output = $doc->saveXML();

        if ($output === false) {
            throw new RtcValidationException('Falha ao serializar o XML da NFe com RTC injetado.');
        }

        return $output;
    }
}
