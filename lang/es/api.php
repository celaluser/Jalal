<?php

return [
    'title' => 'API y webhooks', 'sub' => 'Conecta tu propio sitio web, POS, herramientas de reparto, Zapier o Make. Documentación: docs/API.md.',
    'not_included' => 'Tu plan no incluye la API ni los webhooks.', 'upgrade' => 'Mejorar tu plan',
    'tokens' => 'Tokens de API', 'tokens_help' => 'Envía el token como "Authorization: Bearer …". Da a cada herramienta solo los permisos que necesita.',
    'token_name' => 'Nombre', 'token_days' => 'Caduca después de (días)', 'token_days_help' => 'Vacío = nunca.', 'token_create' => 'Crear token',
    'new_token' => 'Copia tu token ahora. Solo se muestra una vez.', 'token_revoked' => 'Token revocado.', 'too_many_tokens' => 'Puedes tener como máximo 20 tokens.',
    'ability_menu:read' => 'Leer el menú', 'ability_menu:write' => 'Cambiar precios y disponibilidad', 'ability_orders:read' => 'Leer pedidos', 'ability_orders:write' => 'Crear pedidos y cambiar su estado', 'ability_reservations:read' => 'Leer reservas', 'ability_reservations:write' => 'Crear y modificar reservas', 'ability_customers:read' => 'Leer clientes',
    'last_used' => 'Último uso :time', 'never_used' => 'Nunca usado', 'expires' => 'caduca :date', 'revoke' => 'Revocar', 'revoke_confirm' => '¿Revocar este token? Las herramientas que lo usan dejarán de funcionar.',
    'webhooks' => 'Webhooks', 'webhooks_help' => 'Enviamos (POST) un mensaje JSON firmado a tu dirección HTTPS cuando ocurre algo. Verifica el encabezado X-Webhook-Signature (consulta docs/API.md).',
    'webhook_url' => 'Dirección HTTPS', 'webhook_events' => 'Eventos', 'webhook_add' => 'Añadir webhook', 'event_*' => 'Todo',
    'event_order.created' => 'Pedido realizado', 'event_order.status_changed' => 'Estado del pedido cambiado', 'event_order.paid' => 'Pedido pagado', 'event_reservation.created' => 'Reserva realizada', 'event_reservation.status_changed' => 'Estado de la reserva cambiado', 'event_ping' => 'Prueba',
    'new_secret' => 'Secreto de firma para :url. Cópialo ahora; solo se muestra una vez.', 'url_not_allowed' => 'No se puede usar esta dirección. Debe ser una dirección HTTPS pública.',
    'too_many_endpoints' => 'Puedes tener como máximo 10 webhooks.', 'endpoint_deleted' => 'Webhook eliminado.', 'test_sent' => 'Mensaje de prueba en cola.', 'send_test' => 'Enviar prueba',
    'disabled_after_failures' => 'Desactivado tras fallos repetidos. Corrige la dirección y vuelve a activarlo.', 'deliveries_empty' => 'Aún no hay envíos.',
    'status_sent' => 'Entregado', 'status_failed' => 'Fallido', 'status_pending' => 'En espera', 'turn_on' => 'Activar', 'turn_off' => 'Desactivar', 'delete_confirm' => '¿Eliminar este webhook?',
];
