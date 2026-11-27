# Desenvolvimento do módulo Frenet para OpenCart 4

Guia para quem altera o módulo. As instruções para o lojista ficam no [README](README.md).

## Compatibilidade

Um único pacote atende todas as versões do OpenCart 4, de **4.0.0.0 a 4.1.0.4**, em **PHP 8.0 a 8.5**. A tabela foi conferida no código de cada tag do [repositório do OpenCart](https://github.com/opencart/opencart).

| OpenCart 4 | PHP mínimo | PHP máximo | Twig | Separador de rota | Nome do frete no checkout |
| --- | --- | --- | --- | --- | --- |
| 4.0.0.0 | 8.0 | 8.1 | 3.3 | `\|` | `title` |
| 4.0.1.0 a 4.0.1.1 | 8.0 | 8.1 | 3.4 | `\|` | `title` |
| 4.0.2.0 a 4.0.2.3 | 8.0 | 8.3 | 3.4 | `.` (aceita `\|`) | `name` |
| 4.1.0.0 a 4.1.0.3 | 8.0 | 8.4 | 3.18 | `.` (aceita `\|`) | `name` |
| 4.1.0.4 | 8.1 | 8.5 | 3.24 | `.` (aceita `\|`) | `name` |

Em todas as versões o Twig roda com `autoescape` desligado.

O PHP máximo de cada faixa é o maior em que o próprio OpenCart roda, conferido pelo E2E. Acima dele, o core imprime avisos de obsoleto na página, os headers já foram enviados e o cookie de sessão do admin não é gravado:

- 4.0.0.0 a 4.0.1.1 no PHP 8.2: propriedades dinâmicas do core;
- 4.0.2.0 a 4.0.2.3 no PHP 8.4: parâmetro anulável implícito no Guzzle que vem com o OpenCart;
- 4.1.0.0 a 4.1.0.3 no PHP 8.5: `curl_close()` na moeda ECB do core, que o evento de moeda chama a cada `editSetting`. O aviso sai antes do JSON e quebra o salvar de qualquer configuração do admin, inclusive as do core.

### O que muda entre as versões

Toda diferença entre versões passa pela classe `Frenet\Platform`, criada com a constante `VERSION` da loja. O resto do código nunca compara versões.

| Ponto | Diferença | `Frenet\Platform` |
| --- | --- | --- |
| Rotas de links e AJAX | Até a 4.0.1.1 o roteador só aceita `\|` e remove o `.`; a partir da 4.0.2.0 converte `\|` em `.` | `getRoute()` usa `\|`, que funciona em todas |
| Ação de eventos | A ação gravada no banco não passa pela conversão: `\|` até a 4.0.1.1, `.` depois | `getEventAction()` |
| Registro de eventos | `addEvent()` recebe valores separados na 4.0.0.0 e um array a partir da 4.0.1.0 | `getEventArguments()` |
| Promoções | `product_special` até a 4.0.2.3; `product_discount` com `special` a partir da 4.1.0.0 | `hasProductSpecialTable()` |
| Variantes no botão do carrinho | O botão junta às opções todos os valores da variante na 4.0.0.0; da 4.0.1.0 à 4.0.2.1, só os listados no `override`; a partir da 4.0.2.2, só os que são chave do `override`. Até a 4.1.0.3 o carrinho recebe o produto mestre; na 4.1.0.4, a própria variante | `getVariantOptions()`, `addsVariantToCart()` |
| SKU | Coluna `sku` de `product` até a 4.1.0.0; a partir da 4.1.0.3, o admin grava em `product_code` (`code` `SKU`) e a atualização move a coluna para lá com o código `sku` e a apaga | `hasSkuInProductCode()` |

Outras diferenças que o código precisa respeitar:

- **Nome do frete:** o checkout lê `title` até a 4.0.1.1 e `name` depois. O método de frete devolve as duas chaves, no método e em cada serviço.
- **Salvar no admin:** igual em todas as versões. O formulário envia por AJAX para um método `save()` separado, que responde JSON (`success` ou `error`). Um controller que redireciona deixa o lojista sem nenhuma mensagem.
- **Instalador:** a pasta da extensão é o nome do zip, por isso o pacote se chama sempre `opencartfrenetv4.ocmod.zip`. O `install.json` precisa de `name`, `version`, `author` e `link` (obrigatórios da 4.0.1.0 à 4.0.2.3).
- **Conversão de unidades:** `weight->convert()` e `length->convert()` recebem a unidade como texto na 4.0 e como número na 4.1.
- **Região geográfica:** `getGeoZone()` do core só existe a partir da 4.1; use SQL próprio.
- **Links no template:** `url->link()` já devolve o link com `&amp;`. Não use `|escape` nele: escapado de novo, o `user_token` some da URL e o `save()` da 4.0.0.0 responde a página de login, porque o `common.js` dessa versão não troca `&amp;` por `&` (a partir da 4.0.2.0 troca). Os outros valores do template são escapados.
- **Valores que vieram do formulário:** o `Request` do OpenCart passa todo o `$_POST` por `htmlspecialchars` antes do controller, e o valor é gravado já escapado. Para mostrar de novo num campo, decodifique com `html_entity_decode` antes do `|escape`: escapado duas vezes, o campo mostra `&amp;` e cada salvar escapa mais uma vez. A loja imprime o valor gravado como está.
- **Avisos que dependem do que foi salvo:** ficam num template à parte (`frenet_alert`), que o `index()` inclui e o formulário recarrega depois de cada salvar com `data-oc-load` e `data-oc-target`, do próprio `common.js`. Sem isso, o aviso de token ausente continua na tela depois de o lojista salvar o token.
- **Biblioteca nos controllers e models:** o OpenCart não conhece o namespace `Frenet` até o `autoloader->register()` do próprio método. Uma constante de classe que usa a biblioteca quebra a instalação do frete, porque o PHP resolve as constantes ao criar o objeto, antes de qualquer método: use a biblioteca só dentro dos métodos.
- **Endereço do cliente:** `getAddress()` do model `account/address` recebe só o `address_id` até a 4.0.1.1 e o `customer_id` e o `address_id` depois; o simulador lê o CEP do endereço padrão com SQL próprio.
- **Preço no carrinho:** o `Cart::getProducts()` do core muda a conta do preço entre as versões (desconto por quantidade, promoção, assinatura e variantes). O simulador não copia essa conta: usa o próprio `Cart` do core (veja `Frenet\Simulator\Cart`).
- **Página do produto:** o formulário `#form-product`, o `#input-quantity`, os erros `#error-option-<id>`, `#error-subscription` e o JS do botão do carrinho são os mesmos da 4.0.0.0 à 4.1.0.4. O evento `view/product/product/after` recebe o HTML por referência em todas.
- **Funções de texto do core** (`oc_strlen`, `utf8_strlen`...) mudam de nome entre as versões; use as funções nativas `mb_*`.
- **Imposto do frete:** na 4.0.0.0 a 4.0.1.1 o próprio core não aplica imposto sobre nenhum frete.
- **Log do core:** na 4.0.0.0 o `Log` guarda as linhas e só grava o arquivo quando o objeto é destruído, no fim da requisição; nas outras versões grava na hora.
- **Checkout:** até a 4.0.1.1 frete e pagamento são escolhidos num `select`; a partir da 4.0.2.0, num modal aberto pelo botão "Choose".
- **Atualização do módulo:** a partir da 4.0.1.0, o Instalador só remove um pacote depois que as extensões dele são desinstaladas, e desinstalar o frete apaga as configurações `shipping_frenet_*`. Pelo Instalador, a atualização perde o token; copiando os arquivos novos sobre `extension/opencartfrenetv4/` (FTP ou gerenciador de arquivos), as configurações continuam.

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
catalog/                     model de frete, controller e template do simulador e idiomas da loja
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
| `Frenet\Config` | Leitura tipada das configurações `shipping_frenet_*`, com as classes de kg e cm, as medidas mínimas (padrão 2 × 11 × 16 cm) e o peso padrão (1 kg); um valor vazio, zero ou que não é número volta ao padrão. O simulador fica ligado e com 60 cotações por IP e minuto até o lojista mudar |
| `Frenet\Postcode` | Diz se o endereço pode ser cotado (CEP com 8 dígitos no Brasil, pontos, hífen e espaços aceitos, e qualquer CEP nos outros países), formata o CEP como `00000-000` e acha o estado pelas faixas dos Correios (Rondônia de 76800 a 76999, Mato Grosso até 78999) |
| `Frenet\GeoZone` | Diz se o país e a zona do endereço estão na região geográfica escolhida, o país inteiro incluído, e se a região ainda existe |
| `Frenet\UnitClass` | Acha as classes de peso e medida da loja cuja unidade é `kg` e `cm`, usadas enquanto a configuração não escolhe outras |
| `Frenet\Log` | Grava em `frenet.log`. Erros sempre; detalhes só com debug ligado. Mascara o `token` em qualquer nível do contexto |
| `Frenet\Platform` | O que muda entre as versões do OpenCart 4 (tabela acima) |
| `Frenet\Http\Client` | POST via cURL com tempo limite, só HTTP/HTTPS, sem seguir redirecionamento, TLS verificado; envia várias requisições ao mesmo tempo |
| `Frenet\Http\Response` | Status e corpo da resposta, com `isSuccessful()` (2xx) e `isRefused()` (4xx que não vale repetir) |
| `Frenet\Http\Exception` | Falha de transporte (DNS, conexão, TLS ou tempo limite) |
| `Frenet\Module` | Versão do módulo, exibida no admin e enviada no `User-Agent` |
| `Frenet\Api` | Cotação pela cache (`Frenet\Quote\Cache`) ou pela API REST da Frenet por HTTPS, com o token e o `User-Agent` `Frenet-OpenCart4/<versão> (OpenCart <versão>)`. Repete tempo esgotado, falha de conexão, 5xx e resposta ilegível até o número de tentativas, com 0,3 s entre elas; um 4xx nunca é repetido. Cada falha vai para o `frenet.log`; com debug, também a requisição e a resposta, sem o token, e a cotação servida pela cache. Se a cache não puder ser lida ou gravada, o erro vai para o log e a cotação segue pela API |
| `Frenet\Quote\Cache` | Guarda no `$this->cache` do OpenCart, por 10 minutos (`Cache::TTL`), a resposta com pelo menos um serviço, mesmo que outros tenham vindo com `Error`. A chave é o hash do token e do corpo da requisição: outra conta, outro CEP ou qualquer mudança no carrinho, no cupom ou nas configurações enviadas consulta a Frenet de novo. Falha, 4xx, JSON inválido e resposta sem serviço nunca entram na cache. A validade vai no `set()` do core, que no OpenCart 4 aceita uma por entrada (o cache em arquivo apaga o arquivo depois de 10 minutos, e não do `cache_expire` da loja), e também dentro da entrada, porque o `get()` do cache em arquivo até a 4.0.2.3 não confere a validade: na 4.0.0.0 e na 4.0.1.1, o arquivo vencido só é apagado no começo da requisição seguinte, e da 4.0.2.0 à 4.0.2.3, só em 1 de cada 100 requisições, então ele continua saindo da cache. Os avisos de PHP do adaptador de arquivos (duas cotações iguais ao mesmo tempo disputando o mesmo arquivo) não chegam à página, onde sairiam antes do JSON do checkout. Só leitura no simulador quando o cache da loja é em arquivo (`cache_engine` `file`, o padrão), para uma rota pública não encher o disco com um arquivo por CEP |
| `Frenet\Quote\Request` / `Frenet\Quote\Item` | Corpo da cotação (`PlatformName` `OpenCart4`) e cada item do carrinho, com o SKU e as categorias |
| `Frenet\Quote\Builder` | Monta a cotação só com os itens que exigem envio (`shipping = 1`). Converte o peso para kg e as medidas para cm pelo `Weight` e `Length` do core, sobe as medidas abaixo do mínimo ao mínimo e usa o peso padrão para o produto sem peso. O valor declarado é o preço de venda (com a promoção) × a quantidade dos itens enviados, sem o cupom, que vai no `Coupom` |
| `Frenet\Quote\Catalog` | SKU e categorias de cada produto. Pelo `Platform`, o SKU sai da coluna `oc_product.sku` até a 4.1.0.0 e de `oc_product_code` a partir da 4.1.0.3, com o código `SKU` do admin ou `sku` da atualização; sem SKU, vai o modelo. As categorias são as ativas da loja, no idioma da loja, separadas por `|` |
| `Frenet\Quote\Parser` | Lê a resposta, com a lista de vários serviços, de um só serviço ou o serviço fora da lista. Separa os serviços com `Error` (com o `Msg`) e guarda o `Message` geral; deixa de fora os que não têm código ou preço. Cada serviço tem o próprio prazo, sem herdar o do anterior |
| `Frenet\Quote\Result` | Serviços oferecidos, serviços com erro e `Message` da resposta |
| `Frenet\Quote\Service` | Serviço oferecido, com o nome do checkout pela mensagem de prazo (`%s` serviço, `%d` dias) e o nome escapado |
| `Frenet\Quote\Exception` | Resposta da API que não serve como cotação (erro HTTP ou JSON inválido) |
| `Frenet\Quote\UnavailableException` | Cotação que não veio depois de todas as tentativas ou que a API recusou (4xx) |
| `Frenet\Simulator\Cart` | Produto da página como o `Cart` do core o teria sozinho no carrinho de um visitante: o próprio `Cart::getProducts()` da loja calcula preço (desconto, promoção, assinatura), opções, variante e peso, com o registro da loja, um cliente visitante e a `CartTable` no lugar do banco |
| `Frenet\Simulator\CartTable` | Banco da loja com a tabela `cart` trocada: o `SELECT * FROM cart` devolve só o produto da página, com a quantidade, as opções e a assinatura escolhidas; qualquer outra leitura da tabela volta vazia e qualquer gravação (`DELETE`, `UPDATE`, `INSERT`) é ignorada. As outras consultas vão ao banco da loja. O carrinho real do cliente nunca é lido nem alterado |
| `Frenet\Simulator\Origin` | Recusa a cotação que o navegador diz vir de outro site, pelo `Origin` (comparado com o host da requisição e da loja) ou, sem ele, pelo `Sec-Fetch-Site: cross-site` |
| `Frenet\Simulator\ClientIp` | IP do visitante: atrás de um proxy da rede da loja (balanceador), o último endereço público do `X-Forwarded-For`; vindo de um endereço da Cloudflare, o `CF-Connecting-IP`; senão, o `REMOTE_ADDR` |
| `Frenet\Simulator\RateLimit` | Conta as cotações de cada IP por minuto na tabela `frenet_simulator_limit` (hash SHA-256 do IP, contagem e fim do minuto), com um `INSERT … ON DUPLICATE KEY UPDATE` por cotação. Acima do limite, devolve os segundos até o minuto acabar e grava o bloqueio uma vez por minuto no `frenet.log`. IPv6 conta pela rede /64 e IPv4 mapeado em IPv6 como IPv4. De vez em quando, apaga até 100 IPs de minutos passados a partir de um ponto aleatório da chave. Se a tabela não existir (atualização por FTP antes de abrir a configuração), cria e conta; se nem isso der, cota assim mesmo e grava o erro |

### Configurações da versão 1.0.0

A versão 1.0.0 guardava o token em `shipping_frenet_contrato_token`. Até a configuração ser aberta, `Frenet\Config::getToken()` usa essa chave quando `shipping_frenet_token` está vazia, e a cotação continua funcionando. Ao abrir a configuração, o admin move o token para `shipping_frenet_token` e apaga `shipping_frenet_contrato_token`, `shipping_frenet_contrato_codigo` e `shipping_frenet_contrato_senha`.

### Admin e loja

O model de frete (`catalog/model/shipping/frenet.php`) usa a biblioteca e devolve `title` e `name` no método e em cada serviço. Quando a cotação falha, por qualquer motivo, inclusive um erro inesperado, o bloco Frenet do checkout mostra só "O frete Frenet está indisponível no momento…" (`error_unavailable`), os outros fretes continuam, e o motivo vai para o `frenet.log`; num erro inesperado, o log guarda também a classe e o arquivo e a linha onde ele aconteceu. Quando a Frenet não devolve nenhum serviço, o bloco mostra `error_no_services` e o log guarda o `Message`. A configuração tem tempo limite (2 a 10 s, padrão 5), tentativas (1 a 3, padrão 3) e debug. Com 0,3 s entre as tentativas, a cotação que falha leva no máximo tempo limite × tentativas + 0,3 s × (tentativas − 1), ou seja, 15,6 s no padrão e 30,6 s no máximo. O endereço do Brasil sem CEP de 8 dígitos não vai para a Frenet: o bloco pede um CEP válido (`error_postcode`). A cotação leva só os itens com envio, em kg e cm, com o SKU, as categorias e o valor declarado da `Frenet\Quote\Builder`; a configuração escolhe as classes de kg e cm (até ser salva, valem as classes da loja com essas unidades; sem nenhuma, a configuração fica em branco e o log pede para escolher a classe), as medidas mínimas e o peso padrão; um mínimo ou peso padrão vazio, zero ou negativo é salvo com o padrão. Se o SKU e as categorias não puderem ser lidos, a cotação vai com o modelo como SKU e o motivo fica no log. A mesma cotação feita nos últimos 10 minutos sai da cache, sem chamar a Frenet: no checkout até a 4.0.1.1, que cota ao abrir a página e de novo ao escolher o endereço, a segunda já sai da cache. Com uma região geográfica escolhida, o frete Frenet só aparece para endereços dentro dela; se a região foi excluída, ele não aparece e o log pede outra. Cada serviço leva a classe de imposto da configuração, e o nome mostra o prazo da Frenet mais os dias adicionais, com a mensagem do lojista ou a do idioma (`text_delivery_time`), a menos que o prazo esteja desligado.

O admin segue o padrão do OpenCart 4: `index()` monta o formulário e `save()` responde JSON. Com o CEP de origem sem 8 dígitos, o frete habilitado sem token ou o limite do simulador com algo além de dígitos, nada é gravado e cada erro aparece no campo (`error.postcode`, `error.token`, `error.simulator_limit`), com o aviso no topo (`error.warning`). Ao salvar, o CEP vai como `00000-000`, os números saem da faixa aceita ou voltam ao padrão com um aviso na mensagem de sucesso, e a resposta devolve os valores salvos para o formulário. O token fica num campo de senha e nunca volta para a tela; em branco, o token salvo continua. Todo valor do template é escapado, porque o Twig do OpenCart 4 roda sem `autoescape`. Ao abrir a configuração, o admin move o token da 1.0.0 e avisa que a Chave e a Senha foram removidas; sem token, avisa que a Frenet não cota; com a região excluída, avisa para escolher outra. O limite do simulador em branco é salvo como 60. Os controllers e o model entram no fixer e no PHPCompatibility; o PHPStan continua só na biblioteca, porque o model e o controller leem o registro do core, sem tipos, e a regra de negócio fica na biblioteca.

### Simulador na página do produto

O evento `catalog/view/product/product/after` (código `frenet_simulator`) chama `addSimulator()` do controller `catalog/controller/shipping/frenet.php`, que coloca o bloco `frenet_simulator` logo depois do `</form>` do `#form-product`. O bloco só aparece com o frete Frenet habilitado, o simulador ligado, o token salvo e um produto com envio. O admin registra o evento e cria a tabela do limite ao instalar a Frenet e toda vez que a configuração é aberta por quem pode modificá-la, porque uma atualização por FTP não roda o `install()`; o evento é gravado de novo se a ação ou o gatilho estiverem diferentes, e o status que o lojista escolheu em **Extensions → Events** é mantido. Desinstalar apaga o evento e a tabela. Se a tabela não puder ser criada ou apagada (usuário do banco sem `CREATE` ou `DROP`), o erro vai para o `frenet.log` e a configuração e a desinstalação continuam; sem a tabela, o simulador cota sem o limite e grava o erro.

A rota `quote` recebe o `#form-product` serializado (produto, quantidade, opções e assinatura) e o CEP, e responde JSON. Na ordem:

1. Simulador desligado: `error.warning`.
2. `Origin` de outro site: 403.
3. IP acima do limite: 429 com `Retry-After` e `error.warning`.
4. CEP sem 8 dígitos ou fora das faixas dos Correios: `error.frenet_postcode`.
5. Quantidade menor que 1: `error.quantity`.
6. Produto que a loja não vende ou sem envio: `error.warning`.
7. Opção obrigatória vazia e assinatura não escolhida, como o botão do carrinho do core confere: `error.option_<id>` e `error.subscription`. Antes, as opções recebem os valores da variante e o produto vira o mestre como o botão do carrinho da versão faz (`Frenet\Platform`), para a simulação de uma variante cotar o mesmo produto que o carrinho.
8. Quantidade abaixo do mínimo do produto: `error.quantity`.
9. Se o `Cart` do core não devolve o produto que a loja vende (por exemplo, uma extensão mudou a consulta da tabela `cart`), `error.warning` e uma linha de erro no `frenet.log`.
10. Cotação com o produto do `Frenet\Simulator\Cart`, o endereço montado do CEP (país Brasil e estado das faixas dos Correios, para a região geográfica e o imposto valerem como no checkout) e os endereços de imposto como o `startup/tax` do core, com o CEP no lugar do endereço de entrega: `quote` com o nome e o preço de cada serviço, ou `error.warning` com a mensagem do bloco Frenet do checkout.

As chaves de erro são as do botão do carrinho, e o JS do bloco marca os campos do mesmo jeito (`is-invalid` e `#error-<chave>` com `d-block`); a quantidade e os avisos aparecem no próprio bloco. A cotação é a do checkout, com duas diferenças: uma tentativa só, e problemas da resposta da Frenet (serviços com erro, resposta sem serviço) gravados no log só com debug, porque compradores simulam muito mais do que fecham pedidos. Cada simulação conta uma vez no limite, mesmo quando sai da cache. Erro inesperado na rota vira `error_unavailable`, com a classe e o local no log.

A rota `postcode` devolve o CEP do endereço de entrega da sessão ou, para o cliente logado, do endereço padrão, com `Cache-Control: no-store, private`. O JS chama a rota na primeira vez que o visitante foca o campo e, para o cliente logado, ao abrir a página. Enquanto uma simulação está em andamento, outro Enter ou clique fica guardado e só é enviado se o CEP ou o formulário mudaram.

## Padrão de código

O módulo segue o padrão de código do OpenCart 4: o `.php-cs-fixer.php` é o da versão 4.1.0.4 do OpenCart, sem mudanças nas regras. Tabs, variáveis e arquivos em snake_case, classes e métodos em camelCase.

- Classes de fora do arquivo entram por `use`, com alias quando o nome coincide (`use Opencart\System\Engine\Config as CoreConfig;`).
- Todo método tem um docblock com o resumo em uma linha e os tipos em `@param` e `@return`. O construtor só com propriedades promovidas não tem docblock.
- Sem comentários no meio do código.
- Os arquivos de `admin/` e `catalog/` entram no fixer e no PHPCompatibility à medida que forem reescritos (`.php-cs-fixer.php` e `phpcs.xml.dist`).

As classes de teste são `final`, e por isso o fixer exige `self::` nas asserções. Casos da mesma regra com entradas diferentes usam `#[TestWith]` ou `#[DataProvider]`.

Para o PHPStan e os testes, `tests/stubs/opencart.php` imita o `Config`, o `Registry`, o `Log`, o `Cache`, o `DB`, o `Weight` e o `Length` do core do OpenCart 4, e o `Cart` reduzido ao que o simulador usa: apaga carrinhos antigos, lê a tabela `cart` e o produto de cada linha.

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
| `composer compat` | PHPCompatibility do PHP 8.0 em diante em `system/library/frenet`, nos controllers e no model |
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
| `POST /shipping/quote` com `token: slow` | espera 5 s e responde PAC e SEDEX, para testar o tempo limite da cotação |
| `/mock/delay/{segundos}` | espera antes de responder, para testar tempo limite |
| `/mock/redirect` | 302 para `/mock/echo`, para conferir que o cliente não segue redirecionamento |
| `/mock/echo` | devolve método, headers e corpo recebidos |

Para usar fora dos testes:

```bash
php -S 127.0.0.1:8765 tests/mock/router.php
```

Com a variável `FRENET_MOCK_LOG` apontando para um arquivo, o mock grava ali cada `POST /shipping/quote` recebido, uma linha JSON por requisição.

## E2E

`tests/e2e/` monta uma loja OpenCart 4 limpa, com o zip oficial da versão e o tema padrão. Antes do Playwright, o `run.sh` grava no banco um cliente com endereço no Rio de Janeiro, o pagamento na entrega (Cash On Delivery) e as configurações da Frenet, e desliga o aceite dos termos no checkout: a 4.0.0.0 de fábrica pede o aceite de uma página que não existe e nenhum pedido fecha. O cliente entra na loja depois do login no admin: na 4.0.0.0 instalada na raiz, a loja grava o cookie de sessão com `path=//` e o navegador não o devolve; o cookie do admin, com `path=/`, mantém a sessão.

Há dois modos:

- **Instalação** (`run.sh <versão> <php> opencartfrenetv4.ocmod.zip`): envia o pacote em **Extensions → Installer** e instala, instala a Frenet em **Extensions → Extensions → Shipping**, confere o token e a versão na configuração, salva a altura mínima e o peso padrão como zero e confere que voltam a 2 cm e 1 kg, faz o checkout logado com o token `single-service` (a Frenet devolve um só serviço) e fecha o pedido. Depois, com tempo limite de 2 s, abre o checkout em seis cenários e confere o bloco Frenet e os outros fretes: `slow` (2 tentativas; a mensagem aparece em até 5,3 s, que são 2 × 2 s, mais 0,3 s entre as tentativas e 1 s de folga, contados da escolha do endereço até a resposta da cotação), `status-503` (3 tentativas), `status-401` (sem repetir), `empty` (nenhum serviço), `with-errors` (o serviço com `Error` some e o serviço sem prazo aparece sem dias) e `broken` (o arquivo `quote/parser.php` é tirado da loja para provocar um erro inesperado). Nos cenários de falha, a página não pode mostrar caminho de arquivo nem nome de classe. Os textos que o teste viu ficam em `checkout-<cenário>.json`. Em seguida, com 1 tentativa, testa a cache da cotação: no cenário `repeat`, o mesmo carrinho é cotado em duas visitas ao checkout e o mock recebe uma cotação só; no `failure`, com o token `flaky` (o mock falha nas cotações ímpares), a primeira cotação falha, a segunda chega à Frenet e a terceira sai da cache, então o mock recebe duas cotações e o `frenet.log` tem uma falha. Por fim, o `run.sh` cria cinco produtos a partir do produto 40 de exemplo e um cupom de 10% (`FRENET10`), e o teste confere a cotação que o mock recebeu em dois carrinhos: `mixed`, com 2 camisetas de R$ 100 em promoção por R$ 90 (SKU em `oc_product_code` ou em `oc_product.sku`, conforme a versão, e duas categorias), 1 e-book de R$ 50 sem envio e o cupom, que precisa chegar com só as camisetas, R$ 180 declarados e o cupom no `Coupom`; e `units`, com um produto em lb e polegada, um em oz e mm e um sem peso nem medidas, que precisam chegar em kg e cm, com os mínimos. A cotação recebida fica em `quote-<cenário>.json`. No cenário `postcode`, o endereço do cliente fica com o CEP `2004`: o bloco Frenet pede um CEP válido e o mock não recebe nenhuma cotação. Depois, com o token `simulator`, testa o simulador: o bloco logo depois do formulário da camiseta, o visitante sem CEP preenchido, o CEP `2004` recusado embaixo do campo, os fretes de 2 camisetas, nenhum simulador no e-book, a quantidade abaixo do mínimo de outro produto, as opções obrigatórias do produto 42 de exemplo marcadas como no botão do carrinho, o 403 para `Origin` de outro site, o cliente logado com o CEP do endereço padrão e os mesmos fretes, e o checkout com as mesmas 2 camisetas mostrando os mesmos nomes e preços. O mock precisa receber 3 cotações iguais (visitante, cliente e checkout). Numa variante da camiseta, com outro preço e outro peso (`master_id` 1001), a simulação e o checkout precisam mandar a mesma cotação, o que confere o produto mestre até a 4.1.0.3 e a própria variante na 4.1.0.4. Com o limite em 3, a quarta simulação do mesmo IP recebe 429 com `Retry-After` de até 60 s, a tabela guarda o hash do IP e o `frenet.log` grava um bloqueio. Com o simulador desligado, a página não mostra o bloco e a rota recusa a cotação. O `run.sh` confere o evento (gatilho, ação com o separador da versão e status) e a tabela depois da instalação e, no fim, desinstala a Frenet e confere que os dois foram apagados.
- **Atualização** (`run.sh <versão> <php> opencartfrenetv4.ocmod.zip <1.0.0.zip>`): instala a versão 1.0.0 pelo Instalador com o token em `shipping_frenet_contrato_token`, copia o pacote novo sobre `extension/opencartfrenetv4/` como uma atualização por FTP, faz o checkout com o token antigo, fecha o pedido, confere que a página do produto ainda não tem o simulador (o evento não existe) e abre a configuração, que mostra o token na chave nova e registra o evento; aí o simulador aparece e cota.

A cotação com o mesmo token e o mesmo carrinho sai da cache por 10 minutos, também no E2E. Por isso, um cenário novo que confere o que o mock recebeu precisa de um token ou de um carrinho que nenhum cenário anterior da mesma instalação usou.

O teste falha com qualquer erro de JavaScript ou resposta 5xx. Depois do Playwright, o `run.sh` confere que o pedido 1 tem o código `frenet.*` e o nome do serviço, que o token está em `shipping_frenet_token` e nenhuma chave da 1.0.0 sobrou, que o mock recebeu a cotação com o token, `PlatformName` `OpenCart4` e o `User-Agent` com as versões, que a biblioteca carrega pelo autoloader do próprio OpenCart com o token mascarado no `frenet.log`, e que o `error.log` da loja não tem nenhuma linha da Frenet. Na instalação, confere também no `frenet.log` as tentativas de cada cenário de falha, que o 401 não foi repetido, o `Message` da cotação vazia, o `Msg` do serviço com erro e o erro inesperado com a classe, o local e a requisição.

A loja é instalada pelo `install/cli_install.php` do OpenCart, menos a 4.0.0.0: o instalador de linha de comando dessa versão não funciona (falta a constante `APPLICATION`), e o `run.sh` usa o instalador web.

O `compose.yml` sobe o MariaDB, o OpenCart com a versão de PHP pedida, o mock da Frenet e um nginx com o nome `api.frenet.com.br`, que atende o mock por HTTPS com um certificado de uma CA criada a cada execução e confiada só no container da loja.

O CI roda o E2E em 7 versões: a instalação no menor e no maior PHP que cada uma aceita, e a atualização no maior. O pacote da 1.0.0 é gerado do commit publicado na `main` (`PUBLISHED_COMMIT` no workflow).

| OpenCart | Instalação | Atualização |
| --- | --- | --- |
| 4.0.0.0 | PHP 8.0 e 8.1 | PHP 8.1 |
| 4.0.1.1 | PHP 8.0 e 8.1 | PHP 8.1 |
| 4.0.2.0 | PHP 8.0 e 8.3 | PHP 8.3 |
| 4.0.2.3 | PHP 8.0 e 8.3 | PHP 8.3 |
| 4.1.0.0 | PHP 8.0 e 8.4 | PHP 8.4 |
| 4.1.0.3 | PHP 8.0 e 8.4 | PHP 8.4 |
| 4.1.0.4 | PHP 8.1 e 8.5 | PHP 8.5 |

As capturas (instalador, configuração, frete no checkout e pedido no admin), os logs da loja e as cotações recebidas pelo mock ficam nos artefatos `e2e-<modo>-<versão>-php<versão>` da execução; vídeo e trace só quando o teste falha. O teste espera pela tela com localizadores e asserções do Playwright (`expect(...).toBeVisible()`), sem `networkidle` nem esperas fixas.

Para rodar na máquina, com o Docker:

```bash
git archive --format=zip --output=opencartfrenetv4.ocmod.zip HEAD install.json admin catalog system
tests/e2e/run.sh 4.1.0.4 8.1 opencartfrenetv4.ocmod.zip
git archive --format=zip --output=previous.ocmod.zip 3ae7a7befc82ef1a16285703699f249f612de99a install.json admin catalog
tests/e2e/run.sh 4.1.0.4 8.5 opencartfrenetv4.ocmod.zip previous.ocmod.zip
```

O `run.sh` usa o Playwright de `tests/e2e/node_modules` quando há Node na máquina e, sem ele, a imagem `mcr.microsoft.com/playwright` da mesma versão do `package.json`. A loja responde em `http://localhost:8080` (mude com `E2E_PORT`) e é apagada no fim; com `E2E_KEEP=1`, ela continua de pé para investigar. Os resultados ficam em `tests/e2e/.work/artifacts/<versão>-php<versão>-<modo>`.

## Actions do CI

As actions dos workflows são fixadas pelo hash completo do commit, com a versão em comentário (`actions/checkout@<hash> # v7.0.1`), como recomenda o [guia de segurança do GitHub Actions](https://docs.github.com/en/actions/reference/security/secure-use): uma tag pode ser movida para outro commit, o hash não. O Dependabot (`.github/dependabot.yml`) procura versões novas uma vez por semana e, só quando há alguma, abre um PR para a `3.0-develop` com todas juntas, atualizando hash e comentário. Ao adicionar uma action, use o mesmo formato.

## Branches e commits

- `main`: só recebe versões publicadas.
- `3.0-develop`: integração; cada demanda entra aqui por PR.
- Uma branch por demanda, a partir da `3.0-develop`, com o número da issue do roadmap (ex.: `feature/1116-base-tecnica`).
- Commits em inglês com gitmoji, uma responsabilidade por commit (ex.: `:sparkles: feat: add the cURL transport for the Frenet API`).
