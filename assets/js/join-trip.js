/**
 * Fahrtenbuch Beta — join-trip.js
 * 4-Schritt-Wizard: Fahrt wählen → Boot → Mannschaft → Bestätigen
 */
(function () {
    'use strict';

    const STEPS = ['Fahrt', 'Boot', 'Mannschaft', 'Bestätigen'];

    let _step         = 1;
    let _groupId      = null;
    let _groupLabel   = '';
    let _isSoloJoin   = false;
    let _soloTripId   = null;
    let _boatId       = null;
    let _boatName     = null;
    let _boatSeats    = 1;
    let _ownerFilter  = null;
    let _typeFilter   = null;
    let _bSearch      = '';
    let _crew         = [];
    let _defaultCrew  = [];
    let _allMembers   = [];
    let _activeGroups = [];
    let _soloTrips    = [];

    const body   = () => document.getElementById('joinTripBody');
    const footer = () => document.getElementById('joinTripFooter');

    // ── Helpers ───────────────────────────────────────────────────

    function escHtml(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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
    function highlightText(text, q) {
        if (!q) return escHtml(text);
        let r = escHtml(text);
        q.trim().split(/\s+/).filter(Boolean).forEach(w => {
            r = r.replace(new RegExp('(' + w.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')','gi'), '<strong>$1</strong>');
        });
        return r;
    }
    function pad(n) { return String(n).padStart(2, '0'); }

    function setDialogSize(xl) {
        const d = document.getElementById('joinTripDialog');
        if (!d) return;
        d.classList.toggle('modal-xl', xl);
        d.classList.toggle('modal-lg', !xl);
    }

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

    // ── Footer ────────────────────────────────────────────────────

    function renderFooter(canNext, nextLabel) {
        const f = footer();
        if (!f) return;
        const backLabel = _step === 1 ? 'Abbrechen' : '← Zurück';
        f.innerHTML = `
            <button class="kcs-btn kcs-btn--ghost" id="jtBtnBack">${backLabel}</button>
            <div class="kcs-wiz-footer-right">
                <button class="kcs-btn ${_step === 4 ? 'kcs-btn--green' : 'kcs-btn--primary'}" id="jtBtnNext" ${canNext ? '' : 'disabled'}>
                    ${nextLabel || 'Weiter →'}
                </button>
            </div>`;
        document.getElementById('jtBtnBack').addEventListener('click', () => {
            if (_step === 1) KCS.closeModal('joinTripModal');
            else { _step--; render(); }
        });
        document.getElementById('jtBtnNext').addEventListener('click', handleNext);
    }

    function render() {
        switch (_step) {
            case 1: setDialogSize(false); renderFahrt();    break;
            case 2: setDialogSize(true);  renderBoot();     break;
            case 3: setDialogSize(false); renderMannschaft(); break;
            case 4: setDialogSize(false); renderConfirm();  break;
        }
    }

    function handleNext() {
        if (_step === 4) { submitJoin(); return; }
        _step++;
        render();
    }

    // ── Step 1: Fahrt wählen ──────────────────────────────────────

    function renderFahrt() {
        const b = body();
        if (!b) return;

        const hasAny = _activeGroups.length || _soloTrips.length;
        if (!hasAny) {
            b.innerHTML = stepBar() + `
                <div class="kcs-wiz-section" style="align-items:center;justify-content:center;text-align:center;gap:var(--sp-4)">
                    <i class="bi bi-water" style="font-size:3rem;color:var(--muted);opacity:.4"></i>
                    <div>
                        <div style="font-weight:700;font-size:15px;margin-bottom:6px">Keine aktive Fahrt</div>
                        <div style="color:var(--muted);font-size:13px">Es muss zuerst eine Fahrt gestartet werden,<br>bevor du dich anschließen kannst.</div>
                    </div>
                </div>`;
            renderFooter(false);
            return;
        }

        const isSelected = id => !_isSoloJoin && _groupId === id;
        const isSoloSel  = id => _isSoloJoin  && _soloTripId === id;

        // active-groups.php gibt: trip_group_id, name, trips[]{id, boat_name, start_time, crew}
        const groupsHtml = _activeGroups.map(g => {
            const route = g.name || '—';
            const crews = (g.trips || []).map(t => t.crew).filter(Boolean).join(' · ');
            const since = g.trips?.[0]?.start_time || '';
            const sel   = isSelected(g.trip_group_id) ? ' selected' : '';
            return `<div class="kcs-jt-group-row${sel}" data-id="${g.trip_group_id}" data-label="${escHtml(route)}" data-kind="group">
                <div class="kcs-jt-group-row__icon"><i class="bi bi-people-fill"></i></div>
                <div class="kcs-jt-group-row__info">
                    <div class="kcs-jt-group-row__route">${escHtml(route)}</div>
                    <div class="kcs-jt-group-row__crew">${escHtml(crews) || 'Keine Crew-Info'}</div>
                </div>
                ${since ? `<div class="kcs-jt-group-row__since">seit ${escHtml(since)}</div>` : ''}
            </div>`;
        }).join('');

        // active-groups.php gibt für solo: id, boat_name, start_time, crew
        const soloHtml = _soloTrips.map(t => {
            const label = t.boat_name || '—';
            const crew  = t.crew || '—';
            const since = t.start_time || '';
            const sel   = isSoloSel(t.id) ? ' selected' : '';
            return `<div class="kcs-jt-group-row${sel}" data-id="${t.id}" data-label="${escHtml(label)}" data-kind="solo">
                <div class="kcs-jt-group-row__icon"><i class="bi bi-water"></i></div>
                <div class="kcs-jt-group-row__info">
                    <div class="kcs-jt-group-row__route">${escHtml(label)}</div>
                    <div class="kcs-jt-group-row__crew">${escHtml(crew)}</div>
                </div>
                ${since ? `<div class="kcs-jt-group-row__since">seit ${escHtml(since)}</div>` : ''}
            </div>`;
        }).join('');

        b.innerHTML = stepBar() + `
            <div class="kcs-wiz-section">
                <div class="kcs-wiz-crew-label">Welcher Fahrt möchtest du dich anschließen?</div>
                <div class="kcs-jt-group-list" id="jtGroupList">
                    ${_activeGroups.length ? `<div class="kcs-jt-section-label">Gruppenausfahrten</div>${groupsHtml}` : ''}
                    ${_soloTrips.length ? `<div class="kcs-jt-section-label">Einzelfahrer</div>${soloHtml}` : ''}
                </div>
            </div>`;

        b.querySelectorAll('.kcs-jt-group-row').forEach(row => {
            row.addEventListener('click', () => {
                const id   = parseInt(row.dataset.id, 10);
                const kind = row.dataset.kind;
                b.querySelectorAll('.kcs-jt-group-row').forEach(r => r.classList.remove('selected'));
                row.classList.add('selected');
                if (kind === 'solo') {
                    _isSoloJoin = true;
                    _soloTripId = id;
                    _groupId    = null;
                } else {
                    _isSoloJoin = false;
                    _groupId    = id;
                    _soloTripId = null;
                }
                _groupLabel = row.dataset.label;
                renderFooter(true);
            });
        });

        renderFooter(!!_groupId || !!_soloTripId);
    }

    // ── Step 2: Boot ──────────────────────────────────────────────

    function renderBoot() {
        const b = body();
        if (!b) return;

        const allBoats = window._kcsBoats || [];
        const pool = allBoats.filter(x => x.status === 'available');

        const clubCount    = pool.filter(x => x.is_club_boat).length;
        const privateCount = pool.filter(x => !x.is_club_boat).length;

        const ownerPool = pool.filter(x => {
            if (_ownerFilter === '__club')    return x.is_club_boat;
            if (_ownerFilter === '__private') return !x.is_club_boat;
            return true;
        });

        const seatCounts = {};
        ownerPool.forEach(x => { const s = String(x.seats || 1); seatCounts[s] = (seatCounts[s] || 0) + 1; });
        const seatKeys = Object.keys(seatCounts).sort((a, z) => parseInt(a) - parseInt(z));
        if (_typeFilter && !seatCounts[_typeFilter]) _typeFilter = null;

        const filtered = ownerPool
            .filter(x => !_typeFilter || String(x.seats || 1) === _typeFilter)
            .filter(x => !_bSearch || parseName(x.name).display.toLowerCase().includes(_bSearch.toLowerCase()))
            .sort((a, z) => parseName(a.name).display.localeCompare(parseName(z.name).display, 'de'));

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
                    <input class="kcs-input kcs-wiz-boat-search" id="jtBoatSearch"
                           placeholder="${filtered.length} Boote – nach Name suchen…"
                           value="${escHtml(_bSearch)}" autocomplete="off">
                    <div class="kcs-wiz-boat-list" id="jtBoatList">
                        ${filtered.length
                            ? filtered.map(bt => boatRowHtml(bt)).join('')
                            : '<div class="kcs-wiz-empty">Keine Boote verfügbar</div>'}
                    </div>
                </div>
            </div>`;

        b.querySelectorAll('.kcs-wiz-type-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset.owner !== undefined) _ownerFilter = _ownerFilter === btn.dataset.owner ? null : btn.dataset.owner;
                else                                 _typeFilter  = _typeFilter  === btn.dataset.type  ? null : btn.dataset.type;
                _bSearch = '';
                renderBoot();
            });
        });

        const searchInput = b.querySelector('#jtBoatSearch');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                _bSearch = searchInput.value;
                const grid = b.querySelector('#jtBoatList');
                if (!grid) return;
                const pool2 = (window._kcsBoats || []).filter(x => x.status === 'available')
                    .filter(x => { if (_ownerFilter === '__club') return x.is_club_boat; if (_ownerFilter === '__private') return !x.is_club_boat; return true; })
                    .filter(x => !_typeFilter || String(x.seats || 1) === _typeFilter)
                    .filter(x => !_bSearch || parseName(x.name).display.toLowerCase().includes(_bSearch.toLowerCase()))
                    .sort((a, z) => parseName(a.name).display.localeCompare(parseName(z.name).display, 'de'));
                grid.innerHTML = pool2.length ? pool2.map(bt => boatRowHtml(bt)).join('') : '<div class="kcs-wiz-empty">Keine Boote gefunden</div>';
                bindBoatCards(b);
                renderFooter(!!_boatId);
            });
        }

        bindBoatCards(b);
        renderFooter(!!_boatId);
    }

    function boatRowHtml(bt) {
        const { display, owner } = parseName(bt.name);
        const icon  = bt.boat_icon_url || boatTypeIcon(bt.type_full);
        const seats = bt.seats || 1;
        const sel   = _boatId === bt.id ? ' selected' : '';
        return `<div class="kcs-boat-row${sel}" data-id="${bt.id}" data-name="${escHtml(display)}" data-seats="${seats}">
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
                _boatId    = parseInt(card.dataset.id, 10);
                _boatName  = card.dataset.name;
                _boatSeats = parseInt(card.dataset.seats, 10) || 1;
                const boatObj = (window._kcsBoats || []).find(x => x.id === _boatId);
                _defaultCrew = [];
                if (boatObj?.default_crew_1_name) _defaultCrew.push({ name: boatObj.default_crew_1_name, memberId: boatObj.default_crew_1 });
                if (boatObj?.default_crew_2_name) _defaultCrew.push({ name: boatObj.default_crew_2_name, memberId: boatObj.default_crew_2 });
                b.querySelectorAll('.kcs-boat-row[data-id]').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                renderFooter(true);
            });
        });
    }

    // ── Step 3: Mannschaft ────────────────────────────────────────

    function renderMannschaft() {
        const b = body();
        if (!b) return;

        if (!_crew.some(c => c.name)) {
            _crew = _defaultCrew.length ? [..._defaultCrew] : [];
        }
        while (_crew.length < _boatSeats) _crew.push({ name: '', memberId: null });

        b.innerHTML = stepBar() + `
            <div class="kcs-wiz-section">
                <div class="kcs-wiz-boat-banner">
                    <i class="bi bi-water"></i>
                    <span><strong>${escHtml(_boatName)}</strong> · ${_boatSeats} Pl. · Fahrt: ${escHtml(_groupLabel)}</span>
                </div>
                <div class="kcs-wiz-crew-label">
                    Crew <span class="kcs-wiz-crew-required">(Fahrer 1 erforderlich)</span>
                </div>
                <div id="jtCrewList"></div>
                <button type="button" class="kcs-wiz-add-crew-btn" id="jtAddCrew">
                    <i class="bi bi-plus-circle"></i> Weitere Person hinzufügen
                </button>
            </div>`;

        renderCrewList(b);
        b.querySelector('#jtAddCrew').addEventListener('click', () => {
            _crew.push({ name: '', memberId: null });
            renderMannschaft();
        });
        renderFooter(!!_crew[0]?.name?.trim());
    }

    function renderCrewList(b) {
        const list = b.querySelector('#jtCrewList');
        if (!list) return;
        list.innerHTML = _crew.map((c, i) => {
            const label = i === 0 ? 'Fahrer 1' : 'Fahrer ' + (i + 1);
            return `<div class="kcs-wiz-crew-row" data-idx="${i}">
                <div class="kcs-wiz-crew-field">
                    <label class="kcs-wiz-crew-lbl${i === 0 ? ' kcs-wiz-required' : ''}">${label}</label>
                    <div class="kcs-wiz-ac-wrap">
                        <input type="text" class="kcs-input kcs-wiz-crew-input"
                               value="${escHtml(c.name)}"
                               placeholder="Mitglied suchen oder Namen eingeben"
                               ${i === 0 ? 'required' : ''}
                               autocomplete="off" data-idx="${i}">
                        <div class="kcs-wiz-ac-dropdown" id="jtCrewDrop${i}"></div>
                    </div>
                </div>
                ${i > 0 ? `<button type="button" class="kcs-btn kcs-btn--ghost kcs-btn--sm kcs-wiz-crew-del" data-idx="${i}">
                    <i class="bi bi-x-lg"></i>
                </button>` : ''}
            </div>`;
        }).join('');

        list.querySelectorAll('.kcs-wiz-crew-input').forEach(input => {
            const idx  = parseInt(input.dataset.idx, 10);
            const drop = list.querySelector(`#jtCrewDrop${idx}`);
            input.addEventListener('focus', () => showMemberAc(input, drop, idx));
            input.addEventListener('input', () => {
                _crew[idx] = { name: input.value, memberId: null };
                showMemberAc(input, drop, idx);
                if (idx === 0) renderFooter(!!input.value.trim());
            });
            document.addEventListener('click', e => {
                if (!input.closest('.kcs-wiz-ac-wrap').contains(e.target)) drop.classList.remove('show');
            });
        });

        list.querySelectorAll('.kcs-wiz-crew-del').forEach(btn => {
            btn.addEventListener('click', () => { _crew.splice(parseInt(btn.dataset.idx, 10), 1); renderMannschaft(); });
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
                _crew[idx] = { name: item.dataset.name, memberId: parseInt(item.dataset.id, 10) };
                input.value = item.dataset.name;
                drop.classList.remove('show');
                if (idx === 0) renderFooter(true);
            });
        });
    }

    // ── Step 4: Bestätigen ────────────────────────────────────────

    function renderConfirm() {
        const b = body();
        if (!b) return;

        const crewNames = _crew.filter(c => c.name.trim()).map(c => c.name).join(', ') || 'Alleinfahrt';

        b.innerHTML = stepBar() + `
            <div class="kcs-wiz-section">
                <div class="kcs-wiz-confirm-box">
                    <div class="kcs-wiz-confirm-title">
                        <i class="bi bi-check-circle-fill"></i>
                        Bereit zum Anschließen
                    </div>
                    <div class="kcs-wiz-confirm-grid">
                        ${[
                            { label: 'Fahrt',  value: _groupLabel },
                            { label: 'Boot',   value: _boatName },
                            { label: 'Crew',   value: crewNames },
                        ].map(row => `
                            <div class="kcs-wiz-confirm-item">
                                <div class="kcs-wiz-confirm-item__label">${row.label}</div>
                                <div class="kcs-wiz-confirm-item__value">${escHtml(row.value)}</div>
                            </div>`).join('')}
                    </div>
                </div>
                <div id="jtError" class="kcs-st-error" style="display:none"></div>
            </div>`;

        renderFooter(true, '<i class="bi bi-people-fill"></i> Anschließen');
    }

    // ── Submit ────────────────────────────────────────────────────

    async function submitJoin() {
        const btn = footer()?.querySelector('#jtBtnNext');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> …'; }

        const now   = new Date();
        const today = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate());
        const time  = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':00';

        const form = new FormData();
        if (_isSoloJoin) {
            form.append('join_trip_id', _soloTripId);
        } else {
            form.append('trip_group_id', _groupId);
        }
        form.append('boat_id',       _boatId);
        form.append('boat_name',     _boatName || '');
        form.append('start_date',    today);
        form.append('start_time',    time);
        _crew.filter(c => c.name.trim()).forEach((c, i) => {
            form.append('crew_' + (i+1), c.name);
            if (c.memberId) form.append('crew_' + (i+1) + '_id', c.memberId);
        });

        try {
            const res  = await fetch(KCS.apiBase + '/trips/join-group.php', { method: 'POST', body: form });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Fehler');
            KCS.closeModal('joinTripModal');
            KCS.toast('Gute Fahrt!', 'success');
            document.dispatchEvent(new CustomEvent('kcs:trip-started', { detail: {} }));
            document.dispatchEvent(new CustomEvent('kcs:refresh-boats'));
        } catch (e) {
            const err = body()?.querySelector('#jtError');
            if (err) { err.style.display = ''; err.textContent = e.message; }
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-people-fill"></i> Anschließen'; }
        }
    }

    // ── Data loading ──────────────────────────────────────────────

    async function loadData() {
        try {
            const [mData, gData] = await Promise.all([
                KCS.api('/members/list.php'),
                KCS.api('/trips/active-groups.php'),
            ]);
            _allMembers   = Array.isArray(mData) ? mData : (mData.members || []);
            _activeGroups = Array.isArray(gData) ? gData : (gData.groups    || []);
            _soloTrips    = Array.isArray(gData) ? []    : (gData.solo_trips || []);
        } catch (_) {}
    }

    // ── Open ──────────────────────────────────────────────────────

    async function openModal() {
        _step        = 1;
        _groupId     = null;
        _groupLabel  = '';
        _isSoloJoin  = false;
        _soloTripId  = null;
        _boatId      = null;
        _boatName    = null;
        _boatSeats   = 1;
        _ownerFilter = null;
        _typeFilter  = null;
        _bSearch     = '';
        _crew        = [];
        _defaultCrew = [];

        body().innerHTML = '<div class="kcs-sp-empty"><i class="bi bi-hourglass-split kcs-spin"></i> Laden…</div>';
        renderFooter(false);
        KCS.openModal('joinTripModal');

        await loadData();
        render();
    }

    // ── Events ────────────────────────────────────────────────────

    document.addEventListener('kcs:open-join', openModal);

    document.addEventListener('click', e => {
        if (e.target.closest('#ctaBtnJoin')) {
            document.dispatchEvent(new CustomEvent('kcs:open-join'));
        }
    });

})();
