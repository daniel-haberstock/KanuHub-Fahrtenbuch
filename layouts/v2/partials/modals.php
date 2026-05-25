<?php
// Tasks 08–12: Modal-HTML-Skelette
?>

<!-- Mitglieder-Login Modal (Form-POST zu /statistik/ — Original-Logik) -->
<?php require_once __DIR__ . '/../../../includes/modals/member-login-modal.php'; ?>

<!-- Login Modal (Task 12 – kcs:need-login Event) -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content kcs-modal">
            <div class="kcs-modal__header">
                <h5 class="kcs-modal__title" id="loginModalLabel">Anmelden</h5>
                <button type="button" class="kcs-modal__close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body kcs-modal__body" id="loginModalBody">
                <!-- wird via Task 12 gefüllt -->
            </div>
        </div>
    </div>
</div>

<!-- Boot-Steckbrief Modal (Task 08) -->
<div class="modal fade" id="boatDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content kcs-modal">
            <div class="kcs-modal__header kcs-bd-header">
                <div class="kcs-bd-header__left">
                    <h5 class="kcs-modal__title" id="boatDetailTitle">Boot</h5>
                    <div class="kcs-bd-header__sub" id="boatDetailSubtitle"></div>
                </div>
                <div class="kcs-bd-header__right">
                    <div class="kcs-bd-header__meta" id="boatDetailMeta"></div>
                    <button type="button" class="kcs-modal__close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="modal-body kcs-modal__body" id="boatDetailBody">
                <!-- wird via boat-detail.js gefüllt -->
            </div>
        </div>
    </div>
</div>

<!-- Fahrt starten Modal (Task 09) -->
<div class="modal fade" id="startTripModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl" id="startTripDialog">
        <div class="modal-content kcs-modal">
            <div class="kcs-modal__header">
                <h5 class="kcs-modal__title" id="startTripTitle">Fahrt starten</h5>
                <button type="button" class="kcs-modal__close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body kcs-modal__body" id="startTripBody">
                <!-- wird via trips.js + Task 09 gefüllt -->
            </div>
            <div class="modal-footer kcs-modal__footer" id="startTripFooter"></div>
        </div>
    </div>
</div>

<!-- Fahrt beenden Modal (Task 10) -->
<div class="modal fade" id="endTripModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content kcs-modal">
            <div class="kcs-modal__header">
                <h5 class="kcs-modal__title" id="endTripTitle">Fahrt beenden</h5>
                <button type="button" class="kcs-modal__close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body kcs-modal__body" id="endTripBody">
                <!-- wird via trips.js + Task 10 gefüllt -->
            </div>
            <div class="modal-footer kcs-modal__footer" id="endTripFooter"></div>
        </div>
    </div>
</div>

<!-- Fahrt anschließen Modal (Task 11) -->
<div class="modal fade" id="joinTripModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg" id="joinTripDialog">
        <div class="modal-content kcs-modal">
            <div class="kcs-modal__header">
                <h5 class="kcs-modal__title">Fahrt anschließen</h5>
                <button type="button" class="kcs-modal__close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body kcs-modal__body" id="joinTripBody">
                <!-- wird via trips.js + Task 11 gefüllt -->
            </div>
            <div class="modal-footer kcs-modal__footer" id="joinTripFooter"></div>
        </div>
    </div>
</div>

<!-- Reservieren Modal (Task 12) -->
<div class="modal fade" id="reserveModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content kcs-modal">
            <div class="kcs-modal__header">
                <h5 class="kcs-modal__title">Reservieren</h5>
                <button type="button" class="kcs-modal__close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body kcs-modal__body" id="reserveBody">
                <!-- wird via app.js + Task 12 gefüllt -->
            </div>
            <div class="modal-footer kcs-modal__footer" id="reserveFooter"></div>
        </div>
    </div>
</div>

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content kcs-modal">
            <div class="kcs-modal__header">
                <h5 class="kcs-modal__title" id="feedbackModalLabel"><i class="bi bi-envelope-plus"></i> Verbesserungen &amp; Feedback</h5>
                <button type="button" class="kcs-modal__close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body kcs-modal__body">
                <p class="kcs-feedback__intro">
                    Das Fahrtenbuch ist unser gemeinsames Werkzeug. Hast du eine Idee oder ist dir ein Fehler aufgefallen?
                </p>
                <div class="kcs-feedback__hint">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Dieses Formular ist nicht für fehlende Namen oder Boote — dafür bitte direkt an den Admin wenden.
                </div>
                <form id="feedbackForm">
                    <div class="kcs-form-group">
                        <label for="feedbackMemberName">Wer bist du? <span class="kcs-optional">(optional)</span></label>
                        <input type="text" class="kcs-form-control" id="feedbackMemberName"
                               name="member_name" placeholder="Dein Name"
                               value="<?= htmlspecialchars($memberName ?? '') ?>">
                    </div>
                    <div class="kcs-form-group">
                        <label for="feedbackMessage">Deine Nachricht <span class="kcs-required">*</span></label>
                        <textarea class="kcs-form-control" id="feedbackMessage" name="message" rows="4"
                                  placeholder="Beschreib deine Idee oder den Fehler so genau wie möglich…" required></textarea>
                    </div>
                    <div id="feedbackResult"></div>
                </form>
            </div>
            <div class="modal-footer kcs-modal__footer">
                <button type="button" class="kcs-btn kcs-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="kcs-btn kcs-btn--primary" id="sendFeedbackBtn">
                    <i class="bi bi-send"></i> Senden
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reservierungs-Aktionen Modal -->
<div class="modal fade" id="reservationActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content kcs-modal">
            <div class="kcs-modal__header">
                <h5 class="kcs-modal__title" id="resActionTitle">Reservierung</h5>
                <button type="button" class="kcs-modal__close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body kcs-modal__body" id="resActionBody">
                <!-- wird von status.js befüllt -->
            </div>
        </div>
    </div>
</div>

<!-- Termin-Info Modal -->
<div class="modal fade" id="eventInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content kcs-modal">
            <div class="kcs-modal__header" id="eventInfoHeader">
                <h5 class="kcs-modal__title" id="eventInfoTitle"></h5>
                <button type="button" class="kcs-modal__close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body kcs-modal__body" id="eventInfoBody"></div>
            <div class="modal-footer kcs-modal__footer">
                <button type="button" class="kcs-btn kcs-btn--ghost" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- Wetter Modal -->
<div class="modal fade" id="wetterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content kcs-modal">
            <div class="kcs-modal__header">
                <h5 class="kcs-modal__title">Wetter & Sturmwarnung</h5>
                <button type="button" class="kcs-modal__close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body kcs-modal__body" id="wetterBody">
                <!-- wird via Task 07 gefüllt -->
            </div>
        </div>
    </div>
</div>
