<?php
// Task 04: boat_sidebar.php — Bootsliste mit Filter und Suche
?>
<div class="kcs-boat-sidebar" id="boatSidebar">
    <div class="kcs-sidebar__header">
        <div class="kcs-sidebar__titlerow">
            <span class="kcs-sidebar__title">Boote</span>
            <span class="kcs-badge kcs-badge--green" id="boatAvailCount" style="display:none"></span>
        </div>
        <div class="kcs-sidebar__search">
            <i class="bi bi-search kcs-sidebar__search-icon"></i>
            <input type="search" id="boatSearch" class="kcs-sidebar__search-input"
                   placeholder="Boot suchen…" autocomplete="off">
        </div>
        <div class="kcs-sidebar__filters" id="boatFilters"></div>
    </div>
    <div class="kcs-sidebar__list" id="boatList">
        <div class="kcs-loading"><i class="bi bi-arrow-clockwise kcs-spin"></i> Lade Boote…</div>
    </div>
</div>
