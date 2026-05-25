/**
 * Fahrtenbuch Beta — header.js
 * Uhr, Member-Button Event
 */
(function () {
    'use strict';

    // ── Uhr ───────────────────────────────────────────────────────
    function updateClock() {
        const now   = new Date();
        const timeEl = document.getElementById('headerTime');
        const dateEl = document.getElementById('headerDate');
        if (timeEl) {
            timeEl.textContent = now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        }
        if (dateEl && !dateEl.dataset.set) {
            dateEl.dataset.set = '1';
            dateEl.textContent = now.toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long' });
            // Datum täglich aktualisieren (Mitternacht)
            const ms = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1) - now;
            setTimeout(() => { dateEl.dataset.set = ''; updateClock(); }, ms);
        }
    }

    // ── Member-Button → Mitgliederbereich ─────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        updateClock();
        setInterval(updateClock, 1000);

        const btn = document.getElementById('btnOpenMember');
        if (btn) {
            btn.addEventListener('click', () =>
                document.dispatchEvent(new CustomEvent('kcs:open-member')));
        }
    });

})();
