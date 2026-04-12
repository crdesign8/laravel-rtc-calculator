<?php

declare(strict_types=1);

/**
 * Exemplo 01 — Cálculo de Tributos (Regime Geral)
 * ────────────────────────────────────────────────
 * Demonstra o uso básico do pacote: monta o DTO de entrada,
 * envia para a Calculadora RTC Java e exibe o resultado detalhado.
 *
 * Pré-requisito: calculadora Java rodando em http://localhost:8080
 *
 * Executar:
 *   cd packages/crdesign8/laravel-rtc-calculator
 *   php examples/01-calcular-tributos.php
 *
 * Saída esperada (valores reais da API):
 * ┌─────────────────────────────────────────────────────────────┐
 * │  Item 1 — NCM 24021000 (Cigarros)                           │
 * │  Imposto Seletivo (IS):                                     │
 * │    CST ............ 000                                     │
 * │    Base de cálculo  R$ 1.111,00                             │
 * │    Alíquota ....... 13,00%                                  │
 * │    Valor IS ....... R$ 0,00  (Suspensão — Art. 412)         │
 * │  IBS + CBS (IBSCBS):                                        │
 * │    CST ............ 550                                     │
 * │    Base de cálculo  R$ 5.984,03                             │
 * │    IBS UF ......... R$ 0,00  (Suspensão)                    │
 * │    IBS Município .. R$ 0,00                                 │
 * │    CBS ............ R$ 0,00                                 │
 * ├─────────────────────────────────────────────────────────────┤
 * │  TOTALIZADORES                                              │
 * │    IS Total ................. R$ 0,00                       │
 * │    Base IBS+CBS ............. R$ 5.984,03                   │
 * │    IBS Total ................ R$ 0,00                       │
 * │    IBS UF Total ............. R$ 0,00                       │
 * │    IBS Município Total ...... R$ 0,00                       │
 * │    CBS Total ................ R$ 0,00                       │
 * └─────────────────────────────────────────────────────────────┘
 */

$client = require __DIR__ . '/bootstrap.php';

use Crdesign8\LaravelRtcCalculator\Actions\CalcularTributosAction;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcCalculationException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcConnectionException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;

// ─── 1. Monta o DTO de entrada ────────────────────────────────────────────────

$dto = CalculoRequestDTO::fromArray([
    'id' => '507f1f77bcf86cd799439011',
    'versao' => '1.0.0',
    'dataHoraEmissao' => '2027-01-01T03:00:00-03:00',
    'municipio' => 4314902,
    'uf' => 'RS',
    'itens' => [
        [
            'numero' => 1,
            'ncm' => '24021000', // Cigarros
            'quantidade' => 222,
            'unidade' => 'VN',
            'cst' => '550',
            'baseCalculo' => 1111.00,
            'cClassTrib' => '550020',
            'tributacaoRegular' => [
                'cst' => '200',
                'cClassTrib' => '200032',
            ],
            'impostoSeletivo' => [
                'cst' => '000',
                'baseCalculo' => 1111.00,
                'cClassTrib' => '000001',
                'unidade' => 'VN',
                'quantidade' => 222,
                'impostoInformado' => 0,
            ],
        ],
    ],
]);

// ─── 2. Exibe os dados de entrada ─────────────────────────────────────────────

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     Calculadora RTC — Cálculo de Tributos (Regime Geral)     ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "DADOS DE ENTRADA:\n";
echo "  Município ..... {$dto->getMunicipio()} ({$dto->getUf()->value})\n";
echo "  Data emissão .. {$dto->getDataHoraEmissao()}\n";
echo '  Itens ......... ' . count($dto->getItens()) . "\n\n";

foreach ($dto->getItens() as $item) {
    echo "  Item {$item->getNumero()} — NCM {$item->getNcm()}\n";
    echo '    Base de cálculo: R$ ' . valBr($item->getBaseCalculo()) . "\n";
    echo "    Quantidade: {$item->getQuantidade()} {$item->getUnidade()->value}\n";
    echo "    CST: {$item->getCst()} | ClassTrib: {$item->getCClassTrib()}\n";
}

echo "\nCalculando";

