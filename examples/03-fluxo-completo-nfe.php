<?php

declare(strict_types=1);

/**
 * Exemplo 03 — Fluxo Completo: Cálculo → XML → Injeção na NFe
 * ─────────────────────────────────────────────────────────────
 * Este é o exemplo mais completo do pacote. Ele demonstra todo o
 * fluxo de integração com a Calculadora RTC da Receita Federal:
 *
 *   1. Monta e envia o DTO de cálculo
 *   2. Gera o XML com os grupos RTC
 *   3. Injeta IS, IBSCBS, ISTot e IBSCBSTot em uma NFe existente
 *   4. Salva a NFe final com os tributos RTC incluídos
 *
 * Pré-requisito: calculadora Java rodando em http://localhost:8080
 *
 * Executar:
 *   cd packages/crdesign8/laravel-rtc-calculator
 *   php examples/03-fluxo-completo-nfe.php
 *
 * Arquivo de entrada: tests/Fixtures/nfe-sem-rtc.xml
 * Arquivo gerado:     examples/output/nfe-com-rtc.xml
 *
 * Elementos injetados na NFe:
 *   <det nItem="1"><imposto>
 *     + <IS>      → Imposto Seletivo calculado
 *     + <IBSCBS>  → IBS e CBS calculados
 *   </imposto></det>
 *   <total>
 *     + <ISTot>      → Totalizador do IS
 *     + <IBSCBSTot>  → Totalizador do IBS+CBS
 *   </total>
 */

$client = require __DIR__ . '/bootstrap.php';

use Crdesign8\LaravelRtcCalculator\Actions\CalcularTributosAction;
use Crdesign8\LaravelRtcCalculator\Actions\GerarXmlRtcAction;
use Crdesign8\LaravelRtcCalculator\Actions\InjetarXmlNfeAction;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\Enums\TipoDocumento;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcCalculationException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcConnectionException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;

$nfeSemRtcPath = __DIR__ . '/../tests/Fixtures/nfe-sem-rtc.xml';
$nfeComRtcPath = __DIR__ . '/output/nfe-com-rtc.xml';

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     Calculadora RTC — Fluxo Completo: Cálculo + NFe         ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

if (!file_exists($nfeSemRtcPath)) {
    die("❌  NFe de entrada não encontrada: {$nfeSemRtcPath}\n");
}

$xmlNfe = file_get_contents($nfeSemRtcPath);

// ─── Passo 1: Cálculo de tributos ─────────────────────────────────────────────

echo 'Passo 1/3: Calculando tributos RTC';

