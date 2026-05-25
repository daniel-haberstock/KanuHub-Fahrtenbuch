/**
 * Fahrtenbuch Beta — end-trip.js
 * Fahrt beenden: Einzelfahrt (Strecke → Submit) und Gruppenfahrt (Gruppe → Strecke → Submit)
 * Fahrt abbrechen: Confirm → DELETE
 * Feedback-Modal nach Beenden: Badges, Crew-Stats, Club-Fortschritt, Hinweise
 */
(function () {
    'use strict';

    // ── State ─────────────────────────────────────────────────────

    let _tripId      = null;
    let _boatId      = null;
    let _boatName    = null;
    let _isTrailer   = false;
    let _sourceTable = 'trips';

    let _isGroup         = false;
    let _ownTripSelected = true;  // ob die eigene Fahrt mitbeendet wird
    let _groupTrips      = [];   // [{id, boat_name, crew}]
    let _recentRoutes    = [];   // [{route_id, route_label, distance}]
    let _allRoutes       = [];   // alle Strecken aus routes/list.php
    let _selectedGroupIds= new Set();

    let _routeId    = null;
    let _routeLabel = '';
    let _distance   = '';

    let _step = 'route';   // 'group' | 'route'

    // ── DOM-Helpers ───────────────────────────────────────────────

    function body()   { return document.getElementById('endTripBody'); }
    function footer() { return document.getElementById('endTripFooter'); }
    function title()  { return document.getElementById('endTripTitle'); }

    function escHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function fmtKm(v) {
        if (v == null || v === '') return '—';
        return parseFloat(v).toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' km';
    }

    // ── Render: Gruppen-Schritt ───────────────────────────────────

    function renderStepGroup() {
        _step = 'group';
        const t = title();
        if (t) t.textContent = 'Gruppenfahrt beenden';

        _ownTripSelected  = true;
        _selectedGroupIds = new Set(_groupTrips.map(g => g.id));

        function renderGroupList() {
            const anySelected = _ownTripSelected || _selectedGroupIds.size > 0;
            body().innerHTML = `
                <p class="kcs-et-intro">
                    <i class="bi bi-people-fill text-primary"></i>
                    Strecke und Kilometer werden für alle angehakten Fahrten übernommen.
                </p>
                <div class="kcs-et-group-list">
                    <label class="kcs-et-group-item">
                        <input type="checkbox" id="etOwnCb" ${_ownTripSelected ? 'checked' : ''}>
                        <div class="kcs-et-group-info">
                            <span class="kcs-et-group-boat">${escHtml(_boatName)}</span>
                            <span class="kcs-et-group-hint">diese Fahrt</span>
                        </div>
                    </label>
                    ${_groupTrips.map(gt => `
                    <label class="kcs-et-group-item">
                        <input type="checkbox" class="kcs-et-group-cb" data-id="${gt.id}" ${_selectedGroupIds.has(gt.id) ? 'checked' : ''}>
                        <div class="kcs-et-group-info">
                            <span class="kcs-et-group-boat">${escHtml(gt.boat_name)}</span>
                            ${gt.crew ? `<span class="kcs-et-group-crew">${escHtml(gt.crew)}</span>` : ''}
                        </div>
                    </label>`).join('')}
                </div>`;

            body().querySelector('#etOwnCb').addEventListener('change', e => {
                _ownTripSelected = e.target.checked;
                updateGroupFooter();
            });
            body().querySelectorAll('.kcs-et-group-cb').forEach(cb => {
                cb.addEventListener('change', () => {
                    const id = parseInt(cb.dataset.id, 10);
                    if (cb.checked) _selectedGroupIds.add(id);
                    else            _selectedGroupIds.delete(id);
                    updateGroupFooter();
                });
            });

            updateGroupFooter();
        }

        function updateGroupFooter() {
            const anySelected = _ownTripSelected || _selectedGroupIds.size > 0;
            renderFooter('Weiter <i class="bi bi-arrow-right"></i>', !anySelected, true);
        }

        renderGroupList();
    }

    // ── Render: Strecken-Schritt ──────────────────────────────────

    function renderStepRoute() {
        _step = 'route';
        const t = title();
        if (t) t.textContent = 'Fahrt beenden — ' + (_boatName || '');

        const hasRecent = !_isGroup && _recentRoutes.length > 0;

        body().innerHTML = `
            <div class="kcs-et-route-wrap${hasRecent ? ' kcs-et-route-wrap--split' : ''}">
                <div class="kcs-et-route-main">

                    <div class="kcs-et-field">
                        <label class="kcs-et-label">Strecke</label>
                        <div class="kcs-wiz-ac-wrap">
                            <input type="text" class="kcs-input kcs-et-route-input" id="etRouteInput"
                                   value="${escHtml(_routeLabel)}"
                                   placeholder="Suchen oder freitext eingeben"
                                   autocomplete="off">
                            <div class="kcs-wiz-ac-dropdown" id="etRouteDrop"></div>
                        </div>
                    </div>

                    <div class="kcs-et-field">
                        <label class="kcs-et-label kcs-et-label--required">Kilometer *</label>
                        <div class="kcs-et-km-wrap">
                            <button class="kcs-et-km-btn" id="etKmMinus" type="button">−</button>
                            <input type="number" class="kcs-input kcs-et-km-input" id="etKm"
                                   step="0.5" min="0" value="${escHtml(_distance)}"
                                   placeholder="0.0">
                            <button class="kcs-et-km-btn" id="etKmPlus" type="button">+</button>
                        </div>
                        <div class="kcs-et-km-presets">
                            ${[5, 8, 12, 18, 22, 34].map(v =>
                                `<button class="kcs-et-preset-btn${parseFloat(_distance) === v ? ' active' : ''}" type="button" data-km="${v}">${v} km</button>`
                            ).join('')}
                        </div>
                    </div>

                    <div class="kcs-et-field">
                        <label class="kcs-et-label">Notizen zur Fahrt</label>
                        <textarea class="kcs-input kcs-et-textarea" id="etNotes" rows="3"
                                  placeholder="Was war besonders? Sonnenaufgang, Sonnenuntergang, Vollmondfahrt …"></textarea>
                    </div>

                    <div class="kcs-et-field">
                        <label class="kcs-et-label"><i class="bi bi-stars"></i> Wie war's?</label>
                        <div class="kcs-et-exp-grid">
                            ${[
                                ['alles-super',    '⭐', 'Alles Super'],
                                ['wind',           '💨', 'Starker Wind'],
                                ['wellen',         '🌊', 'Starke Wellen'],
                                ['sonnenbrand',    '☀️', 'Sonnenbrand'],
                                ['regen',          '🌧️', 'Nass durch Regen'],
                                ['gekentert',      '🛶', 'Gekentert'],
                                ['stechmuecke',    '🦟', 'Mückenstiche'],
                                ['nebel',          '🌫️', 'Nebel / Nieselregen'],
                                ['gegenwind',      '🌬️', 'Gegenwind'],
                                ['neue-strecke',   '🗺️', 'Neue Strecke'],
                                ['sehr-anstrengend','💪', 'Sehr anstrengend'],
                            ].map(([val, ico, lbl]) => `
                            <label class="kcs-et-exp-item">
                                <input type="checkbox" class="kcs-et-exp-cb" value="${val}">
                                <span>${ico} ${lbl}</span>
                            </label>`).join('')}
                        </div>
                    </div>

                    <div id="etError" class="kcs-st-error" style="display:none"></div>
                </div>

                ${hasRecent ? `
                <div class="kcs-et-recent-panel">
                    <div class="kcs-et-recent-label"><i class="bi bi-clock-history"></i> Zuletzt gefahren</div>
                    ${_recentRoutes.map(r => `
                    <button class="kcs-et-recent-btn" type="button"
                            data-route-id="${r.route_id || ''}"
                            data-route-label="${escHtml(r.route_label || r.route_custom || '')}"
                            data-distance="${r.distance || ''}">
                        <span class="kcs-et-recent-name">${escHtml(r.route_label || r.route_custom || '—')}</span>
                        ${r.distance ? `<span class="kcs-et-recent-dist">${String(r.distance).replace('.', ',')} km</span>` : ''}
                    </button>`).join('')}
                </div>` : ''}
            </div>`;

        // Autocomplete
        const routeInput = body().querySelector('#etRouteInput');
        const routeDrop  = body().querySelector('#etRouteDrop');
        if (routeInput && routeDrop) {
            const showRoutes = q => {
                const filtered = _allRoutes.filter(r => {
                    const text = (r.description || ((r.start_point || '') + (r.end_point ? ' – ' + r.end_point : ''))).toLowerCase();
                    return !q || text.includes(q.toLowerCase());
                }).slice(0, 15);
                if (!filtered.length) { routeDrop.classList.remove('show'); return; }
                routeDrop.innerHTML = filtered.map(r => {
                    const label = r.description || ((r.start_point || '') + (r.end_point ? ' – ' + r.end_point : ''));
                    return `<div class="kcs-wiz-ac-item" data-id="${r.id}" data-label="${escHtml(label)}" data-dist="${r.distance || ''}">
                        ${escHtml(label)}${r.distance ? ` <span class="kcs-wiz-ac-meta">${r.distance} km</span>` : ''}
                    </div>`;
                }).join('');
                routeDrop.classList.add('show');
                routeDrop.querySelectorAll('.kcs-wiz-ac-item').forEach(item => {
                    item.addEventListener('mousedown', e => {
                        e.preventDefault();
                        _routeId    = parseInt(item.dataset.id, 10) || null;
                        _routeLabel = item.dataset.label;
                        routeInput.value = _routeLabel;
                        routeDrop.classList.remove('show');
                        if (item.dataset.dist) {
                            _distance = item.dataset.dist;
                            const kmEl = body().querySelector('#etKm');
                            if (kmEl) { kmEl.value = _distance; updateRouteFooter(); }
                        }
                    });
                });
            };
            routeInput.addEventListener('focus', () => showRoutes(routeInput.value));
            routeInput.addEventListener('input', () => {
                _routeId    = null;
                _routeLabel = routeInput.value;
                showRoutes(routeInput.value);
                updateRouteFooter();
            });
            routeInput.addEventListener('blur', () => setTimeout(() => routeDrop.classList.remove('show'), 150));
        }

        // km-Buttons
        const kmEl    = body().querySelector('#etKm');
        const kmMinus = body().querySelector('#etKmMinus');
        const kmPlus  = body().querySelector('#etKmPlus');

        if (kmEl) {
            kmEl.addEventListener('input', () => { _distance = kmEl.value; updateRouteFooter(); });
        }
        if (kmMinus) kmMinus.addEventListener('click', () => {
            const v = Math.max(0, parseFloat(kmEl?.value || 0) - 0.5);
            _distance = String(v);
            if (kmEl) kmEl.value = _distance;
            updateRouteFooter();
        });
        if (kmPlus) kmPlus.addEventListener('click', () => {
            const v = parseFloat(kmEl?.value || 0) + 0.5;
            _distance = String(v);
            if (kmEl) kmEl.value = _distance;
            updateRouteFooter();
        });

        // Preset-Buttons
        body().querySelectorAll('.kcs-et-preset-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                _distance = btn.dataset.km;
                if (kmEl) kmEl.value = _distance;
                body().querySelectorAll('.kcs-et-preset-btn').forEach(b => b.classList.toggle('active', b === btn));
                updateRouteFooter();
            });
        });

        // Recent-Route-Buttons
        body().querySelectorAll('.kcs-et-recent-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                _routeId    = parseInt(btn.dataset.routeId, 10) || null;
                _routeLabel = btn.dataset.routeLabel;
                if (routeInput) routeInput.value = _routeLabel;
                if (btn.dataset.distance) {
                    _distance = btn.dataset.distance;
                    if (kmEl) kmEl.value = _distance;
                }
                body().querySelectorAll('.kcs-et-recent-btn').forEach(b => b.classList.toggle('active', b === btn));
                updateRouteFooter();
            });
        });

        updateRouteFooter();
    }

    function updateRouteFooter() {
        const km = parseFloat(body()?.querySelector('#etKm')?.value || 0);
        // Einzelfahrt: Abort im Route-Schritt zeigen (einziger Schritt)
        // Gruppenfahrt: Abort nur im Gruppen-Schritt, nicht hier
        renderFooter('<i class="bi bi-flag-fill"></i> Fahrt beenden', km <= 0, !_isGroup);
    }

    // ── Footer rendern ────────────────────────────────────────────

    function renderFooter(nextLabel, nextDisabled, showAbort) {
        const f = footer();
        if (!f) return;

        const backBtn = (_step === 'route' && _isGroup)
            ? `<button class="kcs-btn kcs-btn--ghost" id="etBack" type="button">
                   <i class="bi bi-arrow-left"></i> Zurück
               </button>`
            : '<div></div>';

        const abortBtn = showAbort
            ? `<button class="kcs-btn kcs-et-abort-btn" id="etAbort" type="button">
                   <i class="bi bi-x-circle"></i> Fahrt abbrechen
               </button>`
            : '<div></div>';

        f.innerHTML = `
            ${abortBtn}
            ${backBtn}
            <button class="kcs-btn kcs-btn--green" id="etNext" type="button" ${nextDisabled ? 'disabled' : ''}>
                ${nextLabel}
            </button>`;

        f.querySelector('#etAbort')?.addEventListener('click', abortTrip);
        f.querySelector('#etBack')?.addEventListener('click', () => renderStepGroup());
        f.querySelector('#etNext').addEventListener('click', handleNext);
    }

    // ── Navigation ────────────────────────────────────────────────

    function handleNext() {
        if (_step === 'group') {
            renderStepRoute();
        } else {
            submitEnd();
        }
    }

    // ── Submit: Fahrt beenden ─────────────────────────────────────

    async function submitEnd() {
        const btn = footer()?.querySelector('#etNext');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> …'; }

        const now = new Date();
        const pad = n => String(n).padStart(2, '0');
        const today = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
        const time  = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':00';

        // Bestimme welche Trips zu beenden sind
        // Wenn bei Gruppenfahrt die eigene Fahrt abgehakt wurde:
        // erste gewählte Gruppenfahrt als Hauptfahrt, Rest als Mitbeendigung
        let mainTripId      = _tripId;
        let mainIsTrailer   = _isTrailer;
        let mainSourceTable = _sourceTable;
        const coEndIds      = new Set(_selectedGroupIds);

        if (_isGroup && !_ownTripSelected) {
            const firstGroupId = [..._selectedGroupIds][0];
            mainTripId      = firstGroupId;
            mainIsTrailer   = false;   // Gruppenfahrten sind keine Anhänger-Fahrten
            mainSourceTable = 'trips';
            coEndIds.delete(firstGroupId);
        }

        const form = new FormData();
        form.append('trip_id',      mainTripId);
        form.append('end_date',     today);
        form.append('end_time',     time);
        form.append('distance',     _distance);
        form.append('route_custom', _routeLabel);
        if (_routeId) form.append('route_id', _routeId);
        form.append('is_trailer',   mainIsTrailer ? '1' : '0');
        form.append('source_table', mainSourceTable);
        coEndIds.forEach(id => form.append('group_end_trip_ids[]', id));

        const notes = body()?.querySelector('#etNotes')?.value?.trim() || '';
        if (notes) form.append('trip_notes', notes);
        body()?.querySelectorAll('.kcs-et-exp-cb:checked').forEach(cb => {
            form.append('trip_experiences[]', cb.value);
        });

        try {
            const res  = await fetch(KCS.apiBase + '/trips/end.php', { method: 'POST', body: form });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Fehler beim Beenden');

            KCS.closeModal('endTripModal');
            document.dispatchEvent(new CustomEvent('kcs:trip-ended',    { detail: { tripId: _tripId } }));
            document.dispatchEvent(new CustomEvent('kcs:refresh-boats'));

            // Alle Boote zurück?
            let allBoatsBack = false;
            try {
                const active = await KCS.api('/trips/active.php');
                const list   = Array.isArray(active) ? active : (active.trips || []);
                allBoatsBack = list.length === 0;
            } catch (_) {}

            showTripEndFeedback(data, allBoatsBack);

        } catch (e) {
            const err = body()?.querySelector('#etError');
            if (err) { err.style.display = ''; err.textContent = e.message; }
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-flag-fill"></i> Fahrt beenden'; }
        }
    }

    // ── Abort: Fahrt abbrechen ────────────────────────────────────

    async function abortTrip() {
        if (!confirm('Fahrt wirklich abbrechen? Die Fahrt wird vollständig gelöscht und nicht gespeichert.')) return;

        const form = new FormData();
        form.append('trip_id',   _tripId);
        form.append('is_trailer', _isTrailer ? '1' : '0');

        try {
            const res  = await fetch(KCS.apiBase + '/trips/delete.php', { method: 'POST', body: form });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Fehler beim Abbrechen');

            KCS.closeModal('endTripModal');
            document.dispatchEvent(new CustomEvent('kcs:trip-ended',    { detail: { tripId: _tripId } }));
            document.dispatchEvent(new CustomEvent('kcs:refresh-boats'));
        } catch (e) {
            alert('Fehler: ' + e.message);
        }
    }

    // ── Feedback-Modal nach Beenden ───────────────────────────────

    function showTripEndFeedback(response, allBoatsBack) {
        document.getElementById('tripEndResultModal')?.remove();

        const cp            = response.club_progress   || {};
        const crew          = response.crew_stats      || [];
        const newBadgeCount = response.new_badges_count || 0;
        const newBadgesHtml = response.new_badges_html  || '';

        // Badge-Banner
        let badgeBanner = '';
        if (newBadgeCount > 0) {
            badgeBanner = `
                <div class="alert alert-warning text-center mb-3 kcs-badge-unlock-banner">
                    <h5 class="mb-2"><i class="bi bi-trophy-fill"></i> ${newBadgeCount === 1 ? 'Neues Badge freigeschaltet!' : newBadgeCount + ' neue Badges freigeschaltet!'}</h5>
                    <div class="badges-container">${newBadgesHtml}</div>
                </div>`;
        }

        // Crew-Cards
        let crewCards = '';
        if (crew.length > 0) {
            crewCards = '<h6 class="fw-bold mb-2"><i class="bi bi-people-fill"></i> Crew-Statistiken</h6><div class="row g-2 mb-3">';
            crew.forEach(m => {
                const diffSign  = (m.diff_km >= 0) ? '+' : '';
                const diffClass = (m.diff_km >= 0) ? 'text-success' : 'text-danger';
                const diffIcon  = (m.diff_km >= 0) ? 'bi-arrow-up'  : 'bi-arrow-down';
                let rankHtml = '';
                if (m.rank) {
                    rankHtml = `<span class="badge bg-primary me-1"><i class="bi bi-hash"></i>${m.rank} / ${m.rank_total}</span>`;
                    if (m.rank > 1 && m.gap_to_next > 0) {
                        rankHtml += `<small class="text-muted">noch ${fmtKm(m.gap_to_next)} bis Platz ${m.rank - 1}</small>`;
                    } else if (m.rank === 1) {
                        rankHtml += '<small class="text-success fw-bold"><i class="bi bi-star-fill"></i> Führend!</small>';
                    }
                }
                let badgeHtml = '';
                if (m.badge_count > 0 || (m.new_badges && m.new_badges.length > 0)) {
                    badgeHtml = `<div class="mt-1"><span class="badge bg-secondary"><i class="bi bi-award"></i> ${m.badge_count} Badge${m.badge_count !== 1 ? 's' : ''}</span>`;
                    if (m.new_badges && m.new_badges.length > 0) {
                        badgeHtml += ` <span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> +${m.new_badges.length} neu!</span>`;
                    }
                    badgeHtml += '</div>';
                }
                crewCards += `
                    <div class="col-md-6"><div class="card h-100 border-0 shadow-sm"><div class="card-body py-2 px-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <h6 class="mb-1 fw-bold"><i class="bi bi-person-fill text-primary"></i> ${escHtml(m.name)}</h6>
                            <span class="badge bg-info">+${fmtKm(m.trip_distance)}</span>
                        </div>
                        <div class="mb-1">
                            <span class="fw-semibold">${fmtKm(m.season_km)}</span>
                            <small class="text-muted"> Saison</small>
                            <span class="${diffClass} ms-1"><i class="bi ${diffIcon}"></i> ${diffSign}${fmtKm(m.diff_km)} vs. Vorjahr</span>
                        </div>
                        <div class="mb-1">${rankHtml}</div>
                        ${badgeHtml}
                    </div></div></div>`;
            });
            crewCards += '</div>';
        }

        // Club-Fortschritt
        let clubHtml = '';
        if (cp.season_current !== undefined) {
            const sPct = cp.season_pct || 0, mPct = cp.month_pct || 0;
            const sDiffSign = cp.season_diff >= 0 ? '+' : '', mDiffSign = cp.month_diff >= 0 ? '+' : '';
            const sDiffCls  = cp.season_diff >= 0 ? 'text-success' : 'text-danger';
            const mDiffCls  = cp.month_diff  >= 0 ? 'text-success' : 'text-danger';
            clubHtml = `
                <div class="card border-0 bg-light mb-3"><div class="card-body py-2 px-3">
                    <h6 class="fw-bold mb-2"><i class="bi bi-trophy-fill text-warning"></i> Vereins-Fortschritt</h6>
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted fw-semibold">Saison</small>
                            <div class="progress mb-1" style="height:8px;">
                                <div class="progress-bar ${sPct >= 100 ? 'bg-success' : 'bg-info'}" style="width:${Math.min(sPct,100)}%;"></div>
                            </div>
                            <small>${fmtKm(cp.season_current)} <span class="${sDiffCls}">${sDiffSign}${fmtKm(cp.season_diff)}</span></small>
                        </div>
                        <div class="col-6">
                            <small class="text-muted fw-semibold">Monat</small>
                            <div class="progress mb-1" style="height:8px;">
                                <div class="progress-bar ${mPct >= 100 ? 'bg-success' : 'bg-warning'}" style="width:${Math.min(mPct,100)}%;"></div>
                            </div>
                            <small>${fmtKm(cp.month_current)} <span class="${mDiffCls}">${mDiffSign}${fmtKm(cp.month_diff)}</span></small>
                        </div>
                    </div>
                </div></div>`;
        }

        // Hinweise
        let warnings = '';
        if (response.boat_note_end) {
            warnings += `
                <div class="alert alert-primary border-start border-4 border-primary py-2 mb-2">
                    <i class="bi bi-info-circle-fill"></i> <strong>Hinweis zu diesem Boot:</strong><br>
                    ${escHtml(response.boat_note_end)}
                </div>`;
        }
        if (allBoatsBack) {
            warnings += `<div class="alert alert-info py-2 mb-2">
                <i class="bi bi-house-fill"></i> <strong>Alle Boote sind zurück!</strong> Bitte Bootshaus abschließen.
            </div>`;
        }
        if (response.group_ended_count > 0) {
            warnings += `<div class="alert alert-success py-2 mb-2">
                <i class="bi bi-people-fill"></i> Fahrt und <strong>${response.group_ended_count}</strong> weitere Gruppenfahrt${response.group_ended_count !== 1 ? 'en' : ''} beendet.
            </div>`;
        }
        warnings += `<div class="alert alert-secondary py-2 mb-0">
            <i class="bi bi-droplet"></i> <small>Bitte Boot sauber machen, trocknen und ordentlich verräumen.</small>
        </div>`;

        const modalHtml = `
            <div class="modal fade" id="tripEndResultModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title"><i class="bi bi-check-circle-fill"></i> Fahrt erfolgreich beendet</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">${badgeBanner}${crewCards}${clubHtml}${warnings}</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                                <i class="bi bi-check-lg"></i> Alles klar!
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        if (newBadgeCount > 0) {
            setTimeout(() => document.querySelector('.kcs-badge-unlock-banner')?.classList.add('animate__animated', 'animate__pulse'), 300);
        }

        const feedbackEl = document.getElementById('tripEndResultModal');
        const modal = new bootstrap.Modal(feedbackEl);
        modal.show();
        feedbackEl.addEventListener('hidden.bs.modal', () => feedbackEl.remove());
    }

    // ── Modal öffnen ──────────────────────────────────────────────

    async function openModal(tripId, isTrailer, sourceTable) {
        _tripId      = tripId;
        _isTrailer   = !!isTrailer;
        _sourceTable = sourceTable || 'trips';
        _boatId      = null;
        _boatName    = null;
        _isGroup         = false;
        _ownTripSelected = true;
        _groupTrips  = [];
        _recentRoutes= [];
        _selectedGroupIds = new Set();
        _routeId     = null;
        _routeLabel  = '';
        _distance    = '';

        const b = body();
        if (b) b.innerHTML = '<div class="kcs-sp-empty"><i class="bi bi-hourglass-split kcs-spin"></i> Laden…</div>';
        const f = footer();
        if (f) f.innerHTML = '';
        KCS.openModal('endTripModal');

        try {
            const [tripData, routeData] = await Promise.all([
                KCS.api('/trips/get.php?id=' + tripId + '&is_trailer=' + (_isTrailer ? '1' : '0')),
                KCS.api('/routes/list.php').catch(() => []),
            ]);

            _allRoutes = Array.isArray(routeData) ? routeData : (routeData.routes || []);

            if (tripData.success && tripData.trip) {
                const trip = tripData.trip;
                _boatId       = trip.boat_id   || null;
                _boatName     = trip.boat_name  || '—';
                _groupTrips   = trip.group_trips   || [];
                _recentRoutes = trip.recent_routes  || [];
                _isGroup      = _groupTrips.length > 0;

                // Vorausfüllen falls Strecke bereits gesetzt war
                if (trip.route_description) _routeLabel = trip.route_description;
                else if (trip.route_custom)  _routeLabel = trip.route_custom;
                if (trip.gps_distance_km)    _distance   = String(trip.gps_distance_km);
            } else {
                _boatName = '—';
            }

        } catch (_) {
            _boatName = '—';
        }

        if (_isGroup) {
            renderStepGroup();
        } else {
            renderStepRoute();
        }
    }

    // ── Events ────────────────────────────────────────────────────

    document.addEventListener('kcs:end-trip', e => {
        const { tripId, isTrailer, sourceTable } = e.detail || {};
        if (!tripId) return;
        openModal(tripId, isTrailer, sourceTable);
    });

    // Fallback: "Meine Fahrt beenden" Hero-Button
    document.addEventListener('click', e => {
        if (e.target.closest('#ctaEndTrip')) {
            KCS.api('/trips/active.php').then(data => {
                const list = Array.isArray(data) ? data : (data.trips || []);
                const first = KCS.memberName?.split(' ')[0];
                const mine  = list.find(t => first && t.crew_names?.includes(first));
                if (mine) openModal(mine.id, mine.is_trailer, mine.source_table);
            }).catch(() => {});
        }
    });

})();
