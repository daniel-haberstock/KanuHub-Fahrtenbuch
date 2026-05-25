/**
 * Fahrtenbuch Beta — dashboard.js
 * Task 05: KPI-Cards, CTA-Hero, CTA-Grid, Statistik-Teaser
 */
(function () {
    'use strict';

    // ── KPI-Cards ─────────────────────────────────────────────────

    function setKpi(id, value, sub) {
        const card = document.getElementById(id);
        if (!card) return;
        const valEl = card.querySelector('.kcs-kpi-value');
        const subEl = card.querySelector('.kcs-kpi-sub') || document.getElementById(id + 'Sub');
        if (valEl) valEl.textContent = value;
        if (subEl && sub !== undefined) subEl.innerHTML = sub;
    }

    function updateBoatKpis() {
        const boats = window._kcsBoats || [];
        if (!boats.length) return;

        const nonTrailer = boats.filter(b => (b.type_full || '').toLowerCase() !== 'anhänger');
        const onWater    = nonTrailer.filter(b => b.status === 'onWater').length;
        const total      = nonTrailer.length;

        // Verfügbar = Vereinsboote die weder auf Fahrt, beschädigt noch
        // aktuell/bald (10h) reserviert sind
        const avail = boats.filter(b =>
            b.is_club_boat &&
            b.status === 'available' &&
            !b.reserved_soon
        ).length;

        setKpi('kpiOnWater', onWater, 'von ' + total + ' Booten');
        setKpi('kpiAvail',   avail,   'Vereinsboote am Steg');
    }

    async function loadKpis() {
        try {
            const d = await KCS.api('/statistics/kpis.php');
            if (!d.success) return;

            const fmtKm = km => km.toLocaleString('de-DE', { minimumFractionDigits: 1 }) + ' km';

            const fmtCompareSub = (diffKm, prevKm, prevLabel) => {
                const sign    = diffKm >= 0 ? '+' : '';
                const diffStr = sign + diffKm.toLocaleString('de-DE', { minimumFractionDigits: 1 }) + ' km';
                let pctHtml   = '';
                if (prevKm > 0) {
                    const pct     = Math.round(diffKm / prevKm * 100);
                    const pctSign = pct >= 0 ? '+' : '';
                    const cls     = pct >= 0 ? 'kcs-kpi-pct--pos' : 'kcs-kpi-pct--neg';
                    pctHtml = ` <span class="kcs-kpi-pct ${cls}">${pctSign}${pct}%</span>`;
                }
                return `${diffStr}${pctHtml}`;
            };

            setKpi('kpiToday', fmtKm(d.today_km),
                   d.today_trips === 1 ? '1 Fahrt heute' : d.today_trips + ' Fahrten heute');

            const monthSub = d.month_prev_label !== undefined
                ? fmtCompareSub(d.month_diff_km, d.month_km - d.month_diff_km, d.month_prev_label)
                : d.month_label;
            setKpi('kpiMonth', fmtKm(d.month_km), monthSub);

            const seasonSub = d.season_prev_label !== undefined
                ? fmtCompareSub(d.season_diff_km, d.season_km - d.season_diff_km, d.season_prev_label)
                : d.season_label;
            setKpi('kpiSeason', fmtKm(d.season_km), seasonSub);

        } catch (e) { /* silent */ }
    }

    // ── CTA Hero ──────────────────────────────────────────────────

    function renderHero(activeTrip) {
        const el = document.getElementById('ctaHero');
        if (!el) return;

        if (activeTrip) {
            el.innerHTML = `
                <button class="kcs-cta-hero kcs-cta-hero--red" id="ctaEndTrip">
                    <span class="kcs-cta-hero__icon"><i class="bi bi-flag-fill"></i></span>
                    <span class="kcs-cta-hero__body">
                        <span class="kcs-cta-hero__title">Fahrt beenden</span>
                        <span class="kcs-cta-hero__sub">${activeTrip.boat_name} · seit ${activeTrip.start_time}</span>
                    </span>
                    <i class="bi bi-chevron-right kcs-cta-hero__arrow"></i>
                </button>`;
            el.querySelector('#ctaEndTrip').addEventListener('click', () =>
                document.dispatchEvent(new CustomEvent('kcs:end-trip', { detail: {
                    tripId:      activeTrip.id,
                    isTrailer:   !!activeTrip.is_trailer,
                    sourceTable: activeTrip.source_table || 'trips',
                } })));
        } else {
            el.innerHTML = `
                <button class="kcs-cta-hero kcs-cta-hero--green" id="ctaStartTrip">
                    <span class="kcs-cta-hero__icon"><i class="bi bi-play-circle-fill"></i></span>
                    <span class="kcs-cta-hero__body">
                        <span class="kcs-cta-hero__title">Neue Fahrt beginnen</span>
                        <span class="kcs-cta-hero__sub" id="ctaHeroSub">Boot wählen und los</span>
                    </span>
                    <i class="bi bi-chevron-right kcs-cta-hero__arrow"></i>
                </button>`;
            el.querySelector('#ctaStartTrip').addEventListener('click', () =>
                document.dispatchEvent(new CustomEvent('kcs:start-trip', { detail: { boatId: null } })));
        }
    }

    async function checkActiveTrip() {
        if (!KCS.loggedIn) { renderHero(null); return; }
        try {
            const data = await KCS.api('/trips/active.php');
            const trips = Array.isArray(data) ? data : (data.trips || []);
            const mine  = trips.find(t => t.crew_names && KCS.memberName &&
                t.crew_names.includes(KCS.memberName.split(' ')[0]));
            renderHero(mine || null);
        } catch (e) {
            renderHero(null);
        }
    }

    // ── CTA-Grid Buttons ──────────────────────────────────────────

    function bindCtaGrid() {
        document.getElementById('ctaJoin')?.addEventListener('click', () =>
            document.dispatchEvent(new CustomEvent('kcs:open-join')));
        document.getElementById('ctaReserve')?.addEventListener('click', () =>
            document.dispatchEvent(new CustomEvent('kcs:open-reserve')));
        document.getElementById('ctaBacklog')?.addEventListener('click', () =>
            document.dispatchEvent(new CustomEvent('kcs:start-trip', { detail: { boatId: null, backlog: true } })));
        document.getElementById('ctaMember')?.addEventListener('click', () => {
            if (KCS.loggedIn) {
                window.location.href = (KCS.basePath || '') + '/statistik/';
            } else {
                KCS.openModal('memberLoginModal');
            }
        });
        document.getElementById('ctaTeaserLogin')?.addEventListener('click', () =>
            KCS.openModal('loginModal'));
    }

    // ── Active-Trip-Count für Anschließen-Button ──────────────────

    function updateJoinSub(trips) {
        const sub = document.getElementById('ctaJoinSub');
        if (!sub) return;
        const n = trips.length;
        sub.textContent = n === 0 ? 'Keine aktiven Fahrten' :
                          n === 1 ? '1 aktive Fahrt' : n + ' aktive Fahrten';
    }

    // ── Statistik-Teaser (immer blurred) ─────────────────────────

    async function loadTeaserLeaderboard() {
        const wrap = document.getElementById('teaserLeaderboard');
        if (!wrap) return;
        try {
            const s    = KCS.currentSeason || '';
            const data = await KCS.api('/statistics/leaderboard.php?limit=4&season=' + encodeURIComponent(s));
            if (!data.success || !data.rows?.length) return;

            wrap.innerHTML = data.rows.map((r, i) => `
                <div class="kcs-teaser-row">
                    <span class="kcs-teaser-rank">${['🥇','🥈','🥉','4.'][i] || (i+1) + '.'}</span>
                    <span class="kcs-teaser-name">${r.name}</span>
                    <span class="kcs-teaser-km">${r.km.toLocaleString('de-DE', {minimumFractionDigits:1})} km</span>
                </div>`).join('');
        } catch (e) { /* silent */ }
    }

    // ── Termine ───────────────────────────────────────────────────

    function escHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function openEventInfoModal(evt) {
        const titleEl = document.getElementById('eventInfoTitle');
        const bodyEl  = document.getElementById('eventInfoBody');
        if (!titleEl || !bodyEl) return;

        const name = evt.title || evt.name || '';
        const time = evt.time || (evt.start_time ? evt.start_time.slice(0, 5) : '');
        const dateFormatted = evt.date ? evt.date.split('-').reverse().join('.') : '';

        // Titel mit farbigem Punkt
        titleEl.innerHTML = evt.color
            ? `<span class="kcs-event-info-dot" style="background:${escHtml(evt.color)}"></span>${escHtml(name)}`
            : escHtml(name);

        // Verantwortlich
        const verantwHtml = evt.verantwortlich
            ? `<div class="kcs-event-info-row">
                   <span class="kcs-event-info-label">Verantwortlich</span>
                   <span>${escHtml(evt.verantwortlich)}</span>
               </div>`
            : '';

        // Kategorie
        const katHtml = evt.kategorie
            ? `<div class="kcs-event-info-row">
                   <span class="kcs-event-info-label">Kategorie</span>
                   <span>${escHtml(evt.kategorie)}</span>
               </div>`
            : '';

        const hasDesc = evt.description && evt.description.trim();

        bodyEl.innerHTML = `
            <div class="kcs-event-info">
                <div class="kcs-event-info-meta">
                    <div class="kcs-event-info-row">
                        <span class="kcs-event-info-label">Datum</span>
                        <span>${dateFormatted}</span>
                    </div>
                    <div class="kcs-event-info-row">
                        <span class="kcs-event-info-label">Uhrzeit</span>
                        <span>${time ? time + ' Uhr' : 'Ganztägig'}</span>
                    </div>
                    ${verantwHtml}
                    ${katHtml}
                </div>
                ${hasDesc ? `<div class="kcs-event-info-desc">${evt.description}</div>` : ''}
            </div>`;

        KCS.openModal('eventInfoModal');
    }

    async function loadEvents() {
        const list = document.getElementById('eventsList');
        const card = document.getElementById('eventsCard');
        const todayBadge = document.getElementById('eventsToday');
        if (!list || !card) return;

        try {
            const data = await KCS.api('/events/upcoming.php?limit=5');
            const events = data.events || data || [];
            if (!events.length) return;

            const todayStr = new Date().toISOString().slice(0, 10);
            const todayCount = events.filter(e => e.date === todayStr).length;

            if (todayBadge && todayCount > 0) {
                todayBadge.textContent = 'Heute: ' + todayCount;
                todayBadge.style.display = '';
            }

            list.innerHTML = events.map(e => {
                const isToday = e.date === todayStr;
                const d = new Date(e.date + 'T00:00:00');
                const dateStr = d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
                const colorDot = e.color
                    ? `<span class="kcs-event-dot" style="background:${escHtml(e.color)}"></span>`
                    : '';
                return `
                <div class="kcs-event-row">
                    <div class="kcs-event-date">
                        ${isToday ? '<span class="kcs-badge kcs-badge--red kcs-badge--sm">HEUTE</span>'
                                  : '<span class="kcs-event-day">' + dateStr + '</span>'}
                        <span class="kcs-event-time">${e.start_time ? e.start_time.slice(0,5) : ''}</span>
                    </div>
                    <div class="kcs-event-body">
                        <div class="kcs-event-title">${colorDot}${escHtml(e.title || e.name || '')}</div>
                    </div>
                    <div class="kcs-event-actions">
                        <button class="kcs-btn kcs-btn--ghost kcs-btn--icon kcs-btn--sm kcs-event-info-btn"
                                title="Details"
                                data-event='${JSON.stringify(e).replace(/'/g, "&#39;")}'>
                            <i class="bi bi-info-circle"></i>
                        </button>
                        ${isToday ? `<button class="kcs-btn kcs-btn--green kcs-btn--sm kcs-event-join"
                                             data-event='${JSON.stringify(e).replace(/'/g, "&#39;")}'>Teilnehmen →</button>` : ''}
                    </div>
                </div>`;
            }).join('');

            // Info-Buttons
            list.querySelectorAll('.kcs-event-info-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    try { openEventInfoModal(JSON.parse(btn.dataset.event)); } catch (_) {}
                });
            });

            // Teilnehmen-Buttons → Start-Trip Wizard mit Event vorbelegt
            list.querySelectorAll('.kcs-event-join').forEach(btn => {
                btn.addEventListener('click', () => {
                    try {
                        const evt = JSON.parse(btn.dataset.event);
                        document.dispatchEvent(new CustomEvent('kcs:start-trip', { detail: { boatId: null, event: evt } }));
                    } catch (_) {}
                });
            });

            card.style.display = '';
        } catch (e) { /* silent */ }
    }

    // ── Init ──────────────────────────────────────────────────────

    async function init() {
        // Listener zuerst setzen — boats.js kann vor checkActiveTrip fertig sein
        document.addEventListener('kcs:boats-loaded', e => {
            const sub = document.getElementById('ctaHeroSub');
            if (sub && e.detail?.available !== undefined) {
                sub.textContent = e.detail.available + ' Boote verfügbar';
            }
            updateBoatKpis();

            // Aktive Fahrten für "Anschließen"-Sub
            KCS.api('/trips/active.php').then(d => {
                updateJoinSub(Array.isArray(d) ? d : (d.trips || []));
            }).catch(() => {});
        }, { once: false });

        await checkActiveTrip();
        bindCtaGrid();
        loadKpis();
        loadTeaserLeaderboard();
        loadEvents();
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('kcs:poll', () => {
        checkActiveTrip();
        loadKpis();
        updateBoatKpis();
    });
    document.addEventListener('kcs:login',  init);
    document.addEventListener('kcs:logout', () => renderHero(null));

})();
