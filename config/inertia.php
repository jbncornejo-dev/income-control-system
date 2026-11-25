<?php

return [

    /*
     * Inertia busca las páginas por defecto en 'js/pages' (minúsculas), pero
     * este proyecto usa 'js/Pages'. En Linux el path no coincide y assertInertia
     * falla con "page component file does not exist", así que se fija acá.
     */
    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [
            resource_path('js/Pages'),
        ],
        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],
    ],

];
