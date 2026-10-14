<?php

return [
    'title' => 'API və webhook-lar', 'sub' => 'Öz saytınızı, POS-u, çatdırılma alətlərini, Zapier və ya Make-i qoşun. Sənədləşmə: docs/API.md.',
    'not_included' => 'Planınıza API və webhook-lar daxil deyil.', 'upgrade' => 'Planı yüksəlt',
    'tokens' => 'API tokenləri', 'tokens_help' => 'Tokeni "Authorization: Bearer …" kimi göndərin. Hər alətə yalnız lazım olan səlahiyyətləri verin.',
    'token_name' => 'Ad', 'token_days' => 'Bitmə müddəti (gün)', 'token_days_help' => 'Boş = heç vaxt.', 'token_create' => 'Token yarat',
    'new_token' => 'Tokeninizi indi kopyalayın. Yalnız bir dəfə göstərilir.', 'token_revoked' => 'Token ləğv edildi.', 'too_many_tokens' => 'Ən çox 20 tokeniniz ola bilər.',
    'ability_menu:read' => 'Menyunu oxumaq', 'ability_menu:write' => 'Qiymətləri və mövcudluğu dəyişmək', 'ability_orders:read' => 'Sifarişləri oxumaq', 'ability_orders:write' => 'Sifariş vermək və sifariş statusunu dəyişmək', 'ability_reservations:read' => 'Rezervasiyaları oxumaq', 'ability_reservations:write' => 'Rezervasiya yaratmaq və dəyişmək', 'ability_customers:read' => 'Müştəriləri oxumaq',
    'last_used' => 'Son istifadə: :time', 'never_used' => 'Heç istifadə olunmayıb', 'expires' => 'bitir: :date', 'revoke' => 'Ləğv et', 'revoke_confirm' => 'Bu token ləğv edilsin? Ondan istifadə edən alətlər işləməyi dayandırır.',
    'webhooks' => 'Webhook-lar', 'webhooks_help' => 'Nəsə baş verdikdə HTTPS ünvanınıza imzalı JSON mesajı POST edirik. X-Webhook-Signature başlığını yoxlayın (docs/API.md bölməsinə baxın).',
    'webhook_url' => 'HTTPS ünvanı', 'webhook_events' => 'Hadisələr', 'webhook_add' => 'Webhook əlavə et', 'event_*' => 'Hamısı',
    'event_order.created' => 'Sifariş verildi', 'event_order.status_changed' => 'Sifariş statusu dəyişdi', 'event_order.paid' => 'Sifariş ödənildi', 'event_reservation.created' => 'Rezervasiya edildi', 'event_reservation.status_changed' => 'Rezervasiya statusu dəyişdi', 'event_ping' => 'Sınaq',
    'new_secret' => ':url üçün imza açarı. İndi kopyalayın; yalnız bir dəfə göstərilir.', 'url_not_allowed' => 'Bu ünvandan istifadə etmək olmaz. Açıq HTTPS ünvanı olmalıdır.',
    'too_many_endpoints' => 'Ən çox 10 webhook-unuz ola bilər.', 'endpoint_deleted' => 'Webhook silindi.', 'test_sent' => 'Sınaq mesajı növbəyə qoyuldu.', 'send_test' => 'Sınaq göndər',
    'disabled_after_failures' => 'Təkrarlanan uğursuzluqlardan sonra söndürüldü. Ünvanı düzəldin, sonra yenidən aktiv edin.', 'deliveries_empty' => 'Hələ çatdırılma yoxdur.',
    'status_sent' => 'Çatdırıldı', 'status_failed' => 'Uğursuz oldu', 'status_pending' => 'Gözləyir', 'turn_on' => 'Aktiv et', 'turn_off' => 'Deaktiv et', 'delete_confirm' => 'Bu webhook silinsin?',
];
