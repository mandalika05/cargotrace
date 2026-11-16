{{--
 ============================================================
 BUAT APA : Kartu PETA di halaman detail shipment (kolom kiri, di bawah kartu rute).
            Leaflet + OpenStreetMap: marker asal, tujuan, titik tracking, garis rute.
 DATA     : diambil lewat JavaScript dari /shipments/{id}/map (resources/js/shipment-map.js).
            Customer hanya bisa membuka peta shipment miliknya.
 ============================================================
--}}
<div class="card panel">
    <h2 class="panel-title">Route map</h2>
    <div id="shipment-map" class="map-box"
         data-url="{{ route('shipments.map', $shipment) }}"
         data-tiles="{{ config('cargotrace.map.tile_url') }}">
        <p class="map-message">Loading map&hellip;</p>
    </div>
</div>
