# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Adicionado

- `addItems(ItemDTO[] $itens)` no builder fluente — adiciona múltiplos itens em uma chamada
- `Rtc::calcularPorNfe(xmlNfe, rtcPorItem)` — extrai municipio, UF e itens do XML da NFe e calcula diretamente
- `Actions/CalcularPorNfeXmlAction` — action responsável pelo parsing do XML e montagem do DTO
- `Events/RtcCalculated` — evento Laravel disparado após cada cálculo bem-sucedido (inclui dto + result)
- README: seção "Como subir a Calculadora Java" com comandos Docker e JAR
- README: seção "Como funciona por baixo dos panos" com tabela de endpoints e fluxo de requisição

## [1.0.0] - 2026-04-12

### Adicionado

**Scaffold e configuração (M0–M1):**
- Estrutura inicial do pacote com `composer.json` e autoload PSR-4
- `RtcServiceProvider` com auto-discovery do Laravel
- Config `rtc.php` publicável via `vendor:publish --tag=rtc-config`
- Facade `Rtc` com suporte a autocompletion de IDE
- Contrato `RtcClientContract` para permitir mocking e substituição do client
- GitHub Actions CI rodando PHP 8.1/8.2/8.3 × Laravel 10/11

**Enums e DTOs (M2):**
- `Enums/TipoDocumento` — backed enum `NFe | NFCe | CTe`
- `Enums/UnidadeMedida` — backed enum com 11 unidades (VN, KG, G, L, ML, M, M2, M3, T, MWH, GJ)
- `Enums/Uf` — backed enum com as 27 UFs brasileiras
- `DTOs/TributacaoRegularDTO` — bloco de tributação regular com `toArray()` e `fromArray()`
- `DTOs/ImpostoSeletivoDTO` — bloco de imposto seletivo com `toArray()` e `fromArray()`
- `DTOs/ItemDTO` — item da nota com builder fluente `make(int $numero)->ncm()->quantidade()->...`
- `DTOs/CalculoRequestDTO` — envelope do POST /regime-geral com factory `make(municipio, uf, itens)`

**HTTP Client e Exceptions (M3):**
- `Http/RtcClient` implementando `RtcClientContract` com retry automático e logging opcional
- `Exceptions/RtcConnectionException` — falha de conexão com a calculadora
- `Exceptions/RtcValidationException` — erro de validação da API (inclui array `$errors`)
- `Exceptions/RtcCalculationException` — erro interno no cálculo

**Data Objects tipados (M4):**
- `Data/ItemResult` — item calculado com getters para IS e IBSCBS
- `Data/TotaisResult` — totalizadores com getters para ISTot e IBSCBSTot
- `Data/CalculoResult` — resultado completo com `getObjetos()`, `getTotal()`, `toArray()`, `toJson()`
- Fixtures de teste baseadas na API real: `entrada-regime-geral.json`, `saida-regime-geral.json`, `saida-gerar-xml.xml`, `nfe-sem-rtc.xml`

**Actions (M5):**
- `Actions/CalcularTributosAction` — orquestra `POST /api/calculadora/regime-geral`
- `Actions/GerarXmlRtcAction` — orquestra `POST /api/calculadora/xml/generate`
- `Actions/ValidarXmlRtcAction` — orquestra `POST /api/calculadora/xml/validate`
- `Actions/InjetarXmlNfeAction` — injeta IS/IBSCBS e ISTot/IBSCBSTot em NFe existente via `DOMDocument` + `DOMXPath`

**API fluente (M6):**
- `Rtc::make()->paraFiscal()->emitidoEm()->addItem()->calcular()` — interface builder estilo Laravel
- Métodos diretos: `executarCalculo()`, `gerarXml()`, `validarXml()`, `injetarNfe()`

**Testes (M7):**
- 48 testes, 100 assertions — todos passando em < 120ms
- `Unit/DTOs/CalculoRequestDTOTest` — serialização, roundtrip, fluent `make()`
- `Unit/Actions/InjetarXmlNfeActionTest` — injeção DOM sem dependência de API
- `Feature/CalcularTributosTest` — `Http::fake()` mock do `/regime-geral`
- `Feature/GerarXmlTest` — verificação do query param `tipo=` via `Http::assertSent`
- `Feature/InjetarXmlTest` — end-to-end com fixtures reais e validação XPath

**Comandos Artisan (M8):**
- `php artisan rtc:calcular {arquivo.json}` — calcula e exibe tabela de totais; `--saida=` salva JSON
- `php artisan rtc:injetar {nfe.xml} {calculo.json} {saida.xml}` — gera e injeta XML RTC na NFe; `--tipo=`
- `php artisan rtc:healthcheck` — verifica se a calculadora Java está acessível; `--url=`

[Unreleased]: https://github.com/crdesign8/laravel-rtc-calculator/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/crdesign8/laravel-rtc-calculator/releases/tag/v1.0.0

