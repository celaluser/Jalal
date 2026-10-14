<?php

return [
    'title' => 'API & webhook', 'sub' => 'Hubungkan situs web Anda sendiri, POS, alat pengiriman, Zapier, atau Make. Dokumentasi: docs/API.md.',
    'not_included' => 'Paket Anda tidak mencakup API dan webhook.', 'upgrade' => 'Tingkatkan paket Anda',
    'tokens' => 'Token API', 'tokens_help' => 'Kirim token sebagai "Authorization: Bearer …". Beri setiap alat hanya kemampuan yang dibutuhkan.',
    'token_name' => 'Nama', 'token_days' => 'Kedaluwarsa setelah (hari)', 'token_days_help' => 'Kosong = tidak pernah.', 'token_create' => 'Buat token',
    'new_token' => 'Salin token Anda sekarang. Token hanya ditampilkan sekali.', 'token_revoked' => 'Token dicabut.', 'too_many_tokens' => 'Anda dapat memiliki paling banyak 20 token.',
    'ability_menu:read' => 'Membaca menu', 'ability_menu:write' => 'Mengubah harga dan ketersediaan', 'ability_orders:read' => 'Membaca pesanan', 'ability_orders:write' => 'Membuat pesanan dan mengubah status pesanan', 'ability_reservations:read' => 'Membaca reservasi', 'ability_reservations:write' => 'Membuat dan mengubah reservasi', 'ability_customers:read' => 'Membaca pelanggan',
    'last_used' => 'Terakhir dipakai :time', 'never_used' => 'Belum pernah dipakai', 'expires' => 'kedaluwarsa :date', 'revoke' => 'Cabut', 'revoke_confirm' => 'Cabut token ini? Alat yang memakainya akan berhenti berfungsi.',
    'webhooks' => 'Webhook', 'webhooks_help' => 'Kami mengirim (POST) pesan JSON bertanda tangan ke alamat HTTPS Anda saat sesuatu terjadi. Verifikasi header X-Webhook-Signature (lihat docs/API.md).',
    'webhook_url' => 'Alamat HTTPS', 'webhook_events' => 'Peristiwa', 'webhook_add' => 'Tambah webhook', 'event_*' => 'Semua',
    'event_order.created' => 'Pesanan dibuat', 'event_order.status_changed' => 'Status pesanan berubah', 'event_order.paid' => 'Pesanan dibayar', 'event_reservation.created' => 'Reservasi dibuat', 'event_reservation.status_changed' => 'Status reservasi berubah', 'event_ping' => 'Uji',
    'new_secret' => 'Rahasia penandatanganan untuk :url. Salin sekarang; hanya ditampilkan sekali.', 'url_not_allowed' => 'Alamat ini tidak dapat digunakan. Harus berupa alamat HTTPS publik.',
    'too_many_endpoints' => 'Anda dapat memiliki paling banyak 10 webhook.', 'endpoint_deleted' => 'Webhook dihapus.', 'test_sent' => 'Pesan uji masuk antrean.', 'send_test' => 'Kirim uji',
    'disabled_after_failures' => 'Dinonaktifkan setelah gagal berulang kali. Perbaiki alamatnya, lalu aktifkan kembali.', 'deliveries_empty' => 'Belum ada pengiriman.',
    'status_sent' => 'Terkirim', 'status_failed' => 'Gagal', 'status_pending' => 'Menunggu', 'turn_on' => 'Aktifkan', 'turn_off' => 'Nonaktifkan', 'delete_confirm' => 'Hapus webhook ini?',
];
