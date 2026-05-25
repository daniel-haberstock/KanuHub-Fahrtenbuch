-- ============================================================
-- Fahrtenbuch Bootshaus - Komplettes Datenbankschema
-- ============================================================
-- MySQL/MariaDB Datenbank für das Fahrtenbuch-System
-- Universell einsetzbar für beliebige Kanu-/Kajak-Vereine
-- Keine Demo-Daten, produktionsbereit
-- ============================================================

-- Datenbank erstellen (Name anpassen!)
CREATE DATABASE IF NOT EXISTS fahrtenbuch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fahrtenbuch;

-- ============================================================
-- TABELLE: Mitglieder
-- ============================================================
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    salutation ENUM('Herr', 'Frau', 'Divers') DEFAULT 'Herr',
    status ENUM('Gastmitglied', 'Jugendmitglied', 'Passiv', 'Ehrenmitglied', 'Aktiv') DEFAULT 'Aktiv',
    membership_no VARCHAR(50) UNIQUE,
    email VARCHAR(255),
    password VARCHAR(255) NULL DEFAULT NULL COMMENT 'DEPRECATED — Auth via REDAXO-API. Wird nicht mehr befüllt.',
    valid_until DATE NULL COMMENT 'Mitglied bis (NULL = unbegrenzt, Datum = ab dann inaktiv/nicht mehr in Dropdowns)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_membership_no (membership_no),
    INDEX idx_name (last_name, first_name),
    INDEX idx_valid_until (valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Gewässer
-- ============================================================
CREATE TABLE IF NOT EXISTS waters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Strecken
-- ============================================================
CREATE TABLE IF NOT EXISTS routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    start_point VARCHAR(255) NOT NULL,
    end_point VARCHAR(255) NOT NULL,
    water_id INT,
    description VARCHAR(500) DEFAULT NULL,
    distance DECIMAL(10, 2) NOT NULL COMMENT 'Entfernung in Kilometern',
    is_roundtrip BOOLEAN DEFAULT FALSE COMMENT 'Rundfahrt (Start = Ziel)',
    start_is_boathouse BOOLEAN DEFAULT TRUE COMMENT 'Startpunkt ist Bootshaus',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (water_id) REFERENCES waters(id) ON DELETE SET NULL,
    INDEX idx_water (water_id),
    UNIQUE KEY uq_route_desc (description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Boot-Icons
-- ============================================================
CREATE TABLE IF NOT EXISTS boat_icons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Anzeigename des Icons',
    category ENUM('Boot', 'Paddel', 'Schwimmweste', 'Helm', 'Spritzdecke', 'Zubehoer') NOT NULL DEFAULT 'Boot'
        COMMENT 'Kategorie zur Gruppierung',
    file_path VARCHAR(500) NOT NULL COMMENT 'Pfad zur Datei (relativ zu /assets/icons/boats/)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Boote
-- ============================================================
CREATE TABLE IF NOT EXISTS boats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boat_name VARCHAR(100) NOT NULL UNIQUE,
    boat_type ENUM('Seekajak', 'Tourenkajak', 'Freizeitkajak', 'Rennkajak', 'Surf Ski', 'Faltboot', 'Modul-Kajak', 'Packraft', 'Canadier', 'Wildwasser Kajak', 'Wildwasser-Canadier', 'Outrigger', 'SUP', 'Anhänger') NOT NULL,
    seats INT NOT NULL CHECK (seats >= 1 AND seats <= 10),
    default_crew_1 INT NULL COMMENT 'Standard-Mannschaft Platz 1',
    default_crew_2 INT NULL COMMENT 'Standard-Mannschaft Platz 2',
    valid_until DATE NULL COMMENT 'Boot im Bestand bis (NULL = unbegrenzt)',
    storage_location VARCHAR(255) NULL COMMENT 'Bootsplatz/Lagerort',
    note_start TEXT NULL COMMENT 'Hinweis bei Fahrtbeginn',
    note_end TEXT NULL COMMENT 'Hinweis bei Rückkehr',
    video_url VARCHAR(500) NULL COMMENT 'YouTube-Anleitungsvideo URL',
    boat_image VARCHAR(500) NULL COMMENT 'URL zum eigenen Boot-Bild (relativ oder absolut)',
    boat_icon_id INT NULL COMMENT 'Referenz auf boat_icons (eigenes Icon)',
    boat_details JSON NULL COMMENT 'Boot-Steckbrief als JSON (Modell, Hersteller, Technik, Eignung, etc.)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (default_crew_1) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (default_crew_2) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (boat_icon_id) REFERENCES boat_icons(id) ON DELETE SET NULL,
    INDEX idx_boat_name (boat_name),
    INDEX idx_boat_type (boat_type),
    INDEX idx_valid_until (valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Fahrtgruppen (z.B. Wanderfahrten, Regatten)
-- ============================================================
CREATE TABLE IF NOT EXISTS session_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    route VARCHAR(255),
    organizer VARCHAR(255),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Fahrtengruppen (gemeinsame Ausfahrten)
-- ============================================================
-- Kurzlebige Klammer um mehrere Einzel-Trips die gemeinsam unterwegs sind.
-- Ermöglicht Sammelbeendigung: Eine Person beendet für alle.
CREATE TABLE IF NOT EXISTS trip_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'z.B. "Paddeltreff 15.05.2026"',
    source_type ENUM('termin', 'local_event', 'manual') NOT NULL DEFAULT 'manual'
        COMMENT 'Woher die Gruppe stammt (REDAXO-Termin, lokales Event, manuell)',
    source_id INT NULL COMMENT 'ID des REDAXO-Termins oder local_events-Eintrags',
    created_by_trip_id INT NULL COMMENT 'Erste Fahrt die die Gruppe gegründet hat',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_completed BOOLEAN DEFAULT FALSE COMMENT 'Alle Fahrten beendet',
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_source (source_type, source_id),
    INDEX idx_completed (is_completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Lokale Events (wiederkehrende Trainings/Ausfahrten)
-- ============================================================
-- Im Admin pflegbare Events mit optionaler Wiederholung (wöchentlich/monatlich).
-- Ersetzt den hardcodierten Paddeltreff.
CREATE TABLE IF NOT EXISTS local_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'z.B. "Donnerstags-Paddeltreff"',
    description TEXT NULL COMMENT 'Beschreibung/Infos zur Veranstaltung',
    start_time TIME NOT NULL COMMENT 'Uhrzeit (z.B. 18:00)',
    color VARCHAR(7) NOT NULL DEFAULT '#28a745' COMMENT 'Anzeigefarbe (Hex)',
    icon VARCHAR(50) NOT NULL DEFAULT 'bi-calendar-event' COMMENT 'Bootstrap-Icon-Klasse',
    is_recurring BOOLEAN DEFAULT FALSE COMMENT 'Wiederkehrendes Event',
    recurrence_type ENUM('weekly', 'monthly') NULL COMMENT 'Art der Wiederholung',
    recurrence_day TINYINT NULL
        COMMENT 'Wochentag (1=Mo..7=So) bei weekly, Tag im Monat (1-31) bei monthly',
    valid_from DATE NULL COMMENT 'Wiederkehrend gültig ab (NULL = sofort)',
    valid_to DATE NULL COMMENT 'Wiederkehrend gültig bis (NULL = unbegrenzt)',
    one_time_date DATE NULL COMMENT 'Datum für einmalige Events',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_recurring (is_recurring, recurrence_type, recurrence_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Fahrten (Logbuch)
-- ============================================================
CREATE TABLE IF NOT EXISTS trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boat_id INT NULL COMMENT 'Boot aus Dropdown (ID)',
    boat_name VARCHAR(255) NULL COMMENT 'Boot freie Eingabe (Name)',
    start_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_date DATE,
    end_time TIME,
    route_id INT NULL COMMENT 'Strecke aus Dropdown',
    route_custom VARCHAR(255) NULL COMMENT 'Freie Streckeneingabe',
    distance DECIMAL(10, 2) NULL COMMENT 'Entfernung in Kilometern',
    session_type ENUM('Normal', 'Tour', 'Wanderfahrt', 'Regatta', 'Trainingslager', 'Schulung') DEFAULT 'Normal' COMMENT 'Fahrtart',
    session_group_id INT NULL COMMENT 'Zugehörigkeit zu Session-Gruppe (Wanderfahrt etc.)',
    trip_group_id INT NULL COMMENT 'Zugehörigkeit zu Fahrtengruppe (gemeinsame Ausfahrt)',
    comments TEXT COMMENT 'Bemerkungen zur Fahrt',
    is_completed BOOLEAN DEFAULT FALSE,
    is_overdue BOOLEAN DEFAULT FALSE COMMENT 'End-Zeit überschritten',
    is_backlog BOOLEAN DEFAULT FALSE COMMENT 'Nachtrag',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (boat_id) REFERENCES boats(id) ON DELETE SET NULL,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL,
    FOREIGN KEY (session_group_id) REFERENCES session_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (trip_group_id) REFERENCES trip_groups(id) ON DELETE SET NULL,
    INDEX idx_boat (boat_id),
    INDEX idx_boat_name (boat_name),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_completed (is_completed),
    INDEX idx_session_type (session_type),
    INDEX idx_session_group (session_group_id),
    INDEX idx_trip_group (trip_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Fahrten-Crew (bis zu 10 Personen pro Boot)
-- ============================================================
CREATE TABLE IF NOT EXISTS trip_crew (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    seat_position INT NOT NULL CHECK (seat_position >= 1 AND seat_position <= 10),
    member_id INT NULL COMMENT 'Mitglied aus Dropdown',
    member_name VARCHAR(255) NULL COMMENT 'Freie Eingabe des Namens',
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    INDEX idx_trip (trip_id),
    INDEX idx_member (member_id),
    UNIQUE KEY unique_trip_seat (trip_id, seat_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Bootsreservierungen
-- ============================================================
CREATE TABLE IF NOT EXISTS boat_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boat_id INT NOT NULL,
    member_id INT NULL COMMENT 'Mitglied aus Dropdown',
    member_name VARCHAR(255) NULL COMMENT 'Freie Eingabe des Namens',
    reservation_type ENUM('ONETIME', 'WEEKLY', 'WEEKLY_LIMITED') DEFAULT 'ONETIME' COMMENT 'Reservierungstyp',
    reservation_start DATETIME NOT NULL,
    reservation_end DATETIME NOT NULL,
    day_of_week TINYINT NULL COMMENT 'Wochentag für wöchentliche Reservierungen (1=Montag, 7=Sonntag)',
    valid_from DATE NULL COMMENT 'Gültig ab (für zeitlich begrenzte wöchentliche Reservierungen)',
    valid_to DATE NULL COMMENT 'Gültig bis (für zeitlich begrenzte wöchentliche Reservierungen)',
    reason TEXT NOT NULL,
    phone VARCHAR(50),
    water_id INT NULL COMMENT 'Gewässer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (boat_id) REFERENCES boats(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (water_id) REFERENCES waters(id) ON DELETE SET NULL,
    INDEX idx_boat (boat_id),
    INDEX idx_dates (reservation_start, reservation_end),
    INDEX idx_member (member_id),
    INDEX idx_type (reservation_type),
    INDEX idx_day_of_week (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Bootsschäden
-- ============================================================
CREATE TABLE IF NOT EXISTS boat_damages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boat_id INT NOT NULL,
    damage_description TEXT NOT NULL,
    damage_type ENUM('Boot voll nutzbar', 'Boot eingeschränkt nutzbar', 'Boot nicht nutzbar') NOT NULL,
    reporter_member_id INT NULL COMMENT 'Mitglied aus Dropdown',
    reporter_name VARCHAR(255) NULL COMMENT 'Freie Eingabe des Namens',
    is_fixed BOOLEAN DEFAULT FALSE,
    fixed_at TIMESTAMP NULL,
    fixed_by_member_id INT NULL COMMENT 'Repariert von (Mitglied)',
    fixed_by_name VARCHAR(255) NULL COMMENT 'Repariert von (freie Eingabe)',
    repair_cost DECIMAL(10, 2) NULL COMMENT 'Materialkosten in Euro',
    work_hours DECIMAL(5, 2) NULL COMMENT 'Benötigte Arbeitszeit in Stunden',
    is_insurance_claim BOOLEAN DEFAULT FALSE COMMENT 'Versicherungsfall',
    repair_notes TEXT COMMENT 'Bemerkungen zur Reparatur',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (boat_id) REFERENCES boats(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_member_id) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (fixed_by_member_id) REFERENCES members(id) ON DELETE SET NULL,
    INDEX idx_boat (boat_id),
    INDEX idx_fixed (is_fixed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Arbeitsdienst (Work Duty)
-- ============================================================
CREATE TABLE IF NOT EXISTS work_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    work_date DATE NOT NULL,
    hours DECIMAL(3, 1) NOT NULL,
    description TEXT NOT NULL,
    work_leader VARCHAR(255) NULL COMMENT 'Name des Arbeitsdienst-Leiters (nur bei Admin-Einträgen)',
    created_by VARCHAR(50) DEFAULT 'member' COMMENT 'member oder admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_member_date (member_id, work_date),
    INDEX idx_work_date (work_date),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Anhänger-Fahrten (Separate Statistik, nur für Admin)
-- ============================================================
CREATE TABLE IF NOT EXISTS trailer_trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boat_id INT NULL COMMENT 'Anhänger aus Dropdown (ID)',
    boat_name VARCHAR(255) NULL COMMENT 'Anhänger freie Eingabe (Name)',
    start_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_date DATE,
    end_time TIME,
    route_id INT NULL COMMENT 'Strecke aus Dropdown',
    route_custom VARCHAR(255) NULL COMMENT 'Freie Streckeneingabe',
    water_id INT NULL COMMENT 'Gewässer aus Dropdown',
    water_custom VARCHAR(255) NULL COMMENT 'Freie Gewässer-Eingabe',
    distance DECIMAL(10, 2) NULL COMMENT 'Entfernung in Kilometern',
    session_type ENUM('Normal', 'Tour', 'Wanderfahrt', 'Regatta', 'Trainingslager', 'Schulung') DEFAULT 'Normal' COMMENT 'Fahrtart',
    session_group_id INT NULL COMMENT 'Zugehörigkeit zu Fahrtgruppe',
    trip_group_id INT NULL COMMENT 'Zugehörigkeit zu Fahrtengruppe (gemeinsame Ausfahrt)',
    comments TEXT COMMENT 'Bemerkungen zur Fahrt',
    is_completed BOOLEAN DEFAULT FALSE,
    is_overdue BOOLEAN DEFAULT FALSE COMMENT 'End-Zeit überschritten',
    is_backlog BOOLEAN DEFAULT FALSE COMMENT 'Nachtrag',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (boat_id) REFERENCES boats(id) ON DELETE SET NULL,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL,
    FOREIGN KEY (water_id) REFERENCES waters(id) ON DELETE SET NULL,
    FOREIGN KEY (session_group_id) REFERENCES session_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (trip_group_id) REFERENCES trip_groups(id) ON DELETE SET NULL,
    INDEX idx_boat (boat_id),
    INDEX idx_boat_name (boat_name),
    INDEX idx_water (water_id),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_completed (is_completed),
    INDEX idx_session_type (session_type),
    INDEX idx_session_group (session_group_id),
    INDEX idx_trip_group (trip_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Anhänger-Fahrten-Crew
-- ============================================================
CREATE TABLE IF NOT EXISTS trailer_trip_crew (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    seat_position INT NOT NULL CHECK (seat_position >= 1 AND seat_position <= 10),
    member_id INT NULL COMMENT 'Mitglied aus Dropdown',
    member_name VARCHAR(255) NULL COMMENT 'Freie Eingabe des Namens',
    FOREIGN KEY (trip_id) REFERENCES trailer_trips(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    INDEX idx_trip (trip_id),
    INDEX idx_member (member_id),
    UNIQUE KEY unique_trip_seat (trip_id, seat_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Admin-Benutzer
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STANDARD-DATEN: Admin-Benutzer
-- ============================================================
-- Standard Admin-Benutzer (Benutzername: admin, Passwort: password)
-- WICHTIG: Passwort nach der Installation ändern!
INSERT INTO admins (username, password) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- ============================================================
-- TABELLE: System-Meldungen (bearbeitbare Hinweise/Warnungen)
-- ============================================================
CREATE TABLE IF NOT EXISTS system_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    msg_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'Eindeutiger Schlüssel',
    msg_label VARCHAR(255) NOT NULL COMMENT 'Beschreibung für Admin-UI',
    msg_text TEXT NOT NULL COMMENT 'Meldungstext',
    msg_type ENUM('danger','warning','info','secondary') NOT NULL DEFAULT 'info' COMMENT 'Bootstrap-Alert-Typ',
    msg_icon VARCHAR(100) NOT NULL DEFAULT 'bi-info-circle' COMMENT 'Bootstrap-Icon-Klasse',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=aktiv, 0=deaktiviert',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MIGRATION: E-Mail Benachrichtigungen (2026-04)
-- ============================================================
-- Neue Spalten in members-Tabelle (falls noch nicht vorhanden):
--
-- ALTER TABLE members
--   ADD COLUMN IF NOT EXISTS email_notify_after_trip TINYINT(1) NOT NULL DEFAULT 0,
--   ADD COLUMN IF NOT EXISTS email_notify_weekly     TINYINT(1) NOT NULL DEFAULT 0;
--
-- ============================================================

-- ============================================================
-- TABELLE: E-Mail Benachrichtigungs-Queue
-- ============================================================
CREATE TABLE IF NOT EXISTS email_notifications (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    member_id     INT NOT NULL,
    type          ENUM('trip','weekly') NOT NULL,
    reference_key VARCHAR(20) NOT NULL COMMENT 'Datum YYYY-MM-DD für trip, YYYY-WXX für weekly',
    status        ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at       DATETIME NULL,
    UNIQUE KEY uq_member_type_ref (member_id, type, reference_key),
    INDEX idx_status (status),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Mitglieder-Badges
-- ============================================================
CREATE TABLE IF NOT EXISTS member_badges (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    member_id   INT NOT NULL,
    badge_key   VARCHAR(50) NOT NULL,
    seen        TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = neu/ungesehen, 1 = vom Mitglied gesehen',
    earned_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member_badge (member_id, badge_key),
    INDEX idx_member (member_id),
    INDEX idx_badge_key (badge_key),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: Badge-Konfiguration (Scope + Aktiviert)
-- ============================================================
CREATE TABLE IF NOT EXISTS badge_config (
    badge_key   VARCHAR(50) PRIMARY KEY,
    scope       ENUM('season','lifetime') NOT NULL DEFAULT 'season',
    enabled     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: RFID-Karten (Quick-Start am Kiosk)
-- ============================================================
CREATE TABLE IF NOT EXISTS rfid_cards (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    uid        VARCHAR(20)  NOT NULL,
    name       VARCHAR(80)  NOT NULL,
    member_id  INT NOT NULL,
    boat_id    INT NULL,
    created_at DATETIME NOT NULL DEFAULT NOW(),
    UNIQUE KEY uq_uid (uid),
    INDEX idx_member (member_id),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (boat_id)   REFERENCES boats(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INSTALLATION ABGESCHLOSSEN
-- ============================================================
-- Das Datenbankschema wurde erfolgreich erstellt.
--
-- Nächste Schritte:
-- 1. Admin-Passwort ändern (Standard: admin/password)
-- 2. YCom-Synchronisation durchführen um Mitglieder zu importieren
-- 3. Gewässer und Strecken über Admin-Bereich anlegen
-- 4. Optional: XML-Import für alte EFA-Daten nutzen
-- ============================================================

-- ============================================================
-- Migrationen (konsolidiert)
-- ============================================================

-- ── 2026-04_efb_sync.sql ──
-- eFB-Integration: Nutzer-ID und Sync-Zeitstempel
ALTER TABLE members ADD COLUMN efb_id INT DEFAULT NULL COMMENT 'DKV eFB Nutzer-ID';
ALTER TABLE trips   ADD COLUMN efb_sync_at BIGINT DEFAULT NULL COMMENT 'Unix-Timestamp der letzten eFB-Synchronisierung';

-- ── 2026-04_email_notifications.sql ──
-- ============================================================
-- Migration: E-Mail Benachrichtigungen
-- Datum: 2026-04
-- ============================================================

-- Neue Spalten in members
ALTER TABLE members
    ADD COLUMN email_notify_after_trip TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN email_notify_weekly     TINYINT(1) NOT NULL DEFAULT 0;

-- Queue-Tabelle für ausstehende E-Mails
CREATE TABLE email_notifications (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    member_id     INT NOT NULL,
    type          ENUM('trip','weekly') NOT NULL,
    reference_key VARCHAR(20) NOT NULL COMMENT 'YYYY-MM-DD für trip, YYYY-WXX für weekly',
    status        ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at       DATETIME NULL,
    UNIQUE KEY uq_member_type_ref (member_id, type, reference_key),
    INDEX idx_status (status),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2026-04_fix_trip_crew_member_ids.sql ──
-- ============================================================
-- Migration: trip_crew member_id aus member_name nachpflegen
-- Datum: 2026-04-12
--
-- Problem: Bei Mehrsitzern wird oft nur member_name gespeichert
--          z.B. "Martin Lenhart-Höß (1153)" ohne member_id.
--          Die Zahl in Klammern ist die membership_no.
--
-- Vorher prüfen (Anzahl betroffener Zeilen):
--   SELECT COUNT(*) FROM trip_crew WHERE member_id IS NULL AND member_name REGEXP '\\([0-9]+\\)$';
--
-- Trockenlauf (zeigt was aktualisiert wird):
--   SELECT tc.id, tc.member_name, m.id AS resolved_member_id, m.first_name, m.last_name
--   FROM trip_crew tc
--   JOIN members m ON m.membership_no = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(tc.member_name, '(', -1), ')', 1))
--   WHERE tc.member_id IS NULL
--     AND tc.member_name REGEXP '\\([0-9]+\\)[[:space:]]*$';
-- ============================================================

UPDATE trip_crew tc
JOIN members m
  ON m.membership_no = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(tc.member_name, '(', -1), ')', 1))
SET tc.member_id = m.id
WHERE tc.member_id IS NULL
  AND tc.member_name REGEXP '\\([0-9]+\\)[[:space:]]*$';

-- ── 2026-04_last_login_stats.sql ──
-- ============================================================
-- Migration: Letzter Login im Mitglieder-Bereich
-- Datum: 2026-04
-- ============================================================

ALTER TABLE members
    ADD COLUMN last_login_stats DATETIME NULL COMMENT 'Letzter Login im Statistik-/Mitglieder-Bereich';

-- ── 2026-04_tracker_hwid.sql ──
-- Self-Enrollment: Tracker identifiziert sich per Hardware-UID (12-hex MAC).
-- Admin vergibt den Anzeige-Namen nachträglich.
-- Additive Migration: bestehende Tracker bleiben erhalten (hwid = NULL bis Tracker sich meldet).

ALTER TABLE tracker
  ADD COLUMN hwid CHAR(12) NULL AFTER id,
  ADD UNIQUE KEY uq_hwid (hwid);

-- Nummer wird optional (auto-enrollte Geräte haben erst mal keinen Namen)
ALTER TABLE tracker
  MODIFY nummer VARCHAR(32) NULL;

-- Enrollment-Secret (Shared Secret für Erstkontakt).
-- Wird hier mit SHA2(UUID()) erstmalig befüllt; Admin kann in Config rotieren.
INSERT INTO app_config (k, v) VALUES
  ('enrollment_secret', SHA2(CONCAT(UUID(), RAND()), 256))
ON DUPLICATE KEY UPDATE v = v;

-- Default neuer Tracker: aktiv=0 (Admin muss freischalten)
ALTER TABLE tracker
  MODIFY aktiv TINYINT(1) NOT NULL DEFAULT 0;

-- ── 2026-04_tracker_last_location.sql ──
-- Letzte bekannte Position des Trackers (vom Heartbeat).
-- Macht im Admin sichtbar, wo sich das Geraet zuletzt gemeldet hat -
-- auch ohne dass aktuell eine Session laeuft.
ALTER TABLE tracker
  ADD COLUMN last_lat     DECIMAL(10,7) NULL AFTER last_seen,
  ADD COLUMN last_lon     DECIMAL(10,7) NULL AFTER last_lat,
  ADD COLUMN last_loc_at  DATETIME      NULL AFTER last_lon;

-- ── 2026-04_tracking.sql ──
-- ============================================================
-- Migration: GPS-Tracking
-- Datum: 2026-04
-- ============================================================

-- Mitglieder: Tracker-Freigabe durch Admin
ALTER TABLE members
    ADD COLUMN tracker_allowed TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Darf GPS-Tracker bei Fahrtstart verwenden (Admin-Freigabe)';

-- GPS-Tracker-Geräte
CREATE TABLE tracker (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nummer           VARCHAR(16) UNIQUE NOT NULL        COMMENT 'Gerätenummer aus Firmware-Config (z.B. KCS-01)',
    api_token        CHAR(64) NOT NULL                  COMMENT 'HMAC-Shared-Secret (hex, 32 Byte random)',
    status           ENUM('idle','armed','recording','uploading','offline')
                     NOT NULL DEFAULT 'offline',
    last_seen        DATETIME NULL,
    battery_mv       INT NULL                           COMMENT 'Akkuspannung in Millivolt',
    battery_pct      TINYINT NULL                       COMMENT 'Akku-Ladestand in Prozent (0-100)',
    charging         TINYINT(1) NOT NULL DEFAULT 0      COMMENT 'USB angeschlossen (VBUS-Sense)',
    fast_poll_until  DATETIME NULL                      COMMENT 'Bis wann 15s-Poll-Intervall aktiv',
    firmware_version VARCHAR(32) NULL,
    firmware_target  VARCHAR(32) NULL                   COMMENT 'Ziel-Version für OTA-Update',
    aktiv            TINYINT(1) NOT NULL DEFAULT 1,
    notes            VARCHAR(255) NULL                  COMMENT 'Freitext z.B. Bootsname',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status, aktiv),
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session: Fahrt ↔ Tracker
CREATE TABLE tracker_sessions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    trip_id     INT NOT NULL,
    tracker_id  INT NOT NULL,
    member_id   INT NOT NULL,
    started_at  DATETIME NOT NULL,
    ended_at    DATETIME NULL,
    status      ENUM('assigned','active','finished','aborted')
                NOT NULL DEFAULT 'assigned',
    nonce       CHAR(32) NOT NULL               COMMENT 'Idempotenz-Key gegen Doppel-Assign',
    UNIQUE KEY uq_trip (trip_id),
    KEY ix_tracker_status (tracker_id, status),
    CONSTRAINT fk_ts_tracker FOREIGN KEY (tracker_id) REFERENCES tracker(id),
    CONSTRAINT fk_ts_trip    FOREIGN KEY (trip_id)    REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_ts_member  FOREIGN KEY (member_id)  REFERENCES members(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GPS-Messpunkte
CREATE TABLE tracker_points (
    id         BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    ts         DATETIME(3) NOT NULL              COMMENT 'Messzeitpunkt (ms-genau)',
    lat        DECIMAL(10,7) NOT NULL,
    lon        DECIMAL(10,7) NOT NULL,
    speed_ms   FLOAT NULL                        COMMENT 'Geschwindigkeit m/s aus NMEA',
    hdop       FLOAT NULL,
    sats       TINYINT NULL,
    KEY ix_session_ts (session_id, ts),
    CONSTRAINT fk_tp_session FOREIGN KEY (session_id)
        REFERENCES tracker_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ausgewertete Kennzahlen je Session
CREATE TABLE tracker_stats (
    session_id   INT PRIMARY KEY,
    distance_m   FLOAT NULL,
    duration_s   INT NULL,
    max_speed_ms FLOAT NULL,
    avg_speed_ms FLOAT NULL,
    best_250m_s  FLOAT NULL,
    best_500m_s  FLOAT NULL,
    best_1000m_s FLOAT NULL,
    best_1500m_s FLOAT NULL,
    best_2000m_s FLOAT NULL,
    computed_at  DATETIME NOT NULL,
    CONSTRAINT fk_stats_session FOREIGN KEY (session_id)
        REFERENCES tracker_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Globale Konfiguration (Geofence, Schwellen)
CREATE TABLE IF NOT EXISTS app_config (
    k          VARCHAR(64) PRIMARY KEY,
    v          VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_config (k, v) VALUES
    ('geofence_lat',            '47.715694'),
    ('geofence_lon',            '8.963472'),
    ('geofence_radius_m',       '50'),
    ('tracker_min_battery_pct', '30'),
    ('tracker_heartbeat_timeout_s', '600')
ON DUPLICATE KEY UPDATE v = VALUES(v);

-- Replay-Schutz: verhindert HMAC-Wiederholung innerhalb 5 min.
-- Nonce (16 Byte hex = 32 chars) wird pro Request vom Tracker generiert.
-- Duplikat-Insert => Replay => 401.
CREATE TABLE tracker_nonces (
    tracker_id INT NOT NULL,
    nonce      VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tracker_id, nonce),
    KEY ix_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2026-04_trip_experiences.sql ──
-- ============================================================
-- Migration: Fahrt-Erlebnisse (trip_experiences)
-- Datum: 2026-04
-- ============================================================

-- Erlebnisse als JSON-Array pro Fahrt speichern
ALTER TABLE trips
    ADD COLUMN trip_experiences JSON NULL COMMENT 'Erlebnisse nach Fahrtende, z.B. ["gekentert","sonnenbrand"]',
    ADD COLUMN trip_notes TEXT NULL COMMENT 'Freitext-Notizen zur Fahrt';

ALTER TABLE trailer_trips
    ADD COLUMN trip_experiences JSON NULL COMMENT 'Erlebnisse nach Fahrtende, z.B. ["gekentert","sonnenbrand"]',
    ADD COLUMN trip_notes TEXT NULL COMMENT 'Freitext-Notizen zur Fahrt';

-- ── 2026-05_app_settings.sql ──
-- Migration: app_settings
-- Additive Tabelle fuer Anwendungseinstellungen (ui_layout etc.)
-- Constraint: Keine bestehenden Tabellen/Spalten werden modifiziert

CREATE TABLE IF NOT EXISTS `app_settings` (
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT         NOT NULL DEFAULT '',
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default: v2-Layout (neues Beta-Design)
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`)
VALUES ('ui_layout', 'v2');

-- ── 2026-05_rfid_cards.sql ──
-- Migration: RFID-Karten (2026-05)
-- Ausführen: mysql -u user -p fahrtenbuch < 2026-05_rfid_cards.sql

CREATE TABLE IF NOT EXISTS rfid_cards (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    uid        VARCHAR(20)  NOT NULL,
    name       VARCHAR(80)  NOT NULL,
    member_id  INT NOT NULL,
    boat_id    INT NULL,
    created_at DATETIME NOT NULL DEFAULT NOW(),
    UNIQUE KEY uq_uid (uid),
    INDEX idx_member (member_id),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (boat_id)   REFERENCES boats(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Magic-Link Authentifizierung
-- ============================================================
CREATE TABLE IF NOT EXISTS auth_magic_links (
    token         VARCHAR(64) NOT NULL PRIMARY KEY,
    member_id     INT NOT NULL,
    expires_at    DATETIME NOT NULL,
    used_at       DATETIME NULL,
    requested_ip  VARCHAR(45) NULL,
    user_agent    VARCHAR(255) NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at),
    INDEX idx_member  (member_id),
    CONSTRAINT fk_magic_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
