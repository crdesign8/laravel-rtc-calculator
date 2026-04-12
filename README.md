# 🧾 laravel-rtc-calculator

[![Tests](https://github.com/crdesign8/laravel-rtc-calculator/actions/workflows/tests.yml/badge.svg)](https://github.com/crdesign8/laravel-rtc-calculator/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/crdesign8/laravel-rtc-calculator.svg)](https://packagist.org/packages/crdesign8/laravel-rtc-calculator)
[![License](https://img.shields.io/github/license/crdesign8/laravel-rtc-calculator)](LICENSE)

Pacote Laravel para integração com a **Calculadora da Reforma Tributária do Consumo (RTC)** da Receita Federal do Brasil.

Ao contrário de outros exemplos disponíveis em Python, este pacote permite que desenvolvedores **Laravel** utilizem a calculadora Java (disponibilizada pela Receita Federal) diretamente em suas aplicações PHP, com uma API fluente, tipagem forte e totalmente testável.

---

## ✨ Funcionalidades

- ✅ Cálculo de tributos no **Regime Geral** (IS, IBS, CBS)
- ✅ Geração de **XML RTC** compatível com NFe, NFCe e CTe
- ✅ Validação de XML gerado
- ✅ Injeção automática dos grupos RTC em NFe existentes
- ✅ **Cálculo direto a partir de XML de NFe** (`calcularPorNfe`) — extrai municipio, UF e itens automaticamente
- ✅ **Evento Laravel `RtcCalculated`** — permite logging, auditoria e integrações após o cálculo
- ✅ API fluente e idiomática ao estilo Laravel com `addItem()` e `addItems([])`
- ✅ Configuração via `.env`
- ✅ Retry automático em caso de falha de conexão
- ✅ Cobertura de testes com PHPUnit

---

## 📋 Requisitos

- PHP `^8.1`
- Laravel `^10.0` ou `^11.0`
- A **Calculadora RTC Java** rodando localmente (disponível em [consumo.tributos.gov.br](https://consumo.tributos.gov.br/servico/calcular-tributos-consumo/calculadora))

> 💡 A calculadora Java expõe uma API REST em `http://localhost:8080` por padrão.

---

## 🐳 Como subir a Calculadora Java

O pacote não inclui a Calculadora RTC — ela é disponibilizada pela **Receita Federal** como parte do programa-piloto da Reforma Tributária do Consumo.

### Opção 1 — Docker (recomendado)

Se você já tem a imagem `calculadora-rtc` (obtida via [consumo.tributos.gov.br](https://consumo.tributos.gov.br/servico/calcular-tributos-consumo/calculadora)):

```bash
docker run -d \
  --name calculadora-api \
  -p 8080:8080 \
  -w /calculadora \
  calculadora-rtc \
  /bin/sh -c "
    JAVA_HOME=/opt/java/openjdk
    export PATH=\$JAVA_HOME/bin:\$PATH
    java -jar /calculadora/api-regime-geral.jar --spring.profiles.active=offline
  "
```

Verifique se está respondendo:

```bash
curl -s http://localhost:8080/actuator/health | python3 -m json.tool
# Esperado: { "status": "UP" }
```

### Opção 2 — JAR direto (sem Docker)

```bash
java -jar api-regime-geral.jar --spring.profiles.active=offline
```

O servidor sobe em `http://localhost:8080` por padrão. Use `--server.port=PORTA` para alterar.

### Checar via Artisan

```bash
php artisan rtc:healthcheck
# Calculadora RTC disponível em http://localhost:8080 ✔
```

---

## 📦 Instalação

```bash
composer require crdesign8/laravel-rtc-calculator
```

Publique o arquivo de configuração:

```bash
php artisan vendor:publish --tag=rtc-config
```

Configure no seu `.env`:

```env
RTC_BASE_URL=http://localhost:8080
RTC_TIMEOUT=30
```

---

## 🚀 Uso Básico

### Via Facade (forma mais simples)

```php
use Crdesign8\LaravelRtcCalculator\Facades\Rtc;
use Crdesign8\LaravelRtcCalculator\DTOs\ItemDTO;
use Crdesign8\LaravelRtcCalculator\Enums\UnidadeMedida;

$resultado = Rtc::make()
    ->paraFiscal(municipio: 4314902, uf: 'RS')
    ->emitidoEm('2027-01-01T03:00:00-03:00')
    ->addItem(
        ItemDTO::make(numero: 1)
            ->ncm('24021000')
            ->quantidade(222)
            ->unidade(UnidadeMedida::VN)
            ->cst('550')
            ->baseCalculo(1111.00)
            ->cClassTrib('550020')
    )
    ->calcular();

// Acessa os totais calculados
echo $resultado->getTotal()->getVIsTot();      // Imposto Seletivo total
echo $resultado->getTotal()->getVBcIbsCbs();  // Base de cálculo IBS+CBS
echo $resultado->getTotal()->getVCbsTot();    // CBS total

// Acessa um item específico pelo número
$item = $resultado->getItem(1);
echo $item->getCstIs();    // CST do Imposto Seletivo
echo $item->getVIs();      // Valor do IS
```

### Adicionar múltiplos itens de uma vez

```php
$resultado = Rtc::make()
    ->paraFiscal(municipio: 4314902, uf: 'RS')
    ->emitidoEm('2027-01-01T03:00:00-03:00')
    ->addItems([
        ItemDTO::make(1)->ncm('24021000')->quantidade(100)->unidade(UnidadeMedida::VN)
            ->cst('550')->baseCalculo(500.00)->cClassTrib('550020'),
        ItemDTO::make(2)->ncm('22030000')->quantidade(50)->unidade(UnidadeMedida::L)
            ->cst('200')->baseCalculo(200.00)->cClassTrib('200032'),
    ])
    ->calcular();
```

### Calcular a partir do XML de uma NFe existente

Extrai municipio, UF, data de emissão e dados de produto diretamente da nota; você só precisa informar os campos exclusivos do RTC:

```php
$result = Rtc::make()->calcularPorNfe(
    xmlNfe: file_get_contents('nfe-sem-rtc.xml'),
    rtcPorItem: [
        1 => [
            'cst'               => '200',
            'cClassTrib'        => '200032',
            'tributacaoRegular' => ['cst' => '200', 'cClassTrib' => '200032'],
        ],
    ],
);
```

### Gerar e Injetar XML na NFe

```php
// Gera o XML com grupos RTC
$xmlRtc = Rtc::make()->gerarXml($resultado);

// Injeta na NFe existente
$nfeComRtc = Rtc::make()->injetarNfe(
    xmlRtc: $xmlRtc,
    xmlNfe: file_get_contents('nfe-sem-rtc.xml')
);

file_put_contents('nfe-com-rtc.xml', $nfeComRtc);
```

### Reagindo ao cálculo com Eventos Laravel

Depois de cada cálculo bem-sucedido (via `calcular()` ou `executarCalculo()`),
o evento `RtcCalculated` é despachado automaticamente:

```php
use Crdesign8\LaravelRtcCalculator\Events\RtcCalculated;

// Em qualquer EventServiceProvider ou via closure:
Event::listen(RtcCalculated::class, function (RtcCalculated $event) {
    Log::info('RTC calculado', [
        'municipio' => $event->dto->getMunicipio(),
        'itens'     => count($event->dto->getItens()),
        'vIsTot'    => $event->result->getTotal()->getVIsTot(),
        'vCbsTot'   => $event->result->getTotal()->getVCbsTot(),
    ]);
});
```

---

## 🖥️ Comandos Artisan

### Calcular tributos a partir de um arquivo JSON

```bash
php artisan rtc:calcular entrada.json

# Salvar o resultado em arquivo
php artisan rtc:calcular entrada.json --saida=resultado.json
```

### Injetar grupos RTC em uma NFe existente

```bash
php artisan rtc:injetar nfe-sem-rtc.xml resultado.json nfe-com-rtc.xml

# Para CTe ou NFCe
php artisan rtc:injetar nota.xml resultado.json nota-com-rtc.xml --tipo=CTe
```

### Verificar se a calculadora Java está rodando

```bash
php artisan rtc:healthcheck

# Testar uma URL diferente da configurada
php artisan rtc:healthcheck --url=http://meu-servidor:8080
```

---

## 🔍 Como funciona por baixo dos panos

Este pacote é um **cliente HTTP** para a Calculadora RTC Java da Receita Federal. Ele não implementa nenhuma regra tributária — toda a lógica fiscal fica na calculadora oficial.

### Endpoints consumidos

| Método | Endpoint | Utilizado por |
|--------|----------|---------------|
| `POST` | `/api/calculadora/regime-geral` | `CalcularTributosAction`, `Rtc::calcular()` |
| `POST` | `/api/calculadora/xml/generate?tipo={NFe\|NFCe\|CTe}` | `GerarXmlRtcAction`, `Rtc::gerarXml()` |
| `POST` | `/api/calculadora/xml/validate` | `ValidarXmlRtcAction`, `Rtc::validarXml()` |

### Fluxo de uma requisição

```
Facade / Builder
  └─ Rtc::calcular()
       └─ CalcularTributosAction::handle(CalculoRequestDTO)
            └─ RtcClient::calcularRegimeGeral()
                 └─ POST /api/calculadora/regime-geral  (JSON)
                      └─ CalculoResult::fromArray()     (resposta tipada)
                           └─ event(RtcCalculated)      (evento Laravel)
```

> A injeção na NFe (`InjetarXmlNfeAction`) é executada **localmente**, sem chamada HTTP — ela apenas combina os XMLs usando DOMDocument.

### Autenticação e ambiente de produção

Atualmente a Calculadora RTC está em fase **piloto** e não exige autenticação. Quando a Receita Federal disponibilizar o ambiente de produção com autenticação, bastará adicionar headers customizados via `config/rtc.php`.

---

## ⚙️ Configuração

```php
// config/rtc.php
return [
    'base_url'              => env('RTC_BASE_URL', 'http://localhost:8080'),
    'timeout'               => env('RTC_TIMEOUT', 30),
    'retry_times'           => env('RTC_RETRY_TIMES', 2),
    'retry_sleep_ms'        => env('RTC_RETRY_SLEEP_MS', 500),
    'default_tipo_documento'=> env('RTC_DEFAULT_TIPO_DOCUMENTO', 'NFe'),
    'versao'                => env('RTC_VERSAO', '1.0.0'),
    'logging' => [
        'enabled' => env('RTC_LOGGING_ENABLED', false),
        'channel' => env('RTC_LOGGING_CHANNEL', 'stack'),
    ],
];
```

---

## � Exemplos Executáveis

A pasta [`examples/`](examples/) contém scripts standalone que funcionam sem uma aplicação Laravel completa — apenas `composer install` e a calculadora Java rodando:

```bash
# Pré-requisito: calculadora Java em http://localhost:8080

# 1. Cálculo de tributos (IS + IBS + CBS) com tabela formatada
php examples/01-calcular-tributos.php

# 2. Geração do XML com grupos IS/IBSCBS/ISTot/IBSCBSTot
php examples/02-gerar-xml-rtc.php

# 3. Fluxo completo: calcular → gerar XML → injetar em uma NFe real
php examples/03-fluxo-completo-nfe.php
```

Os arquivos gerados são salvos em `examples/output/` (pasta ignorada pelo Git).

> Para usar uma URL diferente: `RTC_BASE_URL=http://meu-servidor:9090 php examples/01-calcular-tributos.php`

---

## �🤝 Contribuindo

Contribuições são muito bem-vindas! Se você encontrou um bug, tem uma sugestão ou quer adicionar uma funcionalidade, a forma mais simples de contribuir é **abrindo uma issue** no repositório.

Para contribuições de código:

1. Fork o repositório
2. Crie sua branch: `git checkout -b feature/minha-feature`
3. Commit: `git commit -m 'feat: minha feature'`
4. Push: `git push origin feature/minha-feature`
5. Abra um Pull Request descrevendo o que foi alterado e por quê

---

## 📄 Licença

MIT — veja o arquivo [LICENSE](LICENSE) para detalhes.

---

## 🏛️ Sobre a Reforma Tributária

A Reforma Tributária do Consumo (EC 132/2023) institui o IBS (Imposto sobre Bens e Serviços), o CBS (Contribuição sobre Bens e Serviços) e o IS (Imposto Seletivo), em substituição ao PIS, Cofins, IPI, ICMS e ISS. A Calculadora RTC é a ferramenta oficial da Receita Federal para auxiliar na apuração desses novos tributos.
