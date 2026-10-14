<?php

return [
    'title' => 'API et webhooks', 'sub' => 'Connectez votre propre site web, votre caisse (POS), vos outils de livraison, Zapier ou Make. Documentation : docs/API.md.',
    'not_included' => 'Votre offre n\'inclut pas l\'API et les webhooks.', 'upgrade' => 'Changer d\'offre',
    'tokens' => 'Jetons d\'API', 'tokens_help' => 'Envoyez le jeton dans l\'en-tête "Authorization: Bearer …". Donnez à chaque outil uniquement les droits dont il a besoin.',
    'token_name' => 'Nom', 'token_days' => 'Expire après (jours)', 'token_days_help' => 'Vide = jamais.', 'token_create' => 'Créer un jeton',
    'new_token' => 'Copiez votre jeton maintenant. Il ne s\'affiche qu\'une seule fois.', 'token_revoked' => 'Jeton révoqué.', 'too_many_tokens' => 'Vous pouvez avoir 20 jetons au maximum.',
    'ability_menu:read' => 'Lire le menu', 'ability_menu:write' => 'Modifier les prix et la disponibilité', 'ability_orders:read' => 'Lire les commandes', 'ability_orders:write' => 'Passer des commandes et changer leur statut', 'ability_reservations:read' => 'Lire les réservations', 'ability_reservations:write' => 'Créer et modifier des réservations', 'ability_customers:read' => 'Lire les clients',
    'last_used' => 'Dernière utilisation :time', 'never_used' => 'Jamais utilisé', 'expires' => 'expire le :date', 'revoke' => 'Révoquer', 'revoke_confirm' => 'Révoquer ce jeton ? Les outils qui l\'utilisent cesseront de fonctionner.',
    'webhooks' => 'Webhooks', 'webhooks_help' => 'Nous envoyons (POST) un message JSON signé à votre adresse HTTPS lorsqu\'un événement se produit. Vérifiez l\'en-tête X-Webhook-Signature (voir docs/API.md).',
    'webhook_url' => 'Adresse HTTPS', 'webhook_events' => 'Événements', 'webhook_add' => 'Ajouter un webhook', 'event_*' => 'Tout',
    'event_order.created' => 'Commande passée', 'event_order.status_changed' => 'Statut de la commande modifié', 'event_order.paid' => 'Commande payée', 'event_reservation.created' => 'Réservation effectuée', 'event_reservation.status_changed' => 'Statut de la réservation modifié', 'event_ping' => 'Test',
    'new_secret' => 'Secret de signature pour :url. Copiez-le maintenant ; il ne s\'affiche qu\'une seule fois.', 'url_not_allowed' => 'Cette adresse ne peut pas être utilisée. Il doit s\'agir d\'une adresse HTTPS publique.',
    'too_many_endpoints' => 'Vous pouvez avoir 10 webhooks au maximum.', 'endpoint_deleted' => 'Webhook supprimé.', 'test_sent' => 'Message de test mis en file d\'attente.', 'send_test' => 'Envoyer un test',
    'disabled_after_failures' => 'Désactivé après des échecs répétés. Corrigez l\'adresse, puis réactivez-le.', 'deliveries_empty' => 'Aucun envoi pour le moment.',
    'status_sent' => 'Livré', 'status_failed' => 'Échec', 'status_pending' => 'En attente', 'turn_on' => 'Activer', 'turn_off' => 'Désactiver', 'delete_confirm' => 'Supprimer ce webhook ?',
];
