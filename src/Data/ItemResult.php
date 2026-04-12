<?php

namespace Crdesign8\LaravelRtcCalculator\Data;

/**
 * Representa um item calculado retornado pela calculadora RTC.
 *
 * @note Os campos tipados serão definidos no Milestone 4,
 *       após análise do JSON de saída do endpoint /regime-geral.
 */
class ItemResult
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
