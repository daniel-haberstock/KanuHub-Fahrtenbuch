/**
 * Fahrtenbuch – Events/Termine Modul
 *
 * Lädt REDAXO-Termine + lokale Events über /api/events/upcoming.php
 * und rendert sie als Karte im Hauptfenster.
 *
 * Funktionen:
 *   loadUpcomingEvents()    – API abrufen + rendern
 *   canJoinEvent(dateTime)  – Zeitfenster-Prüfung (1h vor bis 20min nach)
 *   renderEventsCard(events)– HTML erzeugen und einfügen
 *   openEventInfoModal(evt) – Info-Modal mit Beschreibung öffnen
 *   joinEventTrip(evt)      – Neue-Fahrt-Modal mit trip_group vorausfüllen
 */

// ── Globaler Event-Cache ────────────────────────────────────────────────
let _upcomingEvents = [];
let _eventsRefreshTimer = null;

/**
 * Prüft ob ein Event im Teilnahme-Zeitfenster liegt.
 * Fenster: 3 Stunden vor bis 3 Stunden nach Startzeit.
 */
function canJoinEvent(dateTimeStr) {
    if (!dateTimeStr || dateTimeStr.indexOf('00:00:00') !== -1) {
        return false;
    }
    const now = new Date();
    const eventTime = new Date(dateTimeStr.replace(' ', 'T'));
    const earliest = new Date(eventTime.getTime() - 3 * 60 * 60 * 1000);  // -3h
    const latest   = new Date(eventTime.getTime() + 30 * 60 * 1000);  // +30 Min
    return now >= earliest && now <= latest;
}

/**
 * Lädt anstehende Events von der API und rendert sie.
 */
function loadUpcomingEvents() {
    $.get('/api/events/upcoming.php?days=14', function(data) {
        if (data.success && data.events) {
            _upcomingEvents = data.events;
            renderEventsCard(data.events);
        }
    }).fail(function() {
        console.warn('[events.js] Konnte Events nicht laden');
    });
}

/**
 * Rendert die Termine-Karte im Container #upcoming-events-container.
 */
