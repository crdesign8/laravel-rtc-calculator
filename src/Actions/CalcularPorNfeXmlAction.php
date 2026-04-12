<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Actions;

use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\DTOs\ItemDTO;
use Crdesign8\LaravelRtcCalculator\Enums\UnidadeMedida;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;
use DOMDocument;
use DOMXPath;
use function array_key_exists;
use function array_map;
use function file_get_contents;
use function implode;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function strtoupper;
use function trim;

/**
 * Calcula tributos RTC extraindo os campos de identificação e de produtos
 * diretamente do XML de uma NFe, evitando a necessidade de remontar um DTO
 * manualmente para operações que já possuem a nota.
 *
 * Campos extraídos automaticamente do XML:
 *   - municipio   → <ide>/<cMunFG>
 *   - UF          → <emit>/<enderEmit>/<UF>
 *   - dhEmi       → <ide>/<dhEmi>
 *   - Por item    → NCM, qCom, uCom, vProd
 *
 * Campos exclusivos do RTC (cst, cClassTrib etc.) devem ser informados
 * via $rtcPorItem porque NÃO existem na NFe padrão.
 *
 * Exemplo de uso:
 *
 *   $result = (new CalcularPorNfeXmlAction($client))->handle(
 *       xmlNfe: file_get_contents('nfe-sem-rtc.xml'),
 *       rtcPorItem: [
 *           1 => [
 *               'cst'        => '200',
 *               'cClassTrib' => '200032',
 *               'tributacaoRegular' => ['cst' => '200', 'cClassTrib' => '200032'],
 *           ],
 *       ],
 *   );
 *
 * Via facade:
 *
 *   $result = Rtc::make()->calcularPorNfe($xmlNfe, $rtcPorItem);
 */
class CalcularPorNfeXmlAction
{
    /**
     * Mapeamento de unidades comerciais da NFe para UnidadeMedida RTC.
     * Unidades não mapeadas ou desconhecidas usam VN (unidade/cada) como padrão.
     */
    private const UNIDADE_MAP = [
        'LT' => 'L', // Litro (abreviação alternativa)
        'LITRO' => 'L',
        'TON' => 'T', // Tonelada
        'TONEL' => 'T',
    ];

    public function __construct(
        private readonly RtcClientContract $client,
    ) {}

    /**
     * @param  string  $xmlNfe      Conteúdo XML da NFe (enviNFe, nfeProc ou infNFe)
     * @param  array<int, array{
     *     cst: string,
     *     cClassTrib: string,
     *     tributacaoRegular?: array{cst: string, cClassTrib: string}|null,
     *     impostoSeletivo?: array{cst: string, baseCalculo: float, cClassTrib: string, unidade: string, quantidade: float, impostoInformado?: float}|null,
     * }>  $rtcPorItem  Dados RTC obrigatórios indexados pelo nItem (1-based)
     * @param  string  $versao      Versão do layout (padrão: '1.0.0')
     *
     * @throws RtcValidationException Se o XML for inválido, estiver incompleto,
     *                                ou se algum item não tiver dados em $rtcPorItem
     */
    public function handle(string $xmlNfe, array $rtcPorItem, string $versao = '1.0.0'): CalculoResult
    {
        $doc = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xmlNfe);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $xmlErrors !== []) {
            $messages = array_map(static fn ($e) => trim($e->message), $xmlErrors);
            throw new RtcValidationException('XML da NFe inválido ou malformado: '.implode('; ', $messages));
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

        /** @var int $municipio */
        $municipio = (int) $xpath->evaluate('string(//nfe:ide/nfe:cMunFG)');
        $uf = (string) $xpath->evaluate('string(//nfe:emit/nfe:enderEmit/nfe:UF)');
        $dhEmi = (string) $xpath->evaluate('string(//nfe:ide/nfe:dhEmi)');

        if ($municipio === 0 || $uf === '' || $dhEmi === '') {
            throw new RtcValidationException('Não foi possível extrair municipio (<cMunFG>), UF (<UF>) ou '
            .'data de emissão (<dhEmi>) do XML da NFe.');
        }

        $detNodes = $xpath->query('//nfe:det');

        if ($detNodes === false || $detNodes->length === 0) {
            throw new RtcValidationException('Nenhum elemento <det> encontrado no XML da NFe.');
        }

        $itens = [];

        foreach ($detNodes as $det) {
            if (! $det instanceof \DOMElement) {
                continue;
            }

            $itens[] = $this->processarDetItem($det, $xpath, $rtcPorItem);
        }

        $dto = CalculoRequestDTO::make(
            municipio: $municipio,
            uf: $uf,
            itens: $itens,
            dataHoraEmissao: $dhEmi,
            versao: $versao,
        );

        return (new CalcularTributosAction($this->client))->handle($dto);
    }

    /**
     * Extrai dados de um elemento <det> e constrói o ItemDTO correspondente.
     *
     * @param  array<int, array{cst: string, cClassTrib: string, ...}>  $rtcPorItem
     *
     * @throws RtcValidationException
     */
    private function processarDetItem(\DOMElement $det, DOMXPath $xpath, array $rtcPorItem): ItemDTO
    {
        $nItem = (int) $det->getAttribute('nItem');

        if (! array_key_exists($nItem, $rtcPorItem)) {
            throw new RtcValidationException(
                "Dados RTC para o item nItem={$nItem} não fornecidos em \$rtcPorItem. "
                .'Informe pelo menos os campos "cst" e "cClassTrib".',
            );
        }

        $rtc = $rtcPorItem[$nItem];

        if (($rtc['cst'] ?? '') === '' || ($rtc['cClassTrib'] ?? '') === '') {
            throw new RtcValidationException(
                "Os campos \"cst\" e \"cClassTrib\" são obrigatórios em \$rtcPorItem[{$nItem}].",
            );
        }

        $ncm = (string) $xpath->evaluate('string(nfe:prod/nfe:NCM)', $det);
        $quantidade = (float) $xpath->evaluate('string(nfe:prod/nfe:qCom)', $det);
        $uNfe = strtoupper((string) $xpath->evaluate('string(nfe:prod/nfe:uCom)', $det));
        $baseCalculo = (float) $xpath->evaluate('string(nfe:prod/nfe:vProd)', $det);

        $uRtc = self::UNIDADE_MAP[$uNfe] ?? $uNfe;
        $unidade = UnidadeMedida::tryFrom($uRtc) ?? UnidadeMedida::VN;

        $item = ItemDTO::make($nItem)
            ->ncm($ncm)
            ->quantidade($quantidade)
            ->unidade($unidade)
            ->cst($rtc['cst'])
            ->baseCalculo($baseCalculo)
            ->cClassTrib($rtc['cClassTrib']);

        if (array_key_exists('tributacaoRegular', $rtc)) {
            $item->tributacaoRegular($rtc['tributacaoRegular']['cst'], $rtc['tributacaoRegular']['cClassTrib']);
        }

        if (array_key_exists('impostoSeletivo', $rtc)) {
            $is = $rtc['impostoSeletivo'];
            $item->impostoSeletivo(
                cst: $is['cst'],
                baseCalculo: (float) $is['baseCalculo'],
                cClassTrib: $is['cClassTrib'],
                unidade: UnidadeMedida::from($is['unidade']),
                quantidade: (float) $is['quantidade'],
                impostoInformado: (float) ($is['impostoInformado'] ?? 0),
            );
        }

        return $item;
    }
}
