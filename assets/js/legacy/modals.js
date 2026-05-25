/**
 * Fahrtenbuch – Modal-Handler & Boot-Aktionen
 * showBoatActionModal, setupModalHandlers, Video, Statistik
 */

/**
 * Baut eine geordnete Liste von Detail-Zeilen für den Steckbrief auf.
 * Definierte Paare werden als eine Zeile zusammengefasst, wenn BEIDE vorhanden sind.
 */
function _buildDetailRows(details, labelMap) {
    var pairDefs = [
        ['modell',             'hersteller'],
        ['laenge',             'breite'],
        ['gewicht',            'material'],
        ['empfohlenes_gewicht','koerpergroesse'],
        ['erfahrungslevel',    'stabilitaet']
    ];
    var pairMap     = {};   // key → partner
    var pairLeaders = {};   // erstes Element jedes Paares
    pairDefs.forEach(function(pair) {
        pairMap[pair[0]] = pair[1];
        pairMap[pair[1]] = pair[0];
        pairLeaders[pair[0]] = true;
    });

    var rows = [];
    var skip = {};

    for (var key in details) {
        if (skip[key]) continue;
        var val = details[key];
        if (!val || isEmptyHtml(val)) continue;

        var partner  = pairMap[key];
        var isLeader = !!pairLeaders[key];

        if (isLeader && partner && details[partner] && !isEmptyHtml(details[partner])) {
            // Beide vorhanden → Paar-Zeile
            rows.push({
                type:   'pair',
                label1: labelMap[key]     || key,
                value1: val,
                isHtml1: WYSIWYG_FIELDS.indexOf(key)     !== -1,
                label2: labelMap[partner] || partner,
                value2: details[partner],
                isHtml2: WYSIWYG_FIELDS.indexOf(partner) !== -1
            });
            skip[partner] = true;
        } else {
            // Einzeln
            rows.push({
                type:   'single',
                label:  labelMap[key] || key,
                value:  val,
                isHtml: WYSIWYG_FIELDS.indexOf(key) !== -1
            });
        }
    }
    return rows;
}

/** Felder die WYSIWYG-HTML enthalten */
var WYSIWYG_FIELDS = ['sitzkomfort', 'eigenheiten', 'schwaechen'];

/** Gibt true zurück wenn der String leer oder nur leere HTML-Tags enthält */
function isEmptyHtml(str) {
    if (!str) return true;
    return str.replace(/<[^>]*>/g, '').trim() === '';
}

function showBoatActionModal(boatId, boatName, boatType, boatSeats, isClub, videoUrl, noteStart, noteEnd, fullBoat) {
    $('#boat_action_id').val(boatId);
    $('#boat_action_name').text(boatName);
    $('#boat_action_type').text(boatType);
    $('#boat_action_seats').text(boatSeats);
    $('#boat_action_is_club').val(isClub ? '1' : '0');

    // Bevorzuge saubere JSON-Daten aus fullBoat gegenüber dem Data-Attribut (HTML-Entities-Problem)
    window._currentBoatNoteStart = (fullBoat && fullBoat.note_start) || noteStart || '';
    window._currentBoatNoteEnd   = (fullBoat && fullBoat.note_end)   || noteEnd   || '';

    if (boatType === 'Anhänger') {
        var trailerMsg = window._systemMessages['trailer_modal_warning'];
        if (trailerMsg) {
            $('#trailer_warning').html('<i class="bi ' + trailerMsg.icon + '"></i> <strong>WICHTIG:</strong> ' + trailerMsg.text).show();
        } else {
            $('#trailer_warning').show();
        }
    } else {
        $('#trailer_warning').hide();
    }

    if (!isEmptyHtml(window._currentBoatNoteStart)) {
        $('#boat_note_start_text').html(window._currentBoatNoteStart);
        $('#boat_note_start').show();
    } else {
        $('#boat_note_start').hide();
    }

    if (isClub) {
        $('#boat_action_reserve').show();
        $('#boat_action_damage').show();
    } else {
        $('#boat_action_reserve').hide();
        $('#boat_action_damage').hide();
    }

    if (videoUrl) {
        $('#boat_action_video').show().data('video-url', videoUrl);
    } else {
        $('#boat_action_video').hide().removeData('video-url');
    }

    // Steckbrief im Modal anzeigen
    renderBoatActionDetails(fullBoat);

    var modal = new bootstrap.Modal(document.getElementById('boatActionModal'));
    modal.show();
}

