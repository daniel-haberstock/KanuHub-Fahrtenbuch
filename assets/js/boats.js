/**
 * Fahrtenbuch Beta — boats.js
 * Task 04: Bootsliste laden, filtern, rendern
 */
(function () {
    'use strict';

    let _boats = [];
    let _filter = { ownership: 'all', seats: 'all' };
    let _search = '';
    let _selectedBoatId = null;

    // ── Name + Eigentümer aus "Name (Eigentümer)" parsen ─────────

    function parseName(raw) {
        const m = (raw || '').match(/^(.+?)\s*\((.+)\)\s*$/);
        return m ? { display: m[1].trim(), owner: m[2].trim() } : { display: raw, owner: null };
    }

    // ── Boot-Typ Icon ─────────────────────────────────────────────

    function boatTypeIcon(typeFull) {
        const t = (typeFull || '').toLowerCase();
        if (t.includes('canadier') || t.includes('wildwasser-can')) return '/assets/images/boot_canadier.png';
        if (t.includes('sup'))                                        return '/assets/images/boot_sup.png';
        return '/assets/images/boot_kajak.png';
    }

    // ── Sitze-Normierung ──────────────────────────────────────────

    function seatsKey(seats) {
        const n = parseInt(seats, 10) || 1;
        return n >= 5 ? '5+' : String(n);
    }

    // ── Filter anwenden ───────────────────────────────────────────

    function applyFilters(boats) {
        const q = _search.toLowerCase();
        return boats.filter(b => {
            // Anhänger immer herausfiltern (eigener Bereich)
            if ((b.type_full || '').toLowerCase() === 'anhänger') return false;
            // Nicht verfügbare nicht anzeigen
            if (b.status !== 'available') return false;
            // Ownership
            if (_filter.ownership === 'club'    && !b.is_club_boat)  return false;
            if (_filter.ownership === 'private' &&  b.is_club_boat)  return false;
            // Sitze
            if (_filter.seats !== 'all' && seatsKey(b.seats) !== _filter.seats) return false;
            // Suche
            if (q && !b.name.toLowerCase().includes(q) &&
                     !(b.type_full || '').toLowerCase().includes(q)) return false;
            return true;
        });
    }

    // ── Boot-Zeile rendern ────────────────────────────────────────

    function renderBoats(boats) {
        const list = document.getElementById('boatList');
        if (!list) return;

        const visible = applyFilters(boats);

        // Verfügbar-Zähler
        const allAvail = boats.filter(b =>
            b.status === 'available' &&
            (b.type_full || '').toLowerCase() !== 'anhänger'
        ).length;
        const badge = document.getElementById('boatAvailCount');
        if (badge) { badge.textContent = allAvail + ' verfügbar'; badge.style.display = ''; }

        if (!visible.length) {
            list.innerHTML = '<div class="kcs-empty">Keine Boote gefunden</div>';
            return;
        }

        list.innerHTML = visible.map(b => {
            const selected        = b.id === _selectedBoatId;
            const icon            = b.boat_icon_url || boatTypeIcon(b.type_full);
            const { display, owner } = parseName(b.name);
            const ownerLine  = owner
                ? `<div class="kcs-boat-row__owner">${owner}</div>` : '';
            const typeLine   = `<div class="kcs-boat-row__type">${b.type_full || b.type}</div>`;
            return `
            <div class="kcs-boat-row${selected ? ' selected' : ''}" data-boat-id="${b.id}">
                <div class="kcs-boat-row__info">
                    <div class="kcs-boat-row__name">${display}</div>
                    ${ownerLine}
                    ${typeLine}
                </div>
                <img class="kcs-boat-row__typeicon" src="${icon}" alt="${b.type}">
            </div>`;
        }).join('');

        // Anhänger-Bereich
        const trailers = boats.filter(b =>
            (b.type_full || '').toLowerCase() === 'anhänger' && b.status === 'available'
        );
        if (trailers.length) {
            list.innerHTML += `
            <div class="kcs-boat-section-divider">Anhänger</div>` +
            trailers.map(b => `
            <div class="kcs-boat-row" data-boat-id="${b.id}">
                <div class="kcs-boat-row__info">
                    <div class="kcs-boat-row__name">${b.name}</div>
                    <div class="kcs-boat-row__meta">Anhänger</div>
                </div>
            </div>`).join('');
        }

        // Events
        list.querySelectorAll('.kcs-boat-row').forEach(row => {
            const id = parseInt(row.dataset.boatId, 10);
            row.addEventListener('click', e => {
                if (e.target.closest('.kcs-start-btn')) return;
                document.dispatchEvent(new CustomEvent('kcs:open-boat-detail', { detail: { boatId: id } }));
            });
        });
    }

    // ── Filter rendern ────────────────────────────────────────────

    function renderFilters(boats) {
        const container = document.getElementById('boatFilters');
        if (!container) return;

        // Verfügbare Sitze ermitteln (nur available, kein Anhänger)
        const availSeats = new Set(
            boats
                .filter(b => b.status === 'available' && (b.type_full||'').toLowerCase() !== 'anhänger')
                .map(b => seatsKey(b.seats))
        );
        const seatOpts = ['all','1','2','3','4','5+'].filter(s => s === 'all' || availSeats.has(s));

        container.innerHTML = `
            <div class="kcs-filter-ownership">
                ${[['all','Alle'],['club','Verein'],['private','Privat']].map(([val,lbl]) => `
                    <button class="kcs-ownership-btn${_filter.ownership === val ? ' active' : ''}"
                            data-filter-ownership="${val}">${lbl}</button>
                `).join('')}
            </div>
            <div class="kcs-filter-divider"></div>
            <div class="kcs-filter-label">Sitzplätze</div>
            <div class="kcs-filter-seats">
                ${seatOpts.map(s => `
                    <button class="kcs-filter-pill${_filter.seats === s ? ' active' : ''}"
                            data-filter-seats="${s}">${s === 'all' ? 'Alle' : s}</button>
                `).join('')}
            </div>`;

        container.addEventListener('click', e => {
            const btn = e.target.closest('[data-filter-ownership],[data-filter-seats]');
            if (!btn) return;
            if (btn.dataset.filterOwnership !== undefined) _filter.ownership = btn.dataset.filterOwnership;
            if (btn.dataset.filterSeats     !== undefined) _filter.seats     = btn.dataset.filterSeats;
            renderFilters(_boats);
            renderBoats(_boats);
        });
    }

    // ── Load ──────────────────────────────────────────────────────

    async function loadBoats() {
        try {
            const data = await KCS.api('/boats/all.php');
            _boats = Array.isArray(data) ? data : (data.boats || []);
            window._kcsBoats = _boats;
            renderFilters(_boats);
            renderBoats(_boats);
        } catch (e) {
            const list = document.getElementById('boatList');
            if (list) list.innerHTML = '<div class="kcs-error"><i class="bi bi-exclamation-triangle"></i> Bootsliste konnte nicht geladen werden.</div>';
        }
    }

    // ── Init + Events ─────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', () => {
        loadBoats();
        const si = document.getElementById('boatSearch');
        if (si) si.addEventListener('input', e => { _search = e.target.value.trim(); renderBoats(_boats); });
    });

    document.addEventListener('kcs:poll',          loadBoats);
    document.addEventListener('kcs:refresh-boats', loadBoats);
    document.addEventListener('kcs:select-boat',   e => { _selectedBoatId = e.detail?.boatId ?? null; renderBoats(_boats); });
    document.addEventListener('kcs:deselect-boat', () => { _selectedBoatId = null; renderBoats(_boats); });

})();
