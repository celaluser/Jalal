<?php

return [
    'title' => 'API 与 Webhook', 'sub' => '连接您自己的网站、POS、配送工具、Zapier 或 Make。文档：docs/API.md。',
    'not_included' => '您的套餐不包含 API 和 Webhook。', 'upgrade' => '升级套餐',
    'tokens' => 'API 令牌', 'tokens_help' => '以“Authorization: Bearer …”发送令牌。只给每个工具它所需的权限。',
    'token_name' => '名称', 'token_days' => '有效天数', 'token_days_help' => '留空 = 永不过期。', 'token_create' => '创建令牌',
    'new_token' => '请立即复制您的令牌，它只显示一次。', 'token_revoked' => '令牌已撤销。', 'too_many_tokens' => '最多只能有 20 个令牌。',
    'ability_menu:read' => '读取菜单', 'ability_menu:write' => '修改价格和供应状态', 'ability_orders:read' => '读取订单', 'ability_orders:write' => '下单并修改订单状态', 'ability_reservations:read' => '读取预订', 'ability_reservations:write' => '创建和修改预订', 'ability_customers:read' => '读取顾客',
    'last_used' => '最近使用 :time', 'never_used' => '从未使用', 'expires' => '到期 :date', 'revoke' => '撤销', 'revoke_confirm' => '撤销此令牌？使用它的工具将停止工作。',
    'webhooks' => 'Webhook', 'webhooks_help' => '事件发生时，我们会向您的 HTTPS 地址 POST 一条带签名的 JSON 消息。请校验 X-Webhook-Signature 头（见 docs/API.md）。',
    'webhook_url' => 'HTTPS 地址', 'webhook_events' => '事件', 'webhook_add' => '添加 Webhook', 'event_*' => '全部',
    'event_order.created' => '订单已提交', 'event_order.status_changed' => '订单状态已变更', 'event_order.paid' => '订单已支付', 'event_reservation.created' => '预订已创建', 'event_reservation.status_changed' => '预订状态已变更', 'event_ping' => '测试',
    'new_secret' => ':url 的签名密钥。请立即复制，它只显示一次。', 'url_not_allowed' => '无法使用此地址，必须是公网 HTTPS 地址。',
    'too_many_endpoints' => '最多只能有 10 个 Webhook。', 'endpoint_deleted' => 'Webhook 已删除。', 'test_sent' => '测试消息已加入队列。', 'send_test' => '发送测试',
    'disabled_after_failures' => '多次失败后已关闭。请修正地址后重新开启。', 'deliveries_empty' => '暂无投递记录。',
    'status_sent' => '已送达', 'status_failed' => '失败', 'status_pending' => '等待中', 'turn_on' => '开启', 'turn_off' => '关闭', 'delete_confirm' => '删除此 Webhook？',
];
