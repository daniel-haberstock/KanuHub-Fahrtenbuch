<?php
/**
 * Mitglieder-Login Modal
 * Statisches Modal — Form-POST zu /statistik/ (Original-Fahrtenbuch-Logik).
 * Beta-Design: kcs-modal-Klassen statt Bootstrap-Standard.
 */
?>
<!-- ── Mitglieder-Login Modal ──────────────────────────────────────── -->
<div class="modal fade" id="memberLoginModal" tabindex="-1" aria-labelledby="memberLoginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content kcs-modal">
            <div class="kcs-modal__header">
                <h5 class="kcs-modal__title" id="memberLoginModalLabel">
                    <i class="bi bi-person-circle"></i> Mitglieder-Bereich
                </h5>
                <button type="button" class="kcs-modal__close" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body kcs-modal__body">
                <div class="kcs-st-section">
                    <p class="kcs-form-hint" style="margin-bottom:1rem">
                        Webseiten-Zugangsdaten verwenden, um auf Statistiken,
                        Arbeitsdienst und Bootsverwaltung zuzugreifen.
                    </p>
                    <div id="memberLoginError" class="kcs-st-error" style="display:none">
                        <i class="bi bi-exclamation-circle"></i>
                        <span id="memberLoginErrorText"></span>
                    </div>
                    <form id="memberLoginForm"
                          action="<?= defined('APP_BASE_PATH') ? APP_BASE_PATH : '' ?>/statistik/"
                          method="POST" autocomplete="off">
                        <div class="kcs-et-field">
                            <label class="kcs-et-label" for="memberLoginEmail">E-Mail-Adresse</label>
                            <input type="email" class="kcs-input" name="email" id="memberLoginEmail"
                                   placeholder="deine@email.de" required autofocus
                                   readonly onfocus="this.removeAttribute('readonly');"
                                   autocomplete="new-password">
                        </div>
                        <div class="kcs-et-field">
                            <label class="kcs-et-label" for="memberLoginPassword">Passwort</label>
                            <input type="password" class="kcs-input" name="password" id="memberLoginPassword"
                                   placeholder="••••••••" required autocomplete="one-time-code">
                        </div>
                        <button type="submit" class="kcs-btn kcs-btn--green kcs-btn--full" id="memberLoginBtn">
                            <i class="bi bi-box-arrow-in-right"></i> Anmelden &amp; zum Mitglieder-Bereich
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
