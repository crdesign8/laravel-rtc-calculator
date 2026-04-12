<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Data;

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
    public function __construct(
        private int $nObj,
        private array $is,
        private array $ibsCbs,
        private array $raw,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            nObj: (int) ($data['nObj'] ?? 0),
            is: $data['tribCalc']['IS'] ?? [],
            ibsCbs: $data['tribCalc']['IBSCBS'] ?? [],
            raw: $data,
        );
    }

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

    /** Bloco IS completo como array */
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

    /** Bloco IBSCBS completo como array */
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

    /** Bloco gTribRegular como array (pode ser null se CST não tiver tributação regular) */
    public function getTribRegular(): ?array
    {
        return $this->ibsCbs['gIBSCBS']['gTribRegular'] ?? null;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ──────────────────────────────────────────────────────────────────────────

    private function getIsField(string $key, string $default = ''): string
    {
        return (string) ($this->is[$key] ?? $default);
    }

    private function getIbsCbsField(string $key, string $default = ''): string
    {
        return (string) ($this->ibsCbs[$key] ?? $default);
    }

    /**
     * @param  string[]  $keys  Caminho de chaves para acesso aninhado
     */
    private function getIbsCbsNestedField(array $keys, string $default = ''): string
    {
        $node = $this->ibsCbs;

        foreach ($keys as $key) {
            if (!is_array($node) || !array_key_exists($key, $node)) {
                return $default;
            }

            $node = $node[$key];
        }

        return (string) $node;
    }
}
