<?php

return [
    'title' => 'Domaines', 'subtitle' => 'Les adresses web que vos clients peuvent utiliser pour ouvrir votre menu.',
    'default_address' => 'Adresse permanente', 'default_help' => 'Ce lien fonctionne toujours, quoi que vous configuriez d\'autre. Imprimez-le sur vos QR codes.',
    'subdomain' => 'Sous-domaine', 'subdomain_help' => 'Lettres, chiffres et tirets, 3 caractères minimum.', 'subdomain_off' => 'Les sous-domaines ne sont pas activés sur cette plateforme.', 'subdomain_preview' => 'Votre menu s\'ouvrira à l\'adresse',
    'custom_domain' => 'Votre propre domaine', 'custom_help' => 'Par exemple menu.votrerestaurant.com. Connectez-le en trois étapes.', 'custom_off' => 'Les domaines personnalisés ne sont pas activés sur cette plateforme.',
    'custom_upgrade' => 'Votre offre n\'inclut pas de domaine personnalisé.', 'upgrade' => 'Changer d\'offre', 'remove_domain' => 'Laissez le champ vide et enregistrez pour déconnecter.',
    'save' => 'Enregistrer', 'status_verified' => 'Connecté', 'status_pending' => 'En attente du DNS', 'status_none' => 'Non défini',
    'step1' => 'Saisissez le domaine ci-dessus et enregistrez.', 'step2' => 'Chez votre fournisseur de domaine, ajoutez cet enregistrement TXT pour prouver qu\'il vous appartient :', 'step3' => 'Appuyez sur « Vérifier le DNS ». Les modifications DNS peuvent prendre jusqu\'à une heure pour se propager.',
    'record_type' => 'Type', 'record_host' => 'Hôte', 'record_value' => 'Valeur', 'cname_hint' => 'Faites aussi pointer le domaine vers nous avec un enregistrement CNAME vers :target (ou suivez les instructions ci-dessous).',
    'platform_instructions' => 'Instructions de la plateforme', 'check_dns' => 'Vérifier le DNS', 'copy' => 'Copier', 'copied' => 'Copié',
    'verified_now' => 'Votre domaine est connecté.', 'not_found_yet' => 'Nous n\'avons pas encore trouvé l\'enregistrement DNS. Vérifiez-le et réessayez dans quelques minutes.', 'verified_manual' => 'Domaine vérifié',
    'error_upgrade' => 'Votre propre adresse est une fonctionnalité de la boutique. Obtenez-la d\'abord dans la boutique.', 'error_unavailable' => 'Ceci n\'est pas disponible pour votre restaurant.', 'error_invalid' => 'Cela ne ressemble pas à une adresse valide.', 'error_reserved' => 'Ce nom est réservé. Choisissez-en un autre.',
    'error_taken' => 'Cette adresse est déjà utilisée par un autre restaurant.', 'error_platform' => 'Vous ne pouvez pas utiliser ici le domaine propre de la plateforme.',
];
