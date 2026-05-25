/**
 * Fahrtenbuch Beta — boat-detail.js
 * Task 08: Boot-Steckbrief Modal
 */
(function () {
    'use strict';

    const STABILITY_LABEL = { 1:'Sehr kippelig', 2:'Kippelig', 3:'Mittel', 4:'Stabil', 5:'Sehr stabil' };
    const STABILITY_COLOR = { 1:'var(--red)', 2:'var(--red)', 3:'var(--amber)', 4:'var(--green)', 5:'var(--green)' };
    const LEVEL_COLOR     = { 'Anfänger':'var(--green)', 'Fortgeschritten':'var(--amber)', 'Experte':'var(--red)' };

    const DAMAGE_TYPES = ['Rumpf', 'Cockpit', 'Steuer', 'Paddel', 'Sonstiges'];

    function stabilityBar(val) {
        const v = Math.min(5, Math.max(1, parseInt(val, 10) || 3));
        const color = STABILITY_COLOR[v];
        const label = STABILITY_LABEL[v];
        return `<div class="kcs-stab-bar">
            ${[1,2,3,4,5].map(i =>
                `<div class="kcs-stab-seg${i <= v ? ' kcs-stab-seg--on' : ''}"
                      style="${i <= v ? 'background:' + color : ''}"></div>`
            ).join('')}
            <span class="kcs-stab-label">${label}</span>
        </div>`;
    }

    function specGrid(specs) {
        const visible = specs.filter(s => s.value !== null && s.value !== undefined && s.value !== '');
        if (!visible.length) return '';
        return `<div class="kcs-spec-grid">` +
            visible.map(s => `
            <div class="kcs-spec-item">
                <div class="kcs-spec-label">${s.label}</div>
                <div class="kcs-spec-value">${s.value}</div>
            </div>`).join('') +
        `</div>`;
    }

    // ── Schadensmeldung (inline im Modal) ─────────────────────────

    function renderDamageForm(boat) {
        const body = document.getElementById('boatDetailBody');
        if (!body) return;

        body.innerHTML = `
            <div class="kcs-bd-damage-form">
                <p class="kcs-bd-damage-intro">Schaden für <strong>${parseName(boat.name).display}</strong> melden</p>

                <div class="kcs-et-field">
                    <label class="kcs-et-label">Schadensart *</label>
                    <div class="kcs-bd-damage-types" id="bdDmgTypes">
                        ${DAMAGE_TYPES.map(t => `
                        <button class="kcs-et-cond-btn" data-val="${t}">${t}</button>`).join('')}
                    </div>
                </div>

                <div class="kcs-et-field">
                    <label class="kcs-et-label">Beschreibung *</label>
                    <textarea class="kcs-input kcs-et-textarea" id="bdDmgDesc" rows="3"
                              placeholder="Was genau ist beschädigt?"></textarea>
                </div>

                <div class="kcs-et-field">
                    <label class="kcs-et-label">Gemeldet von</label>
                    <input type="text" class="kcs-input" id="bdDmgName"
                           placeholder="Dein Name (optional)"
                           value="${KCS.memberName || ''}">
                </div>

                <div id="bdDmgError" class="kcs-st-error" style="display:none"></div>

                <div class="kcs-bd-footer">
                    <div class="kcs-bd-footer-grid">
                        <button class="kcs-btn kcs-btn--red" id="bdDmgSubmit">
                            <i class="bi bi-exclamation-triangle-fill"></i> Melden
                        </button>
                        <button class="kcs-btn kcs-btn--ghost" id="bdDmgBack">Zurück</button>
                    </div>
                </div>
            </div>`;

        let selectedType = null;

        body.querySelectorAll('#bdDmgTypes .kcs-et-cond-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                body.querySelectorAll('#bdDmgTypes .kcs-et-cond-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedType = btn.dataset.val;
            });
        });

        body.querySelector('#bdDmgBack').addEventListener('click', () => renderModal(boat));

        body.querySelector('#bdDmgSubmit').addEventListener('click', async () => {
            const desc = body.querySelector('#bdDmgDesc').value.trim();
            const name = body.querySelector('#bdDmgName').value.trim();
            const errEl = body.querySelector('#bdDmgError');

            if (!selectedType || !desc) {
                errEl.textContent = 'Bitte Schadensart und Beschreibung angeben.';
                errEl.style.display = '';
                return;
            }
            errEl.style.display = 'none';

            try {
                const fd = new FormData();
                fd.append('boat', boat.id);
                fd.append('damage_type', selectedType);
                fd.append('damage_description', desc);
                if (name) fd.append('reporter_name', name);
                if (KCS.memberId) fd.append('reporter_id', KCS.memberId);

                const res = await KCS.api('/damages/create.php', { method: 'POST', body: fd });
                if (res.success) {
                    KCS.closeModal('boatDetailModal');
                    KCS.toast('Schaden gemeldet. Boot gesperrt bis zur Reparatur.', 'success');
                    document.dispatchEvent(new CustomEvent('kcs:trip-ended'));
                } else {
                    errEl.textContent = res.message || 'Fehler beim Melden.';
                    errEl.style.display = '';
                }
            } catch (e) {
                errEl.textContent = 'Netzwerkfehler. Bitte erneut versuchen.';
                errEl.style.display = '';
            }
        });
    }

    // ── Hauptansicht ──────────────────────────────────────────────

    function renderModal(boat) {
        const titleEl    = document.getElementById('boatDetailTitle');
        const subtitleEl = document.getElementById('boatDetailSubtitle');
        const metaEl     = document.getElementById('boatDetailMeta');
        const body       = document.getElementById('boatDetailBody');
        if (!titleEl || !body) return;

        const d     = boat.boat_details || {};
        const avail = boat.status === 'available';
        const isClub = !!boat.is_club_boat;
        const { display: displayName } = parseName(boat.name);

        // ── Header ────────────────────────────────────────────────
        titleEl.textContent = displayName;

        if (subtitleEl) {
            const parts = [
                boat.type_full || boat.type,
                d.material,
                d.weight ? d.weight + ' kg' : null,
            ].filter(Boolean);
            subtitleEl.textContent = parts.join(' · ');
        }

        if (metaEl) {
            const levelColor = LEVEL_COLOR[d.level] || 'var(--muted)';
            metaEl.innerHTML = `
                <span class="kcs-bd-status-dot kcs-bd-status-dot--${boat.status}"></span>
                <span class="kcs-bd-status-label">${statusLabel(boat.status)}</span>
                ${d.level ? `<span class="kcs-bd-level" style="color:${levelColor};border-color:${levelColor}40;background:${levelColor}12">${d.level}</span>` : ''}
            `;
        }

        // ── Specs ─────────────────────────────────────────────────
        const specItems = [
            { label: 'Sitzplätze',      value: boat.seats              },
            { label: 'Modell',          value: d.modell                },
            { label: 'Länge',           value: d.laenge                },
            { label: 'Breite',          value: d.breite                },
            { label: 'Gewicht',         value: d.gewicht               },
            { label: 'Material',        value: d.material              },
            { label: 'Steuerung',       value: d.steuerung             },
            { label: 'Empf. Gewicht',   value: d.empfohlenes_gewicht   },
            { label: 'Körpergröße',     value: d.koerpergroesse        },
            { label: 'Erfahrungslevel', value: d.erfahrungslevel       },
            { label: 'Stabilität',      value: d.stabilitaet           },
            { label: 'Geschwindigkeit', value: d.geschwindigkeit       },
            { label: 'Wendigkeit',      value: d.wendigkeit            },
        ];

        // ── Body ──────────────────────────────────────────────────
        body.innerHTML = `
            ${specGrid(specItems)}

            ${d.stabilitaet ? `
            <div class="kcs-bd-section">
                <div class="kcs-bd-section-label">Stabilität</div>
                ${stabilityBar(d.stabilitaet)}
            </div>` : ''}

            ${boat.note_start && boat.note_start.trim().length >= 10 ? `
            <div class="kcs-bd-notes kcs-bd-notes--green">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    <div class="kcs-bd-notes-title">Starthinweis</div>
                    <div class="kcs-bd-notes-text">${boat.note_start}</div>
                </div>
            </div>` : ''}

            ${d.eigenheiten && d.eigenheiten.replace(/<[^>]*>/g,'').trim() ? `
            <div class="kcs-bd-notes">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    <div class="kcs-bd-notes-title">Eigenheiten</div>
                    <div class="kcs-bd-notes-text">${d.eigenheiten}</div>
                </div>
            </div>` : ''}

            ${boat.status === 'onWater' ? `
            <div class="kcs-bd-onwater">
                <i class="bi bi-water"></i>
                Seit ${boat.since || '—'} auf dem Wasser
                ${boat.crew ? ' · ' + boat.crew : ''}
            </div>` : ''}

            ${boat.status === 'damaged' ? `
            <div class="kcs-bd-damage">
                <i class="bi bi-tools"></i> Schaden gemeldet — Boot nicht verfügbar
            </div>` : ''}

            <div class="kcs-bd-footer">
                <button class="kcs-btn kcs-btn--green kcs-btn--full" id="bdBtnStart"${!avail ? ' disabled' : ''}>
                    <i class="bi bi-play-circle"></i> Fahrt beginnen
                </button>
                <div class="kcs-bd-footer-grid">
                    <button class="kcs-btn kcs-btn--teal" id="bdBtnJoin">
                        <i class="bi bi-people-fill"></i> Anschließen
                    </button>
                    <button class="kcs-btn kcs-btn--red" id="bdBtnBacklog">
                        <i class="bi bi-clock-history"></i> Nachtragen
                    </button>
                    ${isClub ? `
                    <button class="kcs-btn kcs-btn--amber" id="bdBtnReserve">
                        <i class="bi bi-calendar-plus"></i> Reservieren
                    </button>
                    <button class="kcs-btn kcs-btn--red kcs-btn--outline" id="bdBtnDamage">
                        <i class="bi bi-exclamation-triangle"></i> Schaden
                    </button>` : ''}
                    <button class="kcs-btn kcs-btn--ghost ${isClub ? '' : 'kcs-bd-footer-full'}" data-bs-dismiss="modal">Schliessen</button>
                </div>
            </div>`;

        // ── Events ────────────────────────────────────────────────
        body.querySelector('#bdBtnStart')?.addEventListener('click', () => {
            KCS.closeModal('boatDetailModal');
            document.dispatchEvent(new CustomEvent('kcs:start-trip', { detail: { boatId: boat.id } }));
        });
        body.querySelector('#bdBtnJoin')?.addEventListener('click', () => {
            KCS.closeModal('boatDetailModal');
            document.dispatchEvent(new CustomEvent('kcs:open-join', { detail: { boatId: boat.id } }));
        });
        body.querySelector('#bdBtnBacklog')?.addEventListener('click', () => {
            KCS.closeModal('boatDetailModal');
            document.dispatchEvent(new CustomEvent('kcs:start-trip', { detail: { boatId: boat.id, backlog: true } }));
        });
        body.querySelector('#bdBtnReserve')?.addEventListener('click', () => {
            KCS.closeModal('boatDetailModal');
            document.dispatchEvent(new CustomEvent('kcs:open-reserve', { detail: { boatId: boat.id } }));
        });
        body.querySelector('#bdBtnDamage')?.addEventListener('click', () => {
            renderDamageForm(boat);
        });
    }

    function statusLabel(s) {
        return { available:'Verfügbar', onWater:'Auf dem Wasser', reserved:'Reserviert',
                 damaged:'Schaden', maintenance:'Wartung' }[s] || s;
    }

    function parseName(raw) {
        const m = (raw || '').match(/^(.+?)\s*\((.+)\)\s*$/);
        return m ? { display: m[1].trim() } : { display: raw };
    }

    // ── Event-Listener ────────────────────────────────────────────

    document.addEventListener('kcs:open-boat-detail', e => {
        const boatId = e.detail && (e.detail.boatId || e.detail.id);
        if (!boatId) return;

        const boat = (window._kcsBoats || []).find(b => b.id === parseInt(boatId, 10));
        if (!boat) return;

        renderModal(boat);
        KCS.openModal('boatDetailModal');
    });

})();
