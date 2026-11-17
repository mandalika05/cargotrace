/*
 * ============================================================
 * BUAT APA : Kartu "Shipment Map" di dashboard (meniru mockup dosen): menampilkan posisi
 *            terkini semua shipment yang MASIH BERJALAN, memakai Leaflet + OpenStreetMap.
 * DATA     : /dashboard/map (MapController::overview). Customer hanya melihat miliknya.
 * WARNA    : hijau = berjalan normal, oranye = terlambat, hijau tua = masih di lokasi asal.
 * AMAN     : isi popup dipasang dengan textContent (bukan innerHTML) -> aman dari XSS.
 * OFFLINE  : bila peta/tile gagal dimuat tampil pesan rapi; halaman tidak error.
 * ============================================================
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const box = document.getElementById('dashboard-map');

function el(tag, cls, text) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text) e.textContent = text;
    return e;
}

function showMessage(text) {
    box.replaceChildren(el('p', 'map-message', text));
}

function pin(kind) {
    return L.divIcon({
        className: 'map-pin-wrap',
        html: '<span class="map-pin map-pin-' + kind + '"></span>',
        iconSize: [22, 22],
        iconAnchor: [11, 11],
        popupAnchor: [0, -10],
    });
}

function popup(p) {
    const wrap = el('div', 'map-popup');
    wrap.appendChild(el('div', 'map-popup-title', p.number));
    wrap.appendChild(el('div', 'map-popup-place', p.route));
    wrap.appendChild(el('div', 'map-popup-time', p.status + (p.delayed ? ' - delayed' : '')));
    const link = el('a', 'link-accent', 'Open shipment');
    link.href = p.url;
    wrap.appendChild(link);
    return wrap;
}

function draw(points, tileUrl) {
    box.replaceChildren();
    const canvas = el('div', 'map-canvas');
    box.appendChild(canvas);

    const map = L.map(canvas, { scrollWheelZoom: false });
    const tiles = L.tileLayer(tileUrl, {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
    }).addTo(map);

    let warned = false;
    tiles.on('tileerror', () => {
        if (warned) return;
        warned = true;
        box.appendChild(el('p', 'map-note', 'Map tiles could not be loaded (check your internet connection). Markers are still shown.'));
    });

    const bounds = [];
    points.forEach((p) => {
        const kind = p.delayed ? 'delayed' : (p.source === 'origin' ? 'origin' : 'running');
        L.marker([p.latitude, p.longitude], { icon: pin(kind), title: p.number }).addTo(map).bindPopup(popup(p));
        bounds.push([p.latitude, p.longitude]);
    });

    if (bounds.length === 1) map.setView(bounds[0], 5);
    else map.fitBounds(bounds, { padding: [24, 24], maxZoom: 7 });

    const legend = el('div', 'map-legend');
    [['running', 'Running'], ['delayed', 'Delayed'], ['origin', 'At origin']].forEach(([k, t]) => {
        const item = el('span', 'map-legend-item');
        item.appendChild(el('span', 'map-pin map-pin-' + k));
        item.appendChild(document.createTextNode(' ' + t));
        legend.appendChild(item);
    });
    box.appendChild(legend);

    setTimeout(() => map.invalidateSize(), 150);
}

if (box) {
    fetch(box.dataset.url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then((r) => { if (!r.ok) throw new Error('http'); return r.json(); })
        .then(({ data }) => {
            if (!data.available) { showMessage(data.message); return; }
            draw(data.points, box.dataset.tiles);
        })
        .catch(() => showMessage('The map could not be loaded right now.'));
}
