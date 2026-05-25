<!-- Modal: Neue Fahrt beginnen -->
<div class="modal fade" id="newTripModal" tabindex="-1" aria-labelledby="newTripModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newTripModalLabel">
                    <i class="bi bi-play-circle"></i> Neue Fahrt beginnen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Linke Spalte: Formular -->
                    <div id="tripFormCol" class="col-12">
                        <form id="newTripForm">
                            <input type="hidden" id="trip_boat_id" name="boat_id">
                            <input type="hidden" id="trip_boat_type" name="boat_type">
                            <input type="hidden" id="trip_is_backlog" name="is_backlog" value="0">
                            <!-- Gruppen-/Event-Daten (von events.js oder join-trip befüllt) -->
                            <input type="hidden" id="trip_group_id" name="trip_group_id">
                            <input type="hidden" id="trip_group_source_id" name="source_id">
                            <input type="hidden" id="trip_group_source_type" name="source_type">
                            <input type="hidden" id="trip_group_name" name="group_name">

                            <!-- Event-Banner (wird dynamisch von events.js/trips.js befüllt) -->
                            <div id="tripEventBanner" style="display: none;"></div>

                            <!-- Bootsauswahl -->
                            <div class="mb-3">
                                <label for="trip_boat" class="form-label required">Boot</label>
                                <input type="text" class="form-control kcs-ac-input" id="trip_boat" name="boat_name"
                                       data-ac-source="boats" autocomplete="off" placeholder="Boot auswählen oder Namen eingeben" required>
                                <small class="form-text text-muted">Boot aus Liste wählen oder frei eingeben (z.B. externes Boot)</small>
                            </div>

                            <!-- Start-Datum und Uhrzeit -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="trip_start_date" class="form-label required">Start-Datum</label>
                                    <input type="date" class="form-control form-control-lg" id="trip_start_date" name="start_date" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="trip_start_time" class="form-label required">Start-Uhrzeit</label>
                                    <input type="time" class="form-control form-control-lg time-picker-touch"
                                           id="trip_start_time" name="start_time" required>
                                </div>
                            </div>

                            <!-- Rückkehr-Datum und Uhrzeit -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="trip_end_date" class="form-label">Rückkehr-Datum</label>
                                    <input type="date" class="form-control form-control-lg" id="trip_end_date" name="end_date">
                                </div>
                                <div class="col-md-6">
                                    <label for="trip_end_time" class="form-label">Rückkehr-Uhrzeit</label>
                                    <input type="time" class="form-control form-control-lg time-picker-touch"
                                           id="trip_end_time" name="end_time">
                                </div>
                            </div>

                            <!-- Strecke -->
                            <div class="mb-3">
                                <label for="trip_route" class="form-label">Strecke</label>
                                <input type="text" class="form-control kcs-ac-input" id="trip_route" name="route_custom"
                                       data-ac-source="routes" autocomplete="off" placeholder="Freie Eingabe oder aus Liste wählen">
                                <input type="hidden" id="trip_route_id" name="route_id">
                            </div>

                            <!-- Gewässer (nur bei manueller Strecken-Eingabe) -->
                            <div class="mb-3" id="water_field_container" style="display: none;">
                                <label for="trip_water" class="form-label">Gewässer</label>
                                <input type="text" class="form-control kcs-ac-input" id="trip_water" name="water_custom"
                                       data-ac-source="waters" autocomplete="off" placeholder="Gewässer eingeben" value="Bodensee">
                                <input type="hidden" id="trip_water_id" name="water_id">
                                <small class="form-text text-muted">Gewässer aus Liste wählen oder frei eingeben</small>
                            </div>

                            <!-- Entfernung (nur bei Backlog sichtbar) -->
                            <div class="mb-3" id="trip_distance_container" style="display: none;">
                                <label for="trip_distance" class="form-label">Entfernung (km)</label>
                                <input type="number" class="form-control form-control-lg" id="trip_distance"
                                       name="distance" step="0.1" min="0" placeholder="z.B. 12.5">
                            </div>

                            <!-- Crew -->
                            <div id="crewContainer">
                                <!-- Wird dynamisch gefüllt basierend auf Bootstyp -->
                            </div>

                            <!-- Warnungen -->
                            <div id="trip_warnings"></div>

                            <!-- GPS-Tracker (wird per JS ein-/ausgeblendet) -->
                            <div id="trackerSection" style="display:none;" class="mt-3 border-top pt-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-geo-alt-fill text-success"></i> GPS-Tracker verwenden
                                    <span class="fw-normal text-muted small">(optional)</span>
                                </label>
                                <div id="trackerList">
                                    <div class="text-muted small"><i class="bi bi-arrow-repeat"></i> Tracker werden geladen…</div>
                                </div>
                                <input type="hidden" id="selected_tracker_id" name="tracker_id" value="">
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-info-circle"></i>
                                    Aufzeichnung startet automatisch, sobald ihr das Bootshaus verlasst.
                                </small>
                            </div>

                        </form>
                    </div>

                    <!-- Rechte Spalte: Boot-Steckbrief (wird per JS ein-/ausgeblendet) -->
                    <div id="tripBoatInfoCol" class="col-12 col-md-5" style="display: none;">
                        <div class="card bg-light border-0 h-100">
                            <div class="card-body">
                                <h6 class="card-title mb-3">
                                    <i class="bi bi-info-circle text-primary"></i> Boot-Steckbrief
                                </h6>
                                <div id="tripBoatInfoImg" class="text-center mb-3" style="display: none;">
                                    <img src="" alt="Boot" class="img-fluid rounded" style="max-height: 120px;">
                                </div>
                                <div id="tripBoatInfoContent">
                                    <!-- Dynamisch befüllt -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" id="saveTripBtn">Fahrt beginnen</button>
            </div>
        </div>
    </div>
</div>
