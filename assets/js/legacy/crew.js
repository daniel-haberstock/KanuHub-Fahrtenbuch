/**
 * Fahrtenbuch – Crew-Verwaltung
 * Crew-Felder generieren, Default-Crew, Mitglieder-Warnungen
 */

let currentCrewCount = 0;

function generateCrewInputs(seats, containerId) {
    currentCrewCount = seats;
    let html = '<h6>Crew</h6>';
    for (let i = 1; i <= seats; i++) {
        html += buildCrewField(i, i === 1);
    }
    html += `
        <div id="extraCrewContainer"></div>
        <div class="mt-2 mb-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="addCrewBtn" onclick="addExtraCrewMember()">
                <i class="bi bi-plus-circle"></i> Weitere Mitfahrer hinzufügen
            </button>
        </div>
    `;
    $('#' + containerId).html(html);
    bindCrewInputHandlers();
}

function buildCrewField(index, isRequired, isExtra) {
    const label = index === 1 ? 'Fahrer 1' : 'Fahrer ' + index;
    const removeBtn = isExtra ? `<button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-crew-btn" onclick="removeExtraCrewMember(this)" title="Entfernen"><i class="bi bi-x-lg"></i></button>` : '';
    return `
        <div class="mb-2 crew-input-group ${isExtra ? 'd-flex align-items-end gap-1' : ''}">
            <div class="${isExtra ? 'flex-grow-1' : ''}">
                <label for="crew_${index}" class="form-label ${isRequired ? 'required' : ''}">${label}</label>
                <input type="text" class="form-control crew-input kcs-ac-input" id="crew_${index}" name="crew_${index}"
                       data-ac-source="members" autocomplete="off" placeholder="Mitglied auswählen oder Namen eingeben" ${isRequired ? 'required' : ''}>
                <input type="hidden" id="crew_${index}_id" name="crew_${index}_id">
            </div>
            ${removeBtn}
        </div>
    `;
}

function addExtraCrewMember() {
    currentCrewCount++;
    const html = buildCrewField(currentCrewCount, false, true);
    $('#extraCrewContainer').append(html);
    bindCrewInputHandlers();
    $('#crew_' + currentCrewCount).focus();
}

function removeExtraCrewMember(btn) {
    $(btn).closest('.crew-input-group').remove();
    renumberCrewFields();
}

function renumberCrewFields() {
    let index = 1;
    $('#crewContainer .crew-input-group').each(function() {
        const textInput = $(this).find('input[type=text]');
        const hiddenInput = $(this).find('input[type=hidden]');
        const label = $(this).find('label');
        textInput.attr('id', 'crew_' + index).attr('name', 'crew_' + index);
        hiddenInput.attr('id', 'crew_' + index + '_id').attr('name', 'crew_' + index + '_id');
        label.attr('for', 'crew_' + index);
        label.text(index === 1 ? 'Eigentümer / Fahrer 1' : 'Fahrer ' + index);
        index++;
    });
    currentCrewCount = index - 1;
}

function bindCrewInputHandlers() {
    $('.crew-input').off('input kcs:select').on('kcs:select', function(e, item) {
        var crewIdField = $(this).parent().find('input[type=hidden]');
        if (item && item.id) {
            crewIdField.val(item.id);
        }
        checkCrewMemberWarning();
        // Tracker-Verfügbarkeit für Fahrer 1 prüfen
        if ($(this).attr('id') === 'crew_1' && typeof loadTrackerOptions === 'function') {
            loadTrackerOptions(item && item.id ? item.id : null);
        }
    }).on('input', function() {
        var inputValue = $(this).val();
        var crewIdField = $(this).parent().find('input[type=hidden]');
        var found = _kcsAcFindExact('members', inputValue);
        crewIdField.val(found ? found.id : '');
        checkCrewMemberWarning();
        // Tracker-Sektion ausblenden wenn Fahrer 1 geleert wird
        if ($(this).attr('id') === 'crew_1' && !found && typeof loadTrackerOptions === 'function') {
            loadTrackerOptions(null);
        }
    });
}

