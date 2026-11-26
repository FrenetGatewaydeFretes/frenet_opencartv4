<?php
// Heading
$_['heading_title']           = 'Frenet';

// Text
$_['text_extension']          = 'Extensões';
$_['text_success']            = 'Frete Frenet modificado com sucesso!';
$_['text_edit']               = 'Editando Frenet';
$_['text_version']            = 'Versão %s';

// Entry
$_['entry_tax_class']         = 'Grupo de Impostos';
$_['entry_geo_zone']          = 'Região Geográfica';
$_['entry_status']            = 'Situação';
$_['entry_postcode']          = 'CEP de origem';
$_['entry_sort_order']        = 'Posição';
$_['entry_frenet_key']        = 'Chave de acesso';
$_['entry_msg_prazo']         = 'Mensagem que exibe o prazo de entrega';
$_['entry_weight_class']      = 'Classe de peso em quilos';
$_['entry_length_class']      = 'Classe de medida em centímetros';
$_['entry_minimum_height']    = 'Altura mínima (cm)';
$_['entry_minimum_width']     = 'Largura mínima (cm)';
$_['entry_minimum_length']    = 'Comprimento mínimo (cm)';
$_['entry_default_weight']    = 'Peso padrão (kg)';
$_['entry_timeout']           = 'Tempo limite (segundos)';
$_['entry_attempts']          = 'Tentativas';
$_['entry_debug']             = 'Debug';

$_['entry_frenet_token']        = 'Token';
$_['entry_frenet_key_token']   	= 'Token';

// Help
$_['help_frenet_token']       = 'Token de acesso à API REST da Frenet.';
$_['help_msg_prazo']          = '%s - Em média %d dia(s)';
$_['help_weight_class']       = 'Classe de peso da loja que corresponde ao quilograma. O peso de cada produto é convertido para ela.';
$_['help_length_class']       = 'Classe de medida da loja que corresponde ao centímetro. As medidas de cada produto são convertidas para ela.';
$_['help_minimum_height']     = 'Menor altura enviada à Frenet: produto mais baixo ou sem altura vai com este valor. Padrão: 2 cm, a mínima dos Correios.';
$_['help_minimum_width']      = 'Menor largura enviada à Frenet: produto mais estreito ou sem largura vai com este valor. Padrão: 11 cm, a mínima dos Correios.';
$_['help_minimum_length']     = 'Menor comprimento enviado à Frenet: produto mais curto ou sem comprimento vai com este valor. Padrão: 16 cm, o mínimo dos Correios.';
$_['help_default_weight']     = 'Peso enviado à Frenet para o produto cadastrado sem peso. Padrão: 1 kg.';
$_['help_timeout']            = 'Quanto cada tentativa de cotação espera a Frenet responder, de 2 a 10 segundos.';
$_['help_attempts']           = 'Quantas vezes a cotação é enviada quando a Frenet não responde ou responde com erro do servidor, de 1 a 3. Token inválido e as outras recusas da Frenet (erros 4xx, exceto 408 e 429) nunca são repetidos.';
$_['help_debug']              = 'Grava em frenet.log cada requisição e resposta da cotação, sem o token. Os erros são gravados sempre.';

// Error
$_['error_permission']        = 'Atenção: Você não tem permissão para modificar a extensão Frenet!';
$_['error_postcode']          = 'Formato inválido de CEP!';
