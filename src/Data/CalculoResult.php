<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Data;

use function array_keys;
use function is_array;
use function json_encode;

/**
 * Representa o resultado completo de um cálculo RTC.
 *
 * Estrutura real do endpoint POST /api/calculadora/regime-geral:
 * {
 *   "objetos": [ { "nObj": 1, "tribCalc": { "IS": {...}, "IBSCBS": {...} } } ],
 *   "total":   { "tribCalc": { "ISTot": {...}, "IBSCBSTot": {...} } }
 * }
 *
 * O array raw é preservado integralmente para ser reenviado ao endpoint
 * POST /api/calculadora/xml/generate sem transformações.
 */
class CalculoResult
{
    /**
     * @param  ItemResult[]  $objetos
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        private array $objetos,
        private TotaisResult $total,
        private array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var ItemResult[] $objetos */
        $objetos = [];

        foreach (self::asListOfAssociativeArray($data['objetos'] ?? []) as $objetoRaw) {
            $objetos[] = ItemResult::fromArray($objetoRaw);
        }

        $total = TotaisResult::fromArray(self::asAssociativeArray($data['total'] ?? []));

        return new self(objetos: $objetos, total: $total, raw: $data);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }

    public function toJson(): string
    {
        return json_encode($this->raw, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @return ItemResult[]
     */
    public function getObjetos(): array
    {
        return $this->objetos;
    }

    public function getTotal(): TotaisResult
    {
        return $this->total;
    }

    /** Atalho: retorna o ItemResult de um item pelo número (nObj) */
    public function getItem(int $nObj): ?ItemResult
    {
        foreach ($this->objetos as $item) {
            if ($item->getNObj() === $nObj) {
                return $item;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private static function asAssociativeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach (array_keys($value) as $key) {
            $normalized[(string) $key] = $value[$key];
        }

        return $normalized;
    }

    /** @return list<array<string, mixed>> */
    private static function asListOfAssociativeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach (array_keys($value) as $key) {
            $normalized[] = self::asAssociativeArray($value[$key]);
        }

        return $normalized;
    }
}