/**
 * Rendert den Boot-Steckbrief in der rechten Spalte des Boot-Aktions-Modals.
 * Schaltet zwischen 1-spaltig (ohne Details) und 2-spaltig (mit Details) um.
 */
function renderBoatActionDetails(boat) {
    var detailsCol = $('#boat_action_details_col');
    var actionsCol = $('#boat_action_actions_col');
    var dialog = $('#boatActionModal .modal-dialog');

    // Prüfen ob Details vorhanden
    var hasDetails = boat && boat.boat_details && Object.keys(boat.boat_details).length > 0;

    if (!hasDetails) {
        // Keine Details → 1-spaltig, normal
        detailsCol.hide().empty();
        actionsCol.removeClass('col-md-4').addClass('col-12');
        dialog.removeClass('modal-xl');
        return;
    }

    var details = boat.boat_details;
    var boatName = boat.boat_name || '';
    var labelMap = {
        'modell': 'Modell', 'hersteller': 'Hersteller', 'laenge': 'Länge', 'breite': 'Breite',
        'gewicht': 'Gewicht', 'material': 'Material', 'steuerung': 'Steuerung', 'stauraum': 'Stauraum',
        'empfohlenes_gewicht': 'Empf. Gewicht', 'koerpergroesse': 'Körpergröße',
        'erfahrungslevel': 'Erfahrungslevel', 'stabilitaet': 'Stabilität',
        'geschwindigkeit': 'Geschwindigkeit', 'wendigkeit': 'Wendigkeit',
        'wind_wellen': 'Wind/Wellen', 'sitzkomfort': 'Sitzkomfort',
        'eigenheiten': 'Eigenheiten', 'schwaechen': 'Schwächen',
        'bootsklasse': 'Bootsklasse', 'hinweis': 'Hinweis', 'pflegehinweis': 'Pflegehinweis',
        'paddler_gewicht': 'Paddler-Gewicht'
    };

    // Boot-Bild
    var imgHtml = '';
    var imgSrc = boat.boat_icon_url || boat.boat_image || '';
    if (imgSrc) {
        //imgHtml = '<div class="text-center mb-3"><img src="' + $('<span>').text(imgSrc).html() + '" alt="' + $('<span>').text(boatName).html() + '" style="max-height:100px; max-width:100%; object-fit:contain;"></div>';
    }

    // Details als Tabelle (Paare nebeneinander)
    var rows = _buildDetailRows(details, labelMap);
    var tableHtml = '<table class="table table-sm table-borderless mb-0 small">';
    rows.forEach(function(row) {
        if (row.type === 'pair') {
            tableHtml += '<tr>';
            tableHtml += '<td class="text-muted fw-bold pe-2" style="white-space:nowrap;">' + $('<span>').text(row.label1).html() + '</td>';
            tableHtml += '<td>' + (row.isHtml1 ? row.value1 : $('<span>').text(row.value1).html()) + '</td>';
            tableHtml += '<td class="text-muted fw-bold pe-2 ps-3" style="white-space:nowrap;">' + $('<span>').text(row.label2).html() + '</td>';
            tableHtml += '<td>' + (row.isHtml2 ? row.value2 : $('<span>').text(row.value2).html()) + '</td>';
            tableHtml += '</tr>';
        } else {
            tableHtml += '<tr>';
            tableHtml += '<td class="text-muted fw-bold pe-2" style="white-space:nowrap;">' + $('<span>').text(row.label).html() + '</td>';
            tableHtml += '<td colspan="3">' + (row.isHtml ? row.value : $('<span>').text(row.value).html()) + '</td>';
            tableHtml += '</tr>';
        }
    });
    tableHtml += '</table>';

    // Titel: Icon + "Steckbrief BOOTSNAME"
    var titleHtml = '<h6 class="mb-3"><i class="bi bi-info-square"></i> Steckbrief ' + $('<span>').text(boatName).html() + '</h6>';

    detailsCol.html(titleHtml + imgHtml + tableHtml).show();
    actionsCol.removeClass('col-12').addClass('col-md-4');
    dialog.addClass('modal-xl');
}

function convertToNocookieEmbed(url) {
    if (!url) return null;
    let videoId = null;
    let match = url.match(/[?&]v=([a-zA-Z0-9_-]{11})/);
    if (match) videoId = match[1];
    if (!videoId) { match = url.match(/youtu\.be\/([a-zA-Z0-9_-]{11})/); if (match) videoId = match[1]; }
    if (!videoId) { match = url.match(/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/); if (match) videoId = match[1]; }
    if (!videoId) { match = url.match(/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/); if (match) videoId = match[1]; }
    if (!videoId) return null;
    return 'https://www.youtube-nocookie.com/embed/' + videoId + '?rel=0&modestbranding=1';
}

