<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\DTOs;

use Crdesign8\LaravelRtcCalculator\Enums\UnidadeMedida;

use function array_key_exists;

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

    /**
     * @return array{numero: int, ncm: string, quantidade: float, unidade: string, cst: string, baseCalculo: float, cClassTrib: string, tributacaoRegular?: array{cst: string, cClassTrib: string}, impostoSeletivo?: array{cst: string, baseCalculo: float, cClassTrib: string, unidade: string, quantidade: float, impostoInformado: float}}
     */
    public function toArray(): array
    {
        $data = [
            'numero' => $this->numero,
            'ncm' => $this->ncm,
            'quantidade' => $this->quantidade,
            'unidade' => $this->unidade->value,
            'cst' => $this->cst,
            'baseCalculo' => $this->baseCalculo,
            'cClassTrib' => $this->cClassTrib,
        ];

        if ($this->tributacaoRegular !== null) {
            $data['tributacaoRegular'] = $this->tributacaoRegular->toArray();
        }

        if ($this->impostoSeletivo !== null) {
            $data['impostoSeletivo'] = $this->impostoSeletivo->toArray();
        }

        return $data;
    }

    /**
     * @param array{numero: int|string, ncm: string, quantidade: float|int|string, unidade: string, cst: string, baseCalculo: float|int|string, cClassTrib: string, tributacaoRegular?: array{cst: string, cClassTrib: string}, impostoSeletivo?: array{cst: string, baseCalculo: float|int|string, cClassTrib: string, unidade: string, quantidade: float|int|string, impostoInformado?: float|int|string}} $data
     */
    public static function fromArray(array $data): self
    {
        $trRaw = array_key_exists('tributacaoRegular', $data) ? $data['tributacaoRegular'] : null;
        $isRaw = array_key_exists('impostoSeletivo', $data) ? $data['impostoSeletivo'] : null;

        return new self(
            numero: (int) $data['numero'],
            ncm: $data['ncm'],
            quantidade: (float) $data['quantidade'],
            unidade: UnidadeMedida::from($data['unidade']),
            cst: $data['cst'],
            baseCalculo: (float) $data['baseCalculo'],
            cClassTrib: $data['cClassTrib'],
            tributacaoRegular: $trRaw !== null ? TributacaoRegularDTO::fromArray($trRaw) : null,
            impostoSeletivo: $isRaw !== null ? ImpostoSeletivoDTO::fromArray($isRaw) : null,
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

    // ──────────────────────────────────────────────────────────────────────────
    // Fluent builder
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Inicia o builder fluente para um item.
     *
     * Exemplo:
     *   ItemDTO::make(1)
     *       ->ncm('24021000')
     *       ->quantidade(222)
     *       ->unidade(UnidadeMedida::VN)
     *       ->cst('550')
     *       ->baseCalculo(1111.00)
     *       ->cClassTrib('550020')
     *       ->tributacaoRegular('200', '200032')
     *       ->impostoSeletivo(cst: '000', baseCalculo: 1111.00, cClassTrib: '000001',
     *                         unidade: UnidadeMedida::VN, quantidade: 222, impostoInformado: 0)
     */
    public static function make(int $numero): self
    {
        return new self(
            numero: $numero,
            ncm: '',
            quantidade: 0,
            unidade: UnidadeMedida::VN,
            cst: '',
            baseCalculo: 0,
            cClassTrib: '',
        );
    }

    public function ncm(string $ncm): static
    {
        $this->ncm = $ncm;

        return $this;
    }

    public function quantidade(float $quantidade): static
    {
        $this->quantidade = $quantidade;

        return $this;
    }

    public function unidade(UnidadeMedida $unidade): static
    {
        $this->unidade = $unidade;

        return $this;
    }

    public function cst(string $cst): static
    {
        $this->cst = $cst;

        return $this;
    }

    public function baseCalculo(float $baseCalculo): static
    {
        $this->baseCalculo = $baseCalculo;

        return $this;
    }

    public function cClassTrib(string $cClassTrib): static
    {
        $this->cClassTrib = $cClassTrib;

        return $this;
    }

    public function tributacaoRegular(string $cst, string $cClassTrib): static
    {
        $this->tributacaoRegular = new TributacaoRegularDTO(cst: $cst, cClassTrib: $cClassTrib);

        return $this;
    }

    public function impostoSeletivo(
        string $cst,
        float $baseCalculo,
        string $cClassTrib,
        UnidadeMedida $unidade,
        float $quantidade,
        float $impostoInformado = 0,
    ): static {
        $this->impostoSeletivo = new ImpostoSeletivoDTO(
            cst: $cst,
            baseCalculo: $baseCalculo,
            cClassTrib: $cClassTrib,
            unidade: $unidade,
            quantidade: $quantidade,
            impostoInformado: $impostoInformado,
        );

        return $this;
    }
}
