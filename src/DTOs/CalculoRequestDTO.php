<?php

namespace Crdesign8\LaravelRtcCalculator\DTOs;

use Crdesign8\LaravelRtcCalculator\Enums\Uf;

class CalculoRequestDTO
{
    /**
     * @param  ItemDTO[]  $itens
     */
    public function __construct(
        private string $id,
        private string $versao,
        private string $dataHoraEmissao,
        private int $municipio,
        private Uf $uf,
        private array $itens = [],
    ) {}

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'versao'           => $this->versao,
            'dataHoraEmissao'  => $this->dataHoraEmissao,
            'municipio'        => $this->municipio,
            'uf'               => $this->uf->value,
            'itens'            => array_map(fn (ItemDTO $item) => $item->toArray(), $this->itens),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:               $data['id'],
            versao:           $data['versao'],
            dataHoraEmissao:  $data['dataHoraEmissao'],
            municipio:        (int) $data['municipio'],
            uf:               Uf::from($data['uf']),
            itens:            array_map(
                                  fn (array $item) => ItemDTO::fromArray($item),
                                  $data['itens'] ?? []
                              ),
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getVersao(): string
    {
        return $this->versao;
    }

    public function getDataHoraEmissao(): string
    {
        return $this->dataHoraEmissao;
    }

    public function getMunicipio(): int
    {
        return $this->municipio;
    }

    public function getUf(): Uf
    {
        return $this->uf;
    }

    /**
     * @return ItemDTO[]
     */
    public function getItens(): array
    {
        return $this->itens;
    }
}
