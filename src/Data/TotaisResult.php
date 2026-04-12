<?php

namespace Crdesign8\LaravelRtcCalculator\Data;

/**
 * Representa os totalizadores do cálculo RTC (ISTot, IBSCBSTot e totais gerais).
 *
 * @note Os campos tipados serão definidos no Milestone 4,
 *       após análise do JSON de saída do endpoint /regime-geral.
 */
class TotaisResult
{
    public function __construct(
        private array $data,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
