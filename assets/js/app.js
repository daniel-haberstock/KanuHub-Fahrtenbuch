/**
 * Fahrtenbuch Beta — app.js
 * Initialisierung, globale Helpers, AJAX-Basis, Toast-System, Polling
 */
(function () {
    'use strict';

    // ── App-Daten aus PHP-Bootstrap ──────────────────────────────
    const appEl = document.getElementById('app-data');
    const KCS = window.KCS = JSON.parse(appEl ? appEl.textContent : '{}');
    KCS.modal = {}; // Bootstrap-Modal-Instanzen Cache

    // ── AJAX-Helper ──────────────────────────────────────────────
    /**
     * Führt einen API-Request durch.
     * @param {string} endpoint  Pfad relativ zu apiBase, z.B. '/boats/available.php'
     * @param {object} [options] fetch-Options
     * @returns {Promise<any>}
     */
    KCS.api = async function (endpoint, options = {}) {
        const url = KCS.apiBase + endpoint;
        const defaults = {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        };
        const res = await fetch(url, Object.assign(defaults, options));
        if (res.status === 401) {
            document.dispatchEvent(new CustomEvent('kcs:need-login'));
            throw new Error('Nicht angemeldet');
        }
        if (!res.ok) {
            const text = await res.text().catch(() => '');
            throw new Error('API-Fehler ' + res.status + ': ' + text.slice(0, 200));
        }
        return res.json();
    };

    KCS.post = function (endpoint, data) {
        const body = new FormData();
        Object.entries(data).forEach(([k, v]) => {
            if (v !== null && v !== undefined) body.append(k, v);
        });
        return KCS.api(endpoint, { method: 'POST', body });
    };

    // ── Toast-System ─────────────────────────────────────────────
    KCS.toast = function (msg, type = '') {
        const el = document.createElement('div');
        el.className = 'kcs-toast' + (type ? ' kcs-toast--' + type : '');
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3500);
    };

    // ── Modal-Helper ─────────────────────────────────────────────
    KCS.openModal = function (id) {
        if (!KCS.modal[id]) {
            KCS.modal[id] = new bootstrap.Modal(document.getElementById(id));
        }
        KCS.modal[id].show();
    };
    KCS.closeModal = function (id) {
        if (KCS.modal[id]) KCS.modal[id].hide();
    };

    // ── Login-Redirect bei 401 ────────────────────────────────────
    document.addEventListener('kcs:need-login', function () {
        KCS.openModal('loginModal');
    });

    // ── Polling ──────────────────────────────────────────────────
    let _pollTimer = null;
    KCS.startPolling = function (intervalMs) {
        clearInterval(_pollTimer);
        _pollTimer = setInterval(function () {
            document.dispatchEvent(new CustomEvent('kcs:poll'));
        }, intervalMs || 30000);
    };
    KCS.stopPolling = function () { clearInterval(_pollTimer); };

    // ── Feedback ─────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#sendFeedbackBtn')) return;
        const msg  = document.getElementById('feedbackMessage');
        const name = document.getElementById('feedbackMemberName');
        const res  = document.getElementById('feedbackResult');
        if (!msg || !msg.value.trim()) { if (msg) msg.focus(); return; }
        const btn = e.target.closest('#sendFeedbackBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Senden…';
        KCS.post('/feedback/send.php', {
            message: msg.value.trim(),
            member_name: name ? name.value.trim() : '',
        }).then(() => {
            if (res) res.innerHTML = '<div class="kcs-success"><i class="bi bi-check-circle-fill"></i> Danke für dein Feedback!</div>';
            msg.value = '';
            setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('feedbackModal'))?.hide(), 1800);
        }).catch(() => {
            if (res) res.innerHTML = '<div class="kcs-error">Senden fehlgeschlagen — bitte erneut versuchen.</div>';
        }).finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send"></i> Senden';
        });
    });

    // ── DOM Ready ────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        KCS.startPolling(30000);

        // Delegation: Modals öffnen via data-kcs-modal
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-kcs-modal]');
            if (btn) KCS.openModal(btn.dataset.kcsModal);
        });

        // Delegation: Boot-Detail via data-boat-id
        document.addEventListener('click', function (e) {
            const row = e.target.closest('[data-boat-detail]');
            if (row) {
                const boatId = row.dataset.boatDetail;
                document.dispatchEvent(new CustomEvent('kcs:open-boat-detail', { detail: { boatId } }));
            }
        });
    });

})();
