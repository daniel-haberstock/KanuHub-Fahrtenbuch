<!-- Modal: Boot reservieren -->
<div class="modal fade" id="reserveBoatModal" tabindex="-1" aria-labelledby="reserveBoatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="reserveBoatModalLabel">
                    <i class="bi bi-calendar-check"></i> Boot reservieren
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <form id="reserveBoatForm">
                    <!-- Bootsauswahl -->
                    <div class="mb-3">
                        <label for="reserve_boat" class="form-label required">Boot</label>
                        <input type="text" class="form-control kcs-ac-input" id="reserve_boat"
                               data-ac-source="reserveBoats"
                               placeholder="Boot suchen..." autocomplete="off" required>
                        <input type="hidden" id="reserve_boat_id" name="boat">
                    </div>

                    <!-- Mitglied -->
                    <div class="mb-3">
                        <label for="reserve_member" class="form-label required">Mitglied</label>
                        <input type="text" class="form-control kcs-ac-input" id="reserve_member" name="member_name"
                               data-ac-source="members"
                               placeholder="Mitglied suchen..." autocomplete="off" required>
                        <input type="hidden" id="reserve_member_id" name="member_id">
                    </div>

                    <!-- Reservierung Start -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="reserve_start_date" class="form-label required">Reservierung von (Datum)</label>
                            <input type="date" class="form-control" id="reserve_start_date" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="reserve_start_time" class="form-label required">Uhrzeit</label>
                            <input type="time" class="form-control" id="reserve_start_time" name="start_time" required>
                        </div>
                    </div>

                    <!-- Reservierung Ende -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="reserve_end_date" class="form-label required">Reservierung bis (Datum)</label>
                            <input type="date" class="form-control" id="reserve_end_date" name="end_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="reserve_end_time" class="form-label required">Uhrzeit</label>
                            <input type="time" class="form-control" id="reserve_end_time" name="end_time" required>
                        </div>
                    </div>

                    <!-- Reservierungsgrund -->
                    <div class="mb-3">
                        <label for="reserve_reason" class="form-label required">Reservierungsgrund</label>
                        <textarea class="form-control" id="reserve_reason" name="reason" rows="3"
                                  placeholder="Bitte Grund für die Reservierung angeben" required></textarea>
                    </div>

                    <!-- Telefon -->
                    <div class="mb-3">
                        <label for="reserve_phone" class="form-label">Telefon für Rückfragen</label>
                        <input type="tel" class="form-control" id="reserve_phone" name="phone"
                               placeholder="+49 123 456789">
                    </div>

                    <!-- Warnungen -->
                    <div id="reserve_warnings"></div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-warning" id="saveReservationBtn">Reservieren</button>
            </div>
        </div>
    </div>
</div>
