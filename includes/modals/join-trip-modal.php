<!-- Modal: Einer Fahrt anschließen -->
<div class="modal fade" id="joinTripModal" tabindex="-1" aria-labelledby="joinTripModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="joinTripModalLabel">
                    <i class="bi bi-people-fill"></i> Einer Fahrt anschließen
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    Wähle eine aktive Gruppe oder einen Solo-Paddler, um dich mit deinem eigenen Boot anzuschließen.
                </p>

                <div id="joinTripLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Lädt...</span>
                    </div>
                </div>

                <div id="joinTripContent" style="display: none;">
                    <!-- Aktive Gruppen -->
                    <div id="joinTripGroups">
                        <h6 class="fw-bold mb-2"><i class="bi bi-people-fill text-primary"></i> Aktive Gruppen</h6>
                        <div id="joinTripGroupsList" class="mb-3">
                            <!-- Dynamisch befüllt -->
                        </div>
                    </div>

                    <!-- Solo-Paddler -->
                    <div id="joinTripSolo">
                        <h6 class="fw-bold mb-2"><i class="bi bi-person text-secondary"></i> Solo-Paddler</h6>
                        <div id="joinTripSoloList" class="mb-3">
                            <!-- Dynamisch befüllt -->
                        </div>
                    </div>

                    <!-- Leer-Hinweis -->
                    <div id="joinTripEmpty" class="text-center py-4 text-muted" style="display: none;">
                        <i class="bi bi-inbox display-4"></i>
                        <p class="mt-2">Aktuell sind keine Boote auf dem Wasser.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
            </div>
        </div>
    </div>
</div>
