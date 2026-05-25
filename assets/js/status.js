/**
 * Fahrtenbuch Beta — status.js
 * Task 06: Status-Panel — Aktive Fahrten, Reservierungen, Schäden
 */
(function () {
    'use strict';

    // ── Hilfsfunktionen ───────────────────────────────────────────

    function escHtml(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function setCount(id, n) {
        const el = document.getElementById(id);
        if (el) { el.textContent = n; el.style.display = n > 0 ? '' : 'none'; }
    }

    function isMyTrip(trip) {
        if (!KCS.loggedIn || !KCS.memberName) return false;
        const first = KCS.memberName.split(' ')[0];
        return trip.crew_names && trip.crew_names.includes(first);
    }

    function timeSince(dateStr, timeStr) {
        if (!dateStr || !timeStr) return '';
        const start = new Date(dateStr + 'T' + timeStr);
        const mins  = Math.round((Date.now() - start) / 60000);
        if (mins < 60)  return mins + ' min';
        const h = Math.floor(mins / 60), m = mins % 60;
        return h + 'h ' + (m > 0 ? m + 'min' : '');
    }

    // ── Aktive Fahrten ────────────────────────────────────────────

    function renderActiveTrips(trips) {
        const el = document.getElementById('activeTrips');
        if (!el) return;
        setCount('countOnWater', trips.length);

        if (!trips.length) {
            el.innerHTML = '<div class="kcs-sp-empty">Niemand auf dem Wasser</div>';
            return;
        }

        el.innerHTML = trips.map(t => {
            const mine    = isMyTrip(t);
            const dur     = timeSince(t.start_date, t.start_time_raw || t.start_time);
            const overdue = t.is_overdue ? ' kcs-sp-item--overdue' : '';
            return `
            <div class="kcs-sp-item kcs-sp-item--clickable${overdue}"
                 data-trip-id="${t.id}"
                 data-is-trailer="${t.is_trailer ? '1' : '0'}"
                 data-source-table="${t.source_table || 'trips'}">
                <div class="kcs-sp-item__header">
                    <span class="kcs-sp-item__name">${t.crew_names || '—'}</span>
                    ${t.is_overdue ? '<span class="kcs-sp-badge kcs-sp-badge--red">Überfällig</span>' : ''}
                    ${mine ? '<span class="kcs-sp-badge kcs-sp-badge--mine">Meine Fahrt</span>' : ''}
                </div>
                <div class="kcs-sp-item__meta">
                    <span>${t.boat_name || ''}${t.boat_type ? ' · ' + t.boat_type : ''}</span>
                    ${dur ? `<span class="kcs-sp-dur">${dur}</span>` : ''}
                </div>
                <div class="kcs-sp-item__meta">Seit ${t.start_time || ''}${t.trip_group_name ? ' · <i class="bi bi-people-fill"></i> ' + t.trip_group_name : ''}</div>
                <div class="kcs-sp-item__end-hint"><i class="bi bi-flag-fill"></i> Tippen zum Beenden</div>
            </div>`;
        }).join('');

        el.querySelectorAll('.kcs-sp-item--clickable').forEach(item => {
            item.addEventListener('click', () =>
                document.dispatchEvent(new CustomEvent('kcs:end-trip', {
                    detail: {
                        tripId:      parseInt(item.dataset.tripId, 10),
                        isTrailer:   item.dataset.isTrailer === '1',
                        sourceTable: item.dataset.sourceTable || 'trips',
                    }
                })));
        });
    }

    async function loadActiveTrips() {
        try {
            const data = await KCS.api('/trips/active.php');
            renderActiveTrips(Array.isArray(data) ? data : (data.trips || []));
        } catch (e) {
            const el = document.getElementById('activeTrips');
            if (el) el.innerHTML = '<div class="kcs-sp-empty">Fehler beim Laden</div>';
        }
    }

    // ── Reservierungen ────────────────────────────────────────────

    function fmtReservTime(start, end) {
        // "28.04.2026 14:30" → "28.04. 14:30 – 15:30"
        const [datePart, startTime] = (start || '').split(' ');
        const endTime = (end || '').split(' ')[1] || '';
        const shortDate = datePart ? datePart.slice(0, 6) : '';
        return shortDate + ' ' + (startTime || '') + ' – ' + (endTime || '');
    }

    function renderReservations(reservations) {
        const el = document.getElementById('activeReservations');
        if (!el) return;
        setCount('countReserved', reservations.length);

        if (!reservations.length) {
            el.innerHTML = '<div class="kcs-sp-empty">Keine Reservierungen</div>';
            return;
        }

        el.innerHTML = reservations.map(r => {
            const member = escHtml(r.member_display || r.member_name || '—');
            const time   = escHtml(fmtReservTime(r.start_datetime, r.end_datetime));
            return `
            <div class="kcs-sp-item kcs-sp-item--clickable"
                 data-res-id="${r.id}"
                 data-boat-id="${r.boat_id}"
                 data-boat-name="${escHtml(r.boat_name)}"
                 data-member-id="${r.member_id || ''}"
                 data-member-name="${escHtml(r.member_name || '')}"
                 data-member-display="${member}"
                 data-seats="${r.seats || 1}">
                <div class="kcs-sp-item__header">
                    <span class="kcs-sp-item__name">${escHtml(r.boat_name)}</span>
                </div>
                <div class="kcs-sp-item__meta"><i class="bi bi-person"></i> ${member}</div>
                <div class="kcs-sp-item__meta"><i class="bi bi-clock"></i> ${time}</div>
                ${r.reason ? `<div class="kcs-sp-item__note">${escHtml(r.reason)}</div>` : ''}
                <div class="kcs-sp-item__end-hint"><i class="bi bi-hand-index-thumb"></i> Tippen für Optionen</div>
            </div>`;
        }).join('');

        el.querySelectorAll('.kcs-sp-item--clickable').forEach(item => {
            item.addEventListener('click', () => openReservationActions({
                id:            parseInt(item.dataset.resId, 10),
                boat_id:       parseInt(item.dataset.boatId, 10),
                boat_name:     item.dataset.boatName,
                member_id:     item.dataset.memberId ? parseInt(item.dataset.memberId, 10) : null,
                member_name:   item.dataset.memberName,
                member_display: item.dataset.memberDisplay,
                seats:         parseInt(item.dataset.seats, 10) || 1,
            }));
        });
    }

    function openReservationActions(r) {
        const titleEl = document.getElementById('resActionTitle');
        const bodyEl  = document.getElementById('resActionBody');
        if (!titleEl || !bodyEl) return;

        const member = escHtml(r.member_display || r.member_name || '—');
        titleEl.textContent = r.boat_name;
        bodyEl.innerHTML = `
            <div class="kcs-res-action-meta">
                <span><i class="bi bi-person"></i> ${member}</span>
            </div>
            <div class="d-grid gap-2 mt-3">
                <button class="kcs-btn kcs-btn--green kcs-btn--lg" id="resActionStart">
                    <i class="bi bi-play-circle-fill"></i> Fahrt beginnen
                </button>
                <button class="kcs-btn kcs-btn--ghost kcs-res-action-danger" id="resActionCancel">
                    <i class="bi bi-x-circle"></i> Reservierung abbrechen
                </button>
            </div>`;

        KCS.openModal('reservationActionModal');

        document.getElementById('resActionCancel').addEventListener('click', async () => {
            if (!confirm('Reservierung für ' + r.boat_name + ' wirklich abbrechen?')) return;
            try {
                const form = new FormData();
                form.append('reservation_id', r.id);
                const res  = await fetch(KCS.apiBase + '/reservations/cancel.php', { method: 'POST', body: form });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Fehler');
                KCS.closeModal('reservationActionModal');
                KCS.toast('Reservierung abgebrochen', 'success');
                loadReservations();
            } catch (e) { KCS.toast(e.message, 'error'); }
        }, { once: true });

        document.getElementById('resActionStart').addEventListener('click', () => {
            KCS.closeModal('reservationActionModal');
            document.dispatchEvent(new CustomEvent('kcs:start-trip', { detail: {
                boatId:      r.boat_id,
                reservation: {
                    reservationId:  r.id,
                    memberId:       r.member_id,
                    memberName:     r.member_name,
                    memberDisplay:  r.member_display || r.member_name,
                    seats:          r.seats,
                },
            }}));
        }, { once: true });
    }

    async function loadReservations() {
        try {
            const data = await KCS.api('/boats/reserved.php');
            renderReservations(Array.isArray(data) ? data : (data.reservations || []));
        } catch (e) {
            const el = document.getElementById('activeReservations');
            if (el) el.innerHTML = '<div class="kcs-sp-empty">Fehler beim Laden</div>';
        }
    }

    // ── Schäden / Wartung ─────────────────────────────────────────

    function renderDamages(damages) {
        const el = document.getElementById('damagedBoats');
        if (!el) return;
        setCount('countDamaged', damages.length);

        if (!damages.length) {
            el.innerHTML = '<div class="kcs-sp-empty">Keine Schäden gemeldet</div>';
            return;
        }

        el.innerHTML = damages.map(d => `
            <div class="kcs-sp-item kcs-sp-item--damage">
                <div class="kcs-sp-item__name">${d.boat_name}</div>
                <div class="kcs-sp-item__meta">${d.damage_description || d.damage_type || '—'}</div>
                <div class="kcs-sp-item__meta kcs-sp-item__time">
                    <i class="bi bi-calendar3"></i> ${d.created_at ? d.created_at.slice(0, 10) : ''}
                </div>
            </div>`).join('');
    }

    async function loadDamages() {
        try {
            const data = await KCS.api('/boats/damaged.php');
            renderDamages(Array.isArray(data) ? data : (data.damages || []));
        } catch (e) {
            const el = document.getElementById('damagedBoats');
            if (el) el.innerHTML = '<div class="kcs-sp-empty">Fehler beim Laden</div>';
        }
    }

    // ── Init ──────────────────────────────────────────────────────

    function loadAll() {
        loadActiveTrips();
        loadReservations();
        loadDamages();
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadAll();

        const btnRes = document.getElementById('btnReserveFromPanel');
        if (btnRes) btnRes.addEventListener('click', () =>
            document.dispatchEvent(new CustomEvent('kcs:open-reserve')));
    });

    document.addEventListener('kcs:poll',        loadAll);
    document.addEventListener('kcs:trip-ended',  loadAll);
    document.addEventListener('kcs:trip-started',loadAll);

})();
