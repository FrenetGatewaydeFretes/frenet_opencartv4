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
$_['entry_weight_class']      = 'Kilogram weight class';
$_['entry_length_class']      = 'Centimetre length class';
$_['entry_minimum_height']    = 'Minimum height (cm)';
$_['entry_minimum_width']     = 'Minimum width (cm)';
$_['entry_minimum_length']    = 'Minimum length (cm)';
$_['entry_default_weight']    = 'Default weight (kg)';
$_['entry_timeout']           = 'Time limit (seconds)';
$_['entry_attempts']          = 'Attempts';
$_['entry_debug']             = 'Debug';

$_['entry_frenet_token']        = 'Token';
$_['entry_frenet_key_token']   	= 'Token';

// Help
$_['help_frenet_token']       = 'Token de acesso à API REST da Frenet.';
$_['help_msg_prazo']          = '%s - Em média %d dia(s)';
$_['help_weight_class']       = 'Store weight class that matches the kilogram. Each product weight is converted to it.';
$_['help_length_class']       = 'Store length class that matches the centimetre. Each product dimension is converted to it.';
$_['help_minimum_height']     = 'Smallest height sent to Frenet: a lower product, or one without height, goes with this value. Default: 2 cm, the Correios minimum.';
$_['help_minimum_width']      = 'Smallest width sent to Frenet: a narrower product, or one without width, goes with this value. Default: 11 cm, the Correios minimum.';
$_['help_minimum_length']     = 'Smallest length sent to Frenet: a shorter product, or one without length, goes with this value. Default: 16 cm, the Correios minimum.';
$_['help_default_weight']     = 'Weight sent to Frenet for a product registered without one. Default: 1 kg.';
$_['help_timeout']            = 'How long each quote attempt waits for Frenet to answer, from 2 to 10 seconds.';
$_['help_attempts']           = 'How many times the quote is sent when Frenet does not answer or answers with a server error, from 1 to 3. An invalid token and the other refusals of Frenet (4xx errors other than 408 and 429) are never retried.';
$_['help_debug']              = 'Writes every quote request and response to frenet.log, without the token. Errors are always written.';

// Error
$_['error_permission']        = 'Atenção: Você não tem permissão para modificar a extensão Frenet!';
$_['error_postcode']          = 'Formato inválido de CEP!';
