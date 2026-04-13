<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\DTOs;

use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;

use function preg_match;
use function trim;

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

    public function validate(): void
    {
        $errors = [];

        if (! preg_match('/^\d{3}$/', trim($this->cst))) {
            $errors['cst'] = ['CST da tributacaoRegular deve conter exatamente 3 dígitos numéricos.'];
        }

        if (! preg_match('/^\d{6}$/', trim($this->cClassTrib))) {
            $errors['cClassTrib'] = ['cClassTrib da tributacaoRegular deve conter exatamente 6 dígitos numéricos.'];
        }

        if ($errors !== []) {
            throw new RtcValidationException('TributacaoRegularDTO inválido.', $errors);
        }
    }
}