function renderEventsCard(events) {
    const container = document.getElementById('upcoming-events-container');
    if (!container) return;

    if (!events || events.length === 0) {
        container.innerHTML = '';
        return;
    }

    const today = new Date().toISOString().slice(0, 10);

    let rows = '';
    events.forEach(function(evt, i) {
        const isToday = (evt.date === today);
        const datumLabel = isToday ? 'Heute' : formatDateShort(evt.date);

        // Zeitfenster clientseitig nochmal prüfen (Server-Wert kann veraltet sein)
        const joinable = canJoinEvent(evt.date_time);
        const hasTime = evt.time && evt.time !== '';
        const hasDescription = evt.description && evt.description.trim() !== '';
        const hasGroup = evt.has_group && evt.active_trips > 0;

        // Styling: Farbe des Events als Akzent
        let rowStyle = '';
        let dateClass = '';
        let textClass = 'text-muted';

        if (joinable) {
            // Im Zeitfenster: Hervorgehoben mit Event-Farbe
            rowStyle = 'background:' + hexToRgba(evt.color, 0.15) + ';margin:0 -12px;padding:4px 12px;border-radius:4px;';
            dateClass = '';
            textClass = 'fw-bold';
        } else if (isToday) {
            rowStyle = 'background:#fff3cd;margin:0 -12px;padding:4px 12px;border-radius:4px;';
            dateClass = ' text-warning';
            textClass = 'fw-bold text-warning';
        }

        const isLast = (i === events.length - 1);
        const borderClass = !isLast ? 'mb-2 pb-2 border-bottom' : '';

        rows += '<div class="d-flex align-items-center gap-2 ' + borderClass + '"'
            + (rowStyle ? ' style="' + rowStyle + '"' : '') + '>';

        // Datum
        rows += '<span class="text-nowrap fw-semibold' + dateClass + '" style="min-width: 42px;">'
            + escapeHtml(datumLabel) + '</span>';

        // Farbiger Punkt
        rows += '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:'
            + escapeHtml(evt.color) + ';flex-shrink:0;"></span>';

        // Name + Uhrzeit
        rows += '<span class="' + textClass + ' flex-grow-1">';
        if (evt.icon) {
            rows += '<i class="' + escapeHtml(evt.icon) + ' me-1"></i>';
        }
        rows += escapeHtml(evt.name);
        if (hasTime) {
            rows += ' <small class="text-muted">(' + escapeHtml(evt.time) + ' Uhr)</small>';
        }
        rows += '</span>';

        // Buttons
        rows += '<span class="d-flex gap-1 flex-shrink-0">';

        // Info-Button
        if (hasDescription) {
            rows += '<button class="btn btn-sm btn-outline-secondary py-0 px-1" '
                + 'onclick=\'openEventInfoModal(' + JSON.stringify(evt).replace(/'/g, "\\'") + ')\' '
                + 'title="Details"><i class="bi bi-info-circle"></i></button>';
        }

        // Teilnehmen-Button (nur im Zeitfenster)
        if (joinable) {
            rows += '<button class="btn btn-sm btn-success py-0 px-2" '
                + 'onclick=\'joinEventTrip(' + JSON.stringify({
                    id: evt.id,
                    source: evt.source,
                    name: evt.name,
                    date: evt.date,
                    trip_group_id: evt.trip_group_id
                }).replace(/'/g, "\\'") + ')\' '
                + 'title="An dieser Ausfahrt teilnehmen">'
                + '<i class="bi bi-play-circle me-1"></i>Teilnehmen</button>';
        }

        // Gruppe-Badge (wenn aktive Fahrten in der Gruppe)
        if (hasGroup) {
            rows += '<span class="badge bg-primary" title="' + evt.active_trips + ' Boot(e) unterwegs">'
                + '<i class="bi bi-people-fill"></i> ' + evt.active_trips + '</span>';
        }

        rows += '</span>';
        rows += '</div>';
    });

    container.innerHTML = '<div class="card mt-3 club-progress-card">'
        + '<div class="card-header d-flex align-items-center gap-2 py-2 px-3" style="font-size: 14px;">'
        + '<i class="bi bi-calendar-event-fill"></i>'
        + '<span>Nächste Termine</span>'
        + '</div>'
        + '<div class="card-body py-2 px-3">' + rows + '</div>'
        + '</div>';
}

/**
 * Öffnet das Event-Info-Modal mit Details zum Termin.
 */
function openEventInfoModal(evt) {
    const modal = document.getElementById('eventInfoModal');
    if (!modal) return;

    document.getElementById('eventInfoTitle').textContent = evt.name || '';
    document.getElementById('eventInfoDate').textContent = formatDateLong(evt.date);
    document.getElementById('eventInfoTime').textContent = evt.time ? evt.time + ' Uhr' : 'Ganztägig';

    const descEl = document.getElementById('eventInfoDescription');
    descEl.textContent = evt.description || '';
    descEl.style.display = evt.description ? 'block' : 'none';

    const respEl = document.getElementById('eventInfoResponsible');
    const respRow = document.getElementById('eventInfoResponsibleRow');
    if (evt.verantwortlich) {
        respEl.textContent = evt.verantwortlich;
        respRow.style.display = '';
    } else {
        respRow.style.display = 'none';
    }

    // Quelle anzeigen
    const sourceEl = document.getElementById('eventInfoSource');
    if (evt.source === 'local') {
        sourceEl.innerHTML = '<span class="badge bg-success">Lokales Event</span>';
    } else {
        sourceEl.innerHTML = '<span class="badge bg-primary">Vereinstermin</span>';
    }

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

/**
 * Startet eine Fahrt im Rahmen eines Events.
 * Öffnet das Neue-Fahrt-Modal über openNewTripModal() (inkl. Bootsuche),
 * setzt danach die Event-spezifischen Felder.
 */
function joinEventTrip(evtData) {
    // openNewTripModal() übernimmt: Form-Reset, Boots-API, Autocomplete, Modal öffnen
    openNewTripModal(false, null);

    // Event-Felder nach dem Reset befüllen
    $('#trip_group_source_id').val(evtData.id || '');
    $('#trip_group_source_type').val(evtData.source === 'local' ? 'local_event' : 'termin');
    $('#trip_group_name').val(evtData.name || '');
    $('#trip_group_id').val(evtData.trip_group_id || '');

    // Event-Banner anzeigen
    const eventBanner = document.getElementById('tripEventBanner');
    if (eventBanner) {
        eventBanner.innerHTML = '<div class="alert alert-success py-2 mb-3">'
            + '<i class="bi bi-people-fill me-2"></i>'
            + '<strong>Gemeinsame Ausfahrt:</strong> ' + escapeHtml(evtData.name)
            + '</div>';
        eventBanner.style.display = 'block';
    }
}

// ── Hilfsfunktionen ─────────────────────────────────────────────────────

/**
 * Datum kurz formatieren: "15.05."
 */
function formatDateShort(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    return parts[2] + '.' + parts[1] + '.';
}

/**
 * Datum lang formatieren: "15. Mai 2026"
 */
function formatDateLong(dateStr) {
    if (!dateStr) return '';
    const months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    return parseInt(parts[2]) + '. ' + months[parseInt(parts[1]) - 1] + ' ' + parts[0];
}

/**
 * Hex-Farbe zu rgba konvertieren.
 */
function hexToRgba(hex, alpha) {
    if (!hex) return 'rgba(40,167,69,' + alpha + ')';
    hex = hex.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
}

/**
 * HTML-Zeichen escapen.
 */
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ── Init & Auto-Refresh ─────────────────────────────────────────────────

/**
 * Events-Modul initialisieren.
 * Wird von app.js aufgerufen.
 */
function initEvents() {
    loadUpcomingEvents();

    // Alle 60 Sekunden aktualisieren (Zeitfenster-Buttons ein-/ausblenden)
    _eventsRefreshTimer = setInterval(function() {
        // Nur re-rendern wenn Events gecached sind (kein neuer API-Call jede Minute)
        if (_upcomingEvents.length > 0) {
            renderEventsCard(_upcomingEvents);
        }
    }, 60000);

    // Alle 5 Minuten neuen API-Call machen
    setInterval(loadUpcomingEvents, 300000);
}
