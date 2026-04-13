<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Data;

use function array_keys;
use function data_get;
use function is_array;
use function is_scalar;

/**
 * Representa um objeto (item calculado) retornado pela calculadora RTC.
 *
 * Estrutura real do endpoint POST /api/calculadora/regime-geral:
 * {
 *   "nObj": 1,
 *   "tribCalc": {
 *     "IS": { "CSTIS", "vBCIS", "pIS", "vIS", "uTrib", "qTrib", ... },
 *     "IBSCBS": {
 *       "CST", "cClassTrib",
 *       "gIBSCBS": {
 *         "vBC", "gIBSUF": { "pIBSUF", "vIBSUF" }, "gIBSMun": { "pIBSMun", "vIBSMun" },
 *         "vIBS", "gCBS": { "pCBS", "vCBS" },
 *         "gTribRegular": { "CSTReg", "cClassTribReg", "pAliqEfetReg*", "vTribReg*" }
 *       }
 *     }
 *   }
 * }
 */
class ItemResult
{
    /**
     * @param array<string, mixed> $is
     * @param array<string, mixed> $ibsCbs
     * @param array<string, mixed> $raw
     */
    public function __construct(
        private int $nObj,
        private array $is,
        private array $ibsCbs,
        private array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $tribCalc = self::asAssociativeArray(data_get(target: $data, key: 'tribCalc', default: []));
        $is = self::asAssociativeArray(data_get(target: $tribCalc, key: 'IS', default: []));
        $ibsCbs = self::asAssociativeArray(data_get(target: $tribCalc, key: 'IBSCBS', default: []));

        return new self(
            nObj: (int) ($data['nObj'] ?? 0),
            is: $is,
            ibsCbs: $ibsCbs,
            raw: $data,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Identificação
    // ──────────────────────────────────────────────────────────────────────────

    public function getNObj(): int
    {
        return $this->nObj;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Imposto Seletivo (IS)
    // ──────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function getIs(): array
    {
        return $this->is;
    }

    public function getCstIs(): string
    {
        return $this->getIsField('CSTIS');
    }

    public function getVBcIs(): string
    {
        return $this->getIsField('vBCIS', '0.00');
    }

    public function getPIs(): string
    {
        return $this->getIsField('pIS', '0.00');
    }

    public function getVIs(): string
    {
        return $this->getIsField('vIS', '0.00');
    }

    public function getMemoriaCalculoIs(): string
    {
        return $this->getIsField('memoriaCalculo');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // IBS + CBS (IBSCBS)
    // ──────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function getIbsCbs(): array
    {
        return $this->ibsCbs;
    }

    public function getCstIbsCbs(): string
    {
        return $this->getIbsCbsField('CST');
    }

    public function getVBcIbsCbs(): string
    {
        return $this->getIbsCbsNestedField(['gIBSCBS', 'vBC'], '0.00');
    }

    public function getVIbs(): string
    {
        return $this->getIbsCbsNestedField(['gIBSCBS', 'vIBS'], '0.00');
    }

    public function getVIbsUf(): string
    {
        return $this->getIbsCbsNestedField(['gIBSCBS', 'gIBSUF', 'vIBSUF'], '0.00');
    }

    public function getVIbsMun(): string
    {
        return $this->getIbsCbsNestedField(['gIBSCBS', 'gIBSMun', 'vIBSMun'], '0.00');
    }

    public function getVCbs(): string
    {
        return $this->getIbsCbsNestedField(['gIBSCBS', 'gCBS', 'vCBS'], '0.00');
    }

    /** @return array<string, mixed>|null */
    public function getTribRegular(): ?array
    {
        return self::asNullableAssociativeArray(data_get(target: $this->ibsCbs, key: 'gIBSCBS.gTribRegular'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ──────────────────────────────────────────────────────────────────────────

    private function getIsField(string $key, string $default = ''): string
    {
        /** @var mixed $value */
        $value = data_get($this->is, $key, $default);

        return $this->stringOrDefault($value, $default);
    }

    private function getIbsCbsField(string $key, string $default = ''): string
    {
        /** @var mixed $value */
        $value = data_get($this->ibsCbs, $key, $default);

        return $this->stringOrDefault($value, $default);
    }

    /**
     * @param  string[]  $keys  Caminho de chaves para acesso aninhado
     */
    private function getIbsCbsNestedField(array $keys, string $default = ''): string
    {
        $path = \implode(separator: '.', array: $keys);

        /** @var mixed $value */
        $value = data_get($this->ibsCbs, $path, $default);

        return $this->stringOrDefault($value, $default);
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        if (! is_scalar($value)) {
            return $default;
        }

        return (string) $value;
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

    /** @return array<string, mixed>|null */
    private static function asNullableAssociativeArray(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $normalized = [];

        foreach (array_keys($value) as $key) {
            $normalized[(string) $key] = $value[$key];
        }

        return $normalized;
    }
}
