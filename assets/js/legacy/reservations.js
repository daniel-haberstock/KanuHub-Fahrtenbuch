/**
 * Fahrtenbuch – Reservierungen & Schäden
 */

function openReserveBoatModal(preselectedBoatId) {
    preselectedBoatId = preselectedBoatId || null;
    $('#reserveBoatForm')[0].reset();
    $('#reserve_boat_id').val('');
    $('#reserve_warnings').html('');

    $.ajax({
        url: '/api/boats/club.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            window.kcsAcData = window.kcsAcData || {};
            window.kcsAcData.reserveBoats = (data.boats || []).map(function(boat) {
                return {
                    text: boat.boat_name + ' (' + boat.boat_type + ')',
                    id: boat.id,
                    search: (boat.boat_name + ' ' + boat.boat_type).toLowerCase()
                };
            });

            if (preselectedBoatId) {
                var found = window.kcsAcData.reserveBoats.find(function(b) { return b.id == preselectedBoatId; });
                if (found) {
                    $('#reserve_boat').val(found.text);
                    $('#reserve_boat_id').val(found.id);
                }
            }
        }
    });

    // kcs:select-Handler: setzt Hidden-Field wenn Boot gewählt wird
    $('#reserve_boat').off('kcs:select').on('kcs:select', function(e, item) {
        $('#reserve_boat_id').val(item ? item.id : '');
    }).off('input.reserveBoat').on('input.reserveBoat', function() {
        if (!$(this).val()) $('#reserve_boat_id').val('');
    });

    // kcs:select-Handler: setzt Member-ID wenn Mitglied gewählt wird
    $('#reserve_member').off('kcs:select').on('kcs:select', function(e, item) {
        $('#reserve_member_id').val(item ? item.id : '');
    }).off('input.reserveMember').on('input.reserveMember', function() {
        // ID löschen wenn Feld manuell geändert → externe Person möglich
        $('#reserve_member_id').val('');
    });

    var modal = new bootstrap.Modal(document.getElementById('reserveBoatModal'));
    modal.show();
}

function saveReservation() {
    if (!$('#reserve_boat_id').val()) {
        showError('Bitte ein Boot aus der Liste auswählen.');
        return;
    }
    $.ajax({
        url: '/api/reservations/create.php',
        method: 'POST',
        data: $('#reserveBoatForm').serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('reserveBoatModal')).hide();
                loadBoatLists();
                showSuccess('Reservierung wurde erfolgreich erstellt!');
            } else {
                showError(response.message || 'Fehler beim Speichern der Reservierung');
            }
        },
        error: function() { showError('Fehler beim Speichern der Reservierung'); }
    });
}

function openReservationActionModal(reservationId, boatId, boatName) {
    $('#reservation_action_id').val(reservationId);
    $('#reservation_boat_id').val(boatId);
    $('#reservation_boat_name').text(boatName);
    var modal = new bootstrap.Modal(document.getElementById('reservationActionModal'));
    modal.show();
}

function cancelReservation() {
    const boatName = $('#reservation_boat_name').text();
    $('#cancel_reservation_info').html(`<p><strong>Boot:</strong> ${boatName}</p>`);
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmCancelReservationModal'));
    confirmModal.show();
}

function confirmCancelReservation() {
    $.ajax({
        url: '/api/reservations/cancel.php',
        method: 'POST',
        data: { reservation_id: $('#reservation_action_id').val() },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('confirmCancelReservationModal')).hide();
                bootstrap.Modal.getInstance(document.getElementById('reservationActionModal')).hide();
                loadBoatLists();
                showSuccess('Reservierung wurde erfolgreich abgebrochen');
            } else {
                showError(response.message || 'Fehler beim Abbrechen der Reservierung');
            }
        },
        error: function() { showError('Fehler beim Abbrechen der Reservierung'); }
    });
}

function startTripFromReservation() {
    $.ajax({
        url: '/api/reservations/start-trip.php',
        method: 'POST',
        data: { reservation_id: $('#reservation_action_id').val() },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('reservationActionModal')).hide();
                loadBoatLists();
                showSuccess('Fahrt wurde erfolgreich gestartet');
            } else {
                showError(response.message || 'Fehler beim Starten der Fahrt');
            }
        },
        error: function() { showError('Fehler beim Starten der Fahrt'); }
    });
}

function checkReservationDateWarning() {
    const startDate = $('#reserve_start_date').val();
    const endDate = $('#reserve_end_date').val();
    if (startDate && endDate && startDate !== endDate) {
        var rDayMsg = getSystemMessage('club_boat_return_same_day');
        if (!rDayMsg) {
            rDayMsg = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> <strong>Hinweis:</strong> Bitte beachte, dass Vereinsboote am Ende des Tages wieder im Bootshaus sein müssen.</div>';
        }
        $('#reserve_warnings').html(rDayMsg);
    } else {
        $('#reserve_warnings').html('');
    }
}

function checkReservationMemberWarning() {
    const memberName = $('#reserve_member').val();
    const memberId = $('#reserve_member_id').val();
    if (memberName && !memberId) {
        var rExtMsg = getSystemMessage('reservation_external');
        if (!rExtMsg) {
            rExtMsg = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> <strong>Wichtiger Hinweis:</strong> Externe dürfen die Boote nur mit einem aktiven Mitglied verwenden. Das aktive Mitglied haftet für Schäden.</div>';
        }
        $('#reserve_warnings').html(rExtMsg);
    }
}

function saveDamage() {
    $.ajax({
        url: '/api/damages/create.php',
        method: 'POST',
        data: $('#damageReportForm').serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('damageReportModal')).hide();
                loadBoatLists();
                showSuccess('Schaden wurde erfolgreich gemeldet!');
            } else {
                showError(response.message || 'Fehler beim Melden des Schadens');
            }
        },
        error: function() { showError('Fehler beim Melden des Schadens'); }
    });
}

function openDamageReportModal(preselectedBoatId) {
    preselectedBoatId = preselectedBoatId || null;
    $('#damageReportForm')[0].reset();
    var _dmgPrefix = window.FahrtenbuchConfig ? window.FahrtenbuchConfig.clubBoatPrefix : 'Verein';
    $.ajax({
        url: '/api/boats/club.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            let options = '<option value="">-- Boot auswählen (nur ' + _dmgPrefix + ' Boote) --</option>';
            if (data.boats) {
                data.boats.forEach(function(boat) {
                    const selected = boat.id == preselectedBoatId ? 'selected' : '';
                    options += `<option value="${boat.id}" ${selected}>${boat.boat_name} (${boat.boat_type})</option>`;
                });
            }
            $('#damage_boat').html(options);
            if (preselectedBoatId) $('#damage_boat_id').val(preselectedBoatId);
        }
    });
    var modal = new bootstrap.Modal(document.getElementById('damageReportModal'));
    modal.show();
}
