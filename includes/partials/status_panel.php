<?php
// Task 06: status_panel.php — Auf dem Wasser, Reservierungen, Schäden
?>
<div class="kcs-status-panel" id="statusPanel">

    <!-- Auf dem Wasser -->
    <section class="kcs-sp-section">
        <div class="kcs-sp-heading kcs-sp-heading--teal">
            <span class="kcs-live-dot"></span>
            <span>Auf dem Wasser</span>
            <span class="kcs-sp-count" id="countOnWater">0</span>
        </div>
        <div id="activeTrips" class="kcs-sp-list">
            <div class="kcs-loading kcs-loading--sm"><i class="bi bi-arrow-clockwise kcs-spin"></i></div>
        </div>
    </section>

    <!-- Reservierungen -->
    <section class="kcs-sp-section">
        <div class="kcs-sp-heading kcs-sp-heading--amber">
            <i class="bi bi-calendar-check"></i>
            <span>Reservierungen</span>
            <span class="kcs-sp-count" id="countReserved">0</span>
        </div>
        <div id="activeReservations" class="kcs-sp-list">
            <div class="kcs-loading kcs-loading--sm"><i class="bi bi-arrow-clockwise kcs-spin"></i></div>
        </div>
    </section>

    <!-- Schäden / Wartung -->
    <section class="kcs-sp-section">
        <div class="kcs-sp-heading kcs-sp-heading--red">
            <i class="bi bi-tools"></i>
            <span>Schäden / Wartung</span>
            <span class="kcs-sp-count" id="countDamaged">0</span>
        </div>
        <div id="damagedBoats" class="kcs-sp-list">
            <div class="kcs-loading kcs-loading--sm"><i class="bi bi-arrow-clockwise kcs-spin"></i></div>
        </div>
    </section>

    <!-- Reservieren-Button -->
    <div class="kcs-sp-footer">
        <button class="kcs-sp-reserve-btn" id="btnReserveFromPanel">
            <i class="bi bi-calendar-plus"></i> Boot reservieren
        </button>
    </div>

</div>
