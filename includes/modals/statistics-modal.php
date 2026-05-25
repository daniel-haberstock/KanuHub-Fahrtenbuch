<!-- Modal: Statistik -->
<div class="modal fade" id="statisticsModal" tabindex="-1" aria-labelledby="statisticsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="statisticsModalLabel">
                    <i class="bi bi-graph-up"></i> Statistik
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">

                <!-- ================================================ -->
                <!-- 1. LOGIN-FORMULAR                                  -->
                <!-- ================================================ -->
                <div id="statisticsLogin">
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <h6 class="mb-3">Bitte anmelden</h6>
                            <form id="statisticsLoginForm" autocomplete="off">
                                <div class="mb-3">
                                    <label for="stats_email" class="form-label">E-Mail-Adresse</label>
                                    <input type="email" class="form-control" id="stats_email"
                                           name="email" placeholder="E-Mail Adresse"
                                           autocomplete="nope" readonly onfocus="this.removeAttribute('readonly')" required>
                                </div>
                                <div class="mb-3">
                                    <label for="stats_password" class="form-label">Passwort</label>
                                    <input type="password" class="form-control" id="stats_password"
                                           name="password" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly')" required>
                                </div>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    Die Anmeldedaten werden nicht gespeichert und müssen bei jedem Aufruf neu eingegeben werden.
                                    Verwende die Zugangsdaten von der Webseite.
                                </div>
                                <button type="button" class="btn btn-primary" id="statsLoginBtn">Anmelden</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ================================================ -->
                <!-- 2. ERSTENTSCHEIDUNG (nur wenn hide_statistics=null) -->
                <!-- ================================================ -->
                <div id="statisticsDecision" style="display: none;">
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="alert alert-warning">
                                <h5><i class="bi bi-shield-exclamation"></i> Einmalige Datenschutz-Entscheidung</h5>
                                <p>Möchtest du in der Vereinsstatistik sichtbar sein?</p>
                                <ul class="mb-0">
                                    <li><strong>Sichtbar:</strong> Dein Name und deine Kilometer erscheinen in der Rangliste. Du kannst die Statistiken aller Mitglieder sehen.</li>
                                    <li><strong>Unsichtbar:</strong> Du erscheinst als <em>"Versteckter Paddler"</em>. Du kannst deine eigene Statistik sehen, aber nicht die der anderen.</li>
                                </ul>
                            </div>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Wichtig:</strong> Du kannst diese Entscheidung <strong>einmalig</strong> selbst rückgängig machen.
                                Wählst du danach erneut "Unsichtbar", ist das <strong>dauerhaft</strong> –
                                eine Änderung ist dann nur noch über die Vorstandschaft möglich.
                            </div>
                            <div class="d-flex gap-3 mt-3">
                                <button class="btn btn-success btn-lg flex-fill" id="decideVisibleBtn">
                                    <i class="bi bi-eye"></i> Sichtbar bleiben
                                </button>
                                <button class="btn btn-outline-secondary btn-lg flex-fill" id="decideHiddenBtn">
                                    <i class="bi bi-eye-slash"></i> Unsichtbar sein
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================================================ -->
                <!-- 3. STATISTIK-INHALT                               -->
                <!-- ================================================ -->
                <div id="statisticsContent" style="display: none;">

                    <h6 class="mb-3">Persönliche Statistik – <span id="stats_user_name"></span></h6>

                    <!-- Tab-Navigation -->
                    <ul class="nav nav-tabs mb-3" id="statsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabpane-statistik" type="button" role="tab">
                                <i class="bi bi-graph-up"></i> Statistik
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabpane-badges" type="button" role="tab">
                                <i class="bi bi-award-fill text-warning"></i> Errungenschaften
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-einstellungen-btn" data-bs-toggle="tab" data-bs-target="#tabpane-einstellungen" type="button" role="tab">
                                <i class="bi bi-gear"></i> Einstellungen
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="statsTabContent">

                        <!-- ── Tab: Statistik ── -->
                        <div class="tab-pane fade show active" id="tabpane-statistik" role="tabpanel">

                            <!-- Saison-Übersicht -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted">Aktuelle Saison</h6>
                                            <p class="card-text">
                                                <strong><span id="current_season_distance">0</span> km</strong><br>
                                                <small class="text-muted"><span id="current_season_trips">0</span> Fahrten</small>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted">Vorherige Saison</h6>
                                            <p class="card-text">
                                                <strong><span id="last_season_distance">0</span> km</strong><br>
                                                <small class="text-muted"><span id="last_season_trips">0</span> Fahrten</small>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted">Differenz zur Vorsaison</h6>
                                            <p class="card-text">
                                                <strong><span id="season_diff">0</span> km</strong><br>
                                                <small class="text-muted"><span id="season_diff_text"></span></small>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Letzte Fahrten -->
                            <h6 class="mb-3">Letzte Fahrten dieser Saison</h6>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr><th>Datum</th><th>Boot</th><th>Strecke</th><th>km</th></tr>
                                    </thead>
                                    <tbody id="recent_trips">
                                        <tr><td colspan="4" class="text-center">Keine Fahrten</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Rangliste (nur wenn sichtbar) -->
                            <div id="rankingSection">
                                <h6 class="mb-3">Rangliste dieser Saison</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped statistics-table">
                                        <thead>
                                            <tr><th>Platz</th><th>Name</th><th>Fahrten</th><th>km</th></tr>
                                        </thead>
                                        <tbody id="season_ranking">
                                            <tr><td colspan="4" class="text-center">Keine Daten</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="rankingHiddenNote" style="display:none;" class="alert alert-secondary">
                                <i class="bi bi-eye-slash"></i>
                                Du bist unsichtbar – die Rangliste anderer Mitglieder ist nicht einsehbar.
                            </div>

                        </div><!-- /tab-pane Statistik -->

                        <!-- ── Tab: Badges ── -->
                        <div class="tab-pane fade" id="tabpane-badges" role="tabpanel">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="mb-0"><i class="bi bi-award-fill text-warning"></i> Meine Errungenschaften</h6>
                                <small id="badgeCountLabel" class="text-muted"></small>
                            </div>
                            <div id="newBadgesAlert" style="display:none;" class="alert alert-success py-2 mb-3">
                                <i class="bi bi-stars"></i>
                                <strong id="newBadgesAlertText"></strong>
                            </div>
                            <div id="almostBadgesSection" style="display:none;" class="mb-4">
                                <p class="text-muted small mb-2">
                                    <i class="bi bi-hourglass-split"></i> <strong>Fast geschafft:</strong>
                                </p>
                                <div id="almostBadgesList"></div>
                            </div>
                            <div id="badgeGridContainer">
                                <div class="text-center p-3 text-muted">
                                    <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
                                    <span class="ms-2">Errungenschaften werden geladen…</span>
                                </div>
                            </div>
                        </div><!-- /tab-pane Badges -->

                        <!-- ── Tab: Einstellungen ── -->
                        <div class="tab-pane fade" id="tabpane-einstellungen" role="tabpanel">

                            <!-- Sichtbarkeits-Box -->
                            <div id="visibilityBox" class="mb-4 p-3 rounded border" style="background:#f8f9fa;"></div>

                            <!-- E-Mail Benachrichtigung -->
                            <h6 class="mb-2"><i class="bi bi-envelope"></i> E-Mail Benachrichtigung</h6>
                            <p class="text-muted small mb-3">
                                Erhalte nach jeder Fahrt oder einmal wöchentlich (wenn du in der Woche aktiv warst)
                                deine Statistik und weitere Informationen per E-Mail.
                            </p>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="notify_after_trip">
                                <label class="form-check-label" for="notify_after_trip">Nach jeder Fahrt</label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="notify_weekly">
                                <label class="form-check-label" for="notify_weekly">Sonntag nach der letzten Fahrt</label>
                            </div>
                            <button class="btn btn-sm btn-primary" id="saveNotifyBtn">
                                <i class="bi bi-check-lg"></i> Speichern
                            </button>
                            <span id="notifySaveStatus" class="ms-2 small text-success" style="display:none;">Gespeichert!</span>

                        </div><!-- /tab-pane Einstellungen -->

                    </div><!-- /tab-content -->

                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var statsState = { memberId: null, memberName: null, hide: null, locked: false, returned: false, notifyAfterTrip: false, notifyWeekly: false };

    function showSection(id) {
        ['statisticsLogin','statisticsDecision','statisticsContent'].forEach(function(s) {
            document.getElementById(s).style.display = (s === id) ? '' : 'none';
        });
    }

    function renderVisibilityBox() {
        var box = document.getElementById('visibilityBox');
        var s   = statsState;

        // Dauerhaft gesperrt
        if (s.locked) {
            box.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-lock-fill"></i> ' +
                '<strong>Dauerhaft unsichtbar.</strong> Eine Änderung ist nur noch über die Vorstandschaft möglich.</div>';
            box.style.display = '';
            return;
        }

        // Unsichtbar → Option zum Einblenden zeigen
        if (s.hide === 1) {
            var note = s.returned
                ? '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Deinen freien Rückwechsel hast du bereits genutzt – kein erneutes Einblenden möglich (nur Vorstandschaft).</span>'
                : '<span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Du kannst dich <strong>einmalig</strong> selbst wieder sichtbar machen.</span>';
            box.innerHTML = '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">' +
                '<span><i class="bi bi-eye-slash text-secondary"></i> <strong>Unsichtbar</strong> in der Statistik. ' + note + '</span>' +
                (s.returned ? '' :
                    '<button class="btn btn-sm btn-outline-success" id="makeVisibleBtn"><i class="bi bi-eye"></i> Wieder sichtbar</button>') +
                '</div>';
            box.style.display = '';
            if (!s.returned) {
                document.getElementById('makeVisibleBtn').onclick = function() { doVisibilityAction('show'); };
            }
            return;
        }

        // Sichtbar + freier Rückwechsel bereits genutzt → LETZTE WARNUNG anzeigen
        if (s.hide === 0 && s.returned) {
            box.innerHTML = '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">' +
                '<span><i class="bi bi-eye text-success"></i> <strong>Sichtbar</strong> in der Statistik. ' +
                '<strong class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Letzte Warnung: Erneutes Ausblenden ist DAUERHAFT!</strong></span>' +
                '<button class="btn btn-sm btn-outline-secondary" id="makeHiddenBtn"><i class="bi bi-eye-slash"></i> Ausblenden</button>' +
                '</div>';
            box.style.display = '';
            document.getElementById('makeHiddenBtn').onclick = function() {
                if (confirm('ACHTUNG: Erneutes Ausblenden ist DAUERHAFT!\nNur die Vorstandschaft kann das rückgängig machen.\n\nWirklich ausblenden?')) {
                    doVisibilityAction('hide');
                }
            };
            return;
        }

        // Sichtbar + kein Rückwechsel genutzt → Box ausblenden (kein Informationsbedarf)
        box.innerHTML = '';
        box.style.display = 'none';
    }

    function doVisibilityAction(action) {
        var fd = new FormData();
        fd.append('member_id', statsState.memberId);
        fd.append('action', action);
        fetch('/api/statistics/visibility.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.success) { alert(d.message); return; }
                statsState.hide     = d.hide_statistics;
                statsState.locked   = d.locked;
                statsState.returned = d.returned_visible;
                renderVisibilityBox();
                updateRankingVisibility();
                alert(d.message);
            }).catch(function() { alert('Fehler beim Speichern.'); });
    }

    function updateRankingVisibility() {
        document.getElementById('rankingSection').style.display    = statsState.hide === 1 ? 'none' : '';
        document.getElementById('rankingHiddenNote').style.display = statsState.hide === 1 ? ''     : 'none';
    }

    // ---- Login ----
    document.getElementById('statsLoginBtn').addEventListener('click', function() {
        var fd = new FormData();
        fd.append('email',    document.getElementById('stats_email').value);
        fd.append('password', document.getElementById('stats_password').value);
        fetch('/api/statistics/login.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.success) { alert('Anmeldung fehlgeschlagen: ' + d.message); return; }
                statsState.memberId  = d.member_id;
                statsState.memberName = d.member_name;
                statsState.hide      = d.hide_statistics;
                statsState.locked    = d.hide_statistics_locked;
                statsState.returned  = d.hide_statistics_returned_visible;
                document.getElementById('stats_password').value = '';
                if (statsState.hide === null) { showSection('statisticsDecision'); return; }
                loadStatistics();
            }).catch(function() { alert('Verbindungsfehler'); });
    });

    // ---- Erstentscheidung ----
    document.getElementById('decideVisibleBtn').addEventListener('click', function() { sendDecision('decide_show'); });
    document.getElementById('decideHiddenBtn').addEventListener('click', function() {
        if (!confirm('Bist du sicher, dass du unsichtbar sein möchtest?\n\nDu kannst dich einmalig selbst wieder sichtbar machen.\nWählst du danach erneut "Unsichtbar", ist das dauerhaft.')) return;
        sendDecision('decide_hide');
    });
    function sendDecision(action) {
        var fd = new FormData();
        fd.append('member_id', statsState.memberId);
        fd.append('action', action);
        fetch('/api/statistics/visibility.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.success) { alert(d.message); return; }
                statsState.hide     = d.hide_statistics;
                statsState.locked   = d.locked;
                statsState.returned = d.returned_visible;
                loadStatistics();
            }).catch(function() { alert('Fehler'); });
    }

    // ---- Badges laden ----
    function loadBadges() {
        var container = document.getElementById('badgeGridContainer');
        container.innerHTML = '<div class="text-center p-3 text-muted"><div class="spinner-border spinner-border-sm text-warning" role="status"></div><span class="ms-2">Errungenschaften werden geladen…</span></div>';

        fetch('/api/badges/get.php?member_id=' + statsState.memberId)
            .then(function(r) {
                if (!r.ok) {
                    return r.text().then(function(text) {
                        console.error('[KCS] Badge-API HTTP ' + r.status + ':', text.substring(0, 500));
                        throw new Error('HTTP ' + r.status);
                    });
                }
                return r.json();
            })
            .then(function(d) {
                if (!d.success) {
                    console.error('[KCS] Badge-API Fehler:', d.message);
                    container.innerHTML = '<p class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' + (d.message || 'Fehler beim Laden der Badges.') + '</p>';
                    return;
                }

                // Zähler
                document.getElementById('badgeCountLabel').textContent =
                    d.earned_count + ' / ' + d.total_count + ' Errungenschaften verdient';

                // Neue Badges Hinweis
                if (d.new_count > 0) {
                    document.getElementById('newBadgesAlertText').textContent =
                        d.new_count + ' neue' + (d.new_count === 1 ? ' Errungenschaft' : ' Errungenschaften') + ' verdient – herzlichen Glückwunsch!';
                    document.getElementById('newBadgesAlert').style.display = '';

                    // Als gesehen markieren (nach kurzer Verzögerung für Animation)
                    setTimeout(function() {
                        var newEls = document.querySelectorAll('#badgeGridContainer .badge-item[data-badge-new="1"]');
                        newEls.forEach(function(el) {
                            el.classList.add('badge-animate-unlock');
                            fetch('/api/badges/mark-seen.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({ badge_key: el.getAttribute('data-badge'), member_id: statsState.memberId })
                            });
                        });
                    }, 300);
                }

                // "Fast-Badges" Fortschrittsbalken
                if (d.almost_badges && d.almost_badges.length > 0) {
                    document.getElementById('almostBadgesSection').style.display = '';
                    document.getElementById('almostBadgesList').innerHTML = d.almost_badges.map(function(b) {
                        var color = b.progress >= 90 ? 'bg-success' : b.progress >= 80 ? 'bg-warning' : 'bg-info';
                        return '<div class="mb-2">'
                             + '<div class="d-flex justify-content-between align-items-center mb-1">'
                             + '<small class="fw-bold">' + b.label + '</small>'
                             + '<small class="text-muted">' + b.current.toLocaleString('de') + ' / ' + b.target.toLocaleString('de') + ' ' + b.unit + '</small>'
                             + '</div>'
                             + '<div class="progress" style="height:8px; border-radius:4px;">'
                             + '<div class="progress-bar ' + color + '" style="width:' + b.progress + '%;border-radius:4px;" title="' + b.progress + '%"></div>'
                             + '</div></div>';
                    }).join('');
                }

                // Badge-Grid
                container.innerHTML = d.badge_grid_html || '<p class="text-muted">Noch keine Errungenschaften verdient.</p>';
            }).catch(function(err) {
                console.error('[KCS] Badge-Laden fehlgeschlagen:', err);
                container.innerHTML = '<p class="text-danger"><i class="bi bi-exclamation-triangle"></i> Errungenschaften konnten nicht geladen werden. Details in der Konsole.</p>';
            });
    }

    // ---- Statistik laden ----
    function loadStatistics() {
        showSection('statisticsContent');
        document.getElementById('stats_user_name').textContent = statsState.memberName;
        renderVisibilityBox();
        updateRankingVisibility();
        loadBadges();

        fetch('/api/statistics/get.php?member_id=' + statsState.memberId)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.success) { alert('Statistik konnte nicht geladen werden'); return; }

                // Benachrichtigungs-Checkboxen
                statsState.notifyAfterTrip = !!d.email_notify_after_trip;
                statsState.notifyWeekly    = !!d.email_notify_weekly;
                document.getElementById('notify_after_trip').checked = statsState.notifyAfterTrip;
                document.getElementById('notify_weekly').checked     = statsState.notifyWeekly;

                document.getElementById('current_season_distance').textContent = d.current_season.distance;
                document.getElementById('current_season_trips').textContent    = d.current_season.trips;
                document.getElementById('last_season_distance').textContent    = d.last_season.distance;
                document.getElementById('last_season_trips').textContent       = d.last_season.trips;
                document.getElementById('season_diff').textContent             = d.season_diff.distance;
                document.getElementById('season_diff_text').textContent        = d.season_diff.text;

                var tbody = document.getElementById('recent_trips');
                tbody.innerHTML = (d.recent_trips && d.recent_trips.length > 0)
                    ? d.recent_trips.map(function(t) {
                        return '<tr><td>' + (t.date||'') + '</td><td>' + (t.boat_name||'–') + '</td><td>' + (t.route||'–') + '</td><td>' + (t.distance||0) + ' km</td></tr>';
                    }).join('')
                    : '<tr><td colspan="4" class="text-center">Keine Fahrten</td></tr>';

                if (statsState.hide !== 1 && d.ranking && d.ranking.length > 0) {
                    var pos = 0;
                    document.getElementById('season_ranking').innerHTML = d.ranking.map(function(r) {
                        if (!r.is_hidden) pos++;
                        var rowStyle = r.is_current ? ' style="font-weight:bold;background:#e8f5e9;"' : '';
                        var medal    = pos === 1 ? '🥇 ' : pos === 2 ? '🥈 ' : pos === 3 ? '🥉 ' : '';
                        return '<tr' + rowStyle + '><td>' + (r.is_hidden ? '–' : pos + ' ' + medal) + '</td>' +
                               '<td>' + r.member_name + (r.is_current ? ' <strong>(ich)</strong>' : '') + '</td>' +
                               '<td>' + r.trips + '</td>' +
                               '<td>' + parseFloat(r.distance).toFixed(1).replace('.', ',') + ' km</td></tr>';
                    }).join('');
                }
            }).catch(function() { alert('Fehler beim Laden der Statistik'); });
    }

    // ---- E-Mail Benachrichtigung speichern ----
    document.getElementById('saveNotifyBtn').addEventListener('click', function() {
        var fd = new FormData();
        fd.append('member_id',         statsState.memberId);
        fd.append('notify_after_trip', document.getElementById('notify_after_trip').checked ? '1' : '0');
        fd.append('notify_weekly',     document.getElementById('notify_weekly').checked ? '1' : '0');
        var btn = document.getElementById('saveNotifyBtn');
        btn.disabled = true;
        fetch('/api/statistics/notification-settings.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btn.disabled = false;
                if (!d.success) { alert('Fehler beim Speichern: ' + d.message); return; }
                statsState.notifyAfterTrip = d.notify_after_trip;
                statsState.notifyWeekly    = d.notify_weekly;
                var status = document.getElementById('notifySaveStatus');
                status.style.display = '';
                setTimeout(function() { status.style.display = 'none'; }, 2500);
            }).catch(function() { btn.disabled = false; alert('Verbindungsfehler'); });
    });

    // Reset beim Schließen
    document.getElementById('statisticsModal').addEventListener('hidden.bs.modal', function() {
        statsState = { memberId: null, memberName: null, hide: null, locked: false, returned: false, notifyAfterTrip: false, notifyWeekly: false };
        document.getElementById('notify_after_trip').checked = false;
        document.getElementById('notify_weekly').checked     = false;
        document.getElementById('notifySaveStatus').style.display = 'none';
        document.getElementById('stats_email').value    = '';
        document.getElementById('stats_password').value = '';
        document.getElementById('badgeGridContainer').innerHTML = '';
        document.getElementById('almostBadgesList').innerHTML   = '';
        document.getElementById('badgeCountLabel').textContent  = '';
        document.getElementById('newBadgesAlert').style.display = 'none';
        document.getElementById('almostBadgesSection').style.display = 'none';
        showSection('statisticsLogin');
    });
})();
</script>
