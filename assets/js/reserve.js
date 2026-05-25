/**
 * Fahrtenbuch Beta — reserve.js
 * 3-Schritt-Wizard: Boot → Mitglied → Daten
 */
(function () {
    'use strict';

    const STEPS = ['Boot', 'Mitglied', 'Daten'];

    let _step        = 1;
    let _boatId      = null;
    let _boatName    = null;
    let _seatsFilter = null;
    let _bSearch     = '';
    let _memberName  = '';
    let _memberId    = null;
    let _date        = '';
    let _timeFrom    = '';
    let _timeTo      = '';
    let _reason      = '';
    let _phone       = '';
    let _allMembers  = [];

    const body   = () => document.getElementById('reserveBody');
    const footer = () => document.getElementById('reserveFooter');

    function pad(n) { return String(n).padStart(2, '0'); }
    function today() {
        const d = new Date();
        return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());
    }
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
            <button class="kcs-btn kcs-btn--ghost" id="rvBtnBack">${backLabel}</button>
            <div class="kcs-wiz-footer-right">
                ${_step === 1 ? `<span class="kcs-wiz-hint" id="rvBoatHint">${_boatId ? escHtml(_boatName) + ' ausgewählt' : 'Wähle ein Vereinsboot'}</span>` : ''}
                <button class="kcs-btn ${_step === 3 ? 'kcs-btn--green' : 'kcs-btn--primary'}" id="rvBtnNext" ${canNext ? '' : 'disabled'}>
                    ${nextLabel || 'Weiter →'}
                </button>
            </div>`;
        document.getElementById('rvBtnBack').addEventListener('click', () => {
            if (_step === 1) KCS.closeModal('reserveModal');
            else { _step--; render(); }
        });
        document.getElementById('rvBtnNext').addEventListener('click', handleNext);
    }

    function render() {
        switch (_step) {
            case 1: renderBoot();     break;
            case 2: renderMitglied(); break;
            case 3: renderDaten();    break;
        }
    }

    function handleNext() {
        if (_step === 3) { submitReservation(); return; }
        _step++;
        render();
    }

    // ── Step 1: Boot ──────────────────────────────────────────────

    function clubPool() {
        return (window._kcsBoats || []).filter(x => x.is_club_boat && x.status === 'available');
    }

    function renderBoot() {
        const b = body();
        if (!b) return;

        const pool = clubPool();

        const seatCounts = {};
        pool.forEach(x => { const s = String(x.seats || 1); seatCounts[s] = (seatCounts[s] || 0) + 1; });
        const seatKeys = Object.keys(seatCounts).sort((a, z) => parseInt(a) - parseInt(z));

        if (_seatsFilter && !seatCounts[_seatsFilter]) _seatsFilter = null;

        const filtered = pool
            .filter(x => !_seatsFilter || String(x.seats || 1) === _seatsFilter)
            .filter(x => !_bSearch || parseName(x.name).display.toLowerCase().includes(_bSearch.toLowerCase()))
            .sort((a, z) => parseName(a.name).display.localeCompare(parseName(z.name).display, 'de'));

        const seatBtn = (s, count) => `
            <button class="kcs-wiz-type-btn${_seatsFilter === s ? ' active' : ''}" data-seat="${escHtml(s)}">
                <span>${s === '1' ? '1 Platz' : s + ' Plätze'}</span>
                <span class="kcs-wiz-type-count">${count}</span>
            </button>`;

        b.innerHTML = stepBar() + `
            <div class="kcs-wiz-boot">
                <div class="kcs-wiz-type-list">
                    ${seatKeys.map(s => seatBtn(s, seatCounts[s])).join('')}
                </div>
                <div class="kcs-wiz-boat-panel">
                    <input class="kcs-input kcs-wiz-boat-search" id="rvBoatSearch"
                           placeholder="${filtered.length} Vereinsboote – nach Name suchen…"
                           value="${escHtml(_bSearch)}" autocomplete="off">
                    <div class="kcs-wiz-boat-list" id="rvBoatList">
                        ${filtered.length
                            ? filtered.map(bt => boatRowHtml(bt)).join('')
                            : '<div class="kcs-wiz-empty">Keine Vereinsboote verfügbar</div>'}
                    </div>
                </div>
            </div>`;

        b.querySelectorAll('.kcs-wiz-type-btn[data-seat]').forEach(btn => {
            btn.addEventListener('click', () => {
                _seatsFilter = _seatsFilter === btn.dataset.seat ? null : btn.dataset.seat;
                _bSearch = '';
                renderBoot();
            });
        });

        const searchInput = b.querySelector('#rvBoatSearch');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                _bSearch = searchInput.value;
                const grid = b.querySelector('#rvBoatList');
                if (!grid) return;
                const pool2 = clubPool()
                    .filter(x => !_seatsFilter || String(x.seats || 1) === _seatsFilter)
                    .filter(x => !_bSearch || parseName(x.name).display.toLowerCase().includes(_bSearch.toLowerCase()))
                    .sort((a, z) => parseName(a.name).display.localeCompare(parseName(z.name).display, 'de'));
                grid.innerHTML = pool2.length
                    ? pool2.map(bt => boatRowHtml(bt)).join('')
                    : '<div class="kcs-wiz-empty">Keine Boote gefunden</div>';
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

    // ── Steckbrief im Panel ───────────────────────────────────────

    const STAB_LABEL = { 1:'Sehr kippelig', 2:'Kippelig', 3:'Mittel', 4:'Stabil', 5:'Sehr stabil' };
    const STAB_COLOR = { 1:'var(--red)', 2:'var(--red)', 3:'var(--amber)', 4:'var(--green)', 5:'var(--green)' };

    function stabBar(val) {
        const v = Math.min(5, Math.max(1, parseInt(val, 10) || 3));
        return `<div class="kcs-stab-bar">
            ${[1,2,3,4,5].map(i =>
                `<div class="kcs-stab-seg${i <= v ? ' kcs-stab-seg--on' : ''}"
                      style="${i <= v ? 'background:' + STAB_COLOR[v] : ''}"></div>`
            ).join('')}
            <span class="kcs-stab-label">${STAB_LABEL[v]}</span>
        </div>`;
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

        const ROWS = [
            ['_seats',              'Sitzplätze',       'modell',              'Modell'          ],
            ['laenge',              'Länge',             'breite',              'Breite'          ],
            ['gewicht',             'Gewicht',           'material',            'Material'        ],
            ['steuerung',           'Steuerung',         'stauraum',            'Stauraum'        ],
            ['empfohlenes_gewicht', 'Empf. Gewicht',     'koerpergroesse',      'Körpergröße'    ],
            ['erfahrungslevel',     'Erfahrungslevel',   'stabilitaet',         'Stabilität'     ],
            ['geschwindigkeit',     'Geschwindigkeit',   'wendigkeit',          'Wendigkeit'      ],
            ['wind_wellen',         'Wind/Wellen',       'paddler_gewicht',     'Paddler-Gewicht' ],
            ['eigenheiten',         'Eigenheiten'],
            ['schwaechen',          'Schwächen'],
            ['hinweis',             'Hinweis'],
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
                ${d.stabilitaet ? stabBar(d.stabilitaet) : ''}
                ${boat.note_start && !empty(boat.note_start) ? `
                <div class="kcs-bd-notes kcs-bd-notes--green">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>
                        <div class="kcs-bd-notes-title">Starthinweis</div>
                        <div class="kcs-bd-notes-text">${boat.note_start}</div>
                    </div>
                </div>` : ''}
                <div class="kcs-wiz-bd__back">
                    <button class="kcs-btn kcs-btn--ghost kcs-btn--sm" id="rvBdBack">
                        ← Zurück zur Übersicht
                    </button>
                </div>
            </div>`;

        panel.querySelector('#rvBdBack').addEventListener('click', () => renderBoot());
    }

    function bindBoatCards(b) {
        b.querySelectorAll('.kcs-boat-row[data-id]').forEach(card => {
            card.addEventListener('click', () => {
                _boatId   = parseInt(card.dataset.id, 10);
                _boatName = card.dataset.name;
                const boatObj = (window._kcsBoats || []).find(x => x.id === _boatId);
                b.querySelectorAll('.kcs-boat-row[data-id]').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                const panel = b.querySelector('.kcs-wiz-boat-panel');
                if (panel && boatObj) renderPanelDetail(panel, boatObj);
                const hint = document.getElementById('rvBoatHint');
                if (hint) hint.textContent = _boatName + ' ausgewählt';
                renderFooter(true);
            });
        });
    }

    // ── Step 2: Mitglied ──────────────────────────────────────────

    function renderMitglied() {
        const b = body();
        if (!b) return;

        b.innerHTML = stepBar() + `
            <div class="kcs-wiz-section">
                <div class="kcs-wiz-boat-banner">
                    <i class="bi bi-water"></i>
                    <span><strong>${escHtml(_boatName)}</strong></span>
                </div>
                <div class="kcs-wiz-crew-label">
                    Reserviert für <span class="kcs-wiz-crew-required">(erforderlich)</span>
                </div>
                <div class="kcs-wiz-ac-wrap">
                    <input type="text" class="kcs-input" id="rvMemberInput"
                           value="${escHtml(_memberName)}"
                           placeholder="Mitglied suchen oder Namen eingeben"
                           autocomplete="off">
                    <div class="kcs-wiz-ac-dropdown" id="rvMemberDrop"></div>
                </div>
            </div>`;

        const input = b.querySelector('#rvMemberInput');
        const drop  = b.querySelector('#rvMemberDrop');

        const showAc = () => {
            const q = input.value.toLowerCase().trim();
            const matches = _allMembers.filter(m =>
                !q || (m.first_name + ' ' + m.last_name).toLowerCase().includes(q)
            ).slice(0, 10);
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
                    _memberName = item.dataset.name;
                    _memberId   = parseInt(item.dataset.id, 10);
                    input.value = _memberName;
                    drop.classList.remove('show');
                    renderFooter(true);
                });
            });
        };

        input.addEventListener('focus', showAc);
        input.addEventListener('input', () => {
            _memberName = input.value;
            _memberId   = null;
            showAc();
            renderFooter(!!_memberName.trim());
        });
        document.addEventListener('click', e => {
            if (!input.closest('.kcs-wiz-ac-wrap').contains(e.target)) drop.classList.remove('show');
        });

        renderFooter(!!_memberName.trim());
    }

    // ── Step 3: Daten ─────────────────────────────────────────────

    function renderDaten() {
        const b = body();
        if (!b) return;

        if (!_date) _date = today();

        b.innerHTML = stepBar() + `
            <div class="kcs-wiz-section">
                <div class="kcs-wiz-boat-banner">
                    <i class="bi bi-water"></i>
                    <span><strong>${escHtml(_boatName)}</strong> · <strong>${escHtml(_memberName)}</strong></span>
                </div>

                <div class="kcs-wiz-field">
                    <label class="kcs-wiz-lbl kcs-wiz-required">Datum</label>
                    <input type="date" class="kcs-input" id="rvDate" min="${today()}" value="${_date}">
                </div>

                <div class="kcs-wiz-row">
                    <div class="kcs-wiz-field">
                        <label class="kcs-wiz-lbl kcs-wiz-required">Von</label>
                        <input type="time" class="kcs-input" id="rvFrom" value="${_timeFrom}">
                    </div>
                    <div class="kcs-wiz-field">
                        <label class="kcs-wiz-lbl kcs-wiz-required">Bis</label>
                        <input type="time" class="kcs-input" id="rvTo" value="${_timeTo}">
                    </div>
                </div>

                <div class="kcs-wiz-field">
                    <label class="kcs-wiz-lbl kcs-wiz-required">Grund</label>
                    <input type="text" class="kcs-input" id="rvReason"
                           value="${escHtml(_reason)}"
                           placeholder="z.B. Training, Wettkampf, Ausflug…">
                </div>

                <div class="kcs-wiz-field">
                    <label class="kcs-wiz-lbl">Telefon für Rückfragen <span class="kcs-wiz-optional">(optional)</span></label>
                    <input type="tel" class="kcs-input" id="rvPhone"
                           value="${escHtml(_phone)}"
                           placeholder="z.B. 0171 123456">
                </div>

                <div id="rvError" class="kcs-st-error" style="display:none"></div>
            </div>`;

        const checkValid = () => {
            _date     = b.querySelector('#rvDate')?.value         || '';
            _timeFrom = b.querySelector('#rvFrom')?.value         || '';
            _timeTo   = b.querySelector('#rvTo')?.value           || '';
            _reason   = b.querySelector('#rvReason')?.value?.trim() || '';
            _phone    = b.querySelector('#rvPhone')?.value?.trim()  || '';
            renderFooter(!!_date && !!_timeFrom && !!_timeTo && !!_reason,
                '<i class="bi bi-calendar-check"></i> Reservieren');
        };

        ['#rvDate','#rvFrom','#rvTo','#rvReason','#rvPhone'].forEach(sel => {
            b.querySelector(sel)?.addEventListener('input',  checkValid);
            b.querySelector(sel)?.addEventListener('change', checkValid);
        });

        renderFooter(!!_date && !!_timeFrom && !!_timeTo && !!_reason,
            '<i class="bi bi-calendar-check"></i> Reservieren');
    }

    // ── Submit ────────────────────────────────────────────────────

    async function submitReservation() {
        if (_timeFrom && _timeTo && _timeFrom >= _timeTo) {
            const err = document.getElementById('rvError');
            if (err) { err.style.display = ''; err.textContent = '"Von" muss vor "Bis" liegen.'; }
            return;
        }

        const btn = footer()?.querySelector('#rvBtnNext');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> …'; }

        const form = new FormData();
        form.append('boat',        _boatId);
        form.append('member_name', _memberName);
        if (_memberId) form.append('member_id', _memberId);
        form.append('start_date',  _date);
        form.append('start_time',  _timeFrom + ':00');
        form.append('end_date',    _date);
        form.append('end_time',    _timeTo + ':00');
        form.append('reason',      _reason);
        if (_phone) form.append('phone', _phone);

        try {
            const res  = await fetch(KCS.apiBase + '/reservations/create.php', { method: 'POST', body: form });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Fehler');
            KCS.closeModal('reserveModal');
            KCS.toast('Reservierung gespeichert', 'success');
            document.dispatchEvent(new CustomEvent('kcs:refresh-boats'));
            document.dispatchEvent(new CustomEvent('kcs:poll'));
        } catch (e) {
            const err = document.getElementById('rvError');
            if (err) { err.style.display = ''; err.textContent = e.message; }
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-calendar-check"></i> Reservieren'; }
        }
    }

    // ── Open ──────────────────────────────────────────────────────

    function openModal(boatId) {
        _step        = 1;
        _seatsFilter = null;
        _bSearch     = '';
        _memberName  = '';
        _memberId    = null;
        _date        = '';
        _timeFrom    = '';
        _timeTo      = '';
        _reason      = '';
        _phone       = '';

        if (boatId && window._kcsBoats) {
            const boat = window._kcsBoats.find(b => b.id === parseInt(boatId, 10));
            if (boat && boat.is_club_boat) {
                _boatId   = boat.id;
                _boatName = parseName(boat.name).display;
                _step     = 2;
            } else {
                _boatId = null; _boatName = null;
            }
        } else {
            _boatId = null; _boatName = null;
        }

        render();
        KCS.openModal('reserveModal');
    }

    // ── Data loading ──────────────────────────────────────────────

    async function loadData() {
        try {
            const mData = await KCS.api('/members/list.php');
            _allMembers = Array.isArray(mData) ? mData : (mData.members || []);
        } catch (_) {}
    }

    // ── Events ────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', loadData);

    document.addEventListener('kcs:open-reserve', e => openModal(e.detail?.boatId || null));

    document.addEventListener('click', e => {
        if (e.target.closest('#ctaBtnReserve')) {
            document.dispatchEvent(new CustomEvent('kcs:open-reserve'));
        }
    });

})();
