<?php

declare(strict_types=1);

/**
 * Exemplo 02 — Geração de XML RTC
 * ────────────────────────────────
 * Demonstra como usar o resultado de um cálculo para gerar o XML com
 * os grupos RTC (IS, IBSCBS, ISTot, IBSCBSTot) prontos para injeção.
 *
 * Pré-requisito: calculadora Java rodando em http://localhost:8080
 *
 * Executar:
 *   cd packages/crdesign8/laravel-rtc-calculator
 *   php examples/02-gerar-xml-rtc.php
 *
 * Arquivo gerado: examples/output/xml-rtc-gerado.xml
 *
 * Trecho do XML esperado:
 *   <infNFe xmlns="http://www.portalfiscal.inf.br/nfe">
 *     <det nItem="1">
 *       <imposto>
 *         <IS>
 *           <CSTIS>000</CSTIS>
 *           <vBCIS>1111.00</vBCIS>
 *           <pIS>13.00</pIS>
 *           <vIS>0.00</vIS>
 *           ...
 *         </IS>
 *         <IBSCBS>
 *           <CST>550</CST>
 *           <gIBSCBS>
 *             <vBC>5984.03</vBC>
 *             ...
 *           </gIBSCBS>
 *         </IBSCBS>
 *       </imposto>
 *     </det>
 *     <total>
 *       <ISTot><vIS>0.00</vIS></ISTot>
 *       <IBSCBSTot><vBCIBSCBS>5984.03</vBCIBSCBS>...</IBSCBSTot>
 *     </total>
 *   </infNFe>
 */

$client = require __DIR__ . '/bootstrap.php';

use Crdesign8\LaravelRtcCalculator\Actions\CalcularTributosAction;
use Crdesign8\LaravelRtcCalculator\Actions\GerarXmlRtcAction;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\Enums\TipoDocumento;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcCalculationException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcConnectionException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         Calculadora RTC — Geração de XML RTC                 ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// ─── Passo 1: Carrega o resultado de exemplo (ou recalcula) ───────────────────

$resultadoSalvo = __DIR__ . '/output/resultado-calculo.json';

if (file_exists($resultadoSalvo)) {
    echo "Passo 1/2: Carregando resultado do arquivo salvo...\n";
    $rawJson = json_decode(file_get_contents($resultadoSalvo), associative: true);
    $result = \Crdesign8\LaravelRtcCalculator\Data\CalculoResult::fromArray($rawJson);
    echo "          ✔ Carregado ({$resultadoSalvo})\n\n";
} else {
    echo "Passo 1/2: Arquivo resultado-calculo.json não encontrado.\n";
    echo "         → Executando cálculo primeiro...\n";

    $dto = CalculoRequestDTO::fromArray([
        'id' => '507f1f77bcf86cd799439011',
        'versao' => '1.0.0',
        'dataHoraEmissao' => '2027-01-01T03:00:00-03:00',
        'municipio' => 4314902,
        'uf' => 'RS',
        'itens' => [[
            'numero' => 1,
            'ncm' => '24021000',
            'quantidade' => 222,
            'unidade' => 'VN',
            'cst' => '550',
            'baseCalculo' => 1111.00,
            'cClassTrib' => '550020',
            'tributacaoRegular' => ['cst' => '200', 'cClassTrib' => '200032'],
            'impostoSeletivo' => [
                'cst' => '000',
                'baseCalculo' => 1111.00,
                'cClassTrib' => '000001',
                'unidade' => 'VN',
                'quantidade' => 222,
                'impostoInformado' => 0,
            ],
        ]],
    ]);

    try {
        $result = new CalcularTributosAction($client)->handle($dto);
    } catch (RtcConnectionException $e) {
        die("❌  Falha de conexão: {$e->getMessage()}\n");
    } catch (RtcCalculationException|RtcValidationException $e) {
        die("❌  {$e->getMessage()}\n");
    }

    echo "          ✔ Calculado ({$result->getObjetos()[0]->getNObj()} item(s))\n\n";
}

// ─── Passo 2: Gera o XML RTC ──────────────────────────────────────────────────

echo 'Passo 2/2: Gerando XML RTC (tipo: NFe)';

try {
    $xml = new GerarXmlRtcAction($client)->handle($result, TipoDocumento::NFe);
} catch (RtcConnectionException $e) {
    die("\n❌  Falha de conexão: {$e->getMessage()}\n");
} catch (RtcCalculationException|RtcValidationException $e) {
    die("\n❌  {$e->getMessage()}\n");
}

echo " ✔\n\n";

// ─── Exibe um resumo do XML gerado ────────────────────────────────────────────

$doc = new DOMDocument();
$doc->loadXML($xml);
$xpath = new DOMXPath($doc);
$xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

$dets = $xpath->query('//nfe:det');
$isTot = $xpath->evaluate('string(//nfe:total/nfe:ISTot/nfe:vIS)');
$bcIbs = $xpath->evaluate('string(//nfe:total/nfe:IBSCBSTot/nfe:vBCIBSCBS)');

echo "ESTRUTURA DO XML GERADO:\n";
echo str_repeat('─', 64) . "\n";
echo "  Elementos <det> ......... {$dets->length}\n";

foreach ($dets as $det) {
    $nItem = $det->getAttribute('nItem');
    $haIS = $xpath->query('nfe:imposto/nfe:IS', $det)->length;
    $haIbs = $xpath->query('nfe:imposto/nfe:IBSCBS', $det)->length;
    $cstIs = $xpath->evaluate('string(nfe:imposto/nfe:IS/nfe:CSTIS)', $det);
    $vBcIs = $xpath->evaluate('string(nfe:imposto/nfe:IS/nfe:vBCIS)', $det);
    $vIs = $xpath->evaluate('string(nfe:imposto/nfe:IS/nfe:vIS)', $det);
    $cstIbs = $xpath->evaluate('string(nfe:imposto/nfe:IBSCBS/nfe:CST)', $det);
    $vBcIbs = $xpath->evaluate('string(nfe:imposto/nfe:IBSCBS/nfe:gIBSCBS/nfe:vBC)', $det);

    echo "\n  det nItem={$nItem}:\n";
    echo '    <IS> presente ........ ' . ($haIS ? 'sim' : 'não') . "\n";
    echo "      CSTIS .............. {$cstIs}\n";
    echo '      vBCIS .............. R$ ' . number_format((float) $vBcIs, 2, ',', '.') . "\n";
    echo '      vIS ................ R$ ' . number_format((float) $vIs, 2, ',', '.') . "\n";
    echo '    <IBSCBS> presente .... ' . ($haIbs ? 'sim' : 'não') . "\n";
    echo "      CST ................ {$cstIbs}\n";
    echo '      vBC ................ R$ ' . number_format((float) $vBcIbs, 2, ',', '.') . "\n";
}

echo "\n  <ISTot>:\n";
echo '    vIS ..................... R$ ' . number_format((float) $isTot, 2, ',', '.') . "\n";
echo "  <IBSCBSTot>:\n";
echo '    vBCIBSCBS ............... R$ ' . number_format((float) $bcIbs, 2, ',', '.') . "\n";

$outputPath = __DIR__ . '/output/xml-rtc-gerado.xml';
file_put_contents($outputPath, $xml);

echo "\n✔  XML salvo em: examples/output/xml-rtc-gerado.xml\n";
echo '   Tamanho: ' . number_format(strlen($xml)) . " bytes\n\n";
