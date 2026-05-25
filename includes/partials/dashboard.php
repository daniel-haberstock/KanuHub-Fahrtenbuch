<?php
// Task 05: dashboard.php — Mittlere Spalte: KPIs, CTAs, Termine, Statistik-Teaser
?>
<div class="kcs-dashboard" id="dashboard">

    <!-- ── KPI-Cards ────────────────────────────────────────────── -->
    <div class="kcs-kpi-row" id="kpiRow">
        <div class="kcs-kpi-card" id="kpiOnWater">
            <div class="kcs-kpi-label">Auf dem Wasser</div>
            <div class="kcs-kpi-value">—</div>
            <div class="kcs-kpi-sub" id="kpiOnWaterSub"></div>
        </div>
        <div class="kcs-kpi-card" id="kpiAvail">
            <div class="kcs-kpi-label">Verfügbar</div>
            <div class="kcs-kpi-value">—</div>
            <div class="kcs-kpi-sub">Vereinsboote am Steg</div>
        </div>
        <div class="kcs-kpi-card" id="kpiToday">
            <div class="kcs-kpi-label">Heute gepaddelt</div>
            <div class="kcs-kpi-value">—</div>
            <div class="kcs-kpi-sub" id="kpiTodaySub"></div>
        </div>
        <div class="kcs-kpi-card" id="kpiSeason">
            <div class="kcs-kpi-label">Saison gesamt</div>
            <div class="kcs-kpi-value kcs-kpi-value--accent">—</div>
            <div class="kcs-kpi-sub" id="kpiSeasonSub"></div>
        </div>
        <div class="kcs-kpi-card" id="kpiMonth">
            <div class="kcs-kpi-label">Aktueller Monat</div>
            <div class="kcs-kpi-value">—</div>
            <div class="kcs-kpi-sub" id="kpiMonthSub"></div>
        </div>
    </div>

    <!-- ── CTA Hero ─────────────────────────────────────────────── -->
    <div id="ctaHero">
        <!-- via dashboard.js -->
        <div class="kcs-loading kcs-loading--sm"><i class="bi bi-arrow-clockwise kcs-spin"></i></div>
    </div>

    <!-- ── CTA 2×2-Grid ──────────────────────────────────────────── -->
    <div class="kcs-cta2-grid">
        <button class="kcs-cta2-card" id="ctaJoin">
            <span class="kcs-cta2-icon kcs-cta2-icon--teal"><i class="bi bi-people-fill"></i></span>
            <span class="kcs-cta2-body">
                <span class="kcs-cta2-title">Einer Fahrt anschließen</span>
                <span class="kcs-cta2-sub" id="ctaJoinSub">Aktive Fahrten laden…</span>
            </span>
        </button>
        <button class="kcs-cta2-card" id="ctaReserve">
            <span class="kcs-cta2-icon kcs-cta2-icon--amber"><i class="bi bi-calendar-plus"></i></span>
            <span class="kcs-cta2-body">
                <span class="kcs-cta2-title">Boot reservieren</span>
                <span class="kcs-cta2-sub">Trainingsausflug planen</span>
            </span>
        </button>
        <button class="kcs-cta2-card" id="ctaBacklog">
            <span class="kcs-cta2-icon kcs-cta2-icon--red"><i class="bi bi-clock-history"></i></span>
            <span class="kcs-cta2-body">
                <span class="kcs-cta2-title">Fahrt nachtragen</span>
                <span class="kcs-cta2-sub">Nachträglich eintragen</span>
            </span>
        </button>
        <button class="kcs-cta2-card" id="ctaMember">
            <span class="kcs-cta2-icon kcs-cta2-icon--purple"><i class="bi bi-person-badge"></i></span>
            <span class="kcs-cta2-body">
                <span class="kcs-cta2-title">Mitgliederbereich</span>
                <span class="kcs-cta2-sub"><?= $loggedIn ? 'Statistiken &amp; Rangliste' : 'Login erforderlich' ?></span>
            </span>
        </button>
    </div>

    <!-- ── Termine ──────────────────────────────────────────────── -->
    <?php require_once __DIR__ . '/events_preview.php'; ?>

    <!-- ── Statistik-Teaser ──────────────────────────────────────── -->
    <div class="kcs-stats-teaser">
        <div class="kcs-stats-teaser__icon"><i class="bi bi-lock-fill"></i></div>
        <div class="kcs-stats-teaser__title">Saisonrangliste &amp; Fahrtenhistorie</div>
        <div class="kcs-stats-teaser__sub">Melde dich an, um die vollständige Saisonrangliste,
            persönliche Statistiken, Errungenschaften und alle Vereinsfahrten zu sehen.</div>
        <div class="kcs-stats-teaser__blur-wrap" id="teaserLeaderboard">
            <!-- via dashboard.js -->
        </div>
        <?php if (!$loggedIn): ?>
        <button class="kcs-btn kcs-btn--primary kcs-stats-teaser__cta" id="ctaTeaserLogin">
            <i class="bi bi-person-circle"></i> Anmelden
        </button>
        <?php endif; ?>
    </div>

</div>
