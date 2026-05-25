(function(){
  const tripId = window.FAHRT_ID;
  if (!tripId) return;

  const map = L.map('track-map');
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '© OpenStreetMap'
  }).addTo(map);

  let segmentLayer = null;

  function fmtKm(m)     { return (m/1000).toFixed(2) + ' km'; }
  function fmtKmh(ms)   { return (ms*3.6).toFixed(1) + ' km/h'; }
  function fmtDur(s)    {
    if (s == null) return '–';
    s = Math.round(s);
    const h = Math.floor(s/3600), m = Math.floor((s%3600)/60), r = s%60;
    return h > 0 ? `${h}:${String(m).padStart(2,'0')}:${String(r).padStart(2,'0')}`
                 : `${m}:${String(r).padStart(2,'0')}`;
  }

  fetch('/api/fahrt/track.php?trip_id=' + tripId)
    .then(r => r.ok ? r.json() : r.json().then(j => Promise.reject(j)))
    .then(render)
    .catch(err => {
      document.getElementById('track-stats').innerHTML =
        `<div class="alert alert-warning mb-0">${err.message || 'Fehler beim Laden'}</div>`;
    });

  function render(data) {
    const coords = data.track.geometry.coordinates.map(([lon, lat]) => [lat, lon]);
    if (coords.length < 2) {
      document.getElementById('track-stats').innerHTML =
        '<div class="alert alert-info mb-0">Zu wenige GPS-Punkte.</div>';
      map.setView([47.715, 8.963], 14);
      return;
    }
    const line = L.polyline(coords, { color: '#0d6efd', weight: 4 }).addTo(map);
    map.fitBounds(line.getBounds(), { padding: [20, 20] });
    L.marker(coords[0]).addTo(map).bindTooltip('Start');
    L.marker(coords[coords.length-1]).addTo(map).bindTooltip('Ziel');

    const s = data.stats || {};
    const segs = data.segments || {};

    const segBtn = (D, seg) => {
      if (!seg) return `<div class="stat-row"><span class="label">${D} m</span><span class="value">–</span></div>`;
      return `<button type="button" class="btn btn-sm btn-outline-primary seg-btn" data-d="${D}">
        <i class="bi bi-speedometer2"></i> ${D} m: ${fmtDur(seg.duration_s)}
        <span class="text-muted small">(⌀ ${fmtKmh(D/seg.duration_s)})</span>
      </button>`;
    };

    document.getElementById('track-stats').innerHTML = `
      <h5 class="mb-3"><i class="bi bi-graph-up"></i> Kennzahlen</h5>
      <div class="stat-row"><span class="label">Distanz</span><span class="value">${s.distance_m != null ? fmtKm(s.distance_m) : '–'}</span></div>
      <div class="stat-row"><span class="label">Dauer</span><span class="value">${fmtDur(s.duration_s)}</span></div>
      <div class="stat-row"><span class="label">⌀ Speed</span><span class="value">${s.avg_speed_ms != null ? fmtKmh(s.avg_speed_ms) : '–'}</span></div>
      <div class="stat-row"><span class="label">v_max</span><span class="value">${s.max_speed_ms != null ? fmtKmh(s.max_speed_ms) : '–'}</span></div>
      <div class="stat-row"><span class="label">Punkte</span><span class="value">${data.session.point_count}</span></div>
      <hr>
      <h6 class="mb-2">Schnellste Abschnitte</h6>
      ${segBtn(250,  segs['250'])}
      ${segBtn(500,  segs['500'])}
      ${segBtn(1000, segs['1000'])}
      ${segBtn(1500, segs['1500'])}
      ${segBtn(2000, segs['2000'])}
    `;

    document.querySelectorAll('#track-stats .seg-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const D = btn.dataset.d;
        const seg = segs[D];
        if (!seg) return;
        if (segmentLayer) map.removeLayer(segmentLayer);
        const sub = coords.slice(seg.start_idx, seg.end_idx + 1);
        segmentLayer = L.polyline(sub, { color: '#dc3545', weight: 6 }).addTo(map);
        map.fitBounds(segmentLayer.getBounds(), { padding: [30, 30] });
      });
    });
  }
})();
