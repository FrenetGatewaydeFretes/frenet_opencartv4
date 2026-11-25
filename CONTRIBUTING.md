# Desenvolvimento do módulo Frenet para OpenCart 4

Guia para quem altera o módulo. As instruções para o lojista ficam no [README](README.md).

## Compatibilidade

Um único pacote atende todas as versões do OpenCart 4, de **4.0.0.0 a 4.1.0.4**, em **PHP 8.0 a 8.5**. A tabela foi conferida no código de cada tag do [repositório do OpenCart](https://github.com/opencart/opencart).

| OpenCart 4 | PHP mínimo | PHP máximo | Twig | Separador de rota | Nome do frete no checkout |
| --- | --- | --- | --- | --- | --- |
| 4.0.0.0 | 8.0 | 8.1 | 3.3 | `\|` | `title` |
| 4.0.1.0 a 4.0.1.1 | 8.0 | 8.1 | 3.4 | `\|` | `title` |
| 4.0.2.0 a 4.0.2.3 | 8.0 | 8.3 | 3.4 | `.` (aceita `\|`) | `name` |
| 4.1.0.0 a 4.1.0.3 | 8.0 | 8.5 | 3.18 | `.` (aceita `\|`) | `name` |
| 4.1.0.4 | 8.1 | 8.5 | 3.24 | `.` (aceita `\|`) | `name` |

Em todas as versões o Twig roda com `autoescape` desligado.

O PHP máximo de cada faixa é o maior em que o próprio OpenCart roda, conferido pelo E2E. Acima dele, o core imprime avisos de obsoleto na página, os headers já foram enviados e o cookie de sessão do admin não é gravado:

- 4.0.0.0 a 4.0.1.1 no PHP 8.2: propriedades dinâmicas do core;
- 4.0.2.0 a 4.0.2.3 no PHP 8.4: parâmetro anulável implícito no Guzzle que vem com o OpenCart.

### O que muda entre as versões

Toda diferença entre versões passa pela classe `Frenet\Platform`, criada com a constante `VERSION` da loja. O resto do código nunca compara versões.

| Ponto | Diferença | `Frenet\Platform` |
| --- | --- | --- |
| Rotas de links e AJAX | Até a 4.0.1.1 o roteador só aceita `\|` e remove o `.`; a partir da 4.0.2.0 converte `\|` em `.` | `getRoute()` usa `\|`, que funciona em todas |
| Ação de eventos | A ação gravada no banco não passa pela conversão: `\|` até a 4.0.1.1, `.` depois | `getEventAction()` |
| Registro de eventos | `addEvent()` recebe valores separados na 4.0.0.0 e um array a partir da 4.0.1.0 | `getEventArguments()` |
| Promoções | `product_special` até a 4.0.2.3; `product_discount` com `special` a partir da 4.1.0.0 | `hasProductSpecialTable()` |
| SKU | Coluna `sku` de `product` até a 4.1.0.3; tabela `product_code` na 4.1.0.4 | `hasSkuInProductCode()` |

Outras diferenças que o código precisa respeitar:

- **Nome do frete:** o checkout lê `title` até a 4.0.1.1 e `name` depois. O método de frete devolve as duas chaves, no método e em cada serviço.
- **Salvar no admin:** igual em todas as versões. O formulário envia por AJAX para um método `save()` separado, que responde JSON (`success` ou `error`). Um controller que redireciona deixa o lojista sem nenhuma mensagem.
- **Instalador:** a pasta da extensão é o nome do zip, por isso o pacote se chama sempre `opencartfrenetv4.ocmod.zip`. O `install.json` precisa de `name`, `version`, `author` e `link` (obrigatórios da 4.0.1.0 à 4.0.2.3).
- **Conversão de unidades:** `weight->convert()` e `length->convert()` recebem a unidade como texto na 4.0 e como número na 4.1.
- **Região geográfica:** `getGeoZone()` do core só existe a partir da 4.1; use SQL próprio.
- **Funções de texto do core** (`oc_strlen`, `utf8_strlen`...) mudam de nome entre as versões; use as funções nativas `mb_*`.
- **Imposto do frete:** na 4.0.0.0 a 4.0.1.1 o próprio core não aplica imposto sobre nenhum frete.
- **Log do core:** na 4.0.0.0 o `Log` guarda as linhas e só grava o arquivo quando o objeto é destruído, no fim da requisição; nas outras versões grava na hora.

### Regras de PHP

O código precisa rodar no PHP 8.0 e não pode gerar aviso no PHP 8.5.

- Liberado no PHP 8.0: tipos de união, `mixed`, `match`, nullsafe (`?->`), argumentos nomeados, promoção de propriedades no construtor, `str_contains` e afins.
- Proibido, porque só existe a partir do PHP 8.1: `readonly`, `enum`, `never`, `new` em valor padrão de parâmetro e a sintaxe `strlen(...)` de callable.
- Sem `declare(strict_types=1)` no módulo: as APIs do core mudam de tipo entre as versões (como `convert()` acima) e o OpenCart 4 não usa `strict_types`. Os testes podem usar.
- Sem `curl_close()`, obsoleta no PHP 8.5.
- Declare toda propriedade das classes que estendem `Controller` e `Model` do core: o `__set` do core só aceita objetos.

O `composer compat` (PHPCompatibility com `testVersion 8.0-`) e o `php -l` do CI, do PHP 8.0 ao 8.5, barram a maior parte disso. Os testes unitários rodam do PHP 8.2 ao 8.5 e falham com qualquer aviso de obsoleto, notice ou saída na tela.

### Regras de Twig

Os templates precisam funcionar do Twig 3.3 ao 3.24. Como não há `autoescape`, todo valor impresso passa por `|escape` (ou `|escape('js')` dentro de JavaScript).

## Estrutura

O repositório é a própria extensão: o zip leva `install.json`, `admin/`, `catalog/` e `system/` da raiz. O resto (testes, ferramentas e documentação) fica fora do pacote.

```
install.json                 metadados do pacote
admin/                       controller, idiomas e template da configuração
catalog/                     model de frete e idiomas da loja
system/library/frenet/       biblioteca do módulo (namespace Frenet)
tests/unit/                  PHPUnit
tests/mock/                  mock da API REST da Frenet
tests/e2e/                   instalação numa loja OpenCart 4 limpa, com o Playwright
```

### Biblioteca

As classes de `system/library/frenet/` ficam no namespace `Frenet`, com os mesmos nomes do módulo do OpenCart 3, para uma correção de regra de negócio entrar nos dois módulos do mesmo jeito. Os arquivos seguem o padrão do autoloader do OpenCart 4: o nome da classe em snake_case (`Frenet\Http\Client` em `http/client.php`, `Frenet\Quote\UnavailableException` em `quote/unavailable_exception.php`).

O namespace que o OpenCart 4 dá às bibliotecas de extensão muda entre as versões (`Opencart\System\Extension\…` na 4.0.0.0, `Opencart\System\Library\Extension\…` depois). Por isso o módulo registra o próprio prefixo no autoloader do core, igual em todas, antes de usar qualquer classe:

```php
$this->autoloader->register('Frenet', DIR_EXTENSION . 'opencartfrenetv4/system/library/frenet/');
```

| Classe | Função |
| --- | --- |
| `Frenet\Config` | Leitura tipada das configurações `shipping_frenet_*` |
| `Frenet\Log` | Grava em `frenet.log`. Erros sempre; detalhes só com debug ligado. Mascara o `token` em qualquer nível do contexto |
| `Frenet\Platform` | O que muda entre as versões do OpenCart 4 (tabela acima) |
| `Frenet\Http\Client` | POST via cURL com tempo limite, só HTTP/HTTPS, sem seguir redirecionamento, TLS verificado; envia várias requisições ao mesmo tempo |
| `Frenet\Http\Response` | Status e corpo da resposta, com `isSuccessful()` (2xx) e `isRefused()` (4xx que não vale repetir) |
| `Frenet\Http\Exception` | Falha de transporte (DNS, conexão, TLS ou tempo limite) |

Os arquivos atuais de `admin/` e `catalog/` são da versão 1.0.0 e são reescritos nas próximas demandas do épico [Frenet-BR/roadmap#1115](https://github.com/Frenet-BR/roadmap/issues/1115).

## Padrão de código

O módulo segue o padrão de código do OpenCart 4: o `.php-cs-fixer.php` é o da versão 4.1.0.4 do OpenCart, sem mudanças nas regras. Tabs, variáveis e arquivos em snake_case, classes e métodos em camelCase.

- Classes de fora do arquivo entram por `use`, com alias quando o nome coincide (`use Opencart\System\Engine\Config as CoreConfig;`).
- Todo método tem um docblock com o resumo em uma linha e os tipos em `@param` e `@return`. O construtor só com propriedades promovidas não tem docblock.
- Sem comentários no meio do código.
- Os arquivos de `admin/` e `catalog/` entram no fixer, no PHPStan e no PHPCompatibility à medida que forem reescritos.

As classes de teste são `final`, e por isso o fixer exige `self::` nas asserções. Casos da mesma regra com entradas diferentes usam `#[TestWith]` ou `#[DataProvider]`.

Para o PHPStan e os testes, `tests/stubs/opencart.php` imita o `Config` e o `Log` do core do OpenCart 4.

O PHPStan roda no nível 10, o mais alto, com o `phpstan-strict-rules` e, nos testes, com o `phpstan-phpunit`. Valor `mixed` (como o que vem do `Config` do core) é conferido com `is_scalar()`, `is_numeric()` e afins antes de qualquer cast, e condições usam comparações explícitas (`!== []`, `> 0`) em vez de valores verdadeiros ou falsos.

## Comandos

As ferramentas rodam com o Composer (dependências só de desenvolvimento, fora do pacote). Sem PHP na máquina, use o Docker:

```bash
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app composer:2 composer check
```

| Comando | O que faz |
| --- | --- |
| `composer check` | roda `cs`, `compat`, `phpstan` e `test`, como o CI |
| `composer cs` / `composer cs-fix` | confere / aplica o padrão de código |
| `composer compat` | PHPCompatibility do PHP 8.0 em diante em `system/library/frenet` |
| `composer phpstan` | PHPStan nível 10 com as regras estritas: a biblioteca analisada como PHP 8.0 (`phpstan.neon.dist`) e os testes como PHP 8.2, o mínimo do PHPUnit 11 (`phpstan.tests.neon.dist`) |
| `composer test` | PHPUnit; sobe o mock da Frenet sozinho na porta 8765 e falha com avisos de obsoleto, notices e saída na tela |
| `composer lint` | `php -l` em `admin/`, `catalog/` e `system/` com o PHP atual |

Para conferir a sintaxe numa versão específica do PHP:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.0-cli sh -c 'find admin catalog system -name "*.php" -print0 | xargs -0 -n1 php -l'
```

## Mock da API da Frenet

`tests/mock/router.php` é o mesmo mock do módulo do OpenCart 3:

| Rota | Resposta |
| --- | --- |
| `POST /shipping/quote` sem header `token` | 401 |
| `POST /shipping/quote` com header `token` | 200 com PAC e SEDEX (`tests/mock/fixtures/quote.json`) |
| `POST /shipping/quote` com `token: single-service` | 200 só com SEDEX |
| `POST /shipping/quote` com `token: empty` | 200 sem serviços, com `Message` |
| `POST /shipping/quote` com `token: with-errors` | 200 com SEDEX, Jadlog sem `DeliveryTime` e Mini Envios com `Error` |
| `POST /shipping/quote` com `token: flaky` | alterna 503 e 200, começando pelo 503 |
| `POST /shipping/quote` com `token: packages`, `packages-msg` ou `packages-busy` | 200 pelo peso enviado, com o limite dos Correios (para a divisão em pacotes) |
| `POST /shipping/quote` com `token: status-503` (ou outro código) | responde com esse status HTTP |
| `/mock/status/{código}` | responde com esse status HTTP |
| `/mock/delay/{segundos}` | espera antes de responder, para testar tempo limite |
| `/mock/redirect` | 302 para `/mock/echo`, para conferir que o cliente não segue redirecionamento |
| `/mock/echo` | devolve método, headers e corpo recebidos |

Para usar fora dos testes:

```bash
php -S 127.0.0.1:8765 tests/mock/router.php
```

Com a variável `FRENET_MOCK_LOG` apontando para um arquivo, o mock grava ali cada `POST /shipping/quote` recebido, uma linha JSON por requisição.

## E2E

`tests/e2e/` instala o pacote numa loja OpenCart 4 limpa, com o zip oficial da versão e o tema padrão, e faz o caminho do lojista com o Playwright:

1. envia o `opencartfrenetv4.ocmod.zip` em **Extensions → Installer** e instala o pacote;
2. instala a Frenet em **Extensions → Extensions**, no tipo **Shipping**;
3. abre a configuração da Frenet.

O teste falha com qualquer erro de JavaScript ou resposta 5xx. Depois do Playwright, o `run.sh` carrega a biblioteca dentro da loja pelo autoloader do próprio OpenCart, grava uma linha no `frenet.log` e confere que o token saiu mascarado, e confere que o `error.log` da loja não tem nenhuma linha da Frenet.

A loja é instalada pelo `install/cli_install.php` do OpenCart, menos a 4.0.0.0: o instalador de linha de comando dessa versão não funciona (falta a constante `APPLICATION`), e o `run.sh` usa o instalador web.

O `compose.yml` sobe o MariaDB, o OpenCart com a versão de PHP pedida, o mock da Frenet e um nginx com o nome `api.frenet.com.br`, que atende o mock por HTTPS com um certificado de uma CA criada a cada execução e confiada só no container da loja.

O CI roda o E2E em 7 versões, cada uma no menor e no maior PHP que ela aceita:

| OpenCart | PHP |
| --- | --- |
| 4.0.0.0 | 8.0 e 8.1 |
| 4.0.1.1 | 8.0 e 8.1 |
| 4.0.2.0 | 8.0 e 8.3 |
| 4.0.2.3 | 8.0 e 8.3 |
| 4.1.0.0 | 8.0 e 8.5 |
| 4.1.0.3 | 8.0 e 8.5 |
| 4.1.0.4 | 8.1 e 8.5 |

As capturas do instalador e da configuração e os logs da loja ficam nos artefatos `e2e-<versão>-php<versão>` da execução; vídeo e trace só quando o teste falha. O teste espera pela tela com localizadores e asserções do Playwright (`expect(...).toBeVisible()`), sem `networkidle` nem esperas fixas.

Para rodar na máquina, com o Docker:

```bash
git archive --format=zip --output=opencartfrenetv4.ocmod.zip HEAD install.json admin catalog system
tests/e2e/run.sh 4.1.0.4 8.1 opencartfrenetv4.ocmod.zip
```

O `run.sh` usa o Playwright de `tests/e2e/node_modules` quando há Node na máquina e, sem ele, a imagem `mcr.microsoft.com/playwright` da mesma versão do `package.json`. A loja responde em `http://localhost:8080` (mude com `E2E_PORT`) e é apagada no fim; com `E2E_KEEP=1`, ela continua de pé para investigar. Os resultados ficam em `tests/e2e/.work/artifacts/<versão>-php<versão>`.

## Actions do CI

As actions dos workflows são fixadas pelo hash completo do commit, com a versão em comentário (`actions/checkout@<hash> # v7.0.1`), como recomenda o [guia de segurança do GitHub Actions](https://docs.github.com/en/actions/reference/security/secure-use): uma tag pode ser movida para outro commit, o hash não. O Dependabot (`.github/dependabot.yml`) abre toda semana um PR para a `3.0-develop` com as versões novas, atualizando hash e comentário juntos. Ao adicionar uma action, use o mesmo formato.

## Branches e commits

- `main`: só recebe versões publicadas.
- `3.0-develop`: integração; cada demanda entra aqui por PR.
- Uma branch por demanda, a partir da `3.0-develop`, com o número da issue do roadmap (ex.: `feature/1116-base-tecnica`).
- Commits em inglês com gitmoji, uma responsabilidade por commit (ex.: `:sparkles: feat: add the cURL transport for the Frenet API`).
