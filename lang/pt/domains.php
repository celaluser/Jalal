<?php

return [
    'title' => 'Domínios', 'subtitle' => 'Os endereços web que seus clientes podem usar para abrir o seu cardápio.',
    'default_address' => 'Endereço sempre ativo', 'default_help' => 'Este link sempre funciona, independentemente do que você configurar. Imprima-o nos seus QR codes.',
    'subdomain' => 'Subdomínio', 'subdomain_help' => 'Letras, números e hifens, com pelo menos 3 caracteres.', 'subdomain_off' => 'Os subdomínios não estão habilitados nesta plataforma.', 'subdomain_preview' => 'Seu cardápio abrirá em',
    'custom_domain' => 'Seu próprio domínio', 'custom_help' => 'Por exemplo, cardapio.seurestaurante.com.br. Conecte-o em três etapas.', 'custom_off' => 'Os domínios personalizados não estão habilitados nesta plataforma.',
    'custom_upgrade' => 'Seu plano não inclui domínio personalizado.', 'upgrade' => 'Fazer upgrade do plano', 'remove_domain' => 'Deixe o campo vazio e salve para desconectar.',
    'save' => 'Salvar', 'status_verified' => 'Conectado', 'status_pending' => 'Aguardando DNS', 'status_none' => 'Não definido',
    'step1' => 'Digite o domínio acima e salve.', 'step2' => 'No seu provedor de domínio, adicione este registro TXT para comprovar que ele é seu:', 'step3' => 'Clique em “Verificar DNS”. As alterações de DNS podem levar até uma hora para se propagar.',
    'record_type' => 'Tipo', 'record_host' => 'Host', 'record_value' => 'Valor', 'cname_hint' => 'Aponte também o domínio para nós com um registro CNAME para :target (ou siga as instruções abaixo).',
    'platform_instructions' => 'Instruções da plataforma', 'check_dns' => 'Verificar DNS', 'copy' => 'Copiar', 'copied' => 'Copiado',
    'verified_now' => 'Seu domínio está conectado.', 'not_found_yet' => 'Ainda não encontramos o registro DNS. Confira e tente novamente em alguns minutos.', 'verified_manual' => 'Domínio verificado',
    'error_upgrade' => 'O endereço próprio é um recurso da loja. Adquira-o primeiro na loja.', 'error_unavailable' => 'Isto não está disponível para o seu restaurante.', 'error_invalid' => 'Isso não parece um endereço válido.', 'error_reserved' => 'Esse nome é reservado. Escolha outro.',
    'error_taken' => 'Esse endereço já é usado por outro restaurante.', 'error_platform' => 'Você não pode usar aqui o domínio da própria plataforma.',
];
