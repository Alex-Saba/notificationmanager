<?php

return [
    'path' => env('TEMPLATES_PATH', storage_path('app/private/templates')),
    'seed_defaults' => env('TEMPLATES_SEED_DEFAULTS', true),

    // Mode d'utilisation :
    // - le projet principal declare explicitement les entites et les proprietes disponibles
    // - ajoutez une entree par entite a exposer dans les tags du WYSIWYG
    //
    // Exemple :
    // [
    //     'model' => 'Company',
    //     'variable' => '$company',
    //     'properties' => ['name', 'address'],
    // ]

    'tag_entities' => [
        [
            'model' => 'User',
            'variable' => '$user',
            'properties' => [
                'name',
                'email',
            ],
        ],
    ],
];
