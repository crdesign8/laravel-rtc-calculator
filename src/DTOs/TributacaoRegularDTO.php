<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\DTOs;

class TributacaoRegularDTO
{
    public function __construct(
        private string $cst,
        private string $cClassTrib,
    ) {}

    /**
     * @return array{cst: string, cClassTrib: string}
     */
    public function toArray(): array
    {
        return [
            'cst' => $this->cst,
            'cClassTrib' => $this->cClassTrib,
        ];
    }

    /**
     * @param array{cst: string, cClassTrib: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(cst: $data['cst'], cClassTrib: $data['cClassTrib']);
    }

    public function getCst(): string
    {
        return $this->cst;
    }

    public function getCClassTrib(): string
    {
        return $this->cClassTrib;
    }
}
