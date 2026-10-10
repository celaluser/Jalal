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
        // Dynamic themes: gradient backgrounds, glass or glowing cards, animated scroll indicators and reveal-on-scroll.
        'aurora' => ['dark' => true, 'bg' => '#070b1a', 'bg2' => '#1b1146', 'surface' => '#12172b', 'fg' => '#f3f4fb', 'muted' => '#a8aed0', 'line' => '#2a3050', 'font' => 'display', 'layout' => 'cards', 'radius' => 'round', 'bg_style' => 'mesh', 'card' => 'glass', 'scrollbar' => 'gradient', 'reveal' => 'rise', 'progress' => true, 'animated_bg' => true, 'decor' => 'orbs', 'button' => 'gradient', 'heading' => 'gradient', 'hover' => 'lift'],
        'neon' => ['dark' => true, 'bg' => '#050507', 'bg2' => '#0d0618', 'surface' => '#0e0e14', 'fg' => '#f5f5ff', 'muted' => '#9a9ab8', 'line' => '#26263a', 'font' => 'display', 'layout' => 'cards', 'radius' => 'soft', 'bg_style' => 'gradient', 'card' => 'outline', 'scrollbar' => 'glow', 'reveal' => 'zoom', 'progress' => true, 'animated_bg' => false, 'decor' => 'grid', 'button' => 'gradient', 'heading' => 'glow', 'hover' => 'glow'],
        'sunset' => ['dark' => false, 'bg' => '#fff4e8', 'bg2' => '#ffdfe9', 'surface' => '#ffffff', 'fg' => '#2b1a1f', 'muted' => '#7d5f66', 'line' => '#f3d9d2', 'font' => 'display', 'layout' => 'grid', 'radius' => 'round', 'bg_style' => 'gradient', 'card' => 'soft', 'scrollbar' => 'pill', 'reveal' => 'fade', 'progress' => true, 'animated_bg' => true, 'decor' => 'dots', 'button' => 'gradient', 'heading' => 'plain', 'hover' => 'tilt'],
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

    // Page background, card surface, scroll indicator and reveal animation styles a theme can use.
    'backgrounds' => ['solid', 'gradient', 'mesh'],
    'cards' => ['flat', 'soft', 'glass', 'outline'],
    'scrollbars' => ['default', 'slim', 'accent', 'pill', 'gradient', 'glow'],
    'reveals' => ['none', 'fade', 'rise', 'zoom'],
    // Decoration behind the page, button fill, heading treatment and what a card does under the finger or pointer.
    'decors' => ['none', 'orbs', 'grid', 'dots'],
    'buttons' => ['solid', 'gradient'],
    'headings' => ['plain', 'gradient', 'glow'],
    'hovers' => ['none', 'lift', 'tilt', 'glow'],

    'radii' => ['sharp' => '4px', 'soft' => '12px', 'round' => '20px'],
];
