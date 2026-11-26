<?php
// Heading
$_['heading_title']           = 'Frenet';

// Text
$_['text_extension']          = 'Extensões';
$_['text_success']            = 'Frete Frenet modificado com sucesso!';
$_['text_edit']               = 'Editando Frenet';
$_['text_version']            = 'Version %s';

// Entry
$_['entry_tax_class']         = 'Grupo de Impostos';
$_['entry_geo_zone']          = 'Região Geográfica';
$_['entry_status']            = 'Situação';
$_['entry_postcode']          = 'CEP de origem';
$_['entry_sort_order']        = 'Posição';
$_['entry_frenet_key']        = 'Chave de acesso';
$_['entry_msg_prazo']         = 'Mensagem que exibe o prazo de entrega';
$_['entry_timeout']           = 'Time limit (seconds)';
$_['entry_attempts']          = 'Attempts';
$_['entry_debug']             = 'Debug';

$_['entry_frenet_token']        = 'Token';
$_['entry_frenet_key_token']   	= 'Token';

// Help
$_['help_frenet_token']       = 'Token de acesso à API REST da Frenet.';
$_['help_msg_prazo']          = '%s - Em média %d dia(s)';
$_['help_timeout']            = 'How long each quote attempt waits for Frenet to answer, from 2 to 10 seconds.';
$_['help_attempts']           = 'How many times the quote is sent when Frenet does not answer or answers with a server error, from 1 to 3. An invalid token and the other refusals of Frenet (4xx errors other than 408 and 429) are never retried.';
$_['help_debug']              = 'Writes every quote request and response to frenet.log, without the token. Errors are always written.';

// Error
$_['error_permission']        = 'Atenção: Você não tem permissão para modificar a extensão Frenet!';
$_['error_postcode']          = 'Formato inválido de CEP!';
