<?php
// Task 07: header.php — Logo, Uhr, Wetter-Strip, Login/Member
$_initials = '';
if ($loggedIn && !empty($memberName)) {
    $parts = explode(' ', trim($memberName));
    $_initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
?>
<header class="kcs-header">

    <!-- ── Brand ──────────────────────────────────────────────── -->
    <div class="kcs-header__brand">
        <div class="kcs-header__logo-icon">KC</div>
        <div>
            <div class="kcs-header__title"><?= htmlspecialchars(CLUB_NAME) ?> Fahrtenbuch</div>
            <div class="kcs-header__subtitle">Saison <?= htmlspecialchars($currentSeason) ?></div>
        </div>
    </div>

    <div class="kcs-header__spacer"></div>

    <!-- ── Uhr ────────────────────────────────────────────────── -->
    <div class="kcs-header__clock">
        <div class="kcs-header__time" id="headerTime">--:--</div>
        <div class="kcs-header__date" id="headerDate"></div>
    </div>

    <div class="kcs-header__spacer"></div>

    <!-- ── Wetter-Strip ───────────────────────────────────────── -->
    <div class="kcs-header__weather" id="headerWeather">
        <i class="bi bi-cloud-sun kcs-weather__icon" id="weatherIcon"></i>
        <span class="kcs-weather__temp" id="weatherTemp">TODO</span>
        <span class="kcs-weather__sep">·</span>
        <span class="kcs-weather__cond" id="weatherCond">TODO</span>
        <span class="kcs-weather__div">|</span>
        <i class="bi bi-wind kcs-weather__wi" id="weatherWindIcon"></i>
        <span id="weatherWind">TODO km/h</span>
        <span class="kcs-weather__div">|</span>
        <i class="bi bi-thermometer-half kcs-weather__wi" id="weatherWaterIcon"></i>
        <span>Wasser <span id="weatherWater">TODO</span></span>
        <span class="kcs-weather__sep">·</span>
        <i class="bi bi-moisture kcs-weather__wi" id="weatherPegelIcon"></i>
        <span id="weatherPegel">TODO cm</span>
    </div>

    <div class="kcs-header__vdiv"></div>

    <!-- ── User ───────────────────────────────────────────────── -->
    <?php if ($loggedIn): ?>
        <div class="kcs-header__user">
            <a href="<?= APP_BASE_PATH ?>/statistik/" class="kcs-header__member-btn" id="btnOpenMember">
                <span class="kcs-header__avatar"><?= htmlspecialchars($_initials) ?></span>
                <?= htmlspecialchars($memberName ?? '') ?>
            </a>
            <a href="<?= APP_BASE_PATH ?>/statistik/?logout" class="kcs-btn kcs-btn--ghost kcs-btn--sm">Abmelden</a>
        </div>
    <?php else: ?>
        <button class="kcs-btn kcs-btn--primary kcs-btn--sm" data-bs-toggle="modal" data-bs-target="#memberLoginModal">
            <i class="bi bi-person"></i> Anmelden
        </button>
    <?php endif; ?>

    <button class="kcs-header__icon" title="Feedback / Verbesserungsvorschlag"
            data-bs-toggle="modal" data-bs-target="#feedbackModal">
        <i class="bi bi-envelope-plus"></i>
    </button>
    <a href="<?= APP_BASE_PATH ?>/admin/" class="kcs-header__icon" title="Admin"><i class="bi bi-gear"></i></a>

</header>
