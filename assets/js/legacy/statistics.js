/**
 * Fahrtenbuch – Statistik-Login & Anzeige
 */

function loginStatistics() {
    const email = $('#stats_email').val();
    const password = $('#stats_password').val();

    $.ajax({
        url: '/api/statistics/login.php',
        method: 'POST',
        data: { email: email, password: password },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadStatistics(response.member_id);
            } else {
                showError(response.message || 'Ungültige Anmeldedaten');
            }
        },
        error: function() { showError('Fehler bei der Anmeldung'); }
    });
}

function loadStatistics(memberId) {
    $.ajax({
        url: '/api/statistics/get.php?member_id=' + memberId,
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                $('#stats_user_name').text(data.member_name);
                $('#current_season_distance').text(data.current_season.distance);
                $('#current_season_trips').text(data.current_season.trips);
                $('#last_season_distance').text(data.last_season.distance);
                $('#last_season_trips').text(data.last_season.trips);
                $('#season_diff').text(data.season_diff.distance);
                $('#season_diff_text').text(data.season_diff.text);

                let tripsHtml = '';
                if (data.recent_trips && data.recent_trips.length > 0) {
                    data.recent_trips.forEach(function(trip) {
                        tripsHtml += `<tr><td>${trip.date}</td><td>${trip.boat_name}</td><td>${trip.route || '-'}</td><td>${trip.distance}</td></tr>`;
                    });
                } else {
                    tripsHtml = '<tr><td colspan="4" class="text-center">Keine Fahrten</td></tr>';
                }
                $('#recent_trips').html(tripsHtml);

                let rankingHtml = '';
                if (data.ranking && data.ranking.length > 0) {
                    data.ranking.forEach(function(rank, index) {
                        const rowClass = rank.is_current ? 'current-user' : (index < 3 ? 'rank-' + (index + 1) : '');
                        rankingHtml += `<tr class="${rowClass}"><td>${index + 1}</td><td>${rank.member_name}</td><td>${rank.trips}</td><td>${rank.distance}</td></tr>`;
                    });
                } else {
                    rankingHtml = '<tr><td colspan="4" class="text-center">Keine Daten</td></tr>';
                }
                $('#season_ranking').html(rankingHtml);

                $('#statisticsLogin').hide();
                $('#statisticsContent').show();
            }
        },
        error: function() { showError('Fehler beim Laden der Statistiken'); }
    });
}
