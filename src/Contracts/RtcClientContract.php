<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Contracts;

use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\Enums\TipoDocumento;

interface RtcClientContract
{
    /**
     * Calcula os tributos da Reforma Tributária do Consumo
     * para um conjunto de itens no regime geral.
     *
     * @param  CalculoRequestDTO  $dto  Dados da nota (itens, município, UF, etc.)
     * @return CalculoResult           Resultado do cálculo com IS, IBS e CBS por item e totais
     *
     * @throws \Crdesign8\LaravelRtcCalculator\Exceptions\RtcConnectionException
     * @throws \Crdesign8\LaravelRtcCalculator\Exceptions\RtcCalculationException
     */
    public function calcularRegimeGeral(CalculoRequestDTO $dto): CalculoResult;

    /**
     * Gera o XML com os grupos RTC (IS, IBSCBS, ISTot, IBSCBSTot)
     * a partir do resultado do cálculo.
     *
     * @param  CalculoResult  $result  Resultado de um cálculo anterior
     * @param  TipoDocumento  $tipo    Tipo do documento fiscal (NFe, NFCe, CTe)
     * @return string                  Conteúdo XML gerado
     *
     * @throws \Crdesign8\LaravelRtcCalculator\Exceptions\RtcConnectionException
     */
    public function gerarXml(CalculoResult $result, TipoDocumento $tipo): string;

    /**
     * Valida a estrutura de um XML RTC gerado.
     *
     * @param  string  $xml  Conteúdo XML a ser validado
     * @return bool          true se o XML é válido
     *
     * @throws \Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException
     * @throws \Crdesign8\LaravelRtcCalculator\Exceptions\RtcConnectionException
     */
    public function validarXml(string $xml): bool;
}
