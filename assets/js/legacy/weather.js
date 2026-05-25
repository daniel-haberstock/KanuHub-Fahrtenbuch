/**
 * Fahrtenbuch – Wetter & Sturmwarnung
 */

function openWeatherAndStormModal() {
    var cfg = window.FahrtenbuchConfig || {};
    var stormEnabled = cfg.stormWarningEnabled !== false;
    var wfLabel = cfg.windfinderLabel || 'Wettervorhersage';
    var stormLabel = cfg.stormWarningLabel || 'Sturmwarnung';
    var colClass = stormEnabled ? 'col-md-6' : 'col-12';
    var maxWidth = stormEnabled ? '1200px' : '650px';

    var titleHtml = '<i class="bi bi-cloud-sun me-2"></i>Wettervorhersage';
    if (stormEnabled) {
        titleHtml += ' &amp; <i class="bi bi-exclamation-triangle ms-1 me-1 text-warning"></i>Sturmwarnung';
    }

    var stormColHtml = '';
    if (stormEnabled) {
        stormColHtml = `
            <div class="col-md-6">
                <div class="p-2 bg-light border-bottom">
                    <small class="fw-semibold text-muted">
                        <i class="bi bi-exclamation-triangle text-danger"></i> Sturmwarnung – ${stormLabel}
                    </small>
                </div>
                <div style="height:calc(70vh - 38px);overflow:hidden;">
                    <iframe id="stormIframe" src="" style="width:100%;height:100%;border:none;" frameborder="0"></iframe>
                </div>
            </div>`;
    }

    var modalHtml = `
        <div class="modal fade" id="weatherStormModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width:${maxWidth};width:95vw;">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white py-2">
                        <h6 class="modal-title mb-0">${titleHtml}</h6>
                        <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="alert alert-warning rounded-0 mb-0 py-2 px-3" style="font-size:0.82rem;">
                        <i class="bi bi-info-circle"></i>
                        <strong>Hinweis:</strong> ${getSystemMessageText('weather_disclaimer') || 'Wetterdaten sind nur ein Anhaltspunkt – nicht allein darauf verlassen!'}
                    </div>
                    <div class="modal-body p-0">
                        <div class="row g-0" style="min-height:70vh;">
                            <div class="${colClass} ${stormEnabled ? 'border-end' : ''}">
                                <div class="p-2 bg-light border-bottom">
                                    <small class="fw-semibold text-muted">
                                        <i class="bi bi-cloud-sun text-info"></i> Wettervorhersage – ${wfLabel}
                                    </small>
                                </div>
                                <div style="height:calc(70vh - 38px);overflow:hidden;">
                                    <iframe id="windfinderIframe" src="" style="width:100%;height:100%;border:none;" frameborder="0"></iframe>
                                </div>
                            </div>
                            ${stormColHtml}
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Schließen</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    $('#weatherStormModal').remove();
    $('body').append(modalHtml);

    var modal = new bootstrap.Modal(document.getElementById('weatherStormModal'));
    modal.show();

    $('#weatherStormModal').on('shown.bs.modal', function() {
        var wfIframe = document.getElementById('windfinderIframe');
        if (wfIframe && !wfIframe.getAttribute('src')) {
            wfIframe.src = '/api/proxy/windfinder.php';
        }
        if (stormEnabled) {
            var iframe = document.getElementById('stormIframe');
            if (iframe && !iframe.getAttribute('src')) {
                iframe.src = '/api/proxy/storm-warning.php?t=' + Date.now();
            }
        }
    });

    $('#weatherStormModal').on('hidden.bs.modal', function() { $(this).remove(); });
}
