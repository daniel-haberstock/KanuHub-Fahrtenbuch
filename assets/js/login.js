/**
 * Fahrtenbuch Beta — login.js
 * Task 12: Login Modal
 */
(function () {
    'use strict';

    function renderForm() {
        const b = document.getElementById('loginModalBody');
        if (!b) return;
        b.innerHTML = `
            <div class="kcs-st-section">
                <div class="kcs-et-field">
                    <label class="kcs-et-label" for="loginEmail">E-Mail</label>
                    <input type="email" class="kcs-input" id="loginEmail" autocomplete="username" placeholder="name@beispiel.de">
                </div>
                <div class="kcs-et-field">
                    <label class="kcs-et-label" for="loginPassword">Passwort</label>
                    <input type="password" class="kcs-input" id="loginPassword" autocomplete="current-password" placeholder="••••••••">
                </div>
                <div id="loginError" class="kcs-st-error" style="display:none"></div>
                <button class="kcs-btn kcs-btn--green kcs-btn--full" id="loginSubmitBtn">
                    <i class="bi bi-box-arrow-in-right"></i> Anmelden
                </button>
            </div>`;

        const emailIn = b.querySelector('#loginEmail');
        const passIn  = b.querySelector('#loginPassword');
        const btn     = b.querySelector('#loginSubmitBtn');
        const err     = b.querySelector('#loginError');

        btn.addEventListener('click', () => submit(emailIn, passIn, btn, err));
        passIn.addEventListener('keydown', e => { if (e.key === 'Enter') submit(emailIn, passIn, btn, err); });
    }

    async function submit(emailIn, passIn, btn, err) {
        const email = emailIn.value.trim();
        const pass  = passIn.value;
        if (!email || !pass) {
            err.style.display = ''; err.textContent = 'E-Mail und Passwort eingeben.'; return;
        }

        btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> …';
        err.style.display = 'none';

        try {
            const form = new FormData();
            form.append('email', email);
            form.append('password', pass);
            const res  = await fetch(KCS.apiBase + '/statistics/login.php', { method: 'POST', body: form });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Anmeldung fehlgeschlagen');

            // Session speichern via dedicated endpoint
            const sessForm = new FormData();
            sessForm.append('member_id',   data.member_id);
            sessForm.append('member_name', data.member_name);
            await fetch(KCS.apiBase + '/statistics/set-session.php', { method: 'POST', body: sessForm });

            location.href = KCS.basePath + '/statistik/';
        } catch (e) {
            err.style.display = ''; err.textContent = e.message;
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Anmelden';
        }
    }

    // ── Trigger ───────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', () => {
        const loginModal = document.getElementById('loginModal');
        if (loginModal) {
            loginModal.addEventListener('show.bs.modal', renderForm);
        }

        // Header Anmelden button
        const headerBtn = document.getElementById('headerLoginBtn');
        if (headerBtn) headerBtn.addEventListener('click', () => KCS.openModal('loginModal'));
    });

    document.addEventListener('kcs:need-login', () => KCS.openModal('loginModal'));

})();
