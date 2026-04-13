<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Tests\Unit\DTOs;

use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\DTOs\ImpostoSeletivoDTO;
use Crdesign8\LaravelRtcCalculator\DTOs\ItemDTO;
use Crdesign8\LaravelRtcCalculator\DTOs\TributacaoRegularDTO;
use Crdesign8\LaravelRtcCalculator\Enums\Uf;
use Crdesign8\LaravelRtcCalculator\Enums\UnidadeMedida;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;
use PHPUnit\Framework\TestCase;

class CalculoRequestDTOTest extends TestCase
{
    private function fixture(): array
    {
        return json_decode(
            file_get_contents(__DIR__.'/../../Fixtures/entrada-regime-geral.json'),
            associative: true,
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // fromArray
    // ──────────────────────────────────────────────────────────────────────────

    public function test_from_array_popula_campos_escalares(): void
    {
        $dto = CalculoRequestDTO::fromArray($this->fixture());

        $this->assertSame('507f1f77bcf86cd799439011', $dto->getId());
        $this->assertSame('1.0.0', $dto->getVersao());
        $this->assertSame('2027-01-01T03:00:00-03:00', $dto->getDataHoraEmissao());
        $this->assertSame(4314902, $dto->getMunicipio());
        $this->assertSame(Uf::RS, $dto->getUf());
    }

    public function test_from_array_constroi_itens(): void
    {
        $dto = CalculoRequestDTO::fromArray($this->fixture());

        $this->assertCount(1, $dto->getItens());
        $this->assertInstanceOf(ItemDTO::class, $dto->getItens()[0]);
    }

    public function test_from_array_item_popula_campos_obrigatorios(): void
    {
        $item = CalculoRequestDTO::fromArray($this->fixture())->getItens()[0];

        $this->assertSame(1, $item->getNumero());
        $this->assertSame('24021000', $item->getNcm());
        $this->assertSame(222.0, $item->getQuantidade());
        $this->assertSame(UnidadeMedida::VN, $item->getUnidade());
        $this->assertSame('550', $item->getCst());
        $this->assertSame(1111.0, $item->getBaseCalculo());
        $this->assertSame('550020', $item->getCClassTrib());
    }

    public function test_from_array_item_popula_tributacao_regular(): void
    {
        $item = CalculoRequestDTO::fromArray($this->fixture())->getItens()[0];

        $this->assertInstanceOf(TributacaoRegularDTO::class, $item->getTributacaoRegular());
        $this->assertSame('200', $item->getTributacaoRegular()->getCst());
        $this->assertSame('200032', $item->getTributacaoRegular()->getCClassTrib());
    }

    public function test_from_array_item_popula_imposto_seletivo(): void
    {
        $item = CalculoRequestDTO::fromArray($this->fixture())->getItens()[0];

        $this->assertInstanceOf(ImpostoSeletivoDTO::class, $item->getImpostoSeletivo());
        $this->assertSame('000', $item->getImpostoSeletivo()->getCst());
        $this->assertSame(1111.0, $item->getImpostoSeletivo()->getBaseCalculo());
        $this->assertSame('000001', $item->getImpostoSeletivo()->getCClassTrib());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // toArray
    // ──────────────────────────────────────────────────────────────────────────

    public function test_to_array_roundtrip_preserva_estrutura(): void
    {
        $original = $this->fixture();
        $result = CalculoRequestDTO::fromArray($original)->toArray();

        $this->assertSame($original['id'], $result['id']);
        $this->assertSame($original['versao'], $result['versao']);
        $this->assertSame($original['uf'], $result['uf']);
        $this->assertSame($original['municipio'], $result['municipio']);
        $this->assertCount(count($original['itens']), $result['itens']);
    }

    public function test_to_array_uf_serializada_como_string(): void
    {
        $result = CalculoRequestDTO::fromArray($this->fixture())->toArray();

        $this->assertSame('RS', $result['uf']);
    }

    public function test_to_array_unidade_serializada_como_string(): void
    {
        $result = CalculoRequestDTO::fromArray($this->fixture())->toArray();

        $this->assertSame('VN', $result['itens'][0]['unidade']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // make() fluent factory
    // ──────────────────────────────────────────────────────────────────────────

    public function test_make_com_valores_explicitos(): void
    {
        $item = ItemDTO::make(1)
            ->ncm('24021000')
            ->quantidade(222)
            ->unidade(UnidadeMedida::VN)
            ->cst('550')
            ->baseCalculo(1111.0)
            ->cClassTrib('550020');

        $dto = CalculoRequestDTO::make(
            municipio: 4314902,
            uf: 'RS',
            itens: [$item],
            dataHoraEmissao: '2027-01-01T03:00:00-03:00',
            id: 'test-id-123',
        );

        $this->assertSame('test-id-123', $dto->getId());
        $this->assertSame('1.0.0', $dto->getVersao());
        $this->assertSame(Uf::RS, $dto->getUf());
        $this->assertCount(1, $dto->getItens());
    }

    public function test_make_aceita_uf_em_minusculo(): void
    {
        $dto = CalculoRequestDTO::make(
            municipio: 4314902,
            uf: 'rs',
            dataHoraEmissao: '2027-01-01T03:00:00-03:00',
            id: 'qualquer',
        );

        $this->assertSame(Uf::RS, $dto->getUf());
    }

    public function test_make_versao_padrao_e_1_0_0(): void
    {
        $dto = CalculoRequestDTO::make(
            municipio: 4314902,
            uf: 'RS',
            dataHoraEmissao: '2027-01-01T03:00:00-03:00',
            id: 'qualquer',
        );

        $this->assertSame('1.0.0', $dto->getVersao());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ItemDTO: campos opcionais
    // ──────────────────────────────────────────────────────────────────────────

    public function test_to_array_item_sem_campos_opcionais_nao_inclui_chaves(): void
    {
        $item = ItemDTO::fromArray([
            'numero' => 2,
            'ncm' => '12345678',
            'quantidade' => 10.0,
            'unidade' => 'KG',
            'cst' => '000',
            'baseCalculo' => 500.0,
            'cClassTrib' => '000001',
        ]);

        $array = $item->toArray();

        $this->assertArrayNotHasKey('tributacaoRegular', $array);
        $this->assertArrayNotHasKey('impostoSeletivo', $array);
    }

    public function test_item_make_fluent_builder(): void
    {
        $item = ItemDTO::make(3)
            ->ncm('11223344')
            ->quantidade(5.0)
            ->unidade(UnidadeMedida::KG)
            ->cst('000')
            ->baseCalculo(200.0)
            ->cClassTrib('000001');

        $this->assertSame(3, $item->getNumero());
        $this->assertSame('11223344', $item->getNcm());
        $this->assertSame(5.0, $item->getQuantidade());
        $this->assertSame(UnidadeMedida::KG, $item->getUnidade());
        $this->assertNull($item->getTributacaoRegular());
        $this->assertNull($item->getImpostoSeletivo());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // validate()
    // ──────────────────────────────────────────────────────────────────────────

    public function test_validate_com_payload_valido_nao_lanca_excecao(): void
    {
        $dto = CalculoRequestDTO::fromArray($this->fixture());

        $dto->validate();

        $this->assertTrue(true);
    }

    public function test_validate_lanca_exception_quando_ncm_esta_vazio(): void
    {
        $item = ItemDTO::make(1)
            ->ncm('')
            ->quantidade(1)
            ->unidade(UnidadeMedida::VN)
            ->cst('550')
            ->baseCalculo(100.0)
            ->cClassTrib('550020');

        $dto = CalculoRequestDTO::make(
            municipio: 4314902,
            uf: 'RS',
            itens: [$item],
            dataHoraEmissao: '2027-01-01T03:00:00-03:00',
            id: 'qualquer',
        );

        try {
            $dto->validate();
            $this->fail('Era esperada uma RtcValidationException para NCM vazio.');
        } catch (RtcValidationException $e) {
            $this->assertArrayHasKey('itens.1.ncm', $e->getErrors());
        }
    }

    public function test_validate_lanca_exception_quando_base_calculo_e_negativa(): void
    {
        $item = ItemDTO::make(1)
            ->ncm('24021000')
            ->quantidade(1)
            ->unidade(UnidadeMedida::VN)
            ->cst('550')
            ->baseCalculo(-10.0)
            ->cClassTrib('550020');

        $dto = CalculoRequestDTO::make(
            municipio: 4314902,
            uf: 'RS',
            itens: [$item],
            dataHoraEmissao: '2027-01-01T03:00:00-03:00',
            id: 'qualquer',
        );

        try {
            $dto->validate();
            $this->fail('Era esperada uma RtcValidationException para baseCalculo negativa.');
        } catch (RtcValidationException $e) {
            $this->assertArrayHasKey('itens.1.baseCalculo', $e->getErrors());
        }
    }
}
