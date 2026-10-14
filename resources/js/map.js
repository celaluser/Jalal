// Maps for delivery areas and live courier tracking. Leaflet is bundled here; only the map pictures come from the tile server in config/maps.php.
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const base = (el, cfg, center, zoom) => {
    const map = L.map(el, { zoomControl: true, attributionControl: true, worldCopyJump: true }).setView(center, zoom);
    if (cfg.tile) { L.tileLayer(cfg.tile, { maxZoom: cfg.maxZoom || 19, attribution: cfg.attribution || '' }).addTo(map); }
    return map;
};
const pin = (emoji, label) => L.divIcon({ className: 'qrm-pin', html: `<span role="img" aria-label="${label}">${emoji}</span>`, iconSize: [30, 30], iconAnchor: [15, 28] });
const fmtPoint = (p) => [Math.round(p.lat * 1e6) / 1e6, Math.round(p.lng * 1e6) / 1e6];

// Draw the delivery area of one zone: click to add corners, drag a corner to move it, "undo" and "clear". Other zones are shown grey.
function zoneEditor(el, cfg) {
    const center = cfg.restaurant ? [cfg.restaurant.lat, cfg.restaurant.lng] : [20, 0];
    const map = base(el, cfg, center, cfg.restaurant ? 13 : 2);
    const others = L.layerGroup().addTo(map);
    const shape = L.polygon([], { color: cfg.color || '#e07b00', weight: 3, fillOpacity: 0.18 }).addTo(map);
    const handles = L.layerGroup().addTo(map);
    let points = [];
    let home = null;

    const redraw = () => {
        shape.setLatLngs(points);
        handles.clearLayers();
        points.forEach((p, i) => {
            const h = L.circleMarker(p, { radius: 8, color: '#fff', weight: 2, fillColor: cfg.color || '#e07b00', fillOpacity: 1, bubblingMouseEvents: false }).addTo(handles);
            h.on('mousedown touchstart', (ev) => {
                map.dragging.disable();
                const move = (m) => { points[i] = fmtPoint(m.latlng); shape.setLatLngs(points); h.setLatLng(m.latlng); };
                const up = () => { map.off('mousemove', move); map.dragging.enable(); document.removeEventListener('mouseup', up); document.removeEventListener('touchend', up); sync(); };
                map.on('mousemove', move); document.addEventListener('mouseup', up); document.addEventListener('touchend', up);
                L.DomEvent.stop(ev);
            });
        });
        sync();
    };
    const sync = () => { cfg.onChange && cfg.onChange(points.length >= 3 ? JSON.stringify(points) : ''); };

    map.on('click', (e) => { points.push(fmtPoint(e.latlng)); redraw(); });

    if (cfg.restaurant) {
        home = L.marker(center, { icon: pin('🍽', cfg.labels?.restaurant || 'Restaurant'), draggable: !!cfg.onHome }).addTo(map);
        home.on('dragend', () => { const p = home.getLatLng(); cfg.onHome && cfg.onHome(fmtPoint(p)); });
    }
    (cfg.others || []).forEach((z) => { if (z.polygon && z.polygon.length > 2) { L.polygon(z.polygon, { color: '#6b7280', weight: 2, fillOpacity: 0.08 }).bindTooltip(z.name).addTo(others); } });

    return {
        map,
        load(polygon) {
            points = (polygon || []).map((p) => [p[0], p[1]]);
            redraw();
            if (points.length) { map.fitBounds(shape.getBounds().pad(0.2)); }
        },
        undo() { points.pop(); redraw(); },
        clear() { points = []; redraw(); },
        setHome(lat, lng) { if (!home) { home = L.marker([lat, lng], { icon: pin('🍽', cfg.labels?.restaurant || 'Restaurant'), draggable: true }).addTo(map); home.on('dragend', () => cfg.onHome && cfg.onHome(fmtPoint(home.getLatLng()))); } else { home.setLatLng([lat, lng]); } map.setView([lat, lng], 14); cfg.onHome && cfg.onHome([lat, lng]); },
        locate() { map.locate({ setView: true, maxZoom: 15 }); map.once('locationfound', (e) => { this.setHome(e.latlng.lat, e.latlng.lng); }); },
        invalidate() { setTimeout(() => map.invalidateSize(), 50); },
    };
}

// The guest's tracking map: the restaurant, the destination (if known) and the courier, which moves as new positions arrive.
function trackMap(el, cfg) {
    const start = cfg.courier || cfg.destination || cfg.restaurant || { lat: 20, lng: 0 };
    const map = base(el, cfg, [start.lat, start.lng], cfg.courier || cfg.destination ? 14 : 2);
    const layers = {};
    const place = (key, p, icon, label) => {
        if (!p) { if (layers[key]) { map.removeLayer(layers[key]); delete layers[key]; } return; }
        if (layers[key]) { layers[key].setLatLng([p.lat, p.lng]); } else { layers[key] = L.marker([p.lat, p.lng], { icon: pin(icon, label), keyboard: false }).addTo(map); }
    };
    const fit = () => {
        const pts = Object.values(layers).map((l) => l.getLatLng());
        if (pts.length > 1) { map.fitBounds(L.latLngBounds(pts).pad(0.3), { maxZoom: 16 }); } else if (pts.length === 1) { map.setView(pts[0], 15); }
    };
    place('restaurant', cfg.restaurant, '🍽', cfg.labels?.restaurant || 'Restaurant');
    place('destination', cfg.destination, '🏠', cfg.labels?.destination || 'You');
    place('courier', cfg.courier, '🛵', cfg.labels?.courier || 'Courier');
    fit();

    return {
        map,
        update(state) {
            const had = !!layers.courier;
            place('courier', state.courier, '🛵', cfg.labels?.courier || 'Courier');
            if (state.courier && !had) { fit(); }
        },
        invalidate() { setTimeout(() => map.invalidateSize(), 50); },
    };
}

window.qrmMaps = { zoneEditor, trackMap };
