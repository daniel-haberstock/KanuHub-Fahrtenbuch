<?php
// v2-Layout-Template — alle Variablen kommen vom Router (index.php):
// $loggedIn, $memberId, $memberName, $currentSeason, $appData,
// $progressSeasonCurrent, $progressSeasonLast, $_seasonPct, $_seasonDiff,
// $progressMonthCurrent, $progressMonthLast, $_monthPct, $_monthDiff,
// $_monthName, $_currentYear
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?= htmlspecialchars(CLUB_NAME) ?> Fahrtenbuch</title>

    <!-- Lokale Vendor-Assets (kein CDN — Kiosk-kompatibel) -->
    <link rel="stylesheet" href="<?= APP_BASE_PATH ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= APP_BASE_PATH ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_BASE_PATH ?>/assets/css/fonts.css">

    <!-- App CSS (Design-Tokens + neues Layout) -->
    <link rel="stylesheet" href="<?= APP_BASE_PATH ?>/assets/css/app.css">
    <link rel="stylesheet" href="<?= APP_BASE_PATH ?>/assets/css/badges.css">
</head>
<body>

<!-- App-Daten für JavaScript -->
<script id="app-data" type="application/json"><?= json_encode($appData) ?></script>

<!-- ── LAYOUT-SHELL ────────────────────────────────────────── -->
<div class="kcs-app">

    <!-- Header -->
    <?php require_once __DIR__ . '/partials/header.php'; ?>

    <!-- 3-Spalten-Hauptbereich -->
    <div class="kcs-main">

        <!-- Linke Spalte: Bootsliste -->
        <aside class="kcs-sidebar">
            <?php require_once __DIR__ . '/partials/boat_sidebar.php'; ?>
        </aside>

        <!-- Mittlere Spalte: Dashboard -->
        <main class="kcs-content">
            <?php require_once __DIR__ . '/partials/dashboard.php'; ?>
        </main>

        <!-- Rechte Spalte: Status -->
        <aside class="kcs-status">
            <?php require_once __DIR__ . '/partials/status_panel.php'; ?>
        </aside>

    </div><!-- .kcs-main -->

</div><!-- .kcs-app -->

<!-- ── MODALS ──────────────────────────────────────────────── -->
<?php require_once __DIR__ . '/partials/modals.php'; ?>

<!-- ── SCRIPTS ─────────────────────────────────────────────── -->
<script src="<?= APP_BASE_PATH ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/app.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/header.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/wetter.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/boats.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/dashboard.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/status.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/boat-detail.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/start-trip.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/end-trip.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/join-trip.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/trips.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/reserve.js"></script>
<script src="<?= APP_BASE_PATH ?>/assets/js/login.js"></script>

<?php if ($loggedIn): ?>
<script src="<?= APP_BASE_PATH ?>/assets/js/member.js"></script>
<?php endif; ?>

</body>
</html>
