<?php

namespace Crdesign8\LaravelRtcCalculator\Data;

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
    public function __construct(
        private array $isTot,
        private array $ibsCbsTot,
        private array $raw,
    ) {}

    public static function fromArray(array $data): self
    {
        $tribCalc = $data['tribCalc'] ?? $data;

        return new self(
            isTot:    $tribCalc['ISTot'] ?? [],
            ibsCbsTot: $tribCalc['IBSCBSTot'] ?? [],
            raw:      $data,
        );
    }

    public function toArray(): array
    {
        return $this->raw;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Imposto Seletivo — Total (ISTot)
    // ──────────────────────────────────────────────────────────────────────────

    /** Bloco ISTot completo como array */
    public function getIsTot(): array
    {
        return $this->isTot;
    }

    public function getVIsTot(): string
    {
        return (string) ($this->isTot['vIS'] ?? '0.00');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // IBS + CBS — Total (IBSCBSTot)
    // ──────────────────────────────────────────────────────────────────────────

    /** Bloco IBSCBSTot completo como array */
    public function getIbsCbsTot(): array
    {
        return $this->ibsCbsTot;
    }

    public function getVBcIbsCbs(): string
    {
        return (string) ($this->ibsCbsTot['vBCIBSCBS'] ?? '0.00');
    }

    public function getVIbsTot(): string
    {
        return (string) ($this->ibsCbsTot['gIBS']['vIBS'] ?? '0.00');
    }

    public function getVIbsUfTot(): string
    {
        return (string) ($this->ibsCbsTot['gIBS']['gIBSUF']['vIBSUF'] ?? '0.00');
    }

    public function getVIbsMunTot(): string
    {
        return (string) ($this->ibsCbsTot['gIBS']['gIBSMun']['vIBSMun'] ?? '0.00');
    }

    public function getVCbsTot(): string
    {
        return (string) ($this->ibsCbsTot['gCBS']['vCBS'] ?? '0.00');
    }

    public function getVCredPresTot(): string
    {
        return (string) ($this->ibsCbsTot['gIBS']['vCredPres'] ?? '0.00');
    }
}

