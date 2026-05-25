<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<div class="modal fade" id="tripTrackModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-map-fill"></i> <span id="trackModalTitle">Fahrt-Track</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="row g-0">
          <div class="col-lg-8">
            <div id="trackModalMap" style="height:60vh;min-height:360px;"></div>
          </div>
          <div class="col-lg-4 border-start">
            <div class="p-3" id="trackModalStats">
              <div class="text-muted">Lade Daten…</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  let map = null, line = null, segLayer = null, startMarker = null, endMarker = null;

  function fmtKm(m)   { return (m/1000).toFixed(2) + ' km'; }
  function fmtKmh(ms) { return (ms*3.6).toFixed(1) + ' km/h'; }
  function fmtDur(s) {
    if (s == null) return '–';
    s = Math.round(s);
    const h = Math.floor(s/3600), m = Math.floor((s%3600)/60), r = s%60;
    return h > 0 ? `${h}:${String(m).padStart(2,'0')}:${String(r).padStart(2,'0')}`
                 : `${m}:${String(r).padStart(2,'0')}`;
  }

  window.openTripTrackModal = function(tripId, title) {
    document.getElementById('trackModalTitle').textContent = title || ('Fahrt ' + tripId);
    document.getElementById('trackModalStats').innerHTML = '<div class="text-muted">Lade Daten…</div>';

    const modalEl = document.getElementById('tripTrackModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    // Karte erst initialisieren wenn Modal sichtbar (Leaflet braucht sichtbaren Container)
    modalEl.addEventListener('shown.bs.modal', function onShown() {
      modalEl.removeEventListener('shown.bs.modal', onShown);

      if (map) { map.remove(); map = null; }
      line = segLayer = startMarker = endMarker = null;

      map = L.map('trackModalMap');
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
      map.setView([47.715, 8.963], 14);

      fetch('/api/fahrt/track.php?trip_id=' + tripId)
        .then(r => r.ok ? r.json() : r.json().then(j => Promise.reject(j)))
        .then(render)
        .catch(err => {
          document.getElementById('trackModalStats').innerHTML =
            '<div class="alert alert-warning mb-0">' + (err.message || 'Fehler beim Laden') + '</div>';
        });
    }, { once: true });

    // Beim Schließen Karte freigeben (spart RAM)
    modalEl.addEventListener('hidden.bs.modal', function onHidden() {
      modalEl.removeEventListener('hidden.bs.modal', onHidden);
      if (map) { map.remove(); map = null; }
    }, { once: true });
  };

  function render(data) {
    const coords = data.track.geometry.coordinates.map(([lon,lat]) => [lat,lon]);
    if (coords.length < 2) {
      document.getElementById('trackModalStats').innerHTML =
        '<div class="alert alert-info mb-0">Zu wenige GPS-Punkte.</div>';
      return;
    }
    line = L.polyline(coords, { color:'#0d6efd', weight:4 }).addTo(map);
    map.fitBounds(line.getBounds(), { padding:[20,20] });
    startMarker = L.marker(coords[0]).addTo(map).bindTooltip('Start');
    endMarker   = L.marker(coords[coords.length-1]).addTo(map).bindTooltip('Ziel');
    setTimeout(() => map.invalidateSize(), 50);

    const s = data.stats || {};
    const segs = data.segments || {};

    const segBtn = (D, seg) => {
      if (!seg) return `<div class="d-flex justify-content-between py-1"><span class="text-muted">${D} m</span><span>–</span></div>`;
      return `<button type="button" class="btn btn-sm btn-outline-primary w-100 text-start mb-1" data-d="${D}">
        <i class="bi bi-speedometer2"></i> ${D} m: ${fmtDur(seg.duration_s)}
        <span class="text-muted small">(⌀ ${fmtKmh(D/seg.duration_s)})</span>
      </button>`;
    };

    document.getElementById('trackModalStats').innerHTML = `
      <div class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">Distanz</span><strong>${s.distance_m!=null?fmtKm(s.distance_m):'–'}</strong></div>
      <div class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">Dauer</span><strong>${fmtDur(s.duration_s)}</strong></div>
      <div class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">⌀ Speed</span><strong>${s.avg_speed_ms!=null?fmtKmh(s.avg_speed_ms):'–'}</strong></div>
      <div class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">v_max</span><strong>${s.max_speed_ms!=null?fmtKmh(s.max_speed_ms):'–'}</strong></div>
      <div class="d-flex justify-content-between py-1 mb-2"><span class="text-muted">Punkte</span><strong>${data.session.point_count}</strong></div>
      <h6 class="mt-3">Schnellste Abschnitte</h6>
      ${segBtn(250,  segs['250'])}
      ${segBtn(500,  segs['500'])}
      ${segBtn(1000, segs['1000'])}
      ${segBtn(1500, segs['1500'])}
      ${segBtn(2000, segs['2000'])}
    `;

    document.querySelectorAll('#trackModalStats [data-d]').forEach(btn => {
      btn.addEventListener('click', () => {
        const seg = segs[btn.dataset.d];
        if (!seg) return;
        if (segLayer) map.removeLayer(segLayer);
        segLayer = L.polyline(coords.slice(seg.start_idx, seg.end_idx+1),
          { color:'#dc3545', weight:6 }).addTo(map);
        map.fitBounds(segLayer.getBounds(), { padding:[30,30] });
      });
    });
  }
})();
</script>