// ─── 3. Executa o cálculo ─────────────────────────────────────────────────────

try {
    $result = new CalcularTributosAction($client)->handle($dto);
} catch (RtcConnectionException $e) {
    die("\n❌  Falha de conexão: {$e->getMessage()}\n");
} catch (RtcValidationException $e) {
    die("\n❌  Erro de validação: {$e->getMessage()}\n");
} catch (RtcCalculationException $e) {
    die("\n❌  Erro no cálculo: {$e->getMessage()}\n");
}

echo " ✔\n\n";

// ─── 4. Exibe os resultados ───────────────────────────────────────────────────

echo "RESULTADO:\n";
echo str_repeat('─', 64) . "\n\n";

foreach ($result->getObjetos() as $obj) {
    $is = $obj->getIs();
    $ibsCbs = $obj->getIbsCbs();
    $gIbs = $ibsCbs['gIBSCBS'] ?? [];

    echo "  Item nObj={$obj->getNObj()}\n\n";

    echo '  ┌─ Imposto Seletivo (IS) ' . str_repeat('─', 38) . "┐\n";
    echo "  │  CST ............. {$obj->getCstIs()}\n";
    echo '  │  Base de cálculo . R$ ' . valBr((float) ($is['vBCIS'] ?? 0)) . "\n";
    echo '  │  Alíquota ........ ' . ($is['pIS'] ?? '0.00') . "%\n";
    echo '  │  Valor IS ........ R$ ' . valBr((float) $obj->getVIs()) . "\n";
    echo '  └' . str_repeat('─', 62) . "┘\n\n";

    echo '  ┌─ IBS + CBS (IBSCBS) ' . str_repeat('─', 41) . "┐\n";
    echo "  │  CST ............. {$obj->getCstIbsCbs()}\n";
    echo '  │  Base de cálculo . R$ ' . valBr((float) $obj->getVBcIbsCbs()) . "\n";
    echo '  │  IBS UF .......... R$ ' . valBr((float) $obj->getVIbsUf()) . "\n";
    echo '  │  IBS Município ... R$ ' . valBr((float) $obj->getVIbsMun()) . "\n";
    echo '  │  CBS ............. R$ ' . valBr((float) $obj->getVCbs()) . "\n";

    if (!empty($gIbs['gTribRegular'])) {
        $tr = $gIbs['gTribRegular'];
        echo "  │\n";
        echo "  │  Tributação Regular (CST {$tr['CSTReg']}):\n";
        echo '  │    IBS UF reg .. R$ ' . valBr((float) ($tr['vTribRegIBSUF'] ?? 0)) . "\n";
        echo '  │    IBS Mun reg . R$ ' . valBr((float) ($tr['vTribRegIBSMun'] ?? 0)) . "\n";
        echo '  │    CBS reg ..... R$ ' . valBr((float) ($tr['vTribRegCBS'] ?? 0)) . "\n";
    }

    echo '  └' . str_repeat('─', 62) . "┘\n\n";
}

$total = $result->getTotal();

echo "TOTALIZADORES:\n";
echo str_repeat('─', 64) . "\n";
echo '  IS Total ................... R$ ' . valBr((float) $total->getVIsTot()) . "\n";
echo '  Base IBS+CBS ............... R$ ' . valBr((float) $total->getVBcIbsCbs()) . "\n";
echo '  IBS Total .................. R$ ' . valBr((float) $total->getVIbsTot()) . "\n";
echo '  IBS UF Total ............... R$ ' . valBr((float) $total->getVIbsUfTot()) . "\n";
echo '  IBS Município Total ........ R$ ' . valBr((float) $total->getVIbsMunTot()) . "\n";
echo '  CBS Total .................. R$ ' . valBr((float) $total->getVCbsTot()) . "\n";

echo "\n✔  Cálculo concluído. JSON completo salvo em: examples/output/resultado-calculo.json\n\n";
file_put_contents(__DIR__ . '/output/resultado-calculo.json', $result->toJson());

// ─── Helpers ──────────────────────────────────────────────────────────────────

function valBr(float $value): string
{
    return number_format($value, 2, ',', '.');
}
