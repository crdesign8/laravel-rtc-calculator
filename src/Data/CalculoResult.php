<?php

namespace Crdesign8\LaravelRtcCalculator\Data;

/**
 * Representa o resultado completo de um cálculo RTC.
 *
 * @note Os campos tipados e os acessores específicos serão definidos no Milestone 4,
 *       após análise do JSON de saída do endpoint /regime-geral.
 */
class CalculoResult
{
    /**
     * @param  ItemResult[]  $itens
     */
    public function __construct(
        private string $id,
        private string $versao,
        private array $itens,
        private TotaisResult $totais,
        private array $raw,
    ) {}

    public static function fromArray(array $data): self
    {
        $itens = array_map(
            fn (array $item) => ItemResult::fromArray($item),
            $data['itens'] ?? [],
        );

        $totais = TotaisResult::fromArray($data['totais'] ?? []);

        return new self(
            id:     $data['id'] ?? '',
            versao: $data['versao'] ?? '',
            itens:  $itens,
            totais: $totais,
            raw:    $data,
        );
    }

    public function toArray(): array
    {
        return $this->raw;
    }

    public function toJson(): string
    {
        return json_encode($this->raw, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getVersao(): string
    {
        return $this->versao;
    }

    /**
     * @return ItemResult[]
     */
    public function getItens(): array
    {
        return $this->itens;
    }

    public function getTotais(): TotaisResult
    {
        return $this->totais;
    }
}
