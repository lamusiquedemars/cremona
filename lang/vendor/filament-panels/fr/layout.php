<?php

return [
    'direction' => 'ltr',
    'skip_to_content' => ['label' => 'Aller au contenu'],
    'actions' => [
        'billing' => ['label' => 'Gérer l’abonnement'],
        'logout' => ['label' => 'Se déconnecter'],
        'open_database_notifications' => [
            'label' => 'Notifications',
            'label_with_unread_count' => '{1} Notifications, :count notification non lue|[2,*] Notifications, :count notifications non lues',
        ],
        'open_user_menu' => ['label' => 'Menu utilisateur'],
        'sidebar' => [
            'collapse' => ['label' => 'Réduire le menu latéral'],
            'expand' => ['label' => 'Développer le menu latéral'],
        ],
        'theme_switcher' => [
            'label' => 'Thème',
            'dark' => ['label' => 'Activer le thème sombre'],
            'light' => ['label' => 'Activer le thème clair'],
            'system' => ['label' => 'Utiliser le thème du système'],
        ],
    ],
    'navigation' => ['label' => 'Navigation latérale'],
    'topbar' => ['label' => 'Barre supérieure'],
    'avatar' => ['alt' => 'Avatar de :name'],
    'logo' => ['alt' => 'Logo :name'],
    'tenant_menu' => [
        'search_field' => [
            'label' => 'Rechercher une organisation',
            'placeholder' => 'Rechercher une organisation',
        ],
    ],
];
