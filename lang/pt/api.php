<?php

return [
    'title' => 'API e webhooks', 'sub' => 'Conecte seu próprio site, POS, ferramentas de entrega, Zapier ou Make. Documentação: docs/API.md.',
    'not_included' => 'Seu plano não inclui a API e os webhooks.', 'upgrade' => 'Fazer upgrade do plano',
    'tokens' => 'Tokens de API', 'tokens_help' => 'Envie o token como "Authorization: Bearer …". Dê a cada ferramenta apenas as permissões de que ela precisa.',
    'token_name' => 'Nome', 'token_days' => 'Expira após (dias)', 'token_days_help' => 'Vazio = nunca.', 'token_create' => 'Criar token',
    'new_token' => 'Copie seu token agora. Ele é exibido apenas uma vez.', 'token_revoked' => 'Token revogado.', 'too_many_tokens' => 'Você pode ter no máximo 20 tokens.',
    'ability_menu:read' => 'Ler o cardápio', 'ability_menu:write' => 'Alterar preços e disponibilidade', 'ability_orders:read' => 'Ler pedidos', 'ability_orders:write' => 'Fazer pedidos e alterar o status dos pedidos', 'ability_reservations:read' => 'Ler reservas', 'ability_reservations:write' => 'Criar e alterar reservas', 'ability_customers:read' => 'Ler clientes',
    'last_used' => 'Último uso :time', 'never_used' => 'Nunca usado', 'expires' => 'expira em :date', 'revoke' => 'Revogar', 'revoke_confirm' => 'Revogar este token? As ferramentas que o usam deixam de funcionar.',
    'webhooks' => 'Webhooks', 'webhooks_help' => 'Enviamos por POST uma mensagem JSON assinada para o seu endereço HTTPS quando algo acontece. Verifique o cabeçalho X-Webhook-Signature (veja docs/API.md).',
    'webhook_url' => 'Endereço HTTPS', 'webhook_events' => 'Eventos', 'webhook_add' => 'Adicionar webhook', 'event_*' => 'Tudo',
    'event_order.created' => 'Pedido feito', 'event_order.status_changed' => 'Status do pedido alterado', 'event_order.paid' => 'Pedido pago', 'event_reservation.created' => 'Reserva feita', 'event_reservation.status_changed' => 'Status da reserva alterado', 'event_ping' => 'Teste',
    'new_secret' => 'Segredo de assinatura de :url. Copie-o agora; ele é exibido apenas uma vez.', 'url_not_allowed' => 'Este endereço não pode ser usado. Deve ser um endereço HTTPS público.',
    'too_many_endpoints' => 'Você pode ter no máximo 10 webhooks.', 'endpoint_deleted' => 'Webhook excluído.', 'test_sent' => 'Mensagem de teste na fila.', 'send_test' => 'Enviar teste',
    'disabled_after_failures' => 'Desativado após falhas repetidas. Corrija o endereço e ative-o novamente.', 'deliveries_empty' => 'Nenhuma entrega ainda.',
    'status_sent' => 'Entregue', 'status_failed' => 'Falhou', 'status_pending' => 'Aguardando', 'turn_on' => 'Ativar', 'turn_off' => 'Desativar', 'delete_confirm' => 'Excluir este webhook?',
];
