<!-- Event-Info Modal -->
<div class="modal fade" id="eventInfoModal" tabindex="-1" aria-labelledby="eventInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventInfoModalLabel">
                    <i class="bi bi-calendar-event me-2"></i>
                    <span id="eventInfoTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <!-- Quelle (Badge) -->
                <div class="mb-3" id="eventInfoSource"></div>

                <!-- Datum & Uhrzeit -->
                <div class="d-flex gap-4 mb-3">
                    <div>
                        <small class="text-muted d-block">Datum</small>
                        <strong id="eventInfoDate"></strong>
                    </div>
                    <div>
                        <small class="text-muted d-block">Uhrzeit</small>
                        <strong id="eventInfoTime"></strong>
                    </div>
                </div>

                <!-- Verantwortlich -->
                <div class="mb-3" id="eventInfoResponsibleRow">
                    <small class="text-muted d-block">Verantwortlich</small>
                    <strong id="eventInfoResponsible"></strong>
                </div>

                <!-- Beschreibung -->
                <div class="mb-0">
                    <small class="text-muted d-block mb-1">Beschreibung</small>
                    <div id="eventInfoDescription" class="border rounded p-2 bg-light" style="white-space: pre-line;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>
