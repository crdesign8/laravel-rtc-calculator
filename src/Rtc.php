<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator;

use Crdesign8\LaravelRtcCalculator\Actions\CalcularPorNfeXmlAction;
use Crdesign8\LaravelRtcCalculator\Actions\CalcularTributosAction;
use Crdesign8\LaravelRtcCalculator\Actions\GerarXmlRtcAction;
use Crdesign8\LaravelRtcCalculator\Actions\InjetarXmlNfeAction;
use Crdesign8\LaravelRtcCalculator\Actions\ValidarXmlRtcAction;
use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\DTOs\ItemDTO;
use Crdesign8\LaravelRtcCalculator\Enums\TipoDocumento;
use Crdesign8\LaravelRtcCalculator\Events\RtcCalculated;
use Illuminate\Support\Str;
use InvalidArgumentException;

use function app;
use function event;
use function file_get_contents;
use function now;
use function strtoupper;

class Rtc
{
    // -----------------------------------------------------------------------
    // Estado interno do builder fluente
    // -----------------------------------------------------------------------

    private ?int $municipio = null;

    private ?string $uf = null;

    private ?string $dataHoraEmissao = null;

    private ?string $id = null;

    /** @var ItemDTO[] */
    private array $itens = [];

    // -----------------------------------------------------------------------
    // Construtor
    // -----------------------------------------------------------------------

    public function __construct(
        private readonly RtcClientContract $client,
    ) {}

    // -----------------------------------------------------------------------
    // Factory / entry-point
    // -----------------------------------------------------------------------

    /**
     * Cria uma nova instância do builder fluente.
     * Resolve automaticamente pelo container do Laravel.
     */
    public static function make(): static
    {
        return app(static::class);
    }

    // -----------------------------------------------------------------------
    // Builder fluente
    // -----------------------------------------------------------------------

    /**
     * Define o município e a UF da operação.
     *
     * @param  int     $municipio  Código IBGE do município (ex.: 4314902 para Porto Alegre)
     * @param  string  $uf         Sigla do estado (ex.: 'RS')
     */
    public function paraFiscal(int $municipio, string $uf): static
    {
        $this->municipio = $municipio;
        $this->uf = strtoupper($uf);

        return $this;
    }

    /**
     * Define a data e hora de emissão da nota no formato ISO 8601.
     *
     * @param  string  $dataHoraEmissao  Ex.: '2027-01-01T03:00:00-03:00'
     */
    public function emitidoEm(string $dataHoraEmissao): static
    {
        $this->dataHoraEmissao = $dataHoraEmissao;

        return $this;
    }

    /**
     * Define um ID customizado para a requisição.
     *
     * @param  string  $id  Identificador único (ex.: ObjectId MongoDB ou UUID)
     */
    public function comId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Adiciona um item ao cálculo.
     */
    public function addItem(ItemDTO $item): static
    {
        $this->itens[] = $item;

        return $this;
    }

    /**
     * Adiciona múltiplos itens de uma vez ao cálculo.
     *
     * @param  ItemDTO[]  $itens
     */
    public function addItems(array $itens): static
    {
        foreach ($itens as $item) {
            $this->itens[] = $item;
        }

        return $this;
    }

    /**
     * Finaliza o builder e executa o cálculo de tributos.
     * Atalho para chamar o método estático após configurar o builder.
     *
     * @throws \InvalidArgumentException Se municipio, uf ou itens não forem definidos
     */
    public function calcular(): CalculoResult
    {
        try {
            return $this->doCalculo($this->buildDto());
        } finally {
            $this->reset();
        }
    }

    // -----------------------------------------------------------------------
    // Métodos estáticos de acesso direto (sem builder)
    // -----------------------------------------------------------------------

    /**
     * Calcula os tributos RTC a partir de um DTO já construído.
     *
     * @param  CalculoRequestDTO  $dto
     * @return CalculoResult
     */
    public function executarCalculo(CalculoRequestDTO $dto): CalculoResult
    {
        return $this->doCalculo($dto);
    }

