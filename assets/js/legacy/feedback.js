/**
 * Fahrtenbuch – Feedback / Verbesserungsvorschläge
 */

var _feedbackMemberFirstName = '';

function openFeedbackModal() {
    // Formular vollständig zurücksetzen (auch nach vorherigem Versand)
    $('#feedbackForm')[0].reset();
    $('#feedback_member_id').val('');
    $('#feedback_warnings').html('');
    $('#feedback_success').addClass('d-none');
    $('#feedbackForm .mb-3').removeClass('d-none');
    $('#sendFeedbackBtn').removeClass('d-none').prop('disabled', false).html('<i class="bi bi-send"></i> Senden');
    $('#feedbackCloseBtn').text('Abbrechen');
    _feedbackMemberFirstName = '';

    // kcs:select-Handler: Member-ID setzen + Vorname merken
    $('#feedback_member').off('kcs:select').on('kcs:select', function(e, item) {
        $('#feedback_member_id').val(item ? item.id : '');
        if (item && item.text) {
            // Text-Format: "Vorname Nachname (Mitgliedsnr.)" → ersten Teil nehmen
            _feedbackMemberFirstName = item.text.split(' ')[0] || '';
        }
    }).off('input.feedback').on('input.feedback', function() {
        // Bei manueller Änderung ID und Vorname zurücksetzen
        $('#feedback_member_id').val('');
        _feedbackMemberFirstName = '';
    });

    var modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    modal.show();
}

function sendFeedback() {
    var message = $('#feedback_message').val().trim();
    if (!message) {
        $('#feedback_warnings').html(
            '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Bitte gib eine Nachricht ein.</div>'
        );
        return;
    }
    $('#feedback_warnings').html('');
    $('#sendFeedbackBtn').prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Wird gesendet…');

    $.ajax({
        url: '/api/feedback/send.php',
        method: 'POST',
        data: $('#feedbackForm').serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var name = _feedbackMemberFirstName;
                var danke = name
                    ? 'Danke, ' + name + '! Wir haben deine Nachricht erhalten und melden uns bald bei dir. 🎉'
                    : 'Danke! Wir haben deine Nachricht erhalten und melden uns bald.';
                $('#feedback_success_text').text(danke);
                $('#feedback_success').removeClass('d-none');
                $('#feedbackForm .mb-3').addClass('d-none'); // Felder ausblenden
                $('#sendFeedbackBtn').addClass('d-none');
                $('#feedbackCloseBtn').text('Schließen');
            } else {
                $('#feedback_warnings').html(
                    '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> ' +
                    (response.message || 'Fehler beim Senden. Bitte versuche es erneut.') + '</div>'
                );
                $('#sendFeedbackBtn').prop('disabled', false).html('<i class="bi bi-send"></i> Senden');
            }
        },
        error: function() {
            $('#feedback_warnings').html(
                '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Verbindungsfehler. Bitte versuche es erneut.</div>'
            );
            $('#sendFeedbackBtn').prop('disabled', false).html('<i class="bi bi-send"></i> Senden');
        }
    });
}

$(document).ready(function() {
    $('#sendFeedbackBtn').on('click', function() { sendFeedback(); });
});
