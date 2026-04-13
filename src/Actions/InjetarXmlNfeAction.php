<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Actions;

use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use LibXMLError;

use function array_map;
use function implode;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function trim;

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
        $dets = $this->queryNodeList($rtcXpath, '//nfe:det');

        if ($dets === null) {
            return;
        }

        foreach ($dets as $detRtc) {
            if (! $detRtc instanceof DOMElement) {
                continue;
            }

            $nItem = $detRtc->getAttribute('nItem');

            // Localiza o <imposto> correspondente na NFe
            $impostoNfe = $this->queryFirstNode($nfeXpath, "//nfe:det[@nItem='{$nItem}']/nfe:imposto");

            if (! $impostoNfe instanceof DOMElement) {
                continue;
            }

            // IS
            $isRtc = $this->queryFirstNode($rtcXpath, 'nfe:imposto/nfe:IS', $detRtc);
            if ($isRtc instanceof DOMNode) {
                $imported = $nfeDoc->importNode($isRtc, deep: true);

                if ($imported instanceof DOMNode) {
                    $impostoNfe->appendChild($imported);
                }
            }

            // IBSCBS
            $ibsRtc = $this->queryFirstNode($rtcXpath, 'nfe:imposto/nfe:IBSCBS', $detRtc);
            if ($ibsRtc instanceof DOMNode) {
                $imported = $nfeDoc->importNode($ibsRtc, deep: true);

                if ($imported instanceof DOMNode) {
                    $impostoNfe->appendChild($imported);
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Injeção em <total>
    // ──────────────────────────────────────────────────────────────────────────

    private function injetarBlocosTotal(DOMXPath $rtcXpath, DOMXPath $nfeXpath, DOMDocument $nfeDoc): void
    {
        $totalNfe = $this->queryFirstNode($nfeXpath, '//nfe:total');

        if (! $totalNfe instanceof DOMElement) {
            return;
        }

        // Ponto de inserção: antes de <vNFTot> (se existir) ou ao final
        $vNfTot = $this->queryFirstNode($nfeXpath, 'nfe:vNFTot', $totalNfe);

        // ISTot
        $isTot = $this->queryFirstNode($rtcXpath, '//nfe:total/nfe:ISTot');
        if ($isTot instanceof DOMNode) {
            $node = $nfeDoc->importNode($isTot, deep: true);

            if (! $node instanceof DOMNode) {
                return;
            }

            $this->appendOrInsert($totalNfe, $node, $vNfTot);
        }

        // Atualiza referência pois o DOM mudou
        $vNfTot = $this->queryFirstNode($nfeXpath, 'nfe:vNFTot', $totalNfe);

        // IBSCBSTot
        $ibsCbsTot = $this->queryFirstNode($rtcXpath, '//nfe:total/nfe:IBSCBSTot');
        if ($ibsCbsTot instanceof DOMNode) {
            $node = $nfeDoc->importNode($ibsCbsTot, deep: true);

            if (! $node instanceof DOMNode) {
                return;
            }

            $this->appendOrInsert($totalNfe, $node, $vNfTot);
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

        if (! $loaded || $errors !== []) {
            $messages = array_map(
                static fn (LibXMLError $e): string => trim($e->message),
                $errors,
            );

            throw new RtcValidationException(
                "{$label} inválido: ".implode('; ', $messages),
                errors: ['messages' => $messages],
            );
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

    private function queryNodeList(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?DOMNodeList
    {
        return $context === null
            ? $this->ensureNodeList($xpath->query($query))
            : $this->ensureNodeList($xpath->query($query, $context));
    }

    private function queryFirstNode(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?DOMNode
    {
        $nodes = $this->queryNodeList($xpath, $query, $context);

        if ($nodes === null) {
            return null;
        }

        $node = $nodes->item(0);

        return $node instanceof DOMNode ? $node : null;
    }

    private function ensureNodeList(mixed $value): ?DOMNodeList
    {
        return $value instanceof DOMNodeList ? $value : null;
    }

    private function appendOrInsert(DOMElement $parent, DOMNode $node, ?DOMNode $before): void
    {
        if (! $before instanceof DOMNode) {
            $parent->appendChild($node);

            return;
        }

        $parent->insertBefore($node, $before);
    }
}
