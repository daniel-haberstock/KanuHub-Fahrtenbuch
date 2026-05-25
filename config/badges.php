<?php
/**
 * Badge-Konfiguration
 *
 * Jeder Eintrag ist vollständig selbstbeschreibend:
 *   image    – vollständige URL zum Badge-Bild
 *   label    – [Zeile1, Zeile2] für Tooltip-Titel (beide Zeilen CAPS)
 *   ms       – Meilenstein-Text im Tooltip (z.B. "1 km" oder "Sonnenbrand geholt")
 *   category – Gruppenname für das Badge-Grid (Reihenfolge = Reihenfolge der ersten Nennung)
 *
 * Neues Badge hinzufügen: einfach Eintrag ergänzen – fertig.
 */

$_BASE = 'https://kanuhub.de/media/';

return [

    // ── Distanz ──────────────────────────────────────────────────────────────
    '1km'    => ['image' => $_BASE . 'badge_kilometer_1.png',    'label' => ['ERSTER',     'KILOMETER'],   'ms' => '1 km',       'category' => 'Distanz'],
    '10km'   => ['image' => $_BASE . 'badge_kilometer_10.png',   'label' => ['10 KM',      'PADDLER'],     'ms' => '10 km',      'category' => 'Distanz'],
    '50km'   => ['image' => $_BASE . 'badge_kilometer_50.png',   'label' => ['50 KM',      'PADDLER'],     'ms' => '50 km',      'category' => 'Distanz'],
    '100km'  => ['image' => $_BASE . 'badge_kilometer_100.png',  'label' => ['HUNDERT',    'KILOMETER'],   'ms' => '100 km',     'category' => 'Distanz'],
    '250km'  => ['image' => $_BASE . 'badge_kilometer_250.png',  'label' => ['AUSDAUER-',  'PADDLER'],     'ms' => '250 km',     'category' => 'Distanz'],
    '500km'  => ['image' => $_BASE . 'badge_kilometer_500.png',  'label' => ['HALB-',      'MARATHONI'],   'ms' => '500 km',     'category' => 'Distanz'],
    '1000km' => ['image' => $_BASE . 'badge_kilometer_1000.png', 'label' => ['DIAMANT-',   'PADDLER'],     'ms' => '1.000 km',   'category' => 'Distanz'],
    '1500km' => ['image' => $_BASE . 'badge_kilometer_1500.png', 'label' => ['1.500 KM',   'PADDLER'],     'ms' => '1.500 km',   'category' => 'Distanz'],
    '2000km' => ['image' => $_BASE . 'badge_kilometer_2000.png', 'label' => ['2.000 KM',   'PADDLER'],     'ms' => '2.000 km',   'category' => 'Distanz'],

    // ── Fahrten ───────────────────────────────────────────────────────────────
    'erste-ausfahrt' => ['image' => $_BASE . 'badge_ausfahrten_1.png',   'label' => ['ERSTE',      'AUSFAHRT'],    'ms' => '1 Fahrt',     'category' => 'Fahrten'],
    'trips_5'        => ['image' => $_BASE . 'badge_ausfahrten_5.png',   'label' => ['5',          'FAHRTEN'],     'ms' => '5 Fahrten',   'category' => 'Fahrten'],
    'trips_10'       => ['image' => $_BASE . 'badge_ausfahrten_10.png',  'label' => ['10',         'FAHRTEN'],     'ms' => '10 Fahrten',  'category' => 'Fahrten'],
    'trips_50'       => ['image' => $_BASE . 'badge_ausfahrten_50.png',  'label' => ['FLEISSIGER', 'PADDLER'],     'ms' => '50 Fahrten',  'category' => 'Fahrten'],
    'trips_100'      => ['image' => $_BASE . 'badge_ausfahrten_100.png', 'label' => ['CENTURION',  ''],            'ms' => '100 Fahrten', 'category' => 'Fahrten'],

    // ── Jahreszeit ────────────────────────────────────────────────────────────
    'fruehling'    => ['image' => $_BASE . 'badge_jahreszeit_fruehling.png',      'label' => ['FRÜHLINGS-', 'PADDLER'],    'ms' => 'Frühling',        'category' => 'Jahreszeit'],
    'sommer'       => ['image' => $_BASE . 'badge_jahreszeit_sommer.png',         'label' => ['SOMMER-',    'PADDLER'],    'ms' => 'Sommer',          'category' => 'Jahreszeit'],
    'herbst'       => ['image' => $_BASE . 'badge_jahreszeit_herbst.png',         'label' => ['HERBST-',    'WANDERER'],   'ms' => 'Herbst',          'category' => 'Jahreszeit'],
    'winter'       => ['image' => $_BASE . 'badge_jahreszeit_winter.png',         'label' => ['WINTER-',    'PADDLER'],    'ms' => 'Winter',          'category' => 'Jahreszeit'],
    'jahreszeiten' => ['image' => $_BASE . 'badge_jahreszeit_4-jahreszeiten.png', 'label' => ['VIER',       'JAHRESZEITEN'], 'ms' => '4 Jahreszeiten', 'category' => 'Jahreszeit'],

    // ── Boote ─────────────────────────────────────────────────────────────────
    'stammkunde'          => ['image' => $_BASE . 'badge_gleiches_boot_5.png',    'label' => ['STAMM-',    'KUNDE'],    'ms' => '5× selbes Boot',       'category' => 'Boote'],
    'bootsliebe'          => ['image' => $_BASE . 'badge_gleiches_boot_10.png',   'label' => ['BOOTS-',    'LIEBE'],    'ms' => '10× selbes Boot',      'category' => 'Boote'],
    'seekajak_verwendet'  => ['image' => $_BASE . 'badge_boot_seekajak.png',      'label' => ['SEA',       'KAYAKER'],  'ms' => 'Seekajak gefahren',    'category' => 'Boote'],
    'rennkajak_verwendet' => ['image' => $_BASE . 'badge_boot_rennkajak.png',     'label' => ['SPEED',     'PADDLER'],  'ms' => 'Rennkajak gefahren',   'category' => 'Boote'],
    'surfski_verwendet'   => ['image' => $_BASE . 'badge_boot_surfski.png',       'label' => ['SURF',      'SKI'],      'ms' => 'Surfski gefahren',     'category' => 'Boote'],
    'canadier_verwendet'  => ['image' => $_BASE . 'badge_boot_canadier.png',      'label' => ['CANO-',     'EIST'],     'ms' => 'Canadier gefahren',    'category' => 'Boote'],
    'sup_verwendet'       => ['image' => $_BASE . 'badge_boot_sup.png',           'label' => ['STAND UP',  'PADDLER'],  'ms' => 'SUP gefahren',         'category' => 'Boote'],
    'outrigger_verwendet' => ['image' => $_BASE . 'badge_boot_outrigger.png',     'label' => ['OUT-',      'RIGGER'],   'ms' => 'Outrigger gefahren',   'category' => 'Boote'],
    'allrounder'          => ['image' => $_BASE . 'badge_3_verschiedene_boote.png','label' => ['ALL-',     'ROUNDER'],  'ms' => '3 verschiedene Boote', 'category' => 'Boote'],

    // ── Gemeinsam erleben ─────────────────────────────────────────────────────
    'gemeinsame-ausfahrt-1'  => ['image' => $_BASE . 'badge_gemeinsame_ausfahrt_1.png',  'label' => ['ERSTE',        'GEMEINSAME'],    'ms' => '1 Gruppenfahrt',   'category' => 'Gemeinsam erleben'],
    'gemeinsame-ausfahrt-5'  => ['image' => $_BASE . 'badge_gemeinsame_ausfahrt_5.png',  'label' => ['5 GEMEINSAME', 'AUSFAHRTEN'],    'ms' => '5 Gruppenfahrten', 'category' => 'Gemeinsam erleben'],
    'gemeinsame-ausfahrt-10' => ['image' => $_BASE . 'badge_gemeinsame_ausfahrt_10.png', 'label' => ['TEAM-',        'PLAYER'],        'ms' => '10 Gruppenfahrten','category' => 'Gemeinsam erleben'],
    'geplante-ausfahrt-1'    => ['image' => $_BASE . 'badge_geplante_ausfahrt_1.png',    'label' => ['ERSTE',        'GEPLANTE'],      'ms' => '1 Termin-Fahrt',   'category' => 'Gemeinsam erleben'],
    'geplante-ausfahrt-5'    => ['image' => $_BASE . 'badge_geplante_ausfahrt_5.png',    'label' => ['5 GEPLANTE',   'AUSFAHRTEN'],    'ms' => '5 Termin-Fahrten', 'category' => 'Gemeinsam erleben'],
    'geplante-ausfahrt-10'   => ['image' => $_BASE . 'badge_geplante_ausfahrt_10.png',   'label' => ['TERMIN-',      'TREUER'],        'ms' => '10 Termin-Fahrten','category' => 'Gemeinsam erleben'],
    'gruppe-beigetreten-1'   => ['image' => $_BASE . 'badge_gruppe_1.png',               'label' => ['ERSTE',        'GRUPPE'],        'ms' => '1× beigetreten',   'category' => 'Gemeinsam erleben'],
    'gruppe-beigetreten-5'   => ['image' => $_BASE . 'badge_gruppe_5.png',               'label' => ['5×',           'BEIGETRETEN'],   'ms' => '5× beigetreten',   'category' => 'Gemeinsam erleben'],
    'gruppe-beigetreten-10'  => ['image' => $_BASE . 'badge_gruppe_10.png',              'label' => ['GESELLIGER',   'PADDLER'],       'ms' => '10× beigetreten',  'category' => 'Gemeinsam erleben'],

    // ── Training ──────────────────────────────────────────────────────────────
    'training-1'  => ['image' => $_BASE . 'badge_training_1.png',  'label' => ['ERSTES',     'TRAINING'],  'ms' => '1 Training',   'category' => 'Training'],
    'training-5'  => ['image' => $_BASE . 'badge_training_5.png',  'label' => ['5',          'TRAININGS'], 'ms' => '5 Trainings',  'category' => 'Training'],
    'training-10' => ['image' => $_BASE . 'badge_training_10.png', 'label' => ['TRAININGS-', 'PROFI'],     'ms' => '10 Trainings', 'category' => 'Training'],
    'training-20' => ['image' => $_BASE . 'badge_training_20.png', 'label' => ['TRAININGS-', 'JUNKIE'],    'ms' => '20 Trainings', 'category' => 'Training'],

    // ── Mitgliedschaft ────────────────────────────────────────────────────────
    'stammgast' => ['image' => $_BASE . 'badge_aktiv_3_saison.png', 'label' => ['STAMM-', 'GAST'],    'ms' => '3 Saisonen aktiv', 'category' => 'Mitgliedschaft'],
    'veteran'   => ['image' => $_BASE . 'badge_aktiv_5_saison.png', 'label' => ['CLUB-',  'VETERAN'], 'ms' => '5+ Saisonen aktiv','category' => 'Mitgliedschaft'],

    // ── Spezial ───────────────────────────────────────────────────────────────
    'fruehaufsteher' => ['image' => $_BASE . 'badge_zeit_fruehaufsteher.png', 'label' => ['FRÜH-',  'AUFSTEHER'], 'ms' => 'vor 07:00',  'category' => 'Spezial'],
    'nachteule'      => ['image' => $_BASE . 'badge_zeit_nachteule.png',      'label' => ['NACHT-', 'EULE'],      'ms' => 'nach 20:00', 'category' => 'Spezial'],

    // ── Kenterung ────────────────────────────────────────────────────────────
    'gekentert-1'     => ['image' => $_BASE . 'badge_gekentert_1.png',     'label' => ['ERSTER',    'KENTERN'],    'ms' => '1× gekentert',           'category' => 'Kenterung'],
    'gekentert-2'     => ['image' => $_BASE . 'badge_gekentert_2.png',     'label' => ['ZWEIMAL',   'GEKENTERT'],  'ms' => '2× gekentert',           'category' => 'Kenterung'],
    'gekentert-5'     => ['image' => $_BASE . 'badge_gekentert_5.png',     'label' => ['5×',        'GEKENTERT'],  'ms' => '5× gekentert',           'category' => 'Kenterung'],
    'gekentert-10'    => ['image' => $_BASE . 'badge_gekentert_10.png',    'label' => ['ZEHNMAL',   'GEKENTERT'],  'ms' => '10× gekentert',          'category' => 'Kenterung'],
    'gekentert-20'    => ['image' => $_BASE . 'badge_gekentert_20.png',    'label' => ['KENTERN-',  'PROFI'],      'ms' => '20× gekentert',          'category' => 'Kenterung'],
    
    // ── Erlebnisse ────────────────────────────────────────────────────────────
    'sonnenbrand'     => ['image' => $_BASE . 'badge_sonnenbrand.png',     'label' => ['SONNEN-',   'BRAND'],      'ms' => 'Sonnenbrand geholt',      'category' => 'Erlebnisse'],
    'stechmuecke'     => ['image' => $_BASE . 'badge_stechmuecke.png',     'label' => ['STECHEN-',  'OPFER'],      'ms' => 'Mückenstiche kassiert',   'category' => 'Erlebnisse'],
    'regen'           => ['image' => $_BASE . 'badge_regen.png',           'label' => ['REGEN-',    'PADDLER'],    'ms' => 'Nass geworden',           'category' => 'Erlebnisse'],
    'wellen'          => ['image' => $_BASE . 'badge_wellen.png',          'label' => ['WELLEN-',   'REITER'],     'ms' => 'Starke Wellen',           'category' => 'Erlebnisse'],
    'wind'            => ['image' => $_BASE . 'badge_wind.png',            'label' => ['WIND-',     'KÄMPFER'],    'ms' => 'Starker Wind',            'category' => 'Erlebnisse'],
    'sonnenaufgang'   => ['image' => $_BASE . 'badge_sonnenaufgang.png',   'label' => ['SONNEN-',   'AUFGANG'],    'ms' => 'Sonnenaufgang erlebt',    'category' => 'Erlebnisse'],
    'sonnenuntergang' => ['image' => $_BASE . 'badge_sonnenuntergang.png', 'label' => ['SONNEN-',   'UNTERGANG'],  'ms' => 'Sonnenuntergang erlebt',  'category' => 'Erlebnisse'],

];