function fillDefaultCrew(boat) {
    if (boat.default_crew_1_name) {
        $('#crew_1').val(boat.default_crew_1_name);
        $('#crew_1_id').val(boat.default_crew_1);
        if (typeof loadTrackerOptions === 'function') {
            loadTrackerOptions(boat.default_crew_1 || null);
        }
    }
    if (boat.default_crew_2_name) {
        $('#crew_2').val(boat.default_crew_2_name);
        $('#crew_2_id').val(boat.default_crew_2);
    }
}

function checkCrewMemberWarning() {
    let hasNonActiveMember = false;
    let hasActiveMember = false;
    let hasPassiveMember = false;

    $('.crew-input').each(function() {
        const val = $(this).val();
        const memberId = $(this).next('input[type=hidden]').val();
        if (val && memberId && window.membersData) {
            const member = window.membersData.find(m => m.id == memberId);
            if (member) {
                if (member.status === 'Passiv') {
                    hasNonActiveMember = true;
                    hasPassiveMember = true;
                } else {
                    hasActiveMember = true;
                }
            } else {
                hasActiveMember = true;
            }
        } else if (val && !memberId) {
            hasNonActiveMember = true;
        }
    });

    const boatName = $('#trip_boat').val();
    var _clubPrefix = window.FahrtenbuchConfig ? window.FahrtenbuchConfig.clubBoatPrefix : 'Verein';
    var _clubName = window.FahrtenbuchConfig ? window.FahrtenbuchConfig.clubName : 'Verein';
    const isClubBoat = boatName && boatName.toUpperCase().startsWith(_clubPrefix.toUpperCase());

    $('#trip_warnings .alert-info').remove();
    $('#trip_warnings .alert-danger-kcs').remove();
    $('#trip_warnings .alert-warning-passive').remove();

    if (isClubBoat && hasNonActiveMember && !hasActiveMember) {
        var msgHtml = getSystemMessage('club_boat_external', 'alert-danger-kcs');
        if (!msgHtml) {
            msgHtml = '<div class="alert alert-danger alert-danger-kcs"><i class="bi bi-exclamation-triangle"></i> <strong>WICHTIG:</strong> Die Benutzung von ' + _clubName + '-Booten ist für Externe und passive Mitglieder nur möglich, wenn ein aktives ' + _clubName + '-Mitglied dabei ist.</div>';
        }
        $('#trip_warnings').append(msgHtml);
    }
    else if (hasPassiveMember) {
        var msgHtml = getSystemMessage('passive_member', 'alert-warning-passive');
        if (!msgHtml) {
            msgHtml = '<div class="alert alert-warning alert-warning-passive"><i class="bi bi-info-circle"></i> <strong>Hinweis:</strong> Passive Mitglieder werden wie Externe behandelt.</div>';
        }
        $('#trip_warnings').append(msgHtml);
    }
    else if (hasNonActiveMember && !isClubBoat && !hasPassiveMember) {
        var msgHtml = getSystemMessage('external_guest');
        if (!msgHtml) {
            msgHtml = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> <strong>Hinweis:</strong> Sie haben einen oder mehrere Fahrer eingegeben, die nicht in der Mitgliederliste sind. Diese werden als Gäste erfasst.</div>';
        }
        $('#trip_warnings').append(msgHtml);
    }
}

function checkTrailerWarning() {
    const boatTypeInput = $('#trip_boat_type');
    const boatType = boatTypeInput.val() || boatTypeInput.data('boat-type');
    $('#trip_warnings .alert-danger-trailer').remove();
    if (boatType === 'Anhänger') {
        var trailerHtml = getSystemMessage('trailer_warning', 'alert-danger-trailer');
        if (!trailerHtml) {
            trailerHtml = '<div class="alert alert-danger alert-danger-trailer"><i class="bi bi-exclamation-triangle-fill"></i> <strong>WICHTIG:</strong> Für Schaden am Anhänger ist der Fahrer verantwortlich.</div>';
        }
        $('#trip_warnings').prepend(trailerHtml);
    }
}
