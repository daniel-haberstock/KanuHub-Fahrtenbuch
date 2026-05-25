<!-- Modal: Verbesserungsvorschlag / Feedback -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="feedbackModalLabel">
                    <i class="bi bi-envelope-plus"></i> Verbesserungen und Erweiterungen
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">

                <!-- Einleitung -->
                <p class="mb-2">
                    Das Fahrtenbuch ist unser gemeinsames Werkzeug – entwickelt von uns für uns alle.
                    Hast du eine Idee, wie wir es noch besser machen können? Oder ist dir ein Fehler aufgefallen?
                    Dann schreib uns direkt – wir freuen uns über jede Rückmeldung! 🙌
                </p>
                <div class="alert alert-warning py-2 mb-3 small">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>Bitte beachte:</strong> Dieses Formular ist nicht der richtige Weg, wenn dein Name oder
                    dein Boot in der Liste fehlt oder du dir weitere voreingestellte Strecken wünschst.
                    Für solche Anliegen wende dich bitte direkt an den Vorstand oder den Admin.
                </div>

                <form id="feedbackForm">

                    <!-- Mitglied -->
                    <div class="mb-3">
                        <label for="feedback_member" class="form-label">Wer bist du?</label>
                        <input type="text" class="form-control kcs-ac-input" id="feedback_member"
                               name="member_name" data-ac-source="members"
                               placeholder="Mitglied suchen..." autocomplete="off">
                        <input type="hidden" id="feedback_member_id" name="member_id">
                        <div class="form-text">Optional – hilft uns, bei Rückfragen die richtige Person zu kontaktieren.</div>
                    </div>

                    <!-- Nachricht -->
                    <div class="mb-3">
                        <label for="feedback_message" class="form-label required">Deine Nachricht</label>
                        <textarea class="form-control" id="feedback_message" name="message" rows="4"
                                  placeholder="Beschreib deine Idee oder den Fehler so genau wie möglich…" required></textarea>
                    </div>

                    <!-- Fehler-/Warnhinweise -->
                    <div id="feedback_warnings"></div>

                    <!-- Erfolgsmeldung (nach Versand eingeblendet) -->
                    <div id="feedback_success" class="alert alert-success d-none">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <span id="feedback_success_text"></span>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="feedbackCloseBtn" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-info text-white" id="sendFeedbackBtn">
                    <i class="bi bi-send"></i> Senden
                </button>
            </div>
        </div>
    </div>
</div>
