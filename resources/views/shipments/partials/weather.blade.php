{{--
 ============================================================
 BUAT APA : Kartu "Weather forecast" (kolom kanan detail shipment): prakiraan 5 hari untuk
            posisi terkini dan tujuan, dari Open-Meteo lewat WeatherService.
 DIMUAT BELAKANGAN: halaman tampil dulu, cuaca diambil lewat JavaScript dari
            /shipments/{id}/weather. Jadi internet lambat atau mati TIDAK menahan halaman;
            yang muncul pesan rapi. Teks dari server dipasang dengan textContent (aman dari XSS).
 ============================================================
--}}
@if (! $shipment->isTerminal())
<div id="weather" class="card panel">
    <h2 class="panel-title">Weather forecast</h2>
    <div id="weather-box" data-url="{{ route('shipments.weather', $shipment) }}">
        <p class="exc-empty">Loading forecast&hellip;</p>
    </div>
</div>

<script>
(function () {
    var box = document.getElementById('weather-box');
    if (!box) return;

    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined && text !== null) e.textContent = text;
        return e;
    }
    function message(text) { box.replaceChildren(el('p', 'exc-empty', text)); }

    fetch(box.dataset.url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error('http'); return r.json(); })
        .then(function (json) {
            var d = json.data;
            if (!d.applicable) { message(d.message); return; }
            if (!d.available) { message(d.message || 'Weather is not available right now.'); return; }

            var frag = document.createDocumentFragment();

            if (d.advice) {
                frag.appendChild(el('p', 'wx-advice', d.advice));
            }

            d.points.forEach(function (p) {
                var block = el('div', 'wx-point');
                block.appendChild(el('div', 'wx-point-title', (p.kind === 'current' ? 'Current position: ' : 'Destination: ') + p.label));

                if (!p.available) {
                    block.appendChild(el('p', 'exc-empty', 'No forecast for this point right now.'));
                } else {
                    var list = el('div', 'wx-days');
                    p.days.forEach(function (day) {
                        var row = el('div', 'wx-day');
                        row.appendChild(el('div', 'wx-date', day.day));
                        row.appendChild(el('div', 'wx-icon', day.icon));
                        var info = el('div', 'wx-info');
                        info.appendChild(el('div', 'wx-sum', day.summary));
                        info.appendChild(el('div', 'wx-meta', day.temp_min + '–' + day.temp_max + ' °C · rain ' + day.rain_mm + ' mm · wind ' + day.wind_kmh + ' km/h'));
                        row.appendChild(info);
                        row.appendChild(el('span', 'chip ' + day.risk_chip, day.risk_label));
                        list.appendChild(row);
                    });
                    block.appendChild(list);
                }
                frag.appendChild(block);
            });

            frag.appendChild(el('p', 'wx-credit', 'Forecast data by Open-Meteo.com'));
            box.replaceChildren(frag);
        })
        .catch(function () { message('Weather needs an internet connection and is not reachable right now.'); });
})();
</script>
@endif
