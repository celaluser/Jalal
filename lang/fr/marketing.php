<?php

return [
    // Customers
    'customers_title' => 'Clients', 'customers_sub' => 'Toutes les personnes qui ont laissé leurs coordonnées avec une commande. Fidélisez, repérez vos habitués et écrivez à ceux qui ont accepté.',
    'search_customers' => 'Rechercher un nom, un e-mail ou un téléphone', 'filter_all' => 'Tous', 'filter_opted' => 'Ont accepté le marketing', 'filter_repeat' => 'Habitués (2 commandes ou plus)',
    'sort_recent' => 'Dernière commande', 'sort_orders' => 'Le plus de commandes', 'sort_spent' => 'Plus gros dépensiers', 'sort_name' => 'Nom',
    'col_customer' => 'Client', 'col_orders' => 'Commandes', 'col_spent' => 'Dépensé', 'col_last' => 'Dernière commande', 'col_marketing' => 'Marketing',
    'stat_all' => 'Clients', 'stat_opted' => 'Joignables par e-mail', 'stat_repeat' => 'Habitués',
    'consent_yes' => 'Accepté', 'consent_no' => 'Non', 'consent_unsub' => 'Désabonné', 'anonymous' => 'Client', 'export' => 'Exporter en CSV',
    'customers_empty' => 'Aucun client pour le moment', 'customers_empty_text' => 'Les clients apparaissent ici lorsqu\'ils laissent un nom, un téléphone ou un e-mail avec une commande.',
    'customer_since' => 'Client depuis le :date', 'agreed_on' => 'A accepté le marketing le :date', 'unsubscribed_on' => 'Désabonné le :date', 'no_consent' => 'N\'a pas accepté les e-mails marketing.',
    'notes' => 'Notes privées', 'notes_help' => 'Seule votre équipe les voit. Allergies, préférences, tout ce qui mérite d\'être retenu.', 'order_history' => 'Commandes', 'no_orders' => 'Aucune commande.',
    'delete_customer' => 'Supprimer ce client', 'delete_help' => 'Supprime la fiche et efface le nom, le téléphone, l\'e-mail et l\'adresse de ses commandes. Les chiffres de vente sont conservés. Cette action est irréversible.', 'delete_confirm' => 'Supprimer ce client et effacer ses données personnelles ?',
    'customer_deleted' => 'Client supprimé et données personnelles effacées.', 'avg_order' => 'Panier moyen',

    // Promo codes
    'promos_title' => 'Codes promo', 'promos_sub' => 'Des codes de réduction que vos clients saisissent au paiement. À ne pas confondre avec les coupons d\'abonnement de la plateforme.',
    'new_promo' => 'Nouveau code', 'edit_promo' => 'Modifier le code', 'promo_code' => 'Code', 'promo_desc' => 'Description', 'promo_desc_help' => 'Vous seul la voyez.',
    'promo_type' => 'Type de réduction', 'type_percent' => 'Pourcentage de réduction', 'type_fixed' => 'Montant de réduction', 'promo_value' => 'Valeur', 'promo_min' => 'Commande minimale', 'promo_max_uses' => 'Utilisations maximales', 'promo_max_help' => 'Laissez vide pour illimité.',
    'promo_starts' => 'Début', 'promo_ends' => 'Fin', 'promo_active' => 'Actif', 'promo_uses' => 'Utilisé', 'promo_status' => 'Statut',
    'status_active' => 'Actif', 'status_off' => 'Désactivé', 'status_expired' => 'Expiré', 'status_scheduled' => 'Programmé', 'status_used_up' => 'Épuisé', 'reward_badge' => 'Récompense de fidélité', 'reward_for' => 'Pour :name',
    'promo_saved' => 'Code promo enregistré.', 'promo_deleted' => 'Code promo supprimé.', 'promos_empty' => 'Aucun code promo pour le moment', 'promos_empty_text' => 'Créez un code comme BIENVENUE10 et partagez-le sur les réseaux sociaux ou dans vos campagnes.',
    'turn_off' => 'Désactiver', 'turn_on' => 'Activer', 'delete_promo_confirm' => 'Supprimer ce code promo ?', 'min_order_label' => 'dès :amount',
    'reward_percent' => '-:value%', 'reward_fixed' => '-:amount',

    // Guest checkout
    'promo_field' => 'Code promo', 'promo_apply' => 'Appliquer', 'promo_remove' => 'Retirer', 'promo_applied' => 'Code :code appliqué : -:amount.', 'promo_discount' => 'Remise',
    'opt_in' => 'Envoyez-moi par e-mail les offres et les nouvelles de :name. Je peux me désabonner à tout moment.',

    // Reviews
    'reviews_title' => 'Avis', 'reviews_sub' => 'Ce que vos clients ont dit après leur repas.', 'reviews_empty' => 'Aucun avis pour le moment', 'reviews_empty_text' => 'Les clients sont invités à noter leur commande une fois terminée.',
    'average' => 'Note moyenne', 'review_count' => '{1} 1 avis|[2,*] :count avis', 'filter_low' => 'Notes basses', 'filter_unanswered' => 'Sans réponse',
    'low_alert' => '{1} 1 note basse attend encore une réponse.|[2,*] :count notes basses attendent encore une réponse.', 'low_flag' => 'À traiter',
    'reply' => 'Votre réponse', 'reply_save' => 'Enregistrer la réponse', 'reply_help' => 'Affichée au client sur la page de sa commande.', 'hide' => 'Masquer du menu', 'show' => 'Afficher sur le menu', 'hidden' => 'Masqué', 'order_ref' => 'Commande :number',
    'public_note' => 'Visible sur votre menu', 'stars' => '{1} :count étoile|[2,*] :count étoiles sur 5',

    // Guest review form
    'rate_title' => 'C\'était comment ?', 'rate_sub' => 'Notez votre commande. Cela prend dix secondes.', 'rate_comment' => 'Quelque chose à ajouter ? (facultatif)', 'rate_public' => 'Afficher ma note (avec mon prénom) sur le menu',
    'rate_send' => 'Envoyer ma note', 'rate_thanks' => 'Merci pour votre retour !', 'rate_yours' => 'Votre note', 'rate_reply' => 'Réponse du restaurant',
    'review_error_closed' => 'Les notes ne sont pas ouvertes pour cette commande.', 'review_error_done' => 'Vous avez déjà noté cette commande.', 'review_error_rating' => 'Choisissez entre 1 et 5 étoiles.',
    'rating_on_menu' => ':average (:count)',

    // Loyalty & settings
    'loyalty_title' => 'Fidélité et avis', 'loyalty_sub' => 'Récompensez automatiquement vos habitués et demandez à vos clients comment cela s\'est passé.',
    'loyalty_section' => 'Récompense de fidélité', 'loyalty_enabled' => 'Récompenser les clients réguliers', 'loyalty_enabled_help' => 'Après chaque N-ième commande terminée, le client gagne un code promo à usage unique que lui seul peut utiliser. Il s\'affiche sur la page de sa commande et dans un e-mail.',
    'loyalty_every' => 'Récompenser toutes les … commandes terminées', 'loyalty_type' => 'Récompense', 'loyalty_value' => 'Valeur', 'loyalty_percent_hint' => 'Pourcentage de la commande', 'loyalty_fixed_hint' => 'Montant dans votre devise', 'loyalty_valid' => 'Valable (jours)',
    'reviews_section' => 'Avis', 'reviews_enabled' => 'Demander aux clients de noter les commandes terminées', 'review_email' => 'Demander aussi par e-mail lorsque le client a laissé une adresse', 'show_rating' => 'Afficher la note moyenne sur le menu public',
    'messages_section' => 'SMS', 'calling_code' => 'Indicatif téléphonique du pays', 'calling_code_help' => 'Utilisé pour envoyer des SMS et des messages WhatsApp aux clients qui ont saisi un numéro local, par exemple 33 pour la France. Laissez vide pour ne contacter que les numéros commençant par +.',
    'email_section' => 'Envoi d\'e-mails', 'daily_cap' => 'E-mails de campagne par jour', 'daily_cap_help' => 'La plateforme autorise au maximum :max par restaurant et par jour. Cela préserve la réputation de votre compte de messagerie.',
    'reward_earned' => 'Vous avez gagné une récompense !', 'reward_earned_text' => 'Merci de commander encore et encore. Utilisez ce code lors de votre prochaine commande :', 'reward_valid_until' => 'Valable jusqu\'au :date, une seule fois, rien que pour vous.',
    'there' => 'vous',

    // Campaigns
    'campaigns_title' => 'Campagnes', 'campaigns_sub' => 'Envoyez des e-mails aux clients qui ont accepté d\'avoir de vos nouvelles. Chaque message contient un lien de désabonnement.',
    'new_campaign' => 'Nouvelle campagne', 'edit_campaign' => 'Modifier la campagne', 'campaign_name' => 'Nom', 'campaign_name_help' => 'Vous seul le voyez.', 'campaign_subject' => 'Objet', 'campaign_body' => 'Message',
    'campaign_body_help' => 'Texte brut avec une mise en forme simple (**gras**, [lien](https://…)). Utilisez {{name}} et {{restaurant}} pour personnaliser.', 'campaign_audience' => 'Destinataires', 'min_orders' => 'Uniquement les clients ayant au moins … commandes', 'min_orders_help' => '0 signifie tous ceux qui ont accepté.',
    'audience_now' => '{0} Personne ne peut encore le recevoir.|{1} 1 client le recevra.|[2,*] :count clients le recevront.', 'audience_note' => 'Uniquement les clients qui ont coché la case e-mail au paiement, ont une adresse et ne se sont pas désabonnés.',
    'campaign_saved' => 'Campagne enregistrée.', 'campaign_deleted' => 'Campagne supprimée.', 'campaign_sending' => 'Votre campagne est en cours d\'envoi. Cela peut prendre quelques minutes.',
    'campaign_error_sent' => 'Cette campagne a déjà été envoyée.', 'campaign_error_empty' => 'Personne ne peut encore recevoir cette campagne.',
    'status_draft' => 'Brouillon', 'status_sending' => 'Envoi en cours', 'status_sent' => 'Envoyée', 'campaigns_empty' => 'Aucune campagne pour le moment', 'campaigns_empty_text' => 'Parlez à vos habitués d\'un nouveau plat ou d\'une offre pour les jours calmes.',
    'sent_count' => 'Envoyés', 'skipped_count' => 'Non envoyés', 'recipients' => 'Destinataires', 'send_test' => 'M\'envoyer un test', 'send_now' => 'Envoyer la campagne', 'send_confirm' => 'Envoyer cette campagne maintenant ? Cette action est irréversible.',
    'test_sent' => 'E-mail de test envoyé à :email.', 'today_room' => ':count e-mails de plus peuvent partir aujourd\'hui (limite : :cap).', 'cap_note' => 'Les clients au-delà de la limite du jour sont ignorés. Renvoyez demain avec une nouvelle campagne.', 'sent_on' => 'Envoyée le :date',
    'preview' => 'Aperçu', 'unsub_footer' => 'Vous recevez ce message parce que vous avez accepté de recevoir les offres de :name.', 'unsub_link' => 'Se désabonner',

    // Unsubscribe page
    'unsub_title' => 'Désabonnement', 'unsub_ask' => 'Ne plus recevoir les e-mails de :name ?', 'unsub_ask_text' => 'Vous ne recevrez plus d\'offres ni de nouvelles. Vous recevrez toujours les mises à jour de vos commandes.', 'unsub_button' => 'Oui, me désabonner',
    'unsub_done' => 'Vous êtes désabonné', 'unsub_done_text' => 'Vous ne recevrez plus d\'e-mails marketing de la part de :name.', 'unsub_already' => 'Vous êtes déjà désabonné de :name.',

    // Banners and pop-ups
    'banners_title' => 'Bannières et pop-ups', 'banners_sub' => 'Mettez en avant un nouveau plat, une offre ou une annonce en haut de votre menu.', 'new_banner' => 'Nouvelle bannière', 'edit_banner' => 'Modifier la bannière',
    'banners_empty' => 'Aucune bannière pour le moment', 'banners_empty_text' => 'Ajoutez une bannière pour informer vos clients d\'une offre, d\'un nouveau plat ou des horaires d\'ouverture.', 'banner_saved' => 'Bannière enregistrée.', 'banner_deleted' => 'Bannière supprimée.',
    'banner_title' => 'Titre', 'banner_text' => 'Texte court', 'banner_link' => 'Lien', 'banner_link_hint' => 'Une adresse https://, ou #cat-12 pour accéder à une section du menu. Facultatif.', 'banner_button' => 'Texte du bouton',
    'banner_starts' => 'Afficher à partir du', 'banner_ends' => 'Afficher jusqu\'au', 'banner_popup' => 'Afficher en pop-up (une fois par visite) au lieu d\'une bannière', 'popup' => 'Pop-up', 'status_off' => 'Désactivée',

    'review_url' => 'Lien d\'avis public', 'review_url_hint' => 'Par exemple votre lien d\'avis Google. Les clients satisfaits sont invités à y partager leur note.', 'review_min' => 'Le proposer à partir de (étoiles)', 'review_public_invite' => 'Ravi que vous ayez aimé ! Voulez-vous aussi le partager sur Google ?', 'review_public_button' => 'Écrire un avis public',

    'ai_assistant' => 'Assistant du menu sur le menu client (IA)', 'ai_assistant_hint' => 'Les clients peuvent poser des questions sur les plats, les régimes et les allergènes. Il répond uniquement à partir de votre menu, et chaque réponse consomme un crédit d\'IA.',

    // Growth: channels, segments, happy hour, gift cards, NPS, pixels, link page, flyer, widget.
    'campaign_channel' => 'Canal', 'channel_email' => 'E-mail', 'channel_sms' => 'SMS', 'channel_whatsapp' => 'WhatsApp', 'channel_push' => 'Notification push',
    'channel_push_help' => 'Envoyée aux navigateurs dont les clients ont touché « Recevoir les offres » sur votre menu. Le nom de la campagne sert de titre, le texte est le message (180 caractères maximum). Les segments ne s\'appliquent pas.', 'push_offer' => 'Recevoir les offres', 'push_on' => 'Vous recevrez nos offres.', 'push_denied' => 'Les notifications sont bloquées ou non prises en charge dans ce navigateur.',
    'channel_help' => 'Envoyée aux clients qui ont accepté le marketing et laissé un numéro de téléphone. Nécessite un fournisseur SMS/WhatsApp dans les Paramètres, et utilise votre quota mensuel de messages. 600 caractères maximum ; utilisez {{name}} pour le nom du client.',
    'campaign_segment' => 'Audience', 'segment_all' => 'Tout le monde', 'segment_new' => 'Nouveaux clients (0 à 1 commande)', 'segment_regulars' => 'Habitués (niveau argent et plus)', 'segment_vip' => 'VIP (niveau or)', 'segment_lapsed' => 'Clients inactifs',
    'sms_stop' => 'Pour ne plus recevoir de messages : :url',
    'growth_section' => 'Niveaux, enquêtes et pilote automatique', 'tier_silver' => 'Niveau argent à partir de (commandes)', 'tier_gold' => 'Niveau or à partir de (commandes)', 'tier_help' => 'Les niveaux regroupent les clients pour les campagnes. Ils sont calculés à partir des commandes terminées.',
    'nps_enabled' => 'Poser la question de 0 à 10 « nous recommanderiez-vous » avec la note',
    'autopilot_winback' => 'Pilote automatique : reconquérir les clients inactifs', 'autopilot_help' => 'Une fois par jour, les clients qui ont accepté le marketing et ne sont pas revenus reçoivent par e-mail un code personnel à usage unique (au plus une fois tous les 90 jours).',
    'autopilot_days' => 'Absents depuis (jours)', 'autopilot_percent' => 'Remise (%)',
    'pixels_section' => 'Suivi publicitaire', 'pixels_help' => 'Collez uniquement l\'identifiant. Les scripts se chargent sur le menu client et sont ignorés lorsque le client envoie Do Not Track. Mentionnez-les dans votre politique de confidentialité.',
    'pixel_meta' => 'ID du pixel Meta (Facebook)', 'pixel_ga' => 'ID de balise Google', 'pixel_tiktok' => 'ID du pixel TikTok',
    'links_section' => 'Page de liens, flyer et bouton pour votre site', 'links_help' => 'Votre page de liens pour les profils de réseaux sociaux : :url',
    'link_phone' => 'Téléphone', 'link_whatsapp' => 'Numéro WhatsApp', 'link_whatsapp_hint' => 'Chiffres avec indicatif du pays, p. ex. 33612345678', 'link_instagram' => 'URL Instagram', 'link_facebook' => 'URL Facebook', 'link_website' => 'URL du site web',
    'link_label_menu' => 'Voir le menu et commander', 'link_label_whatsapp' => 'Discuter sur WhatsApp', 'link_label_phone' => 'Nous appeler', 'link_label_instagram' => 'Instagram', 'link_label_facebook' => 'Facebook', 'link_label_website' => 'Notre site web', 'link_label_review' => 'Laisser un avis',
    'flyer_open' => 'Flyer imprimable', 'flyer_print' => 'Imprimer', 'flyer_scan' => 'Scannez pour voir notre menu et commander', 'widget_open' => 'Bouton pour site web', 'widget_button' => 'Commander en ligne',
    'widget_title' => 'Bouton pour site web', 'widget_sub' => 'Permettez aux visiteurs de votre propre site d\'ouvrir le menu sans quitter la page.', 'widget_button_title' => 'Bouton flottant « Commander en ligne »', 'widget_button_help' => 'À coller avant la balise de fermeture body de votre site.',
    'widget_iframe_title' => 'Intégrer le menu dans une page', 'widget_iframe_help' => 'Ou affichez tout le menu à l\'intérieur d\'une page.',
    'nps_question' => 'Dans quelle mesure nous recommanderiez-vous à un ami ?', 'nps_low' => 'Pas du tout', 'nps_high' => 'Très probablement',
    'nps_score' => 'Net Promoter Score', 'nps_breakdown' => ':p promoteurs, :n passifs, :d détracteurs',
    'templates' => 'Partir d\'un modèle', 'template_first_order' => 'Bienvenue -10%', 'template_weekend' => 'Week-end -15%', 'template_big_basket' => 'Bonus grosse commande', 'template_flash' => 'Vente flash -20%',
    'pricing_title' => 'Happy hour', 'pricing_sub' => 'Des remises automatiques pour certains jours et certaines heures. Elles sont appliquées par le serveur lors du calcul du panier ; la meilleure règle l\'emporte et les règles ne se cumulent jamais.',
    'rule_name' => 'Nom', 'rule_name_ph' => 'Boissons en happy hour', 'rule_percent' => 'Pourcentage de réduction', 'rule_from' => 'De', 'rule_to' => 'Jusqu\'à', 'rule_scope' => 'S\'applique à', 'rule_all_menu' => 'Tout le menu',
    'rule_days' => 'Jours', 'rule_days_help' => 'Laissez tout décoché pour tous les jours.', 'rule_add' => 'Ajouter une règle', 'rule_saved' => 'Règle enregistrée.', 'rule_deleted' => 'Règle supprimée.', 'every_day' => 'Tous les jours',
    'rules_empty' => 'Aucune happy hour pour le moment', 'rules_empty_text' => 'Ajoutez une règle comme -20% sur les boissons en semaine de 16:00 à 18:00.', 'delete_rule_confirm' => 'Supprimer cette règle ?',
    'day_0' => 'Dim', 'day_1' => 'Lun', 'day_2' => 'Mar', 'day_3' => 'Mer', 'day_4' => 'Jeu', 'day_5' => 'Ven', 'day_6' => 'Sam',
    'gifts_title' => 'Cartes cadeaux', 'gifts_sub' => 'Vendez ou offrez des cartes cadeaux. Les clients saisissent le code au paiement et le solde est débité.',
    'gift_amount' => 'Montant', 'gift_valid_days' => 'Valable (jours)', 'gift_valid_help' => 'Vide = n\'expire jamais.', 'gift_email' => 'Envoyer à (e-mail)', 'gift_email_help' => 'Facultatif. Le destinataire reçoit le code par e-mail.', 'gift_note' => 'Message',
    'gift_issue' => 'Émettre une carte cadeau', 'gift_issued' => 'Carte cadeau :code créée.', 'gifts_empty' => 'Aucune carte cadeau pour le moment', 'gifts_empty_text' => 'Émettez-en une ci-dessus et remettez le code à l\'acheteur.',
    'gift_balance' => 'Solde :balance sur :initial', 'gift_expires' => 'expire le :date',
    'weekly_digest' => 'M\'envoyer par e-mail un résumé de la semaine passée chaque lundi',
    'privacy_section' => 'Confidentialité', 'retention_months' => 'Effacer les données personnelles des clients après (mois)',
    'retention_help' => '0 conserve tout. Sinon, les clients qui n\'ont pas commandé depuis cette durée sont supprimés et leur nom, téléphone, e-mail et adresse sont retirés des anciennes commandes. Les commandes elles-mêmes (montants, plats) sont conservées pour votre comptabilité.',
    'segment_birthday' => 'Anniversaire ce mois-ci', 'autopilot_birthday' => 'Pilote automatique : attention d\'anniversaire', 'birthday_percent' => 'Remise d\'anniversaire (%)',
    'segments_title' => 'Segments', 'segments_sub' => 'Enregistrez une audience une fois et choisissez-la dans n\'importe quelle campagne. Un client doit respecter chaque règle que vous renseignez.',
    'segment_name' => 'Nom', 'segment_name_ph' => 'Habitués devenus silencieux', 'segment_rules_help' => 'Laissez une règle vide pour l\'ignorer. Seuls les clients ayant accepté le marketing sont contactés.', 'segment_save' => 'Enregistrer le segment',
    'rule_min_orders' => 'Au moins ce nombre de commandes', 'rule_max_orders' => 'Au plus ce nombre de commandes', 'rule_min_spent' => 'A dépensé au moins', 'rule_active_within' => 'A commandé durant les derniers (jours)', 'rule_inactive_for' => 'N\'a pas commandé depuis (jours)', 'rule_joined_within' => 'Nouveau durant les derniers (jours)',
    'rule_birthday' => 'Anniversaire', 'rule_birthday_this' => 'Ce mois-ci', 'rule_birthday_next' => 'Le mois prochain', 'rule_tier' => 'Niveau',
    'rule_value_this_month' => 'ce mois-ci', 'rule_value_next_month' => 'le mois prochain', 'rule_value_bronze' => 'bronze', 'rule_value_silver' => 'argent', 'rule_value_gold' => 'or',
    'rule_label_min_orders' => ':value commandes ou plus', 'rule_label_max_orders' => 'jusqu\'à :value commandes', 'rule_label_min_spent' => 'a dépensé :value ou plus', 'rule_label_active_within_days' => 'a commandé ces :value derniers jours', 'rule_label_inactive_for_days' => 'inactif depuis :value jours ou plus',
    'rule_label_joined_within_days' => 'nouveau ces :value derniers jours', 'rule_label_birthday' => 'anniversaire : :value', 'rule_label_tier' => 'niveau : :value',
    'segment_everyone' => 'Tout le monde', 'segment_reach' => '{0} personne ne peut être joint|{1} :count client peut être joint|[2,*] :count clients peuvent être joints', 'segment_saved' => 'Segment enregistré.', 'segment_deleted' => 'Segment supprimé.', 'segment_delete_confirm' => 'Supprimer ce segment ?', 'segments_empty' => 'Aucun segment enregistré', 'segments_empty_text' => 'Créez-en un ci-dessus, puis choisissez-le lorsque vous rédigez une campagne.',
];
