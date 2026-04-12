<?php

namespace Crdesign8\LaravelRtcCalculator\DTOs;

use Crdesign8\LaravelRtcCalculator\Enums\UnidadeMedida;

class ItemDTO
{
    public function __construct(
        private int $numero,
        private string $ncm,
        private float $quantidade,
        private UnidadeMedida $unidade,
        private string $cst,
        private float $baseCalculo,
        private string $cClassTrib,
        private ?TributacaoRegularDTO $tributacaoRegular = null,
        private ?ImpostoSeletivoDTO $impostoSeletivo = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'numero'       => $this->numero,
            'ncm'          => $this->ncm,
            'quantidade'   => $this->quantidade,
            'unidade'      => $this->unidade->value,
            'cst'          => $this->cst,
            'baseCalculo'  => $this->baseCalculo,
            'cClassTrib'   => $this->cClassTrib,
        ];

        if ($this->tributacaoRegular !== null) {
            $data['tributacaoRegular'] = $this->tributacaoRegular->toArray();
        }

        if ($this->impostoSeletivo !== null) {
            $data['impostoSeletivo'] = $this->impostoSeletivo->toArray();
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            numero:              (int) $data['numero'],
            ncm:                 $data['ncm'],
            quantidade:          (float) $data['quantidade'],
            unidade:             UnidadeMedida::from($data['unidade']),
            cst:                 $data['cst'],
            baseCalculo:         (float) $data['baseCalculo'],
            cClassTrib:          $data['cClassTrib'],
            tributacaoRegular:   isset($data['tributacaoRegular'])
                                     ? TributacaoRegularDTO::fromArray($data['tributacaoRegular'])
                                     : null,
            impostoSeletivo:     isset($data['impostoSeletivo'])
                                     ? ImpostoSeletivoDTO::fromArray($data['impostoSeletivo'])
                                     : null,
        );
    }

    public function getNumero(): int
    {
        return $this->numero;
    }

    public function getNcm(): string
    {
        return $this->ncm;
    }

    public function getQuantidade(): float
    {
        return $this->quantidade;
    }

    public function getUnidade(): UnidadeMedida
    {
        return $this->unidade;
    }

    public function getCst(): string
    {
        return $this->cst;
    }

    public function getBaseCalculo(): float
    {
        return $this->baseCalculo;
    }

    public function getCClassTrib(): string
    {
        return $this->cClassTrib;
    }

    public function getTributacaoRegular(): ?TributacaoRegularDTO
    {
        return $this->tributacaoRegular;
    }

    public function getImpostoSeletivo(): ?ImpostoSeletivoDTO
    {
        return $this->impostoSeletivo;
    }
}
