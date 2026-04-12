<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Data;

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
     */
    public function __construct(
        private array $objetos,
        private TotaisResult $total,
        private array $raw,
    ) {}

    public static function fromArray(array $data): self
    {
        $objetos = array_map(fn(array $item) => ItemResult::fromArray($item), $data['objetos'] ?? []);

        $total = TotaisResult::fromArray($data['total'] ?? []);

        return new self(objetos: $objetos, total: $total, raw: $data);
    }

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
}
