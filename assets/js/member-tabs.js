/**
 * Fahrtenbuch Beta — member-tabs.js
 * Task 14: Mitglieder-Tabs Inhalte
 */
(function () {
    'use strict';

    const MEDALS = ['🥇', '🥈', '🥉'];

    // ── Hilfsfunktionen ───────────────────────────────────────────

    function pane(id) { return document.getElementById('pane-' + id); }

    function fmt(n, dec) {
        return (parseFloat(n) || 0).toLocaleString('de-DE', { minimumFractionDigits: dec ?? 1, maximumFractionDigits: dec ?? 1 });
    }

    function seasonOptions(seasons, current) {
        if (!seasons?.length) return `<option value="${current}">${current}</option>`;
        return seasons.map(s => `<option value="${s}" ${s === current ? 'selected' : ''}>${s}</option>`).join('');
    }

    // ── Tab: Statistik ────────────────────────────────────────────

    async function loadStatistik(season) {
        const p = pane('statistik');
        if (!p) return;
        p.innerHTML = '<div class="kcs-member-loading"><i class="bi bi-hourglass-split kcs-spin"></i></div>';

        try {
            const [statsData, tripsData] = await Promise.all([
                KCS.api('/statistics/get.php?member_id=' + KCS.memberId),
                KCS.api('/trips/me.php?member_id=' + KCS.memberId + '&season=' + season + '&limit=3')
            ]);

            const cur = statsData.current_season || {};
            const last = statsData.last_season   || {};
            const seasons = tripsData.seasons    || [];

            // distance is formatted like "12,5" — parse it
            const curKm  = parseFloat((cur.distance  || '0').replace(',', '.')) || 0;
            const lastKm = parseFloat((last.distance || '0').replace(',', '.')) || 0;
            const kmDiff = curKm - lastKm;

            // Find own rank in ranking list
            const myRankEntry = (statsData.ranking || []).find(r => r.is_current);
            const myRank = myRankEntry ? ((statsData.ranking || []).indexOf(myRankEntry) + 1) : null;

            p.innerHTML = `
                <!-- Saison-Selector -->
                <div class="kcs-member-section-header">
                    <h3 class="kcs-member-section-title">Statistik</h3>
                    <select class="kcs-input kcs-member-season-sel" id="statSeasonSel" style="width:auto">
                        ${seasonOptions(seasons, season)}
                    </select>
                </div>

                <!-- KPI Grid -->
                <div class="kcs-kpi-grid">
                    <div class="kcs-kpi-card">
                        <div class="kcs-kpi-icon"><i class="bi bi-geo-alt-fill"></i></div>
                        <div class="kcs-kpi-value">${cur.distance || '0'} km</div>
                        <div class="kcs-kpi-label">Kilometer</div>
                        <div class="kcs-kpi-diff ${kmDiff >= 0 ? 'pos' : 'neg'}">${kmDiff >= 0 ? '+' : ''}${fmt(kmDiff)} vs. Vorjahr</div>
                    </div>
                    <div class="kcs-kpi-card">
                        <div class="kcs-kpi-icon"><i class="bi bi-compass"></i></div>
                        <div class="kcs-kpi-value">${cur.trips || 0}</div>
                        <div class="kcs-kpi-label">Fahrten</div>
                    </div>
                    <div class="kcs-kpi-card">
                        <div class="kcs-kpi-icon"><i class="bi bi-calculator"></i></div>
                        <div class="kcs-kpi-value">${cur.trips > 0 ? fmt(curKm / cur.trips) : '—'} km</div>
                        <div class="kcs-kpi-label">ø pro Fahrt</div>
                    </div>
                    <div class="kcs-kpi-card">
                        <div class="kcs-kpi-icon"><i class="bi bi-trophy"></i></div>
                        <div class="kcs-kpi-value">#${myRank || '—'}</div>
                        <div class="kcs-kpi-label">Saison-Rang</div>
                    </div>
                </div>

                <!-- Letzte Fahrten -->
                <h4 class="kcs-member-sub-title">Letzte Fahrten</h4>
                ${renderTripsTable(tripsData.trips || [])}
            `;

            p.querySelector('#statSeasonSel')?.addEventListener('change', e => loadStatistik(e.target.value));
        } catch (e) {
            p.innerHTML = '<div class="kcs-sp-empty">Fehler beim Laden</div>';
        }
    }

    // ── Tab: Fahrten ──────────────────────────────────────────────

    async function loadFahrten(season) {
        const p = pane('fahrten');
        if (!p) return;
        p.innerHTML = '<div class="kcs-member-loading"><i class="bi bi-hourglass-split kcs-spin"></i></div>';

        try {
            const data = await KCS.api('/trips/me.php?member_id=' + KCS.memberId + '&season=' + (season || '') + '&limit=200');
            const seasons = data.seasons || [];
            const curSeason = data.season || season || '';

            p.innerHTML = `
                <div class="kcs-member-section-header">
                    <h3 class="kcs-member-section-title">Fahrten</h3>
                    <select class="kcs-input kcs-member-season-sel" id="fahrtenSeasonSel" style="width:auto">
                        ${seasonOptions(seasons, curSeason)}
                    </select>
                </div>
                ${renderTripsTable(data.trips || [], true)}
            `;

            p.querySelector('#fahrtenSeasonSel')?.addEventListener('change', e => loadFahrten(e.target.value));
        } catch (e) {
            p.innerHTML = '<div class="kcs-sp-empty">Fehler beim Laden</div>';
        }
    }

    function renderTripsTable(trips, full) {
        if (!trips.length) return '<div class="kcs-sp-empty">Keine Fahrten in dieser Saison</div>';
        return `<div class="kcs-member-table-wrap">
            <table class="kcs-member-table">
                <thead><tr>
                    <th>Datum</th>
                    ${full ? '<th>Boot</th>' : ''}
                    <th>Strecke</th>
                    <th>Zeit</th>
                    <th>km</th>
                </tr></thead>
                <tbody>
                    ${trips.map(t => `<tr>
                        <td>${t.start_date}</td>
                        ${full ? `<td>${t.boat_name || '—'}</td>` : ''}
                        <td>${t.route_label || '—'}</td>
                        <td>${t.start_time || ''}${t.end_time ? ' – ' + t.end_time : ''}</td>
                        <td>${t.distance ? fmt(t.distance) : '—'}</td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
    }

    // ── Tab: Errungenschaften ─────────────────────────────────────

    async function loadBadges() {
        const p = pane('errungenschaften');
        if (!p) return;
        p.innerHTML = '<div class="kcs-member-loading"><i class="bi bi-hourglass-split kcs-spin"></i></div>';

        try {
            const data = await KCS.api('/badges/get.php?member_id=' + KCS.memberId);
            p.innerHTML = `
                <div class="kcs-member-section-header">
                    <h3 class="kcs-member-section-title">Errungenschaften</h3>
                    <span class="kcs-badge-count">${data.earned_count || 0} / ${data.total_count || '?'}</span>
                </div>
                <div class="kcs-badge-grid-wrap">${data.badge_grid_html || data.badgeGridHtml || '<div class="kcs-sp-empty">Keine Badge-Daten</div>'}</div>
            `;
        } catch (e) {
            p.innerHTML = '<div class="kcs-sp-empty">Fehler beim Laden</div>';
        }
    }

    // ── Tab: Rangliste ────────────────────────────────────────────

    async function loadRangliste(season) {
        const p = pane('rangliste');
        if (!p) return;
        p.innerHTML = '<div class="kcs-member-loading"><i class="bi bi-hourglass-split kcs-spin"></i></div>';

        try {
            const data = await KCS.api('/statistics/leaderboard.php?season=' + (season || KCS.currentSeason || '') + '&limit=50&member_id=' + KCS.memberId);
            const list = Array.isArray(data) ? data : (data.leaderboard || data.members || []);
            const seasons = data.seasons || [];
            const curSeason = season || KCS.currentSeason || '';

            p.innerHTML = `
                <div class="kcs-member-section-header">
                    <h3 class="kcs-member-section-title">Rangliste</h3>
                    <select class="kcs-input kcs-member-season-sel" id="rankSeasonSel" style="width:auto">
                        ${seasonOptions(seasons, curSeason)}
                    </select>
                </div>
                <div class="kcs-rank-list">
                    ${list.map((m, i) => `
                    <div class="kcs-rank-item ${m.is_me ? 'is-me' : ''}">
                        <div class="kcs-rank-pos">${i < 3 ? MEDALS[i] : '#' + (i+1)}</div>
                        <div class="kcs-rank-name">${m.name || m.first_name + ' ' + m.last_name}</div>
                        <div class="kcs-rank-km">${fmt(m.km)} km</div>
                        <div class="kcs-rank-trips">${m.trips} Fahrten</div>
                    </div>`).join('')}
                    ${!list.length ? '<div class="kcs-sp-empty">Keine Daten</div>' : ''}
                </div>`;

            p.querySelector('#rankSeasonSel')?.addEventListener('change', e => loadRangliste(e.target.value));
        } catch (e) {
            p.innerHTML = '<div class="kcs-sp-empty">Fehler beim Laden</div>';
        }
    }

    // ── Tab: Einstellungen ────────────────────────────────────────

    async function loadEinstellungen() {
        const p = pane('einstellungen');
        if (!p) return;
        p.innerHTML = '<div class="kcs-member-loading"><i class="bi bi-hourglass-split kcs-spin"></i></div>';

        try {
            const data = await KCS.api('/statistics/get.php?member_id=' + KCS.memberId);
            const nameParts = (KCS.memberName || '').split(' ');
            const fn = nameParts[0] || '';
            const ln = nameParts.slice(1).join(' ') || '';

            p.innerHTML = `
                <h3 class="kcs-member-section-title" style="margin-bottom:var(--sp-4)">Einstellungen</h3>
                <div class="kcs-st-section" style="max-width:480px">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-3)">
                        <div class="kcs-et-field">
                            <label class="kcs-et-label">Vorname</label>
                            <input type="text" class="kcs-input" id="setFn" value="${fn}">
                        </div>
                        <div class="kcs-et-field">
                            <label class="kcs-et-label">Nachname</label>
                            <input type="text" class="kcs-input" id="setLn" value="${ln}">
                        </div>
                    </div>
                    <div class="kcs-et-field">
                        <label class="kcs-et-label">E-Mail</label>
                        <input type="email" class="kcs-input" id="setEmail" value="">
                    </div>
                    <div class="kcs-et-field">
                        <label class="kcs-et-label">Telefon</label>
                        <input type="tel" class="kcs-input" id="setPhone" value="">
                    </div>
                    <div id="settingsMsg" class="kcs-st-error" style="display:none"></div>
                    <button class="kcs-btn kcs-btn--green" id="settingsSave">
                        <i class="bi bi-floppy"></i> Speichern
                    </button>
                </div>`;

            p.querySelector('#settingsSave').addEventListener('click', async () => {
                const btn = p.querySelector('#settingsSave');
                const msg = p.querySelector('#settingsMsg');
                btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> …';
                msg.style.display = 'none';

                const form = new FormData();
                form.append('first_name', p.querySelector('#setFn').value.trim());
                form.append('last_name',  p.querySelector('#setLn').value.trim());
                form.append('email',      p.querySelector('#setEmail').value.trim());
                form.append('phone',      p.querySelector('#setPhone').value.trim());

                try {
                    const res  = await fetch(KCS.apiBase + '/members/update.php', { method: 'POST', body: form });
                    const resp = await res.json();
                    if (!resp.success) throw new Error(resp.message || 'Fehler');
                    msg.style.display = '';
                    msg.style.background = 'color-mix(in srgb, var(--green) 10%, transparent)';
                    msg.style.borderColor = 'var(--green)';
                    msg.style.color = 'var(--green)';
                    msg.textContent = 'Gespeichert ✓';
                } catch (e) {
                    msg.style.display = ''; msg.style.background = ''; msg.style.borderColor = ''; msg.style.color = '';
                    msg.textContent = e.message;
                } finally {
                    btn.disabled = false; btn.innerHTML = '<i class="bi bi-floppy"></i> Speichern';
                }
            });
        } catch (e) {
            p.innerHTML = '<div class="kcs-sp-empty">Fehler beim Laden</div>';
        }
    }

    // ── Tab: Arbeitseinsatz ───────────────────────────────────────

    let _aeEntries = [];
    let _aeTotal = 0, _aeConfirmed = 0, _aeTarget = 40;

    async function loadArbeitseinsatz(year) {
        const p = pane('arbeitseinsatz');
        if (!p) return;
        p.innerHTML = '<div class="kcs-member-loading"><i class="bi bi-hourglass-split kcs-spin"></i></div>';

        const yr = year || new Date().getFullYear();
        try {
            const data = await KCS.api('/work-duty/me.php?member_id=' + KCS.memberId + '&year=' + yr);
            _aeEntries   = data.entries   || [];
            _aeTotal     = data.total_hours     || 0;
            _aeConfirmed = data.confirmed_hours || 0;
            _aeTarget    = data.target_hours    || 40;

            const pct = Math.min(100, Math.round((_aeConfirmed / _aeTarget) * 100));
            const barColor = pct >= 100 ? 'var(--green)' : pct >= 50 ? 'var(--amber)' : 'var(--red)';

            p.innerHTML = `
                <div class="kcs-member-section-header">
                    <h3 class="kcs-member-section-title">Arbeitseinsatz ${yr}</h3>
                    <div style="display:flex;gap:var(--sp-2);align-items:center">
                        <select class="kcs-input" id="aeYearSel" style="width:auto">
                            ${[yr-1, yr, yr+1].map(y => `<option ${y===parseInt(yr)?'selected':''}>${y}</option>`).join('')}
                        </select>
                        <button class="kcs-btn kcs-btn--green" id="aeBtnAdd" style="white-space:nowrap">
                            <i class="bi bi-plus-circle"></i> Eintragen
                        </button>
                    </div>
                </div>

                <!-- KPI-Karten -->
                <div class="kcs-kpi-grid" style="grid-template-columns:repeat(3,1fr)">
                    <div class="kcs-kpi-card">
                        <div class="kcs-kpi-icon"><i class="bi bi-bullseye"></i></div>
                        <div class="kcs-kpi-value">${_aeTarget} h</div>
                        <div class="kcs-kpi-label">Soll</div>
                    </div>
                    <div class="kcs-kpi-card">
                        <div class="kcs-kpi-icon"><i class="bi bi-check2-circle"></i></div>
                        <div class="kcs-kpi-value">${_aeConfirmed} h</div>
                        <div class="kcs-kpi-label">Bestätigt</div>
                    </div>
                    <div class="kcs-kpi-card">
                        <div class="kcs-kpi-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div class="kcs-kpi-value" style="color:${barColor}">${pct >= 100 ? '✓ Ziel erreicht' : (_aeTarget - _aeConfirmed) + ' h offen'}</div>
                        <div class="kcs-kpi-label">Fortschritt</div>
                    </div>
                </div>

                <!-- Fortschrittsbalken -->
                <div class="kcs-ae-bar-wrap">
                    <div class="kcs-ae-bar">
                        <div class="kcs-ae-bar__fill" style="width:${pct}%;background:${barColor}"></div>
                    </div>
                    <span class="kcs-ae-bar__pct">${pct}%</span>
                </div>

                <!-- Formular (toggle) -->
                <div id="aeForm" style="display:none;margin-bottom:var(--sp-4)">
                    <div class="kcs-st-section" style="max-width:480px;border:1px solid var(--border);border-radius:var(--r-md);padding:var(--sp-4)">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-3)">
                            <div class="kcs-et-field">
                                <label class="kcs-et-label">Datum</label>
                                <input type="date" class="kcs-input" id="aeDate" value="${new Date().toISOString().slice(0,10)}">
                            </div>
                            <div class="kcs-et-field">
                                <label class="kcs-et-label">Stunden</label>
                                <div class="kcs-et-km-wrap">
                                    <button class="kcs-et-km-btn" id="aeHMinus">−</button>
                                    <input type="number" class="kcs-input kcs-et-km-input" id="aeHours" step="0.5" min="0.5" value="4">
                                    <button class="kcs-et-km-btn" id="aeHPlus">+</button>
                                </div>
                            </div>
                        </div>
                        <div class="kcs-et-field">
                            <label class="kcs-et-label">Tätigkeit *</label>
                            <textarea class="kcs-input kcs-et-textarea" id="aeDesc" placeholder="Was hast du gemacht?"></textarea>
                        </div>
                        <div id="aeFormErr" class="kcs-st-error" style="display:none"></div>
                        <div style="display:flex;gap:var(--sp-2)">
                            <button class="kcs-btn kcs-btn--ghost" id="aeBtnCancel">Abbrechen</button>
                            <button class="kcs-btn kcs-btn--green" id="aeBtnSave"><i class="bi bi-floppy"></i> Speichern</button>
                        </div>
                    </div>
                </div>

                <!-- Liste -->
                <h4 class="kcs-member-sub-title">Einträge</h4>
                <div id="aeList" class="kcs-ae-list">${renderAeList(_aeEntries)}</div>`;

            // Events
            p.querySelector('#aeYearSel').addEventListener('change', e => loadArbeitseinsatz(e.target.value));
            p.querySelector('#aeBtnAdd').addEventListener('click', () => { p.querySelector('#aeForm').style.display = ''; });
            p.querySelector('#aeBtnCancel').addEventListener('click', () => { p.querySelector('#aeForm').style.display = 'none'; });

            const hoursIn = p.querySelector('#aeHours');
            p.querySelector('#aeHMinus').addEventListener('click', () => { hoursIn.value = Math.max(0.5, parseFloat(hoursIn.value||0) - 0.5); });
            p.querySelector('#aeHPlus').addEventListener('click',  () => { hoursIn.value = parseFloat(hoursIn.value||0) + 0.5; });

            p.querySelector('#aeBtnSave').addEventListener('click', async () => {
                const btn = p.querySelector('#aeBtnSave');
                const err = p.querySelector('#aeFormErr');
                const date = p.querySelector('#aeDate').value;
                const hrs  = parseFloat(hoursIn.value);
                const desc = p.querySelector('#aeDesc').value.trim();
                if (!date || !hrs || !desc) { err.style.display=''; err.textContent='Alle Felder ausfüllen.'; return; }

                btn.disabled=true; btn.innerHTML='<i class="bi bi-hourglass-split"></i> …';
                err.style.display='none';
                const form = new FormData();
                form.append('work_date', date); form.append('hours', hrs); form.append('description', desc);
                try {
                    const res = await fetch(KCS.apiBase + '/work-duty/create.php', { method:'POST', body:form });
                    const d   = await res.json();
                    if (!d.success) throw new Error(d.message);
                    // Optimistic update
                    _aeEntries.unshift({ id: d.id, work_date: date, hours: hrs, description: desc, is_confirmed: 0 });
                    _aeTotal += hrs;
                    p.querySelector('#aeList').innerHTML = renderAeList(_aeEntries);
                    p.querySelector('#aeForm').style.display = 'none';
                    p.querySelector('#aeDesc').value = '';
                } catch (e) { err.style.display=''; err.textContent=e.message; }
                finally { btn.disabled=false; btn.innerHTML='<i class="bi bi-floppy"></i> Speichern'; }
            });

        } catch (e) {
            p.innerHTML = '<div class="kcs-sp-empty">Fehler beim Laden</div>';
        }
    }

    function renderAeList(entries) {
        if (!entries.length) return '<div class="kcs-sp-empty">Noch keine Einträge</div>';
        return entries.map(e => `
            <div class="kcs-ae-item">
                <div class="kcs-ae-item__date">${e.work_date}</div>
                <div class="kcs-ae-item__desc">${e.description}</div>
                <div class="kcs-ae-item__hrs ${e.is_confirmed ? 'confirmed' : 'pending'}">${e.hours} h</div>
            </div>`).join('');
    }

    // ── Tab: Boote ────────────────────────────────────────────────

    async function loadBoote() {
        const p = pane('boote');
        if (!p) return;
        p.innerHTML = '<div class="kcs-member-loading"><i class="bi bi-hourglass-split kcs-spin"></i></div>';

        try {
            const data = await KCS.api('/boats/all.php');
            const boats = (data.boats || []).filter(b =>
                !b.is_club_boat && (b.type_full || '').toLowerCase() !== 'anhänger'
            );
            // Filter to current member's boats
            const myBoats = boats.filter(b => {
                const owner = (b.name || '').match(/\((.+)\)/)?.[1] || '';
                const nameParts = (KCS.memberName || '').split(' ');
                return nameParts.some(n => n.length > 2 && owner.toLowerCase().includes(n.toLowerCase()));
            });
            const show = myBoats.length ? myBoats : boats;

            p.innerHTML = `
                <h3 class="kcs-member-section-title" style="margin-bottom:16px">Meine Boote</h3>
                ${!show.length ? '<div class="kcs-sp-empty">Keine privaten Boote gefunden</div>' :
                    show.map(b => `
                    <div class="km-boat-card">
                        <i class="bi bi-life-preserver" style="font-size:24px;color:var(--muted)"></i>
                        <div>
                            <div class="km-boat-name">${b.name}</div>
                            <div class="km-boat-meta">${b.type_full || b.type} · ${b.seats} Sitzplatz${b.seats > 1 ? 'e' : ''}</div>
                        </div>
                        <span class="kcs-bd-status-dot kcs-bd-status-dot--${b.status}" style="margin-left:auto"></span>
                        <span style="font-size:12px;color:var(--muted)">${
                            {available:'Verfügbar',onWater:'Auf dem Wasser',reserved:'Reserviert',damaged:'Schaden'}[b.status] || b.status
                        }</span>
                    </div>`).join('')
                }`;
        } catch (e) {
            p.innerHTML = '<div class="kcs-sp-empty">Fehler beim Laden</div>';
        }
    }

    // ── Tab-Dispatcher ────────────────────────────────────────────

    document.addEventListener('kcs:tab-change', e => {
        const tab = e.detail?.tab;
        switch (tab) {
            case 'statistik':        loadStatistik(KCS.currentSeason); break;
            case 'fahrten':          loadFahrten(KCS.currentSeason); break;
            case 'errungenschaften': loadBadges(); break;
            case 'rangliste':        loadRangliste(KCS.currentSeason); break;
            case 'arbeitseinsatz':   loadArbeitseinsatz(null); break;
            case 'boote':            loadBoote(); break;
            case 'einstellungen':    loadEinstellungen(); break;
        }
    });

})();
