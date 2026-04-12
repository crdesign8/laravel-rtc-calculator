<?php

namespace Crdesign8\LaravelRtcCalculator\DTOs;

use Crdesign8\LaravelRtcCalculator\Enums\UnidadeMedida;

class ImpostoSeletivoDTO
{
    public function __construct(
        private string $cst,
        private float $baseCalculo,
        private string $cClassTrib,
        private UnidadeMedida $unidade,
        private float $quantidade,
        private float $impostoInformado,
    ) {}

    public function toArray(): array
    {
        return [
            'cst'               => $this->cst,
            'baseCalculo'       => $this->baseCalculo,
            'cClassTrib'        => $this->cClassTrib,
            'unidade'           => $this->unidade->value,
            'quantidade'        => $this->quantidade,
            'impostoInformado'  => $this->impostoInformado,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            cst:               $data['cst'],
            baseCalculo:       (float) $data['baseCalculo'],
            cClassTrib:        $data['cClassTrib'],
            unidade:           UnidadeMedida::from($data['unidade']),
            quantidade:        (float) $data['quantidade'],
            impostoInformado:  (float) $data['impostoInformado'],
        );
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

    public function getUnidade(): UnidadeMedida
    {
        return $this->unidade;
    }

    public function getQuantidade(): float
    {
        return $this->quantidade;
    }

    public function getImpostoInformado(): float
    {
        return $this->impostoInformado;
    }
}
