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
- ✅ API fluente e idiomática ao estilo Laravel
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

## 🤝 Contribuindo

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
