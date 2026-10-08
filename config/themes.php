<?php

/*
| Customer menu themes. A theme is a starting palette and style; the restaurant's own brand colour
| is always used for buttons and highlights, and font, layout and corner radius can be overridden
| per restaurant. Only self-hosted or system fonts are offered: the public menu never calls out to
| third-party font servers (faster, and nothing for the cookie banner to explain).
*/
return [
    'default' => 'classic',

    'themes' => [
        'classic' => ['dark' => false, 'bg' => '#faf7f2', 'surface' => '#ffffff', 'fg' => '#241f1a', 'muted' => '#6e655b', 'line' => '#e8e0d4', 'font' => 'serif', 'layout' => 'list', 'radius' => 'soft'],
        'modern' => ['dark' => false, 'bg' => '#f5f6f8', 'surface' => '#ffffff', 'fg' => '#14161b', 'muted' => '#5d6577', 'line' => '#e3e6ec', 'font' => 'sans', 'layout' => 'cards', 'radius' => 'round'],
        'midnight' => ['dark' => true, 'bg' => '#0e1014', 'surface' => '#171a21', 'fg' => '#f1f2f5', 'muted' => '#a3a9b7', 'line' => '#262b35', 'font' => 'display', 'layout' => 'cards', 'radius' => 'soft'],
        'fresh' => ['dark' => false, 'bg' => '#f1f8f4', 'surface' => '#ffffff', 'fg' => '#10261c', 'muted' => '#53705f', 'line' => '#d6e8dd', 'font' => 'sans', 'layout' => 'grid', 'radius' => 'round'],
        'ocean' => ['dark' => false, 'bg' => '#eef7f8', 'surface' => '#ffffff', 'fg' => '#0d2b33', 'muted' => '#4d6d75', 'line' => '#cfe5e8', 'font' => 'display', 'layout' => 'cards', 'radius' => 'round'],
        'minimal' => ['dark' => false, 'bg' => '#ffffff', 'surface' => '#ffffff', 'fg' => '#111111', 'muted' => '#6b6b6b', 'line' => '#e5e5e5', 'font' => 'sans', 'layout' => 'list', 'radius' => 'sharp'],
    ],

    'fonts' => [
        'sans' => "'Inter Variable', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Noto Sans', 'Noto Sans Arabic', sans-serif",
        'display' => "'Bricolage Grotesque Variable', 'Inter Variable', ui-sans-serif, system-ui, sans-serif",
        'serif' => "ui-serif, Georgia, Cambria, 'Times New Roman', 'Noto Naskh Arabic', serif",
    ],

    'layouts' => ['list', 'cards', 'grid'],

    // Header styles and how long menus load: all at once, or category by category while scrolling.
    'heroes' => ['full', 'compact'],
    'scrolls' => ['all', 'infinite'],

    'radii' => ['sharp' => '4px', 'soft' => '12px', 'round' => '20px'],
];
