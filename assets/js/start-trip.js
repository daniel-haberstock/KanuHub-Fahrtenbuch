/**
 * Fahrtenbuch Beta — start-trip.js
 * 4-Schritt-Wizard: Boot → Mannschaft → Strecke → Bestätigen
 * Design: Fahrtenbuch v2.html — Logik: Original fahrtenbuch_bootshaus
 */
(function () {
    'use strict';

    const STEPS = ['Boot', 'Mannschaft', 'Strecke', 'Bestätigen'];

    let _step        = 1;
    let _boatId      = null;
    let _boatName    = null;
    let _boatSeats   = 1;
    let _ownerFilter  = null;  // null | '__club' | '__private'
    let _typeFilter   = null;  // null | seat-count string
    let _boatNoteStart  = null;
    let _defaultCrew    = [];  // [{name, memberId}] — Standardbesatzung des Boots
    let _bSearch     = '';
    let _crew      = [];   // [{name, memberId}]
    let _routeId   = null;
    let _routeLabel= '';
    let _startDate = '';
    let _startTime = '';
    let _isBacklog = false;
    let _endDate   = '';
    let _endTime   = '';
    let _distance  = '';
    let _allMembers= [];
    let _allRoutes = [];
    // Event/Gruppenfahrt
    let _tripGroupId         = '';
    let _tripGroupSourceId   = '';
    let _tripGroupSourceType = '';
    let _tripGroupName       = '';
    // Reservierung
    let _reservationId = null;

    // ── DOM helpers ───────────────────────────────────────────────

    const body   = () => document.getElementById('startTripBody');
    const footer = () => document.getElementById('startTripFooter');

    // ── Step indicator ────────────────────────────────────────────

    function stepBar() {
        return `<div class="kcs-wiz-bar">
            ${STEPS.map((label, i) => {
                const n = i + 1;
                const cls = n < _step ? 'done' : n === _step ? 'active' : '';
                return `<div class="kcs-wiz-step ${cls}">
                    <span class="kcs-wiz-dot">${n < _step ? '<i class="bi bi-check-lg"></i>' : n}</span>
                    <span class="kcs-wiz-label">${label}</span>
                </div>${i < STEPS.length - 1 ? '<div class="kcs-wiz-line"></div>' : ''}`;
            }).join('')}
        </div>`;
    }

    // ── Footer ─────────────────────────────────────────────────────

    function renderFooter(canNext, nextLabel) {
        const f = footer();
        if (!f) return;
        const backLabel = _step === 1 ? 'Abbrechen' : '← Zurück';
        f.innerHTML = `
            <button class="kcs-btn kcs-btn--ghost" id="stBtnBack">${backLabel}</button>
            <div class="kcs-wiz-footer-right">
                ${_step === 1 ? `<span class="kcs-wiz-hint" id="stBoatHint">${_boatId ? escHtml(_boatName) + ' ausgewählt' : 'Wähle ein freies Boot'}</span>` : ''}
                <button class="kcs-btn ${_step === 4 ? 'kcs-btn--green' : 'kcs-btn--primary'}" id="stBtnNext" ${canNext ? '' : 'disabled'}>
                    ${nextLabel || 'Weiter →'}
                </button>
            </div>`;
        document.getElementById('stBtnBack').addEventListener('click', () => {
            if (_step === 1) KCS.closeModal('startTripModal');
            else { _step--; render(); }
        });
        document.getElementById('stBtnNext').addEventListener('click', handleNext);
    }

    // ── Render dispatcher ─────────────────────────────────────────

    function setDialogSize(xl) {
        const d = document.getElementById('startTripDialog');
        if (!d) return;
        d.classList.toggle('modal-xl', xl);
        d.classList.toggle('modal-lg', !xl);
    }

    function render() {
        switch (_step) {
            case 1: setDialogSize(true);  renderBoot();    break;
            case 2: setDialogSize(false); renderCrew();    break;
            case 3: setDialogSize(false); renderStrecke(); break;
            case 4: setDialogSize(false); renderConfirm(); break;
        }
    }

    function handleNext() {
        if (_step === 4) { submitTrip(); return; }
        _step++;
        render();
    }

    // ── Step 1: Boot ──────────────────────────────────────────────

    function renderBoot() {
        const b = body();
        if (!b) return;

        const allBoats = window._kcsBoats || [];
        const pool = _isBacklog
            ? allBoats.filter(b => (b.type_full || '').toLowerCase() !== 'anhänger')
            : allBoats.filter(b => b.status === 'available');

        // Owner quick-filter counts (over full pool)
        const clubCount    = pool.filter(b => b.is_club_boat).length;
        const privateCount = pool.filter(b => !b.is_club_boat).length;

        // Owner-Filter anwenden, dann Sitzplätze aus diesem Subset ableiten
        const ownerPool = pool.filter(b => {
            if (_ownerFilter === '__club')    return b.is_club_boat;
            if (_ownerFilter === '__private') return !b.is_club_boat;
            return true;
        });

        const seatCounts = {};
        ownerPool.forEach(b => {
            const s = String(b.seats || 1);
            seatCounts[s] = (seatCounts[s] || 0) + 1;
        });
        const seatKeys = Object.keys(seatCounts).sort((a, b) => parseInt(a) - parseInt(b));

        // Wenn aktueller Filter nicht mehr im Subset → zurücksetzen
        if (_typeFilter && !seatCounts[_typeFilter]) _typeFilter = null;

        const filtered = ownerPool
            .filter(b => !_typeFilter || String(b.seats || 1) === _typeFilter)
            .filter(b => !_bSearch || parseName(b.name).display.toLowerCase().includes(_bSearch.toLowerCase()))
            .sort((a, b) => parseName(a.name).display.localeCompare(parseName(b.name).display, 'de'));

        const ownerBtn = (key, label, count) => `
            <button class="kcs-wiz-type-btn${_ownerFilter === key ? ' active' : ''}" data-owner="${escHtml(key)}">
                <span>${escHtml(label)}</span>
                <span class="kcs-wiz-type-count">${count}</span>
            </button>`;
        const seatBtn = (s, count) => `
            <button class="kcs-wiz-type-btn${_typeFilter === s ? ' active' : ''}" data-type="${escHtml(s)}">
                <span>${s === '1' ? '1 Platz' : s + ' Plätze'}</span>
                <span class="kcs-wiz-type-count">${count}</span>
            </button>`;

        b.innerHTML = stepBar() + `
            <div class="kcs-wiz-boot">
                <div class="kcs-wiz-type-list">
                    ${clubCount    > 0 ? ownerBtn('__club',    'Vereinsboot', clubCount)    : ''}
                    ${privateCount > 0 ? ownerBtn('__private', 'Privatboot',  privateCount) : ''}
                    ${(clubCount > 0 || privateCount > 0) && seatKeys.length > 0
                        ? '<div class="kcs-wiz-type-sep"></div>' : ''}
                    ${seatKeys.map(s => seatBtn(s, seatCounts[s])).join('')}
                </div>
                <div class="kcs-wiz-boat-panel">
                    <input class="kcs-input kcs-wiz-boat-search" id="stBoatSearch"
                           placeholder="${filtered.length} Boote – nach Name suchen…"
                           value="${escHtml(_bSearch)}" autocomplete="off">
                    <div class="kcs-wiz-boat-list" id="stBoatList">
                        ${filtered.length
                            ? filtered.map(bt => boatRowHtml(bt)).join('')
                            : '<div class="kcs-wiz-empty">Keine Boote verfügbar</div>'}
                    </div>
                </div>
            </div>`;

        // Owner + type filter clicks
        b.querySelectorAll('.kcs-wiz-type-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset.owner) {
                    // Toggle owner filter
                    _ownerFilter = _ownerFilter === btn.dataset.owner ? null : btn.dataset.owner;
                } else {
                    // Toggle type filter
                    _typeFilter = _typeFilter === btn.dataset.type ? null : btn.dataset.type;
                }
                _bSearch = '';
                renderBoot();
            });
        });

        // Boat search
        const searchInput = b.querySelector('#stBoatSearch');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                _bSearch = searchInput.value;
                // Refresh grid only
                const grid = b.querySelector('#stBoatList');
                if (!grid) return;
                const pool2 = (_isBacklog ? allBoats.filter(x => (x.type_full||'').toLowerCase() !== 'anhänger') : allBoats.filter(x => x.status === 'available'))
                    .filter(x => {
                        if (_ownerFilter === '__club')    return x.is_club_boat;
                        if (_ownerFilter === '__private') return !x.is_club_boat;
                        return true;
                    })
                    .filter(x => !_typeFilter || String(x.seats || 1) === _typeFilter)
                    .filter(x => !_bSearch || parseName(x.name).display.toLowerCase().includes(_bSearch.toLowerCase()))
                    .sort((a, b) => parseName(a.name).display.localeCompare(parseName(b.name).display, 'de'));
                if (!pool2.length) { grid.innerHTML = '<div class="kcs-wiz-empty">Keine Boote gefunden</div>'; return; }
                grid.innerHTML = pool2.map(bt => boatRowHtml(bt)).join('');
                bindBoatCards(b);
                renderFooter(!!_boatId);
            });
        }

        bindBoatCards(b);
        renderFooter(!!_boatId);
    }

    // ── Steckbrief im Panel ───────────────────────────────────────

    const STAB_LABEL = { 1:'Sehr kippelig', 2:'Kippelig', 3:'Mittel', 4:'Stabil', 5:'Sehr stabil' };
    const STAB_COLOR = { 1:'var(--red)', 2:'var(--red)', 3:'var(--amber)', 4:'var(--green)', 5:'var(--green)' };

    function wizStabBar(val) {
        const v = Math.min(5, Math.max(1, parseInt(val, 10) || 3));
        return `<div class="kcs-stab-bar">
            ${[1,2,3,4,5].map(i =>
                `<div class="kcs-stab-seg${i <= v ? ' kcs-stab-seg--on' : ''}"
                      style="${i <= v ? 'background:' + STAB_COLOR[v] : ''}"></div>`
            ).join('')}
            <span class="kcs-stab-label">${STAB_LABEL[v]}</span>
        </div>`;
    }

    function wizSpecGrid(specs) {
        const vis = specs.filter(s => s.value !== null && s.value !== undefined && s.value !== '');
        if (!vis.length) return '';
        return `<div class="kcs-spec-grid">${vis.map(s => `
            <div class="kcs-spec-item">
                <div class="kcs-spec-label">${s.label}</div>
                <div class="kcs-spec-value">${escHtml(String(s.value))}</div>
            </div>`).join('')}</div>`;
    }

    function renderPanelDetail(panel, boat) {
        const d = boat.boat_details || {};
        const { display, owner } = parseName(boat.name);
        const icon = boat.boat_icon_url || boatTypeIcon(boat.type_full);

        const HTML_KEYS = ['sitzkomfort', 'eigenheiten', 'schwaechen'];
        function empty(v) { return !v || String(v).replace(/<[^>]*>/g, '').trim() === ''; }
        function fv(k) {
            const v = k === '_seats' ? boat.seats : d[k];
            return HTML_KEYS.includes(k) ? String(v || '') : escHtml(String(v || ''));
        }
        function hasVal(k) { return !empty(k === '_seats' ? boat.seats : d[k]); }

        // Paare: je 2 Felder in einer Zeile (3-Spalten-Grid)
        const ROWS = [
            ['_seats',              'Sitzplätze',       'modell',              'Modell'           ],
            ['laenge',              'Länge',             'breite',              'Breite'           ],
            ['gewicht',             'Gewicht',           'material',            'Material'         ],
            ['steuerung',           'Steuerung',         'stauraum',            'Stauraum'         ],
            ['empfohlenes_gewicht', 'Empf. Gewicht',     'koerpergroesse',      'Körpergröße'     ],
            ['erfahrungslevel',     'Erfahrungslevel',   'stabilitaet',         'Stabilität'      ],
            ['geschwindigkeit',     'Geschwindigkeit',   'wendigkeit',          'Wendigkeit'       ],
            ['wind_wellen',         'Wind/Wellen',       'paddler_gewicht',     'Paddler-Gewicht'  ],
            ['sitzkomfort',         'Sitzkomfort'],
            ['eigenheiten',         'Eigenheiten'],
            ['schwaechen',          'Schwächen'],
            ['hinweis',             'Hinweis'],
            ['pflegehinweis',       'Pflegehinweis'],
        ];

        let specHtml = '';
        ROWS.forEach(([k1, l1, k2, l2]) => {
            const h1 = hasVal(k1), h2 = k2 && hasVal(k2);
            if (!h1 && !h2) return;
            if (h1) specHtml += `<div class="kcs-spec-item${!k2 || !h2 ? ' kcs-spec-item--wide' : ''}">
                <div class="kcs-spec-label">${l1}</div>
                <div class="kcs-spec-value">${fv(k1)}</div></div>`;
            if (h2) specHtml += `<div class="kcs-spec-item${!h1 ? ' kcs-spec-item--wide' : ''}">
                <div class="kcs-spec-label">${l2}</div>
                <div class="kcs-spec-value">${fv(k2)}</div></div>`;
        });

        panel.innerHTML = `
            <div class="kcs-wiz-bd">
                <div class="kcs-wiz-bd__head">
                    <img class="kcs-wiz-bd__icon" src="${escHtml(icon)}" alt="">
                    <div>
                        <div class="kcs-wiz-bd__name">${escHtml(display)}</div>
                        ${owner ? `<div class="kcs-wiz-bd__owner">${escHtml(owner)}</div>` : ''}
                        <div class="kcs-wiz-bd__type">${escHtml(boat.type_full || '')}</div>
                    </div>
                </div>
                ${specHtml ? `<div class="kcs-spec-grid">${specHtml}</div>` : ''}
                ${boat.note_start && !empty(boat.note_start) ? `
                <div class="kcs-bd-notes kcs-bd-notes--green">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>
                        <div class="kcs-bd-notes-title">Starthinweis</div>
                        <div class="kcs-bd-notes-text">${boat.note_start}</div>
                    </div>
                </div>` : ''}
                <div class="kcs-wiz-bd__back">
                    <button class="kcs-btn kcs-btn--ghost kcs-btn--sm" id="stBdBack">
                        ← Zurück zur Übersicht
                    </button>
                </div>
            </div>`;

        panel.querySelector('#stBdBack').addEventListener('click', () => renderBoot());
    }

    function boatRowHtml(bt) {
        const { display, owner } = parseName(bt.name);
        const icon  = bt.boat_icon_url || boatTypeIcon(bt.type_full);
        const seats = bt.seats || 1;
        const sel   = _boatId === bt.id ? ' selected' : '';
        return `<div class="kcs-boat-row${sel}" data-id="${bt.id}"
                     data-name="${escHtml(display)}" data-seats="${seats}"
                     data-note="${escHtml(bt.note_start || '')}">
            <div class="kcs-boat-row__info">
                <div class="kcs-boat-row__name">${escHtml(display)}</div>
                ${owner ? `<div class="kcs-boat-row__owner">${escHtml(owner)}</div>` : ''}
                <div class="kcs-boat-row__type"><i class="bi bi-person-fill"></i> ${seats} Platz${seats !== 1 ? 'ä' : ''}tze</div>
            </div>
            <img class="kcs-boat-row__typeicon" src="${escHtml(icon)}" alt="">
        </div>`;
    }

    function bindBoatCards(b) {
        b.querySelectorAll('.kcs-boat-row[data-id]').forEach(card => {
            card.addEventListener('click', () => {
                _boatId        = parseInt(card.dataset.id, 10);
                _boatName      = card.dataset.name;
                _boatSeats     = parseInt(card.dataset.seats, 10) || 1;
                _boatNoteStart = card.dataset.note?.trim() || null;
                const boatObj = (window._kcsBoats || []).find(x => x.id === _boatId);
                _defaultCrew = [];
                if (boatObj?.default_crew_1_name) _defaultCrew.push({ name: boatObj.default_crew_1_name, memberId: boatObj.default_crew_1 });
                if (boatObj?.default_crew_2_name) _defaultCrew.push({ name: boatObj.default_crew_2_name, memberId: boatObj.default_crew_2 });
                if (!boatObj?.is_club_boat) {
                    _step = 2; render(); return;
                }
                setDialogSize(true);
                const panel = b.querySelector('.kcs-wiz-boat-panel');
                if (panel && boatObj) renderPanelDetail(panel, boatObj);
                const hint = document.getElementById('stBoatHint');
                if (hint) hint.textContent = _boatName + ' ausgewählt';
                renderFooter(true);
            });
        });
    }

    // ── Step 2: Mannschaft ─────────────────────────────────────────

    function renderCrew() {
        const b = body();
        if (!b) return;

        // Standard-Crew vorbelegen – nur wenn _crew noch vollständig leer ist (nicht initialisiert)
        if (_crew.length === 0) {
            _crew = _defaultCrew.length ? [..._defaultCrew] : [];
        }
        // Mindestens so viele Slots wie Sitzplätze
        while (_crew.length < _boatSeats) _crew.push({ name: '', memberId: null });

        b.innerHTML = stepBar() + `
            <div class="kcs-wiz-section">
                ${_tripGroupName ? `
                <div class="kcs-wiz-event-banner">
                    <i class="bi bi-people-fill"></i>
                    <span><strong>Gemeinsame Ausfahrt:</strong> ${escHtml(_tripGroupName)}</span>
                </div>` : ''}
                <div class="kcs-wiz-boat-banner">
                    <i class="bi bi-water"></i>
                    <span><strong>${escHtml(_boatName)}</strong> · ${_boatSeats} Pl.</span>
                </div>
                <div class="kcs-wiz-crew-label">
                    Crew <span class="kcs-wiz-crew-required">(Fahrer 1 erforderlich)</span>
                </div>
                <div id="stCrewList"></div>
                <button type="button" class="kcs-wiz-add-crew-btn" id="stAddCrew">
                    <i class="bi bi-plus-circle"></i> Weitere Person hinzufügen
                </button>
            </div>`;

        renderCrewList(b);

        b.querySelector('#stAddCrew').addEventListener('click', () => {
            _crew.push({ name: '', memberId: null });
            renderCrew();
        });

        renderFooter(!!_crew[0]?.name?.trim());
    }

    function renderCrewList(b) {
        const list = b.querySelector('#stCrewList');
        if (!list) return;
        list.innerHTML = _crew.map((c, i) => {
            const label = i === 0 ? 'Fahrer 1' : 'Fahrer ' + (i + 1);
            const isRequired = i === 0;
            return `<div class="kcs-wiz-crew-row" data-idx="${i}">
                <div class="kcs-wiz-crew-field">
                    <label class="kcs-wiz-crew-lbl${isRequired ? ' kcs-wiz-required' : ''}">${label}</label>
                    <div class="kcs-wiz-ac-wrap">
                        <input type="text" class="kcs-input kcs-wiz-crew-input"
                               value="${escHtml(c.name)}"
                               placeholder="Mitglied suchen oder Namen eingeben"
                               ${isRequired ? 'required' : ''}
                               autocomplete="off" data-idx="${i}">
                        <div class="kcs-wiz-ac-dropdown" id="stCrewDrop${i}"></div>
                    </div>
                    <div class="kcs-wiz-crew-recent" id="stCrewRecent${i}" style="display:none"></div>
                </div>
                ${i > 0 ? `<button type="button" class="kcs-btn kcs-btn--ghost kcs-btn--sm kcs-wiz-crew-del" data-idx="${i}">
                    <i class="bi bi-x-lg"></i>
                </button>` : ''}
            </div>`;
        }).join('');

        // Bind crew inputs
        list.querySelectorAll('.kcs-wiz-crew-input').forEach(input => {
            const idx = parseInt(input.dataset.idx, 10);
            const drop = list.querySelector(`#stCrewDrop${idx}`);

            input.addEventListener('focus', () => showMemberAc(input, drop, idx));
            input.addEventListener('input', () => {
                _crew[idx] = { name: input.value, memberId: null };
                showMemberAc(input, drop, idx);
                if (idx === 0) renderFooter(!!input.value.trim());
            });

            document.addEventListener('click', e => {
                if (!input.closest('.kcs-wiz-ac-wrap').contains(e.target)) {
                    drop.classList.remove('show');
                }
            });
        });

        // Remove crew member
        list.querySelectorAll('.kcs-wiz-crew-del').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.dataset.idx, 10);
                _crew.splice(idx, 1);
                renderCrew();
            });
        });
    }

    function showMemberAc(input, drop, idx) {
        const q = input.value.toLowerCase().trim();
        const already = _crew.map((c, i) => i !== idx ? c.name.toLowerCase() : '').filter(Boolean);
        const matches = _allMembers.filter(m => {
            const full = (m.first_name + ' ' + m.last_name).toLowerCase();
            return (!q || full.includes(q)) && !already.includes(full);
        }).slice(0, 10);

        if (!matches.length) { drop.classList.remove('show'); return; }
        drop.innerHTML = matches.map(m =>
            `<div class="kcs-wiz-ac-item" data-id="${m.id}" data-name="${escHtml(m.first_name + ' ' + m.last_name)}">
                ${highlightText(m.first_name + ' ' + m.last_name, q)}
            </div>`
        ).join('');
        drop.classList.add('show');

        drop.querySelectorAll('.kcs-wiz-ac-item').forEach(item => {
            item.addEventListener('mousedown', e => {
                e.preventDefault();
                const name     = item.dataset.name;
                const memberId = parseInt(item.dataset.id, 10);
                _crew[idx] = { name, memberId };
                input.value = name;
                drop.classList.remove('show');
                if (idx === 0) renderFooter(true);
                if (memberId) loadRecentTrips(memberId, idx);
            });
        });
    }

    async function loadRecentTrips(memberId, crewIdx) {
        const recentEl = document.getElementById('stCrewRecent' + crewIdx);
        if (!recentEl) return;
        try {
            const data = await KCS.api('/members/recent-trips.php?member_id=' + memberId + '&limit=5');
            const trips = Array.isArray(data) ? data : (data.trips || []);
            if (!trips.length) { recentEl.style.display = 'none'; return; }
            recentEl.innerHTML = `<div class="kcs-wiz-recent-label">Letzte Fahrten:</div>` +
                trips.map(t => `<span class="kcs-wiz-recent-tag">
                    ${escHtml(t.route_label || t.route_custom || '—')}${t.distance ? ' · ' + t.distance + ' km' : ''}
                </span>`).join('');
            recentEl.style.display = '';
        } catch (_) {
            recentEl.style.display = 'none';
        }
    }

    // ── Step 3: Strecke ───────────────────────────────────────────

    function renderStrecke() {
        const b = body();
        if (!b) return;

        const now = new Date();
        const pad = n => String(n).padStart(2, '0');
        if (!_startDate) _startDate = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate());
        if (!_startTime) _startTime = pad(now.getHours()) + ':' + pad(now.getMinutes());
        if (_isBacklog && !_endDate) _endDate = _startDate;

        const routeItems = _allRoutes.map(r => ({
            id: r.id,
            text: r.description || ((r.start_point || '') + (r.end_point ? ' – ' + r.end_point : '')),
            dist: r.distance || ''
        }));

        b.innerHTML = stepBar() + `
            <div class="kcs-wiz-section">
                <div class="kcs-wiz-row">
                    <div class="kcs-wiz-field">
                        <label class="kcs-wiz-lbl kcs-wiz-required">Startdatum</label>
                        <input type="date" class="kcs-input" id="stStartDate" value="${_startDate}" required>
                    </div>
                    <div class="kcs-wiz-field">
                        <label class="kcs-wiz-lbl kcs-wiz-required">Startzeit</label>
                        <input type="time" class="kcs-input" id="stStartTime" value="${_startTime}" required>
                    </div>
                </div>

                ${_isBacklog ? `
                <div class="kcs-wiz-row">
                    <div class="kcs-wiz-field">
                        <label class="kcs-wiz-lbl">Rückkehr-Datum</label>
                        <input type="date" class="kcs-input" id="stEndDate" value="${_endDate}">
                    </div>
                    <div class="kcs-wiz-field">
                        <label class="kcs-wiz-lbl">Rückkehr-Uhrzeit</label>
                        <input type="time" class="kcs-input" id="stEndTime" value="${_endTime}">
                    </div>
                </div>
                <div class="kcs-wiz-field kcs-wiz-field--narrow">
                    <label class="kcs-wiz-lbl">Entfernung (km)</label>
                    <input type="number" class="kcs-input" id="stDist" step="0.1" min="0" value="${_distance}" placeholder="z.B. 12.5">
                </div>` : ''}

                <div class="kcs-wiz-field">
                    <label class="kcs-wiz-lbl">Strecke <span class="kcs-wiz-optional">(optional)</span></label>
                    <div class="kcs-wiz-ac-wrap">
                        <input type="text" class="kcs-input kcs-wiz-route-input" id="stRouteInput"
                               value="${escHtml(_routeLabel)}"
                               placeholder="Freie Eingabe oder aus Liste wählen"
                               autocomplete="off">
                        <div class="kcs-wiz-ac-dropdown" id="stRouteDrop"></div>
                    </div>
                </div>
            </div>`;

        const checkDt = () => renderFooter(!!_startDate && !!_startTime, 'Weiter →');

        // Date/time binding
        b.querySelector('#stStartDate').addEventListener('change', e => { _startDate = e.target.value; checkDt(); });
        b.querySelector('#stStartTime').addEventListener('change', e => { _startTime = e.target.value; checkDt(); });
        if (_isBacklog) {
            b.querySelector('#stEndDate')?.addEventListener('change', e => _endDate = e.target.value);
            b.querySelector('#stEndTime')?.addEventListener('change', e => _endTime = e.target.value);
            b.querySelector('#stDist')?.addEventListener('change', e => _distance = e.target.value);
        }

        // Route autocomplete
        const routeInput = b.querySelector('#stRouteInput');
        const routeDrop  = b.querySelector('#stRouteDrop');
        if (routeInput && routeDrop) {
            const showRoutes = (q) => {
                const filtered = routeItems.filter(r =>
                    !q || r.text.toLowerCase().includes(q.toLowerCase())
                ).slice(0, 15);
                if (!filtered.length) { routeDrop.classList.remove('show'); return; }
                routeDrop.innerHTML = filtered.map(r =>
                    `<div class="kcs-wiz-ac-item" data-id="${r.id}" data-label="${escHtml(r.text)}">
                        ${highlightText(r.text, q)}${r.dist ? ` <span class="kcs-wiz-ac-meta">${r.dist} km</span>` : ''}
                    </div>`
                ).join('');
                routeDrop.classList.add('show');
                routeDrop.querySelectorAll('.kcs-wiz-ac-item').forEach(item => {
                    item.addEventListener('mousedown', e => {
                        e.preventDefault();
                        _routeId    = parseInt(item.dataset.id, 10);
                        _routeLabel = item.dataset.label;
                        routeInput.value = _routeLabel;
                        routeDrop.classList.remove('show');
                    });
                });
            };
            routeInput.addEventListener('focus', () => showRoutes(routeInput.value));
            routeInput.addEventListener('input', () => {
                _routeId    = null;
                _routeLabel = routeInput.value;
                showRoutes(routeInput.value);
            });
            document.addEventListener('click', e => {
                if (!routeInput.closest('.kcs-wiz-ac-wrap').contains(e.target)) {
                    routeDrop.classList.remove('show');
                }
            });
        }

        // Route optional, but date+time required
        renderFooter(!!_startDate && !!_startTime, 'Weiter →');
    }

    // ── Step 4: Bestätigen ────────────────────────────────────────

    function renderConfirm() {
        const b = body();
        if (!b) return;

        const crewNames = _crew.filter(c => c.name.trim()).map(c => c.name).join(', ') || 'Alleinfahrt';
        const routeDisplay = _routeLabel || '—';

        b.innerHTML = stepBar() + `
            <div class="kcs-wiz-section">
                <div class="kcs-wiz-confirm-box">
                    <div class="kcs-wiz-confirm-title">
                        <i class="bi bi-check-circle-fill"></i>
                        Fahrt bereit zum Start
                    </div>
                    <div class="kcs-wiz-confirm-grid">
                        ${[
                            { label: 'Boot',    value: _boatName },
                            { label: 'Strecke', value: routeDisplay },
                            { label: 'Start',   value: (_startDate ? _startDate.split('-').reverse().join('.') : '') + ' · ' + _startTime },
                            { label: 'Crew',    value: crewNames },
                            ...(_tripGroupName ? [{ label: 'Ausfahrt', value: _tripGroupName }] : []),
                        ].map(row => `
                            <div class="kcs-wiz-confirm-item">
                                <div class="kcs-wiz-confirm-item__label">${row.label}</div>
                                <div class="kcs-wiz-confirm-item__value">${escHtml(row.value)}</div>
                            </div>`).join('')}
                    </div>
                </div>
                ${_boatNoteStart && _boatNoteStart.length >= 10 ? `
                <div class="kcs-wiz-note-start">
                    <div class="kcs-wiz-note-start__title"><i class="bi bi-info-circle-fill"></i> Starthinweis</div>
                    <div class="kcs-wiz-note-start__text">${_boatNoteStart}</div>
                </div>` : ''}
                <div id="stError" class="kcs-st-error" style="display:none"></div>
            </div>`;

        const submitLabel = _isBacklog
            ? '<i class="bi bi-clock-history"></i> Fahrt nachtragen'
            : '<i class="bi bi-play-circle-fill"></i> Fahrt beginnen!';
        renderFooter(true, submitLabel);
    }

    // ── Submit ────────────────────────────────────────────────────

    async function submitTrip() {
        // Validate: crew_1 required
        const c1 = _crew[0]?.name?.trim();
        if (!c1) {
            showError('Fahrer 1 muss angegeben werden.');
            return;
        }

        const btn = footer()?.querySelector('#stBtnNext');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> …'; }

        const pad = n => String(n).padStart(2, '0');
        const now = new Date();
        const today = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate());
        const time  = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':00';

        const form = new FormData();
        form.append('boat_id',   _boatId || '');
        form.append('boat_name', _boatName || '');
        form.append('start_date', _startDate || today);
        form.append('start_time', (_startTime || time.slice(0,5)) + ':00');

        if (_isBacklog) {
            form.append('is_backlog', '1');
            if (_endDate)   form.append('end_date', _endDate);
            if (_endTime)   form.append('end_time', _endTime + ':00');
            if (_distance)  form.append('distance', _distance);
        }

        if (_routeId) {
            form.append('route_id', _routeId);
        } else if (_routeLabel.trim()) {
            form.append('route_custom', _routeLabel.trim());
        }

        _crew.filter(c => c.name.trim()).forEach((c, i) => {
            form.append('crew_' + (i+1),       c.name);
            if (c.memberId) form.append('crew_' + (i+1) + '_id', c.memberId);
        });

        // Event-Fahrten laufen über join-group.php, damit Gruppen korrekt erstellt/zusammengeführt werden
        const isGroupTrip = !!_tripGroupId || !!(_tripGroupSourceId && _tripGroupSourceType);
        const endpoint    = isGroupTrip ? '/trips/join-group.php' : '/trips/create.php';

        if (isGroupTrip) {
            if (_tripGroupId)         form.append('trip_group_id', _tripGroupId);
            if (_tripGroupSourceType) form.append('source_type',   _tripGroupSourceType);
            if (_tripGroupSourceId)   form.append('source_id',     _tripGroupSourceId);
            if (_tripGroupName)       form.append('group_name',    _tripGroupName);
        }

        try {
            const res  = await fetch(KCS.apiBase + endpoint, { method: 'POST', body: form });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Fehler beim Speichern');

            KCS.closeModal('startTripModal');
            if (_isBacklog) {
                KCS.toast('Fahrt nachgetragen', 'success');
            } else {
                document.dispatchEvent(new CustomEvent('kcs:trip-started', { detail: { tripId: data.trip_id } }));
                KCS.toast('Gute Fahrt!', 'success');
            }
            document.dispatchEvent(new CustomEvent('kcs:refresh-boats'));
            // Reservierung automatisch auflösen
            if (_reservationId) {
                const cf = new FormData();
                cf.append('reservation_id', _reservationId);
                fetch(KCS.apiBase + '/reservations/cancel.php', { method: 'POST', body: cf }).catch(() => {});
                _reservationId = null;
            }
        } catch (e) {
            showError(e.message);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = _isBacklog
                    ? '<i class="bi bi-clock-history"></i> Fahrt nachtragen'
                    : '<i class="bi bi-play-circle-fill"></i> Fahrt beginnen!';
            }
        }
    }

    function showError(msg) {
        const el = document.getElementById('stError');
        if (el) { el.style.display = ''; el.textContent = msg; }
    }

    // ── Helpers ───────────────────────────────────────────────────

    function escHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function highlightText(text, q) {
        if (!q) return escHtml(text);
        let result = escHtml(text);
        q.trim().split(/\s+/).filter(Boolean).forEach(w => {
            result = result.replace(
                new RegExp('(' + w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi'),
                '<strong>$1</strong>'
            );
        });
        return result;
    }

    function parseName(raw) {
        const m = (raw || '').match(/^(.+?)\s*\((.+)\)\s*$/);
        return m ? { display: m[1].trim(), owner: m[2].trim() } : { display: raw || '', owner: null };
    }

    function boatTypeIcon(typeFull) {
        const t = (typeFull || '').toLowerCase();
        if (t.includes('canadier')) return '/assets/images/boot_canadier.png';
        if (t === 'sup')            return '/assets/images/boot_sup.png';
        return '/assets/images/boot_kajak.png';
    }

    // ── Data loading ──────────────────────────────────────────────

    async function loadData() {
        try {
            const [mData, rData] = await Promise.all([
                KCS.api('/members/list.php'),
                KCS.api('/routes/list.php')
            ]);
            _allMembers = Array.isArray(mData) ? mData : (mData.members || []);
            _allRoutes  = Array.isArray(rData) ? rData : (rData.routes || []);
        } catch (_) { /* non-fatal */ }
    }

    // ── Open modal ────────────────────────────────────────────────

    function openModal(boatId, isBacklog, eventData, reservationData) {
        _step        = 1;
        _isBacklog   = !!isBacklog;
        _ownerFilter = null;
        _typeFilter  = null;
        _bSearch     = '';
        _crew        = [];
        _defaultCrew = [];
        _routeId   = null;
        _routeLabel= '';
        _startDate = '';
        _startTime = '';
        _endDate   = '';
        _endTime   = '';
        _distance  = '';
        _reservationId = null;
        // Event/Gruppenfahrt zurücksetzen
        _tripGroupId         = '';
        _tripGroupSourceId   = '';
        _tripGroupSourceType = '';
        _tripGroupName       = '';
        if (eventData) {
            _tripGroupSourceId   = String(eventData.id || '');
            _tripGroupSourceType = eventData.source === 'local' ? 'local_event' : 'termin';
            _tripGroupName       = eventData.title || eventData.name || '';
            _tripGroupId         = String(eventData.trip_group_id || '');
        }

        if (boatId && window._kcsBoats) {
            // Suche ohne Status-Filter — reserviertes Boot ist bekannt
            const boat = window._kcsBoats.find(b => b.id === parseInt(boatId, 10));
            if (boat) {
                _boatId        = boat.id;
                _boatName      = parseName(boat.name).display;
                _boatSeats     = parseInt(boat.seats, 10) || 1;
                _boatNoteStart = boat.note_start?.trim() || null;
                _defaultCrew   = [];
                if (boat.default_crew_1_name) _defaultCrew.push({ name: boat.default_crew_1_name, memberId: boat.default_crew_1 });
                if (boat.default_crew_2_name) _defaultCrew.push({ name: boat.default_crew_2_name, memberId: boat.default_crew_2 });
                _step = 2;
            } else {
                _boatId = null; _boatName = null; _boatSeats = 1;
                _boatNoteStart = null; _defaultCrew = [];
            }
        } else {
            _boatId = null; _boatName = null; _boatSeats = 1;
            _boatNoteStart = null; _defaultCrew = [];
        }

        // Reservierung: Fahrer vorbelegen, richtigen Schritt wählen
        if (reservationData) {
            _reservationId = reservationData.reservationId;
            const driverName = reservationData.memberDisplay || reservationData.memberName || '';
            _crew = [{ name: driverName, memberId: reservationData.memberId || null }];
            while (_crew.length < _boatSeats) _crew.push({ name: '', memberId: null });
            // Mehr als 1 Platz → Crew-Schritt (weitere Crew eintragen)
            // Genau 1 Platz → direkt zur Strecke
            _step = _boatSeats > 1 ? 2 : 3;
        }

        const titleEl = document.getElementById('startTripTitle');
        if (titleEl) titleEl.textContent = _isBacklog ? 'Fahrt nachtragen' : 'Fahrt starten';

        render();
        KCS.openModal('startTripModal');
    }

    // ── Event listeners ───────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', loadData);

    document.addEventListener('kcs:start-trip', e => {
        openModal(
            e.detail?.boatId    || null,
            e.detail?.backlog   || false,
            e.detail?.event     || null,
            e.detail?.reservation || null
        );
    });

    document.addEventListener('click', e => {
        if (e.target.closest('#ctaBtnStart')) openModal(null, false);
    });

})();