function openVideoModal(videoUrl, boatName) {
    const embedUrl = convertToNocookieEmbed(videoUrl);
    if (!embedUrl) { showError('Ungültige YouTube-URL: ' + videoUrl); return; }
    var existingModal = document.getElementById('videoModal');
    if (existingModal) existingModal.remove();
    const modalHtml = `
        <div class="modal fade" id="videoModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white py-2">
                        <h6 class="modal-title mb-0"><i class="bi bi-play-btn me-2"></i>Anleitung: ${boatName}</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="ratio ratio-16x9">
                            <iframe src="${embedUrl}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="border:0;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    var modal = new bootstrap.Modal(document.getElementById('videoModal'));
    modal.show();
    document.getElementById('videoModal').addEventListener('hidden.bs.modal', function() { this.remove(); });
}

function openStatisticsModal() {
    $('#statisticsLogin').show();
    $('#statisticsContent').hide();
    $('#statisticsLoginForm')[0].reset();
    var modal = new bootstrap.Modal(document.getElementById('statisticsModal'));
    modal.show();
}

function setupModalHandlers() {
    // Boot-Aktions-Modal
    $('#boat_action_start_trip').click(function() {
        const boatId = $('#boat_action_id').val();
        bootstrap.Modal.getInstance(document.getElementById('boatActionModal')).hide();
        setTimeout(function() { openNewTripModal(false, boatId); }, 300);
    });

    $('#boat_action_video').click(function() {
        const videoUrl = $(this).data('video-url');
        const boatName = $('#boat_action_name').text();
        if (videoUrl) {
            bootstrap.Modal.getInstance(document.getElementById('boatActionModal')).hide();
            setTimeout(function() { openVideoModal(videoUrl, boatName); }, 300);
        }
    });

    $('#boat_action_backlog_trip').click(function() {
        const boatId = $('#boat_action_id').val();
        bootstrap.Modal.getInstance(document.getElementById('boatActionModal')).hide();
        setTimeout(function() { openNewTripModal(true, boatId); }, 300);
    });

    $('#boat_action_reserve').click(function() {
        const boatId = $('#boat_action_id').val();
        bootstrap.Modal.getInstance(document.getElementById('boatActionModal')).hide();
        setTimeout(function() { openReserveBoatModal(boatId); }, 300);
    });

    $('#boat_action_damage').click(function() {
        const boatId = $('#boat_action_id').val();
        bootstrap.Modal.getInstance(document.getElementById('boatActionModal')).hide();
        setTimeout(function() { openDamageReportModal(boatId); }, 300);
    });

    // "Einer Fahrt anschließen" Button (aus Boot-Aktions-Modal)
    $('#boat_action_join_trip').off('click').on('click', function() {
        // Boot-ID merken, damit sie nach der Gruppen-Auswahl vorbelegt werden kann
        window._joinFromBoatId = $('#boat_action_id').val() || null;
        bootstrap.Modal.getInstance(document.getElementById('boatActionModal')).hide();
        setTimeout(function() { openJoinTripModal(); }, 300);
    });

    // Reservierungs-Aktions-Modal
    $('#reservation_action_start_trip').off('click').on('click', function() { startTripFromReservation(); });
    $('#reservation_action_cancel').off('click').on('click', function() { cancelReservation(); });
    $('#confirmCancelReservationBtn').off('click').on('click', function() { confirmCancelReservation(); });

    // Boot-Auswahl im Fahrt-Modal
    window._lastSelectedBoatName = '';
    $('#trip_boat').on('input change blur', function() {
        const boatName = $(this).val();
        if (boatName === window._lastSelectedBoatName) return;
        window._lastSelectedBoatName = boatName;
        let foundBoat = null;
        if (window.availableBoatsData) {
            foundBoat = window.availableBoatsData.find(b => b.boat_name === boatName);
        }

        if (foundBoat) {
            $('#trip_boat_id').val(foundBoat.id);
            $('#trip_boat_type').val(foundBoat.boat_type);
            generateCrewInputs(foundBoat.seats, 'crewContainer');
            window._currentBoatNoteStart = foundBoat.note_start || '';
            window._currentBoatNoteEnd = foundBoat.note_end || '';
            $('#trip_warnings .alert-boat-note').remove();
            if (!isEmptyHtml(foundBoat.note_start)) {
                $('#trip_warnings').prepend(
                    '<div class="alert alert-primary border-start border-4 border-primary alert-boat-note">' +
                    '<i class="bi bi-info-circle-fill"></i> <strong>Hinweis zu diesem Boot:</strong><br>' +
                    foundBoat.note_start + '</div>'
                );
            }
            if (foundBoat.boat_type === 'Anhänger') {
                $('#water_field_container').hide();
                $('#trip_route').attr('placeholder', 'Freie Eingabe (z.B. Zielort)');
            }
            setTimeout(function() {
                fillDefaultCrew(foundBoat);
                checkCrewMemberWarning();
                checkTrailerWarning();
            }, 100);
            showBoatInfoPanel(foundBoat);
        } else if (boatName) {
            $('#trip_boat_id').val('');
            $('#trip_boat_type').val('');
            generateCrewInputs(2, 'crewContainer');
            $('#trip_route').attr('placeholder', 'Freie Eingabe oder aus Liste wählen');
            checkTrailerWarning();
            hideBoatInfoPanel();
        } else {
            $('#trip_boat_id').val('');
            $('#trip_boat_type').val('');
            $('#crewContainer').html('');
            $('#water_field_container').hide();
            checkTrailerWarning();
            hideBoatInfoPanel();
        }
    });

    // Datums-Warnungen
    $('#trip_start_date, #trip_end_date').change(function() { checkTripDateWarning(); });
    $('#trip_start_date').on('change', function() {
        const startDate = $(this).val();
        const endDate = $('#trip_end_date').val();
        if (!endDate || endDate < startDate) $('#trip_end_date').val(startDate);
    });
    $('#reserve_start_date').on('change', function() {
        const startDate = $(this).val();
        const endDate = $('#reserve_end_date').val();
        if (!endDate || endDate < startDate) $('#reserve_end_date').val(startDate);
    });

    // Strecken-Auswahl (Neue Fahrt)
    $('#trip_route').on('kcs:select', function(e, item) {
        const boatType = $('#trip_boat_type').val();
        if (boatType === 'Anhänger') return;
        if (item && item.id) {
            $('#trip_route_id').val(item.id);
            $('#water_field_container').hide();
            if (item.distance && $('#trip_distance').length > 0) $('#trip_distance').val(item.distance);
        }
    });
    $('#trip_route').on('input', function() {
        const boatType = $('#trip_boat_type').val();
        if (boatType === 'Anhänger') { $('#trip_route_id').val(''); $('#water_field_container').hide(); return; }
        var routeText = $(this).val();
        var found = _kcsAcFindExact('routes', routeText);
        if (found) {
            $('#trip_route_id').val(found.id);
            $('#water_field_container').hide();
        } else {
            $('#trip_route_id').val('');
            $('#water_field_container').show();
            if (!$('#trip_water').val()) $('#trip_water').val('Bodensee');
        }
    });

    // Gewässer-Auswahl
    $('#trip_water').on('kcs:select', function(e, item) { if (item && item.id) $('#trip_water_id').val(item.id); });
    $('#trip_water').on('input', function() {
        var found = _kcsAcFindExact('waters', $(this).val());
        $('#trip_water_id').val(found ? found.id : '');
    });

    // Fahrt beenden – Strecken
    $('#end_trip_route').on('kcs:select', function(e, item) {
        if (item && item.id) {
            $('#end_trip_route_id').val(item.id);
            if (item.distance && !$('#end_trip_distance').attr('data-gps')) $('#end_trip_distance').val(item.distance);
        }
    });
    $('#end_trip_route').on('input', function() {
        var found = _kcsAcFindExact('routes', $(this).val());
        if (found) {
            $('#end_trip_route_id').val(found.id);
            if (found.distance && !$('#end_trip_distance').attr('data-gps')) $('#end_trip_distance').val(found.distance);
        } else {
            $('#end_trip_route_id').val('');
        }
    });

    // Zuletzt gefahrene Strecke mit Klick übernehmen
    $(document).on('click', '.recent-route-btn', function() {
        var routeId    = $(this).data('route-id');
        var routeLabel = $(this).data('route-label');
        var distance   = $(this).data('distance');

        $('#end_trip_route').val(routeLabel).trigger('input');
        // route_id nach input-Handler setzen (dieser löscht ihn bei keinem Exact-Match)
        if (!$('#end_trip_route_id').val() && routeId) {
            $('#end_trip_route_id').val(routeId);
        }
        if (distance && !$('#end_trip_distance').attr('data-gps')) {
            $('#end_trip_distance').val(String(distance).replace('.', ','));
        }
    });

    // Reservierung-Warnungen
    $('#reserve_start_date, #reserve_end_date').change(function() { checkReservationDateWarning(); });
    $('#reserve_member').on('input', function() { checkReservationMemberWarning(); });

    // Save-Buttons
    $('#saveTripBtn').click(function() { saveTrip(); });
    $('#saveReservationBtn').click(function() { saveReservation(); });
    $('#saveDamageBtn').click(function() { saveDamage(); });
    $('#endTripBtn').click(function() { endTrip(); });
    $('#abortTripBtn').click(function() { abortTrip(); });
    $('#statsLoginBtn').click(function() { loginStatistics(); });
}

/**
 * Zeigt den Boot-Steckbrief im Neue-Fahrt-Modal an (rechte Spalte).
 * Modal wird auf modal-xl erweitert wenn Infos vorhanden sind.
 */
function showBoatInfoPanel(boat) {
    var details = boat.boat_details || {};
    var labelMap = {
        'modell': 'Modell', 'hersteller': 'Hersteller', 'laenge': 'Länge', 'breite': 'Breite',
        'gewicht': 'Gewicht', 'material': 'Material', 'steuerung': 'Steuerung', 'stauraum': 'Stauraum',
        'empfohlenes_gewicht': 'Empf. Gewicht', 'koerpergroesse': 'Körpergröße',
        'erfahrungslevel': 'Erfahrungslevel', 'stabilitaet': 'Stabilität',
        'geschwindigkeit': 'Geschwindigkeit', 'wendigkeit': 'Wendigkeit',
        'wind_wellen': 'Wind/Wellen', 'sitzkomfort': 'Sitzkomfort',
        'eigenheiten': 'Eigenheiten', 'schwaechen': 'Schwächen',
        'bootsklasse': 'Bootsklasse', 'hinweis': 'Hinweis', 'pflegehinweis': 'Pflegehinweis',
        'paddler_gewicht': 'Paddler-Gewicht'
    };

    var rows = _buildDetailRows(details, labelMap);

    if (rows.length === 0) {
        hideBoatInfoPanel();
        return;
    }

    var html = '<dl class="mb-0">';
    rows.forEach(function(row) {
        if (row.type === 'pair') {
            html += '<div class="row g-0">';
            html += '<div class="col-6 pe-2">';
            html += '<dt class="text-muted small mb-0">' + $('<span>').text(row.label1).html() + '</dt>';
            html += '<dd class="mb-2">' + (row.isHtml1 ? row.value1 : $('<span>').text(row.value1).html()) + '</dd>';
            html += '</div>';
            html += '<div class="col-6">';
            html += '<dt class="text-muted small mb-0">' + $('<span>').text(row.label2).html() + '</dt>';
            html += '<dd class="mb-2">' + (row.isHtml2 ? row.value2 : $('<span>').text(row.value2).html()) + '</dd>';
            html += '</div>';
            html += '</div>';
        } else {
            html += '<dt class="text-muted small mb-0">' + $('<span>').text(row.label).html() + '</dt>';
            html += '<dd class="mb-2">' + (row.isHtml ? row.value : $('<span>').text(row.value).html()) + '</dd>';
        }
    });
    html += '</dl>';
    $('#tripBoatInfoContent').html(html);

    // Boot-Bild: Icon > eigenes Bild > nichts
    var imgContainer = $('#tripBoatInfoImg');
    var boatImg = boat.boat_icon_url || boat.boat_image || '';
    if (boatImg) {
        imgContainer.find('img').attr('src', boatImg).attr('alt', boat.boat_name);
        imgContainer.show();
    } else {
        imgContainer.hide();
    }

    // Spalten umschalten
    $('#tripFormCol').removeClass('col-12').addClass('col-md-7');
    $('#tripBoatInfoCol').show();

    // Modal verbreitern
    $('#newTripModal .modal-dialog').removeClass('modal-lg').addClass('modal-xl');
}

/**
 * Versteckt den Boot-Steckbrief und setzt Modal auf normale Breite zurück.
 */
function hideBoatInfoPanel() {
    $('#tripBoatInfoCol').hide();
    $('#tripFormCol').removeClass('col-md-7').addClass('col-12');
    $('#newTripModal .modal-dialog').removeClass('modal-xl').addClass('modal-lg');
    $('#tripBoatInfoContent').html('');
    $('#tripBoatInfoImg').hide();
}
