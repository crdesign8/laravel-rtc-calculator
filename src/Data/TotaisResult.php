<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Data;

use function array_keys;
use function data_get;
use function is_array;

/**
 * Representa os totalizadores do cálculo RTC.
 *
 * Estrutura real do endpoint POST /api/calculadora/regime-geral:
 * {
 *   "total": {
 *     "tribCalc": {
 *       "ISTot":    { "vIS": "0.00" },
 *       "IBSCBSTot": {
 *         "vBCIBSCBS": "5984.03",
 *         "gIBS": {
 *           "gIBSUF":  { "vDif", "vDevTrib", "vIBSUF" },
 *           "gIBSMun": { "vDif", "vDevTrib", "vIBSMun" },
 *           "vIBS", "vCredPres", "vCredPresCondSus"
 *         },
 *         "gCBS": { "vDif", "vDevTrib", "vCBS", "vCredPres", "vCredPresCondSus" }
 *       }
 *     }
 *   }
 * }
 */
class TotaisResult
{
    /**
     * @param array<string, mixed> $isTot
     * @param array<string, mixed> $ibsCbsTot
     * @param array<string, mixed> $raw
     */
    public function __construct(
        private array $isTot,
        private array $ibsCbsTot,
        private array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $tribCalc = self::asAssociativeArray(data_get(target: $data, key: 'tribCalc', default: $data));

        $isTot = self::asAssociativeArray(data_get(target: $tribCalc, key: 'ISTot', default: []));

        $ibsCbsTot = self::asAssociativeArray(data_get(target: $tribCalc, key: 'IBSCBSTot', default: []));

        return new self(isTot: $isTot, ibsCbsTot: $ibsCbsTot, raw: $data);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Imposto Seletivo — Total (ISTot)
    // ──────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function getIsTot(): array
    {
        return $this->isTot;
    }

    public function getVIsTot(): string
    {
        return (string) data_get(target: $this->isTot, key: 'vIS', default: '0.00');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // IBS + CBS — Total (IBSCBSTot)
    // ──────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function getIbsCbsTot(): array
    {
        return $this->ibsCbsTot;
    }

    public function getVBcIbsCbs(): string
    {
        return (string) data_get(target: $this->ibsCbsTot, key: 'vBCIBSCBS', default: '0.00');
    }

    public function getVIbsTot(): string
    {
        return (string) data_get(target: $this->ibsCbsTot, key: 'gIBS.vIBS', default: '0.00');
    }

    public function getVIbsUfTot(): string
    {
        return (string) data_get(target: $this->ibsCbsTot, key: 'gIBS.gIBSUF.vIBSUF', default: '0.00');
    }

    public function getVIbsMunTot(): string
    {
        return (string) data_get(target: $this->ibsCbsTot, key: 'gIBS.gIBSMun.vIBSMun', default: '0.00');
    }

    public function getVCbsTot(): string
    {
        return (string) data_get(target: $this->ibsCbsTot, key: 'gCBS.vCBS', default: '0.00');
    }

    public function getVCredPresTot(): string
    {
        return (string) data_get(target: $this->ibsCbsTot, key: 'gIBS.vCredPres', default: '0.00');
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
}
