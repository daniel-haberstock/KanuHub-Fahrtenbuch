<!-- Modal: Reservierung abbrechen bestätigen -->
<div class="modal fade" id="confirmCancelReservationModal" tabindex="-1" aria-labelledby="confirmCancelReservationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="confirmCancelReservationModalLabel">
                    <i class="bi bi-exclamation-triangle"></i> Reservierung abbrechen
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Möchten Sie diese Reservierung wirklich abbrechen?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle"></i> Diese Aktion kann nicht rückgängig gemacht werden.
                </div>
                <div id="cancel_reservation_info">
                    <!-- Wird dynamisch gefüllt mit Bootsnamen -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Nein, behalten
                </button>
                <button type="button" class="btn btn-danger" id="confirmCancelReservationBtn">
                    <i class="bi bi-trash"></i> Ja, abbrechen
                </button>
            </div>
        </div>
    </div>
</div>
