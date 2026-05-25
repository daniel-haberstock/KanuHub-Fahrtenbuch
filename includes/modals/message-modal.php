<!-- Modal: Nachricht (Erfolg/Fehler) -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0" id="messageModalHeader">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body text-center py-4">
                <!-- Icon (groß und zentriert) -->
                <div class="mb-4">
                    <i class="bi display-1" id="messageModalIcon"></i>
                </div>

                <!-- Titel -->
                <h3 class="mb-3" id="messageModalTitle"></h3>

                <!-- Nachricht -->
                <div id="messageModalBody" class="lead"></div>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-lg px-5" id="messageModalCloseBtn" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<style>
#messageModal .modal-content {
    border-radius: 20px;
}

#messageModal.success-modal .modal-header {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

#messageModal.error-modal .modal-header {
    background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
}

#messageModal .bi {
    animation: iconPulse 0.6s ease-in-out;
}

@keyframes iconPulse {
    0% {
        transform: scale(0.5);
        opacity: 0;
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

#messageModalCloseBtn {
    transition: all 0.3s ease;
}

#messageModalCloseBtn:hover {
    transform: scale(1.05);
}
</style>