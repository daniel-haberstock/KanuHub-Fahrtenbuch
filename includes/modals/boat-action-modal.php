<!-- Modal: Boot-Aktionen (wenn auf Boot geklickt) -->
<div class="modal fade" id="boatActionModal" tabindex="-1" aria-labelledby="boatActionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="boatActionModalLabel">
                    <i class="bi bi-water"></i> <span id="boat_action_name"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="boat_action_id">
                <input type="hidden" id="boat_action_is_club">

                <div class="row">
                    <!-- Linke Spalte: Aktionen -->
                    <div class="col-12" id="boat_action_actions_col">
                        <p class="text-muted mb-3">
                            <strong>Typ:</strong> <span id="boat_action_type"></span><br>
                            <strong>Sitzplätze:</strong> <span id="boat_action_seats"></span>
                        </p>

                        <!-- Anhänger-Hinweis -->
                        <div id="trailer_warning" class="alert alert-danger" style="display: none;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>WICHTIG:</strong> Für Schäden am Anhänger trägt der Ausleiher Verantwortung!
                        </div>

                        <!-- Bootspezifischer Hinweis -->
                        <div id="boat_note_start" class="alert alert-primary border-start border-4 border-primary" style="display: none;">
                            <i class="bi bi-info-circle-fill"></i>
                            <strong>Hinweis zu diesem Boot:</strong><br>
                            <span id="boat_note_start_text"></span>
                        </div>

                        <div class="d-grid gap-2">
                            <button class="btn btn-info btn-lg text-white" id="boat_action_video" style="display: none;">
                                <i class="bi bi-play-btn"></i> Anleitungsvideo ansehen
                            </button>
                            <button class="btn btn-primary btn-lg" id="boat_action_start_trip">
                                <i class="bi bi-play-circle"></i> Neue Fahrt beginnen
                            </button>
                            <button class="btn btn-info btn-lg text-white" id="boat_action_backlog_trip">
                                <i class="bi bi-journal-plus"></i> Fahrt nachtragen
                            </button>
                            <button class="btn btn-outline-primary btn-lg" id="boat_action_join_trip">
                                <i class="bi bi-people-fill"></i> Einer Fahrt anschließen
                            </button>
                            <button class="btn btn-warning btn-lg" id="boat_action_reserve" style="display: none;">
                                <i class="bi bi-calendar-check"></i> Boot reservieren
                            </button>
                            <button class="btn btn-danger btn-lg" id="boat_action_damage" style="display: none;">
                                <i class="bi bi-exclamation-triangle"></i> Schaden melden
                            </button>
                        </div>
                    </div>

                    <!-- Rechte Spalte: Steckbrief (dynamisch befüllt via JS) -->
                    <div class="col-md-8" id="boat_action_details_col" style="display: none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
            </div>
        </div>
    </div>
</div>
