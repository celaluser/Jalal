<?php

/*
| Maps for delivery areas and live courier tracking. Leaflet is bundled with the app (no external script);
| only the map pictures ("tiles") come from a tile server. The default is the public OpenStreetMap server,
| which is fine for light use and asks for attribution; for a busy platform use a tile provider you have an account with.
| Set MAPS_TILE_URL= (empty) to draw without a background map.
*/
return [
    'tile_url' => env('MAPS_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'attribution' => env('MAPS_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
    'max_zoom' => (int) env('MAPS_MAX_ZOOM', 19),
    // A courier position older than this is not shown to the guest.
    'courier_fresh_seconds' => (int) env('MAPS_COURIER_FRESH_SECONDS', 300),
];
