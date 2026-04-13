<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\DTOs;

use Crdesign8\LaravelRtcCalculator\Enums\Uf;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;
use DateTimeImmutable;
use Throwable;

use function array_key_exists;
use function array_map;
use function count;
use function now;
use function preg_match;
use function strtoupper;
use function trim;

class CalculoRequestDTO
{
    /**
     * @param  ItemDTO[]  $itens
     */
    public function __construct(
        private string $id,
        private string $versao,
        private string $dataHoraEmissao,
        private int $municipio,
        private Uf $uf,
        private array $itens = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'versao' => $this->versao,
            'dataHoraEmissao' => $this->dataHoraEmissao,
            'municipio' => $this->municipio,
            'uf' => $this->uf->value,
            'itens' => array_map(
                /** @return array<string, mixed> */
                static fn (ItemDTO $item): array => $item->toArray(),
                $this->itens,
            ),
        ];
    }

    /**
     * @param array{id: string, versao: string, dataHoraEmissao: string, municipio: int|string, uf: string, itens?: list<array{numero: int|string, ncm: string, quantidade: float|int|string, unidade: string, cst: string, baseCalculo: float|int|string, cClassTrib: string, tributacaoRegular?: array{cst: string, cClassTrib: string}, impostoSeletivo?: array{cst: string, baseCalculo: float|int|string, cClassTrib: string, unidade: string, quantidade: float|int|string, impostoInformado?: float|int|string}}>} $data
     */
    public static function fromArray(array $data): self
    {
        $itens = array_key_exists('itens', $data) ? $data['itens'] : [];

        return new self(
            id: $data['id'],
            versao: $data['versao'],
            dataHoraEmissao: $data['dataHoraEmissao'],
            municipio: (int) $data['municipio'],
            uf: Uf::from($data['uf']),
            itens: array_map(
                /**
                 * @param array{numero: int|string, ncm: string, quantidade: float|int|string, unidade: string, cst: string, baseCalculo: float|int|string, cClassTrib: string, tributacaoRegular?: array{cst: string, cClassTrib: string}, impostoSeletivo?: array{cst: string, baseCalculo: float|int|string, cClassTrib: string, unidade: string, quantidade: float|int|string, impostoInformado?: float|int|string}} $item
                 */
                ItemDTO::fromArray(...),
                $itens,
            ),
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getVersao(): string
    {
        return $this->versao;
    }

    public function getDataHoraEmissao(): string
    {
        return $this->dataHoraEmissao;
    }

    public function getMunicipio(): int
    {
        return $this->municipio;
    }

    public function getUf(): Uf
    {
        return $this->uf;
    }

    /**
     * @return ItemDTO[]
     */
    public function getItens(): array
    {
        return $this->itens;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Fluent factory
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Cria um CalculoRequestDTO de forma fluente.
     *
     * Exemplo:
     *   CalculoRequestDTO::make(
     *       municipio: 4314902,
     *       uf: 'RS',
     *       itens: [$item1, $item2],
     *       dataHoraEmissao: '2027-01-01T03:00:00-03:00',
     *   )
     *
     * @param  ItemDTO[]  $itens
     */
    public static function make(
        int $municipio,
        string $uf,
        array $itens = [],
        ?string $dataHoraEmissao = null,
        ?string $id = null,
        string $versao = '1.0.0',
    ): self {
        return new self(
            id: $id ?? \Illuminate\Support\Str::uuid()->toString(),
            versao: $versao,
            dataHoraEmissao: $dataHoraEmissao ?? now()->toIso8601String(),
            municipio: $municipio,
            uf: Uf::from(strtoupper($uf)),
            itens: $itens,
        );
    }

    public function validate(): void
    {
        $errors = [];

        if (trim($this->id) === '') {
            $errors['id'] = ['id é obrigatório e não pode ser vazio.'];
        }

        if (! preg_match('/^\d+\.\d+\.\d+$/', trim($this->versao))) {
            $errors['versao'] = ['versao deve estar no formato semântico x.y.z (ex.: 1.0.0).'];
        }

        if (trim($this->dataHoraEmissao) === '') {
            $errors['dataHoraEmissao'] = ['dataHoraEmissao é obrigatória e deve estar em ISO 8601.'];
        } else {
            try {
                new DateTimeImmutable($this->dataHoraEmissao);
            } catch (Throwable) {
                $errors['dataHoraEmissao'] = [
                    'dataHoraEmissao inválida. Use formato ISO 8601 (ex.: 2027-01-01T03:00:00-03:00).',
                ];
            }
        }

        if ($this->municipio <= 0) {
            $errors['municipio'] = ['municipio deve ser um código IBGE válido (maior que zero).'];
        }

        if (count($this->itens) === 0) {
            $errors['itens'] = ['A requisição deve conter ao menos um item.'];
        }

        foreach ($this->itens as $index => $item) {
            try {
                $item->validate();
            } catch (RtcValidationException $e) {
                $itemErrors = $e->getErrors();

                if ($itemErrors === []) {
                    $errors['itens.'.($index + 1)] = [$e->getMessage()];

                    continue;
                }

                foreach ($itemErrors as $field => $messages) {
                    $errors['itens.'.($index + 1).'.'.$field] = $messages;
                }
            }
        }

        if ($errors !== []) {
            throw new RtcValidationException('CalculoRequestDTO inválido.', $errors);
        }
    }
}
