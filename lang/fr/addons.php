<?php

return [
    'title' => 'Extensions', 'sub' => 'Des fonctionnalités supplémentaires livrées sous forme de paquets signés. Elles s\'exécutent avec un accès complet à l\'application : n\'installez que des extensions provenant de sources de confiance.',
    'install' => 'Installer une extension', 'install_help' => 'Téléversez le fichier .zip que vous avez reçu. La signature et chaque fichier sont d\'abord vérifiés. Les nouvelles extensions sont désactivées par défaut.',
    'trust_warning' => 'Une extension peut tout lire et tout modifier, y compris votre base de données. Faites d\'abord une sauvegarde.', 'install_button' => 'Vérifier et installer',
    'installed_title' => 'Extensions installées', 'installed' => ':name installée. Activez-la pour commencer à l\'utiliser.', 'updated' => 'Extension mise à jour.', 'removed' => 'Extension supprimée. Ses données restent dans la base de données.',
    'on' => 'Activée', 'off' => 'Désactivée', 'turn_on' => 'Activer', 'turn_off' => 'Désactiver', 'remove_confirm' => 'Supprimer cette extension ? Ses fichiers sont effacés ; ses tables de base de données restent.',
    'empty' => 'Aucune extension installée', 'empty_text' => 'Les extensions que vous achetez ou créez apparaissent ici.',
    'not_an_addon' => 'Ce paquet n\'est pas une extension (chaque fichier doit se trouver dans addons/<name>/).', 'invalid_manifest' => 'La description de l\'extension (addon.json) est absente ou invalide.',
    'needs_core' => 'Nécessite la version :version de l\'application ou une version plus récente.', 'provider_missing' => 'Le code de l\'extension n\'a pas pu être chargé.', 'not_found' => 'Extension introuvable.',
];