    /**
     * Gera o XML RTC com os grupos IS, IBSCBS e seus totalizadores.
     *
     * @param  CalculoResult  $result  Resultado de um cálculo anterior
     * @param  TipoDocumento  $tipo    Tipo de documento (NFe por padrão)
     * @return string                  Conteúdo XML
     */
    public function gerarXml(CalculoResult $result, TipoDocumento $tipo = TipoDocumento::NFe): string
    {
        return (new GerarXmlRtcAction($this->client))->handle($result, $tipo);
    }

    /**
     * Valida um XML RTC gerado.
     *
     * @param  string  $xml  Conteúdo XML a ser validado
     * @return bool
     */
    public function validarXml(string $xml): bool
    {
        return (new ValidarXmlRtcAction($this->client))->handle($xml);
    }

    /**
     * Injeta os grupos RTC (IS, IBSCBS, ISTot, IBSCBSTot) em uma NFe existente.
     *
     * @param  string  $xmlRtc  XML RTC gerado pelo {@see gerarXml()}
     * @param  string  $xmlNfe  XML da NFe sem RTC
     * @return string           XML da NFe com blocos RTC injetados
     */
    public function injetarNfe(string $xmlRtc, string $xmlNfe): string
    {
        return (new InjetarXmlNfeAction)->handle($xmlRtc, $xmlNfe);
    }

    /**
     * Extrai municipio, UF, data de emissão e itens diretamente do XML da NFe
     * e executa o cálculo de tributos RTC em uma única chamada.
     *
     * Os campos exclusivos do RTC (cst, cClassTrib) devem ser informados via
     * $rtcPorItem porque não existem na NFe padrão.
     *
     * Exemplo:
     *
     *   $result = Rtc::make()->calcularPorNfe(
     *       xmlNfe: file_get_contents('nfe.xml'),
     *       rtcPorItem: [
     *           1 => ['cst' => '200', 'cClassTrib' => '200032'],
     *       ],
     *   );
     *
     * @param  string  $xmlNfe     XML da NFe (enviNFe, nfeProc ou infNFe)
     * @param  array<int, array{
     *     cst: string,
     *     cClassTrib: string,
     *     tributacaoRegular?: array{cst: string, cClassTrib: string}|null,
     *     impostoSeletivo?: array{cst: string, baseCalculo: float, cClassTrib: string, unidade: string, quantidade: float, impostoInformado?: float}|null,
     * }>  $rtcPorItem  Dados RTC indexados pelo nItem (1-based)
     * @param  string  $versao     Versão do layout (padrão: '1.0.0')
     */
    public function calcularPorNfe(string $xmlNfe, array $rtcPorItem, string $versao = '1.0.0'): CalculoResult
    {
        return (new CalcularPorNfeXmlAction($this->client))->handle($xmlNfe, $rtcPorItem, $versao);
    }

    // -----------------------------------------------------------------------
    // Helpers internos
    // -----------------------------------------------------------------------

    /**
     * Executa o cálculo e despacha o evento RtcCalculated.
     * Ponto único de saída para calcular() e executarCalculo() (DRY).
     */
    private function doCalculo(CalculoRequestDTO $dto): CalculoResult
    {
        $result = (new CalcularTributosAction($this->client))->handle($dto);

        event(new RtcCalculated($dto, $result));

        return $result;
    }

    /**
     * Constrói o DTO de requisição a partir do estado do builder.
     *
     * @throws \InvalidArgumentException
     */
    private function buildDto(): CalculoRequestDTO
    {
        if ($this->municipio === null || $this->uf === null) {
            throw new InvalidArgumentException('Município e UF são obrigatórios. Use paraFiscal(municipio: X, uf: Y).');
        }

        if ($this->itens === []) {
            throw new InvalidArgumentException('Ao menos um item é necessário. Use addItem(ItemDTO).');
        }

        return CalculoRequestDTO::make(
            municipio: $this->municipio,
            uf: $this->uf,
            itens: $this->itens,
            dataHoraEmissao: $this->dataHoraEmissao ?? now()->toIso8601String(),
            id: $this->id ?? Str::uuid()->toString(),
        );
    }

    private function reset(): void
    {
        $this->municipio = null;
        $this->uf = null;
        $this->dataHoraEmissao = null;
        $this->id = null;
        $this->itens = [];
    }
}
