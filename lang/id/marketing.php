<?php

return [
    // Customers
    'customers_title' => 'Pelanggan', 'customers_sub' => 'Semua orang yang meninggalkan detail mereka saat memesan. Bangun loyalitas, kenali pelanggan tetap, dan kirim pesan kepada yang setuju.',
    'search_customers' => 'Cari nama, email, atau telepon', 'filter_all' => 'Semua', 'filter_opted' => 'Setuju menerima pemasaran', 'filter_repeat' => 'Pelanggan tetap (2+ pesanan)',
    'sort_recent' => 'Pesanan terbaru', 'sort_orders' => 'Pesanan terbanyak', 'sort_spent' => 'Pengeluaran terbesar', 'sort_name' => 'Nama',
    'col_customer' => 'Pelanggan', 'col_orders' => 'Pesanan', 'col_spent' => 'Pengeluaran', 'col_last' => 'Pesanan terakhir', 'col_marketing' => 'Pemasaran',
    'stat_all' => 'Pelanggan', 'stat_opted' => 'Dapat dihubungi lewat email', 'stat_repeat' => 'Pelanggan tetap',
    'consent_yes' => 'Setuju', 'consent_no' => 'Tidak', 'consent_unsub' => 'Berhenti berlangganan', 'anonymous' => 'Tamu', 'export' => 'Ekspor CSV',
    'customers_empty' => 'Belum ada pelanggan', 'customers_empty_text' => 'Tamu muncul di sini saat mereka meninggalkan nama, telepon, atau email bersama pesanan.',
    'customer_since' => 'Pelanggan sejak :date', 'agreed_on' => 'Setuju menerima pemasaran pada :date', 'unsubscribed_on' => 'Berhenti berlangganan pada :date', 'no_consent' => 'Belum setuju menerima email pemasaran.',
    'notes' => 'Catatan pribadi', 'notes_help' => 'Hanya tim Anda yang melihat ini. Alergi, preferensi, apa pun yang perlu diingat.', 'order_history' => 'Pesanan', 'no_orders' => 'Tidak ada pesanan.',
    'delete_customer' => 'Hapus pelanggan ini', 'delete_help' => 'Menghapus catatan dan menghapus nama, telepon, email, dan alamat dari pesanan mereka. Angka penjualan tetap. Tindakan ini tidak dapat dibatalkan.', 'delete_confirm' => 'Hapus pelanggan ini dan hapus data pribadinya?',
    'customer_deleted' => 'Pelanggan dihapus dan data pribadi dihapus.', 'avg_order' => 'Rata-rata pesanan',

    // Promo codes
    'promos_title' => 'Kode promo', 'promos_sub' => 'Kode diskon yang dimasukkan tamu saat pembayaran. Jangan tertukar dengan kupon langganan dari platform.',
    'new_promo' => 'Kode baru', 'edit_promo' => 'Ubah kode', 'promo_code' => 'Kode', 'promo_desc' => 'Deskripsi', 'promo_desc_help' => 'Hanya Anda yang melihat ini.',
    'promo_type' => 'Jenis diskon', 'type_percent' => 'Diskon persen', 'type_fixed' => 'Potongan nominal', 'promo_value' => 'Nilai', 'promo_min' => 'Pesanan minimum', 'promo_max_uses' => 'Penggunaan maksimum', 'promo_max_help' => 'Kosongkan untuk tanpa batas.',
    'promo_starts' => 'Mulai', 'promo_ends' => 'Berakhir', 'promo_active' => 'Aktif', 'promo_uses' => 'Dipakai', 'promo_status' => 'Status',
    'status_active' => 'Aktif', 'status_off' => 'Dinonaktifkan', 'status_expired' => 'Kedaluwarsa', 'status_scheduled' => 'Terjadwal', 'status_used_up' => 'Habis dipakai', 'reward_badge' => 'Hadiah loyalitas', 'reward_for' => 'Untuk :name',
    'promo_saved' => 'Kode promo tersimpan.', 'promo_deleted' => 'Kode promo dihapus.', 'promos_empty' => 'Belum ada kode promo', 'promos_empty_text' => 'Buat kode seperti WELCOME10 dan bagikan di media sosial atau kampanye Anda.',
    'turn_off' => 'Nonaktifkan', 'turn_on' => 'Aktifkan', 'delete_promo_confirm' => 'Hapus kode promo ini?', 'min_order_label' => 'mulai :amount',
    'reward_percent' => 'Diskon :value%', 'reward_fixed' => 'Potongan :amount',

    // Guest checkout
    'promo_field' => 'Kode promo', 'promo_apply' => 'Terapkan', 'promo_remove' => 'Hapus', 'promo_applied' => 'Kode :code diterapkan: potongan :amount.', 'promo_discount' => 'Diskon',
    'opt_in' => 'Kirimi saya penawaran dan kabar dari :name lewat email. Saya dapat berhenti berlangganan kapan saja.',

    // Reviews
    'reviews_title' => 'Ulasan', 'reviews_sub' => 'Apa kata tamu setelah makan.', 'reviews_empty' => 'Belum ada ulasan', 'reviews_empty_text' => 'Tamu diundang menilai pesanan mereka setelah selesai.',
    'average' => 'Rating rata-rata', 'review_count' => '{1} 1 ulasan|[2,*] :count ulasan', 'filter_low' => 'Rating rendah', 'filter_unanswered' => 'Belum dijawab',
    'low_alert' => '{1} 1 rating rendah masih perlu dijawab.|[2,*] :count rating rendah masih perlu dijawab.', 'low_flag' => 'Perlu perhatian',
    'reply' => 'Balasan Anda', 'reply_save' => 'Simpan balasan', 'reply_help' => 'Ditampilkan kepada tamu di halaman pesanan mereka.', 'hide' => 'Sembunyikan dari menu', 'show' => 'Tampilkan di menu', 'hidden' => 'Tersembunyi', 'order_ref' => 'Pesanan :number',
    'public_note' => 'Terlihat di menu Anda', 'stars' => '{1} :count bintang|[2,*] :count dari 5 bintang',

    // Guest review form
    'rate_title' => 'Bagaimana?', 'rate_sub' => 'Nilai pesanan Anda. Hanya butuh sepuluh detik.', 'rate_comment' => 'Ada yang ingin ditambahkan? (opsional)', 'rate_public' => 'Tampilkan rating saya (dengan nama depan saya) di menu',
    'rate_send' => 'Kirim rating saya', 'rate_thanks' => 'Terima kasih atas masukan Anda!', 'rate_yours' => 'Rating Anda', 'rate_reply' => 'Balasan dari restoran',
    'review_error_closed' => 'Rating tidak dibuka untuk pesanan ini.', 'review_error_done' => 'Anda sudah menilai pesanan ini.', 'review_error_rating' => 'Pilih antara 1 dan 5 bintang.',
    'rating_on_menu' => ':average (:count)',

    // Loyalty & settings
    'loyalty_title' => 'Loyalitas & ulasan', 'loyalty_sub' => 'Beri hadiah pelanggan tetap secara otomatis dan tanyakan pendapat tamu.',
    'loyalty_section' => 'Hadiah loyalitas', 'loyalty_enabled' => 'Beri hadiah tamu tetap', 'loyalty_enabled_help' => 'Setelah setiap pesanan selesai ke-N, tamu mendapat kode promo sekali pakai yang hanya bisa digunakan olehnya. Muncul di halaman pesanan dan di email.',
    'loyalty_every' => 'Beri hadiah setiap … pesanan selesai', 'loyalty_type' => 'Hadiah', 'loyalty_value' => 'Nilai', 'loyalty_percent_hint' => 'Persen dari pesanan', 'loyalty_fixed_hint' => 'Nominal dalam mata uang Anda', 'loyalty_valid' => 'Berlaku selama (hari)',
    'reviews_section' => 'Ulasan', 'reviews_enabled' => 'Minta tamu menilai pesanan yang selesai', 'review_email' => 'Minta juga lewat email jika tamu meninggalkan alamat', 'show_rating' => 'Tampilkan rating rata-rata di menu publik',
    'messages_section' => 'Pesan teks', 'calling_code' => 'Kode panggilan negara', 'calling_code_help' => 'Dipakai untuk mengirim SMS dan WhatsApp ke tamu yang mengetik nomor lokal, misalnya 62 untuk Indonesia. Kosongkan untuk hanya mengirim ke nomor yang diawali +.',
    'email_section' => 'Pengiriman email', 'daily_cap' => 'Email kampanye per hari', 'daily_cap_help' => 'Platform mengizinkan paling banyak :max per restoran per hari. Menjaga akun email Anda tetap baik.',
    'reward_earned' => 'Anda mendapat hadiah!', 'reward_earned_text' => 'Terima kasih sudah memesan lagi dan lagi. Gunakan kode ini pada pesanan berikutnya:', 'reward_valid_until' => 'Berlaku hingga :date, sekali pakai, hanya untuk Anda.',
    'there' => 'Anda',

    // Campaigns
    'campaigns_title' => 'Kampanye', 'campaigns_sub' => 'Kirim email ke tamu yang setuju menerima kabar dari Anda. Setiap pesan memiliki tautan berhenti berlangganan.',
    'new_campaign' => 'Kampanye baru', 'edit_campaign' => 'Ubah kampanye', 'campaign_name' => 'Nama', 'campaign_name_help' => 'Hanya Anda yang melihat ini.', 'campaign_subject' => 'Subjek', 'campaign_body' => 'Pesan',
    'campaign_body_help' => 'Teks biasa dengan format sederhana (**tebal**, [tautan](https://…)). Gunakan {{name}} dan {{restaurant}} untuk personalisasi.', 'campaign_audience' => 'Siapa yang menerima', 'min_orders' => 'Hanya tamu dengan minimal … pesanan', 'min_orders_help' => '0 berarti semua yang setuju.',
    'audience_now' => '{0} Belum ada yang dapat menerima ini.|{1} 1 tamu akan menerima ini.|[2,*] :count tamu akan menerima ini.', 'audience_note' => 'Hanya tamu yang mencentang kotak email saat pembayaran, memiliki alamat, dan belum berhenti berlangganan.',
    'campaign_saved' => 'Kampanye tersimpan.', 'campaign_deleted' => 'Kampanye dihapus.', 'campaign_sending' => 'Kampanye Anda sedang dikirim. Bisa memakan waktu beberapa menit.',
    'campaign_error_sent' => 'Kampanye ini sudah dikirim.', 'campaign_error_empty' => 'Belum ada yang dapat menerima kampanye ini.',
    'status_draft' => 'Draf', 'status_sending' => 'Mengirim', 'status_sent' => 'Terkirim', 'campaigns_empty' => 'Belum ada kampanye', 'campaigns_empty_text' => 'Beri tahu pelanggan tetap Anda tentang hidangan baru atau penawaran hari sepi.',
    'sent_count' => 'Terkirim', 'skipped_count' => 'Tidak terkirim', 'recipients' => 'Penerima', 'send_test' => 'Kirim uji kepada saya', 'send_now' => 'Kirim kampanye', 'send_confirm' => 'Kirim kampanye ini sekarang? Tidak dapat dibatalkan.',
    'test_sent' => 'Email uji dikirim ke :email.', 'today_room' => ':count email lagi dapat dikirim hari ini (batas :cap).', 'cap_note' => 'Tamu di luar batas hari ini dilewati. Kirim lagi besok dengan kampanye baru.', 'sent_on' => 'Dikirim :date',
    'preview' => 'Pratinjau', 'unsub_footer' => 'Anda menerima ini karena Anda setuju menerima penawaran dari :name.', 'unsub_link' => 'Berhenti berlangganan',

    // Unsubscribe page
    'unsub_title' => 'Berhenti berlangganan', 'unsub_ask' => 'Hentikan email dari :name?', 'unsub_ask_text' => 'Anda tidak akan lagi menerima penawaran dan kabar. Pembaruan pesanan tetap sampai kepada Anda.', 'unsub_button' => 'Ya, hentikan langganan saya',
    'unsub_done' => 'Anda telah berhenti berlangganan', 'unsub_done_text' => 'Anda tidak akan lagi menerima email pemasaran dari :name.', 'unsub_already' => 'Anda sudah berhenti berlangganan dari :name.',

    // Banners and pop-ups
    'banners_title' => 'Banner & pop-up', 'banners_sub' => 'Tampilkan hidangan baru, penawaran, atau pemberitahuan di bagian atas menu Anda.', 'new_banner' => 'Banner baru', 'edit_banner' => 'Ubah banner',
    'banners_empty' => 'Belum ada banner', 'banners_empty_text' => 'Tambahkan banner untuk memberi tahu tamu tentang penawaran, hidangan baru, atau jam buka.', 'banner_saved' => 'Banner tersimpan.', 'banner_deleted' => 'Banner dihapus.',
    'banner_title' => 'Judul', 'banner_text' => 'Teks singkat', 'banner_link' => 'Tautan', 'banner_link_hint' => 'Alamat https://, atau #cat-12 untuk melompat ke bagian menu. Opsional.', 'banner_button' => 'Teks tombol',
    'banner_starts' => 'Tampilkan mulai', 'banner_ends' => 'Tampilkan hingga', 'banner_popup' => 'Tampilkan sebagai pop-up (sekali per kunjungan) alih-alih banner', 'popup' => 'Pop-up', 'status_off' => 'Mati',

    'review_url' => 'Tautan ulasan publik', 'review_url_hint' => 'Misalnya tautan ulasan Google Anda. Tamu yang puas diundang membagikan rating mereka di sana.', 'review_min' => 'Tawarkan mulai (bintang)', 'review_public_invite' => 'Senang Anda menyukainya! Maukah Anda membagikannya juga di Google?', 'review_public_button' => 'Tulis ulasan publik',

    'ai_assistant' => 'Asisten menu di menu tamu (AI)', 'ai_assistant_hint' => 'Tamu dapat bertanya tentang hidangan, diet, dan alergen. Asisten hanya menjawab dari menu Anda, dan setiap jawaban memakai satu kredit AI.',

    // Growth: channels, segments, happy hour, gift cards, NPS, pixels, link page, flyer, widget.
    'campaign_channel' => 'Kanal', 'channel_email' => 'Email', 'channel_sms' => 'SMS', 'channel_whatsapp' => 'WhatsApp', 'channel_push' => 'Notifikasi push',
    'channel_push_help' => 'Dikirim ke browser yang tamunya mengetuk "Dapatkan penawaran" di menu Anda. Nama kampanye menjadi judul, teksnya menjadi pesan (maksimal 180 karakter). Segmen tidak berlaku.', 'push_offer' => 'Dapatkan penawaran', 'push_on' => 'Anda akan menerima penawaran kami.', 'push_denied' => 'Notifikasi diblokir atau tidak didukung di browser ini.',
    'channel_help' => 'Dikirim ke tamu yang setuju menerima pemasaran dan meninggalkan nomor telepon. Memerlukan penyedia SMS/WhatsApp di Pengaturan, dan memakai jatah pesan bulanan Anda. Maksimal 600 karakter; gunakan {{name}} untuk nama tamu.',
    'campaign_segment' => 'Audiens', 'segment_all' => 'Semua orang', 'segment_new' => 'Tamu baru (0-1 pesanan)', 'segment_regulars' => 'Pelanggan tetap (tingkat perak ke atas)', 'segment_vip' => 'VIP (tingkat emas)', 'segment_lapsed' => 'Tamu yang lama tidak datang',
    'sms_stop' => 'Hentikan pesan: :url',
    'growth_section' => 'Tingkat, survei, dan autopilot', 'tier_silver' => 'Tingkat perak mulai (pesanan)', 'tier_gold' => 'Tingkat emas mulai (pesanan)', 'tier_help' => 'Tingkat mengelompokkan tamu untuk kampanye. Dihitung dari pesanan yang selesai.',
    'nps_enabled' => 'Ajukan pertanyaan 0-10 "seberapa mungkin Anda merekomendasikan kami" bersama rating',
    'autopilot_winback' => 'Autopilot: ajak kembali tamu yang lama tidak datang', 'autopilot_help' => 'Sekali sehari, tamu yang setuju menerima pemasaran dan sudah lama tidak datang mendapat kode sekali pakai pribadi lewat email (paling banyak sekali setiap 90 hari).',
    'autopilot_days' => 'Tidak datang selama (hari)', 'autopilot_percent' => 'Diskon (%)',
    'pixels_section' => 'Pelacakan iklan', 'pixels_help' => 'Tempel ID-nya saja. Skrip dimuat di menu tamu dan dilewati saat tamu mengirim Do Not Track. Sebutkan di kebijakan privasi Anda.',
    'pixel_meta' => 'ID piksel Meta (Facebook)', 'pixel_ga' => 'ID tag Google', 'pixel_tiktok' => 'ID piksel TikTok',
    'links_section' => 'Halaman tautan, brosur, dan tombol situs web', 'links_help' => 'Halaman link-in-bio Anda untuk profil media sosial: :url',
    'link_phone' => 'Telepon', 'link_whatsapp' => 'Nomor WhatsApp', 'link_whatsapp_hint' => 'Angka dengan kode negara, mis. 6281234567890', 'link_instagram' => 'URL Instagram', 'link_facebook' => 'URL Facebook', 'link_website' => 'URL situs web',
    'link_label_menu' => 'Lihat menu & pesan', 'link_label_whatsapp' => 'Chat di WhatsApp', 'link_label_phone' => 'Telepon kami', 'link_label_instagram' => 'Instagram', 'link_label_facebook' => 'Facebook', 'link_label_website' => 'Situs web kami', 'link_label_review' => 'Beri ulasan',
    'flyer_open' => 'Brosur siap cetak', 'flyer_print' => 'Cetak', 'flyer_scan' => 'Pindai untuk melihat menu kami dan memesan', 'widget_open' => 'Tombol situs web', 'widget_button' => 'Pesan online',
    'widget_title' => 'Tombol situs web', 'widget_sub' => 'Izinkan pengunjung situs web Anda membuka menu tanpa meninggalkan halaman.', 'widget_button_title' => 'Tombol mengambang "Pesan online"', 'widget_button_help' => 'Tempel sebelum tag body penutup di situs web Anda.',
    'widget_iframe_title' => 'Sematkan menu di halaman', 'widget_iframe_help' => 'Atau tampilkan seluruh menu di dalam halaman.',
    'nps_question' => 'Seberapa mungkin Anda merekomendasikan kami kepada teman?', 'nps_low' => 'Tidak mungkin', 'nps_high' => 'Sangat mungkin',
    'nps_score' => 'Net Promoter Score', 'nps_breakdown' => ':p promotor, :n pasif, :d detraktor',
    'templates' => 'Mulai dari templat', 'template_first_order' => 'Sambutan 10%', 'template_weekend' => 'Akhir pekan 15%', 'template_big_basket' => 'Bonus pesanan besar', 'template_flash' => 'Flash sale 20%',
    'pricing_title' => 'Happy hour', 'pricing_sub' => 'Diskon otomatis untuk hari dan jam tertentu. Diterapkan oleh server saat harga keranjang dihitung; aturan terbaik yang dipakai dan aturan tidak pernah ditumpuk.',
    'rule_name' => 'Nama', 'rule_name_ph' => 'Minuman happy hour', 'rule_percent' => 'Diskon persen', 'rule_from' => 'Dari', 'rule_to' => 'Sampai', 'rule_scope' => 'Berlaku untuk', 'rule_all_menu' => 'Seluruh menu',
    'rule_days' => 'Hari', 'rule_days_help' => 'Biarkan semua tidak dicentang untuk setiap hari.', 'rule_add' => 'Tambah aturan', 'rule_saved' => 'Aturan tersimpan.', 'rule_deleted' => 'Aturan dihapus.', 'every_day' => 'Setiap hari',
    'rules_empty' => 'Belum ada happy hour', 'rules_empty_text' => 'Tambahkan aturan seperti diskon 20% untuk minuman pada hari kerja pukul 16:00 sampai 18:00.', 'delete_rule_confirm' => 'Hapus aturan ini?',
    'day_0' => 'Min', 'day_1' => 'Sen', 'day_2' => 'Sel', 'day_3' => 'Rab', 'day_4' => 'Kam', 'day_5' => 'Jum', 'day_6' => 'Sab',
    'gifts_title' => 'Kartu hadiah', 'gifts_sub' => 'Jual atau berikan kartu hadiah. Tamu mengetik kodenya saat pembayaran dan saldo dipakai sampai habis.',
    'gift_amount' => 'Jumlah', 'gift_valid_days' => 'Berlaku selama (hari)', 'gift_valid_help' => 'Kosong = tidak pernah kedaluwarsa.', 'gift_email' => 'Kirim ke (email)', 'gift_email_help' => 'Opsional. Penerima mendapat kodenya lewat email.', 'gift_note' => 'Pesan',
    'gift_issue' => 'Terbitkan kartu hadiah', 'gift_issued' => 'Kartu hadiah :code dibuat.', 'gifts_empty' => 'Belum ada kartu hadiah', 'gifts_empty_text' => 'Terbitkan satu di atas dan serahkan kodenya kepada pembeli.',
    'gift_balance' => 'Saldo :balance dari :initial', 'gift_expires' => 'kedaluwarsa :date',
    'weekly_digest' => 'Kirimi saya email ringkasan minggu lalu setiap Senin',
    'privacy_section' => 'Privasi', 'retention_months' => 'Hapus data pribadi tamu setelah (bulan)',
    'retention_help' => '0 menyimpan semuanya. Jika tidak, tamu yang tidak memesan selama itu dihapus dan nama, telepon, email, serta alamat mereka dihapus dari pesanan lama. Pesanan itu sendiri (jumlah, hidangan) tetap ada untuk pembukuan Anda.',
    'segment_birthday' => 'Ulang tahun bulan ini', 'autopilot_birthday' => 'Autopilot: kejutan ulang tahun', 'birthday_percent' => 'Diskon ulang tahun (%)',
    'segments_title' => 'Segmen', 'segments_sub' => 'Simpan audiens sekali dan pilih di kampanye mana pun. Tamu harus memenuhi setiap aturan yang Anda isi.',
    'segment_name' => 'Nama', 'segment_name_ph' => 'Pelanggan tetap yang mulai sepi', 'segment_rules_help' => 'Kosongkan aturan untuk mengabaikannya. Hanya tamu yang setuju menerima pemasaran yang pernah dihubungi.', 'segment_save' => 'Simpan segmen',
    'rule_min_orders' => 'Minimal sekian pesanan', 'rule_max_orders' => 'Maksimal sekian pesanan', 'rule_min_spent' => 'Pengeluaran minimal', 'rule_active_within' => 'Memesan dalam (hari) terakhir', 'rule_inactive_for' => 'Tidak memesan selama (hari)', 'rule_joined_within' => 'Baru dalam (hari) terakhir',
    'rule_birthday' => 'Ulang tahun', 'rule_birthday_this' => 'Bulan ini', 'rule_birthday_next' => 'Bulan depan', 'rule_tier' => 'Tingkat',
    'rule_value_this_month' => 'bulan ini', 'rule_value_next_month' => 'bulan depan', 'rule_value_bronze' => 'perunggu', 'rule_value_silver' => 'perak', 'rule_value_gold' => 'emas',
    'rule_label_min_orders' => ':value+ pesanan', 'rule_label_max_orders' => 'hingga :value pesanan', 'rule_label_min_spent' => 'pengeluaran :value+', 'rule_label_active_within_days' => 'memesan dalam :value hari terakhir', 'rule_label_inactive_for_days' => 'sepi selama :value+ hari',
    'rule_label_joined_within_days' => 'baru dalam :value hari terakhir', 'rule_label_birthday' => 'ulang tahun :value', 'rule_label_tier' => 'tingkat :value',
    'segment_everyone' => 'Semua orang', 'segment_reach' => '{0} tidak ada yang dapat dihubungi|{1} :count tamu dapat dihubungi|[2,*] :count tamu dapat dihubungi', 'segment_saved' => 'Segmen tersimpan.', 'segment_deleted' => 'Segmen dihapus.', 'segment_delete_confirm' => 'Hapus segmen ini?', 'segments_empty' => 'Belum ada segmen tersimpan', 'segments_empty_text' => 'Buat satu di atas, lalu pilih saat Anda menulis kampanye.',
];
