/**
 * Fahrtenbuch – UI-Feedback & Hilfsfunktionen
 * showSuccess, showError, formatKm, Inaktivitäts-Timer
 */

// ── Inaktivitäts-Timer ─────────────────────────────────────
let inactivityTimer;

function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(function() {
        if ($('.modal.show').length === 0) {
            $('html, body').animate({ scrollTop: 0 }, 800);
            $('#boat-search').val('');
            $('.boat-item, .boat-group-header').show();
            $('.boat-list-column').animate({ scrollTop: 0 }, 800);
        }
    }, 120000);
}

// ── System-Meldungen ───────────────────────────────────────
window._systemMessages = {};

function loadSystemMessages() {
    $.ajax({
        url: '/api/messages/list.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.messages) {
                window._systemMessages = response.messages;
            }
        }
    });
}

function getSystemMessage(key, extraClass) {
    var msg = window._systemMessages[key];
    if (!msg) return '';
    var cls = extraClass ? ' ' + extraClass : '';
    return '<div class="alert alert-' + msg.type + cls + '">' +
        '<i class="bi ' + msg.icon + '"></i> ' +
        '<strong>' + (msg.type === 'danger' ? 'WICHTIG:' : 'Hinweis:') + '</strong> ' +
        msg.text +
        '</div>';
}

function getSystemMessageText(key) {
    var msg = window._systemMessages[key];
    return msg ? msg.text : '';
}

// ── Erfolg / Fehler Nachrichten ────────────────────────────
function showSuccess(message, closeOtherModals) {
    if (closeOtherModals !== false) {
        $('.modal').modal('hide');
    }
    $('#messageModal').removeClass('error-modal').addClass('success-modal');
    $('#messageModalIcon').removeClass('bi-exclamation-triangle-fill text-danger')
                          .addClass('bi-check-circle-fill text-success');
    $('#messageModalTitle').text('Erfolg!');
    $('#messageModalBody').html(message);
    $('#messageModalCloseBtn').removeClass('btn-danger').addClass('btn-success');
    $('#messageModal').modal('show');
}

function showError(message, closeOtherModals) {
    if (closeOtherModals !== false) {
        $('.modal').modal('hide');
    }
    $('#messageModal').removeClass('success-modal').addClass('error-modal');
    $('#messageModalIcon').removeClass('bi-check-circle-fill text-success')
                          .addClass('bi-exclamation-triangle-fill text-danger');
    $('#messageModalTitle').text('Fehler!');
    $('#messageModalBody').html(message);
    $('#messageModalCloseBtn').removeClass('btn-success').addClass('btn-danger');
    $('#messageModal').modal('show');
}

// ── Formatierung ───────────────────────────────────────────
function formatKm(val) {
    if (val === undefined || val === null) return '0';
    return Number(val).toFixed(1).replace('.', ',');
}
