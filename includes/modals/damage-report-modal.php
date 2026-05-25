<!-- Modal: Schaden melden -->
<div class="modal fade" id="damageReportModal" tabindex="-1" aria-labelledby="damageReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="damageReportModalLabel">
                    <i class="bi bi-exclamation-triangle"></i> Schaden melden
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <form id="damageReportForm">
                    <input type="hidden" id="damage_boat_id" name="boat_id">

                    <!-- Bootsauswahl -->
                    <div class="mb-3">
                        <label for="damage_boat" class="form-label required">Boot</label>
                        <select class="form-select" id="damage_boat" name="boat" required size="7">
                            <option value="">-- Boot auswählen (nur Fahrtenbuch Boote) --</option>
                        </select>
                    </div>

                    <!-- Schadensart -->
                    <div class="mb-3">
                        <label for="damage_type" class="form-label required">Schadensart</label>
                        <select class="form-select" id="damage_type" name="damage_type" required size="3">
                            <option value="">-- Schadensart auswählen --</option>
                            <option value="Boot eingeschränkt nutzbar">Boot eingeschränkt nutzbar</option>
                            <option value="Boot nicht nutzbar">Boot nicht nutzbar</option>
                        </select>
                    </div>

                    <!-- Schadensbeschreibung -->
                    <div class="mb-3">
                        <label for="damage_description" class="form-label required">Schadensbeschreibung</label>
                        <textarea class="form-control" id="damage_description" name="damage_description" rows="4"
                                  placeholder="Bitte beschreiben Sie den Schaden ausführlich" required></textarea>
                    </div>

                    <!-- Name -->
                    <div class="mb-3">
                        <label for="damage_reporter" class="form-label">Name</label>
                        <input type="text" class="form-control" id="damage_reporter" name="reporter_name"
                               list="membersListDamage" placeholder="Mitglied auswählen oder Namen eingeben">
                        <datalist id="membersListDamage">
                            <!-- Wird via JavaScript gefüllt -->
                        </datalist>
                        <input type="hidden" id="damage_reporter_id" name="reporter_id">
                    </div>

                    <!-- Hinweis (wird dynamisch aus Systemmeldungen geladen) -->
                    <div id="damage_report_info_msg" class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Wichtiger Hinweis:</strong> Bitte geben Sie Ihren Namen für Rückfragen an.
                        Dies dient nicht der Schuldzuweisung, sondern hilft uns, den Schaden korrekt zu erfassen
                        und bei Bedarf Rückfragen zu stellen.
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-danger" id="saveDamageBtn">Schaden melden</button>
            </div>
        </div>
    </div>
</div>
