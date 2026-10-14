<?php

return [
    'error_not_configured' => 'L\'IA n\'est pas encore configurée. Demandez à l\'administrateur de la plateforme d\'ajouter une clé d\'IA.',
    'error_no_credits' => 'Vous avez utilisé tous les crédits d\'IA de votre offre pour ce mois. Ils se renouvellent le 1er du mois, ou vous pouvez passer à une offre supérieure.',
    'error_provider_error' => 'Le service d\'IA n\'a pas répondu. Veuillez réessayer dans un instant.',
    'error_rate_limited' => 'Le service d\'IA est actuellement occupé. Veuillez réessayer dans une minute.',
    'error_bad_response' => 'L\'IA n\'a pas pu produire de réponse exploitable cette fois. Veuillez réessayer, ou ajouter plus de détails.',
    'error_limit_categories' => 'L\'importation de ce menu dépasserait la limite de catégories de votre offre.',
    'error_limit_products' => 'L\'importation de ce menu dépasserait la limite de produits de votre offre.',

    'write' => 'Rédiger avec l\'IA', 'writing' => 'Rédaction…', 'hints' => 'Ingrédients ou notes (facultatif)', 'tone' => 'Style',
    'tone_appetizing' => 'Appétissant', 'tone_short' => 'Court et simple', 'tone_elegant' => 'Élégant', 'tone_playful' => 'Ludique',
    'translate' => 'Traduire avec l\'IA', 'translating' => 'Traduction…', 'translate_hint' => 'Remplit les champs vides des autres langues à partir de votre langue principale.',
    'suggest_tags' => 'Suggérer les allergènes avec l\'IA', 'suggesting' => 'Vérification…',
    'tags_notice' => 'Suggéré par l\'IA. Vérifiez chaque allergène vous-même : vos clients se fient à ces informations.', 'tags_none' => 'Aucun allergène ni étiquette de régime n\'a été suggéré.',
    'tags_applied' => 'Coché : :list',
    'credits_left' => ':count crédits restants ce mois-ci', 'credits_unlimited' => 'Les crédits d\'IA ne sont pas limités dans votre offre', 'credits_cost' => 'Coûte :count crédit(s)',
    'review_note' => 'Relisez le texte avant d\'enregistrer. L\'IA peut se tromper.',

    'import_title' => 'Importer un menu avec l\'IA', 'import_sub' => 'Collez votre menu depuis un PDF, un site web ou un document. L\'IA le classe en catégories et en plats, et vous vérifiez tout avant l\'ajout.',
    'import_paste' => 'Collez le texte de votre menu', 'import_placeholder' => "STARTERS\nBruschetta - 7.50\nSoup of the day - 6\n\nMAINS\nMargherita pizza, tomato and basil - 12", 'import_chars' => ':count caractères sur :max',
    'import_read' => 'Lire mon menu', 'import_cost' => 'Coûte :count crédits', 'import_review' => 'Vérifier', 'import_found' => ':count plats trouvés',
    'import_no_price' => 'Aucun prix trouvé : ces plats sont ajoutés masqués jusqu\'à ce que vous indiquiez un prix.', 'import_add' => 'Ajouter à mon menu', 'import_adding' => 'Ajout…',
    'import_done' => ':categories catégories et :products plats ajoutés. :hidden sont masqués tant qu\'ils n\'ont pas de prix.', 'import_remove' => 'Retirer', 'import_again' => 'Recommencer',
    'import_not_configured_title' => 'L\'IA n\'est pas encore disponible', 'import_how' => 'Comment ça marche',
    'import_step1' => 'Collez le texte de votre menu.', 'import_step2' => 'L\'IA le lit et le regroupe. Modifiez les noms et les prix si besoin.', 'import_step3' => 'Ajoutez-le à votre menu en un clic.',
    'category' => 'Catégorie', 'dish' => 'Nom du plat', 'price' => 'Prix', 'description' => 'Description',
    'button_import' => 'Importer avec l\'IA',
    'need_name' => 'Saisissez d\'abord le nom.',

    'import_photo' => 'Ou une photo du menu', 'import_photo_cost' => 'Une photo coûte :count crédits d\'IA (chaque page, jusqu\'à 5, est comptée). Fonctionne avec les menus imprimés et les captures d\'écran.', 'import_pdf' => 'Ou un menu en PDF', 'import_pdf_hint' => 'Uniquement les PDF contenant du vrai texte. Pour un PDF scanné, prenez plutôt la page en photo.',
    'error_bad_file' => 'Ce fichier n\'a pas pu être lu. Essayez un JPG, un PNG ou un PDF net.', 'error_pdf_no_text' => 'Ce PDF ne contient aucun texte lisible (c\'est probablement un scan). Utilisez plutôt l\'import par photo.',
    'bulk_title' => 'Traduire tout le menu', 'bulk_text' => 'Complète en arrière-plan toutes les traductions manquantes des plats et des catégories. Les langues que vous avez verrouillées ne sont jamais modifiées. Chaque élément consomme des crédits de traduction.', 'bulk_start' => 'Traduire tout ce qui manque',
    'bulk_running' => 'Traduction…', 'bulk_done' => 'Terminé : :n textes traduits.', 'bulk_stopped' => 'Arrêté prématurément (plus de crédits ou service d\'IA indisponible). Ce qui a été fait est conservé.',
    'draft_reply' => 'Rédiger une réponse avec l\'IA', 'draft_note' => 'Relisez et modifiez le brouillon avant de l\'envoyer.', 'insights_title' => 'Idées tirées de vos chiffres', 'insights_button' => 'Obtenir des idées', 'insights_note' => 'Rédigé par une IA à partir des chiffres ci-dessus. Considérez-les comme des suggestions.',
    'assistant_name' => 'Assistant du menu', 'assistant_ask' => 'Poser une question sur le menu', 'assistant_placeholder' => 'Quelque chose de végétarien ? Qu\'est-ce qui accompagne le burger ?', 'assistant_send' => 'Envoyer', 'assistant_hint' => 'Les réponses proviennent uniquement de ce menu. Demandez au personnel de confirmer les allergies.', 'assistant_error' => 'L\'assistant n\'est pas disponible pour le moment.',
];