$dto = CalculoRequestDTO::fromArray([
    'id' => '507f1f77bcf86cd799439011',
    'versao' => '1.0.0',
    'dataHoraEmissao' => '2027-01-01T03:00:00-03:00',
    'municipio' => 4314902,
    'uf' => 'RS',
    'itens' => [[
        'numero' => 1,
        'ncm' => '24021000', // Cigarros — sujeitos ao IS
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
    die("\n❌  Falha de conexão: {$e->getMessage()}\n");
} catch (RtcCalculationException|RtcValidationException $e) {
    die("\n❌  {$e->getMessage()}\n");
}

$item = $result->getItem(1);
$total = $result->getTotal();

echo " ✔\n";
echo '          → IS (vIS): R$ ' . br($total->getVIsTot()) . "\n";
echo '          → BC IBS+CBS: R$ ' . br($total->getVBcIbsCbs()) . "\n";
echo '          → CBS: R$ ' . br($total->getVCbsTot()) . "\n\n";

// ─── Passo 2: Geração do XML RTC ──────────────────────────────────────────────

echo 'Passo 2/3: Gerando XML RTC (tipo: NFe)';

try {
    $xmlRtc = new GerarXmlRtcAction($client)->handle($result, TipoDocumento::NFe);
} catch (RtcConnectionException $e) {
    die("\n❌  Falha de conexão: {$e->getMessage()}\n");
} catch (RtcCalculationException|RtcValidationException $e) {
    die("\n❌  {$e->getMessage()}\n");
}

echo " ✔\n";
echo '          → Tamanho do XML RTC: ' . number_format(strlen($xmlRtc)) . " bytes\n\n";

// ─── Passo 3: Injeção na NFe ──────────────────────────────────────────────────

echo 'Passo 3/3: Injetando grupos RTC na NFe';

try {
    $nfeComRtc = new InjetarXmlNfeAction()->handle($xmlRtc, $xmlNfe);
} catch (RtcValidationException $e) {
    die("\n❌  Erro na injeção XML: {$e->getMessage()}\n");
}

echo " ✔\n\n";

// ─── Valida e exibe o resultado ───────────────────────────────────────────────

$doc = new DOMDocument();
$doc->loadXML($nfeComRtc);
$xpath = new DOMXPath($doc);
$xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

$isInjetado = $xpath->query('//nfe:det[@nItem="1"]/nfe:imposto/nfe:IS')->length === 1;
$ibsInjetado = $xpath->query('//nfe:det[@nItem="1"]/nfe:imposto/nfe:IBSCBS')->length === 1;
$isTotInjetado = $xpath->query('//nfe:total/nfe:ISTot')->length === 1;
$ibsTotInjetado = $xpath->query('//nfe:total/nfe:IBSCBSTot')->length === 1;

$vIs = $xpath->evaluate('string(//nfe:det[@nItem="1"]/nfe:imposto/nfe:IS/nfe:vIS)');
$cstIs = $xpath->evaluate('string(//nfe:det[@nItem="1"]/nfe:imposto/nfe:IS/nfe:CSTIS)');
$vBcIbs = $xpath->evaluate('string(//nfe:det[@nItem="1"]/nfe:imposto/nfe:IBSCBS/nfe:gIBSCBS/nfe:vBC)');
$vIsTot = $xpath->evaluate('string(//nfe:total/nfe:ISTot/nfe:vIS)');
$vBcTot = $xpath->evaluate('string(//nfe:total/nfe:IBSCBSTot/nfe:vBCIBSCBS)');

// Dados originais preservados
$cnpjEmit = $xpath->evaluate('string(//nfe:emit/nfe:CNPJ)');
$cnpjDest = $xpath->evaluate('string(//nfe:dest/nfe:CNPJ)');
$nNF = $xpath->evaluate('string(//nfe:ide/nfe:nNF)');

echo "RESULTADO DA INJEÇÃO:\n";
echo str_repeat('─', 64) . "\n";

echo "\n  ✔  Dados originais da NFe preservados:\n";
echo "       Emitente CNPJ ........ {$cnpjEmit}\n";
echo "       Destinatário CNPJ .... {$cnpjDest}\n";
echo "       Número da NF ......... {$nNF}\n";

echo "\n  Grupos injetados em <det nItem=\"1\">/<imposto>:\n";
echo '    <IS>     ................. ' . ($isInjetado ? '✔  injetado' : '✘  ausente') . "\n";
echo "      CSTIS .................. {$cstIs}\n";
echo '      vIS .................... R$ ' . br((float) $vIs) . "\n";
echo '    <IBSCBS> ................. ' . ($ibsInjetado ? '✔  injetado' : '✘  ausente') . "\n";
echo '      vBC .................... R$ ' . br((float) $vBcIbs) . "\n";

echo "\n  Grupos injetados em <total>:\n";
echo '    <ISTot> .................. ' . ($isTotInjetado ? '✔  injetado' : '✘  ausente') . "\n";
echo '      vIS .................... R$ ' . br((float) $vIsTot) . "\n";
echo '    <IBSCBSTot> .............. ' . ($ibsTotInjetado ? '✔  injetado' : '✘  ausente') . "\n";
echo '      vBCIBSCBS .............. R$ ' . br((float) $vBcTot) . "\n";

file_put_contents($nfeComRtcPath, $nfeComRtc);

echo "\n✔  NFe com RTC salva em: examples/output/nfe-com-rtc.xml\n";
echo '   Antes: ' . number_format(strlen($xmlNfe)) . " bytes\n";
echo '   Depois: ' . number_format(strlen($nfeComRtc)) . " bytes\n";
echo '   Acréscimo: +' . number_format(strlen($nfeComRtc) - strlen($xmlNfe)) . " bytes\n\n";

function br(string|float $v): string
{
    return number_format((float) $v, 2, ',', '.');
}
