-- ============================================================
-- Demo-Daten — wird an schema_with_demo.sql angehängt
-- ============================================================
-- 10 Mitglieder, 8 Boote, ~30 Fahrten, Reservierungen, Schäden.
-- Mailadressen @demo.local sind absichtlich nicht zustellbar —
-- Magic-Link erscheint in logs/magic-link.log (BETA_MODE=true).
-- ============================================================
USE fahrtenbuch;

-- ── Gewässer ─────────────────────────────────────────────────
INSERT INTO waters (name, description) VALUES
  ('Hausgewässer', 'Ständiger Übungsfluss/See vor dem Bootshaus'),
  ('Großer See', 'Größeres Binnengewässer'),
  ('Wanderfluss', 'Mehrtages-Wanderstrecke'),
  ('Trainingsgewässer', 'Kurze Trainingsrunde');

-- ── Strecken ─────────────────────────────────────────────────
INSERT INTO routes (start_point, end_point, water_id, description, distance, is_roundtrip, start_is_boathouse) VALUES
  ('Bootshaus', 'Bootshaus', 1, 'Hausrunde klein',  5.0,  TRUE,  TRUE),
  ('Bootshaus', 'Bootshaus', 1, 'Hausrunde groß',  10.0,  TRUE,  TRUE),
  ('Bootshaus', 'Insel',     2, 'Insel und zurück',12.5,  TRUE,  TRUE),
  ('Bootshaus', 'Brücke',    2, 'Brückentour',      8.0,  FALSE, TRUE),
  ('Wehrsteg',  'Bootshaus', 3, 'Wanderfahrt Tag 1',20.0, FALSE, FALSE),
  ('Bootshaus', 'Trainingsschleife', 4, 'Trainingsschleife', 3.0, TRUE, TRUE),
  ('Bootshaus', 'Schilfbucht', 1, 'Schilfbucht-Rundkurs', 6.5, TRUE, TRUE);

-- ── Mitglieder ───────────────────────────────────────────────
INSERT INTO members (id, first_name, last_name, salutation, status, membership_no, email) VALUES
  (1,'Anna',  'Beispiel', 'Frau', 'Aktiv',         '1001', 'anna@demo.local'),
  (2,'Ben',   'Demo',     'Herr', 'Aktiv',         '1002', 'ben@demo.local'),
  (3,'Carla', 'Test',     'Frau', 'Jugendmitglied','1003', 'carla@demo.local'),
  (4,'Dieter','Probe',    'Herr', 'Aktiv',         '1004', 'dieter@demo.local'),
  (5,'Eva',   'Beispiel', 'Frau', 'Aktiv',         '1005', 'eva@demo.local'),
  (6,'Felix', 'Demo',     'Herr', 'Passiv',        '1006', 'felix@demo.local'),
  (7,'Gabi',  'Test',     'Frau', 'Aktiv',         '1007', 'gabi@demo.local'),
  (8,'Hannes','Probe',    'Herr', 'Aktiv',         '1008', 'hannes@demo.local'),
  (9,'Ina',   'Demo',     'Frau', 'Aktiv',         '1009', 'ina@demo.local'),
  (10,'Jonas','Test',     'Herr', 'Jugendmitglied','1010', 'jonas@demo.local');

-- ── Admin (Vorstand) ─────────────────────────────────────────
-- Anna & Hannes sind die Admins
INSERT INTO admins (id, member_id) VALUES (1,1), (2,8);

-- ── Boote ────────────────────────────────────────────────────
INSERT INTO boats (id, boat_name, boat_type, seats, storage_location) VALUES
  (1, 'Verein-01', 'Canadier',      4, 'Halle A, Regal 1'),
  (2, 'Verein-02', 'Canadier',      3, 'Halle A, Regal 2'),
  (3, 'Verein-03', 'Canadier',      2, 'Halle A, Regal 3'),
  (4, 'Verein-04', 'Tourenkajak',   1, 'Halle B, Regal 1'),
  (5, 'Verein-05', 'Tourenkajak',   2, 'Halle B, Regal 2'),
  (6, 'Verein-06', 'Seekajak',      1, 'Halle B, Regal 3'),
  (7, 'Verein-07', 'SUP',           1, 'Halle C'),
  (8, 'Anna-Privat', 'Freizeitkajak', 1, 'Privat — Anna');

-- ── Fahrten (abgeschlossene) ─────────────────────────────────
-- Verteilt über letzte 60 Tage, mit Distanzen
INSERT INTO trips (boat_id, start_date, start_time, end_date, end_time, route_id, distance, is_completed) VALUES
  (1, CURDATE() - INTERVAL 55 DAY, '14:00', CURDATE() - INTERVAL 55 DAY, '16:00', 2, 10.0, 1),
  (2, CURDATE() - INTERVAL 53 DAY, '10:00', CURDATE() - INTERVAL 53 DAY, '12:30', 3, 12.5, 1),
  (3, CURDATE() - INTERVAL 50 DAY, '09:00', CURDATE() - INTERVAL 50 DAY, '10:15', 1,  5.0, 1),
  (4, CURDATE() - INTERVAL 48 DAY, '16:00', CURDATE() - INTERVAL 48 DAY, '17:30', 6,  3.0, 1),
  (5, CURDATE() - INTERVAL 45 DAY, '11:00', CURDATE() - INTERVAL 45 DAY, '14:00', 7,  6.5, 1),
  (1, CURDATE() - INTERVAL 42 DAY, '13:00', CURDATE() - INTERVAL 42 DAY, '16:00', 2, 10.0, 1),
  (6, CURDATE() - INTERVAL 40 DAY, '08:00', CURDATE() - INTERVAL 40 DAY, '12:00', 4,  8.0, 1),
  (2, CURDATE() - INTERVAL 38 DAY, '17:00', CURDATE() - INTERVAL 38 DAY, '18:30', 1,  5.0, 1),
  (4, CURDATE() - INTERVAL 35 DAY, '14:00', CURDATE() - INTERVAL 35 DAY, '15:30', 6,  3.0, 1),
  (7, CURDATE() - INTERVAL 33 DAY, '15:00', CURDATE() - INTERVAL 33 DAY, '17:00', 1,  5.0, 1),
  (3, CURDATE() - INTERVAL 30 DAY, '10:00', CURDATE() - INTERVAL 30 DAY, '13:00', 2, 10.0, 1),
  (5, CURDATE() - INTERVAL 28 DAY, '11:00', CURDATE() - INTERVAL 28 DAY, '13:30', 3, 12.5, 1),
  (1, CURDATE() - INTERVAL 25 DAY, '09:00', CURDATE() - INTERVAL 25 DAY, '10:30', 1,  5.0, 1),
  (2, CURDATE() - INTERVAL 23 DAY, '16:00', CURDATE() - INTERVAL 23 DAY, '17:00', 6,  3.0, 1),
  (6, CURDATE() - INTERVAL 20 DAY, '08:00', CURDATE() - INTERVAL 20 DAY, '13:00', 5, 20.0, 1),
  (4, CURDATE() - INTERVAL 18 DAY, '17:30', CURDATE() - INTERVAL 18 DAY, '19:00', 1,  5.0, 1),
  (8, CURDATE() - INTERVAL 15 DAY, '13:00', CURDATE() - INTERVAL 15 DAY, '15:00', 7,  6.5, 1),
  (1, CURDATE() - INTERVAL 12 DAY, '14:00', CURDATE() - INTERVAL 12 DAY, '16:00', 2, 10.0, 1),
  (3, CURDATE() - INTERVAL 10 DAY, '10:00', CURDATE() - INTERVAL 10 DAY, '11:30', 1,  5.0, 1),
  (5, CURDATE() - INTERVAL  8 DAY, '16:30', CURDATE() - INTERVAL  8 DAY, '18:00', 4,  8.0, 1),
  (7, CURDATE() - INTERVAL  6 DAY, '11:00', CURDATE() - INTERVAL  6 DAY, '12:30', 1,  5.0, 1),
  (2, CURDATE() - INTERVAL  4 DAY, '14:00', CURDATE() - INTERVAL  4 DAY, '15:30', 6,  3.0, 1),
  (1, CURDATE() - INTERVAL  3 DAY, '09:00', CURDATE() - INTERVAL  3 DAY, '12:00', 3, 12.5, 1),
  (4, CURDATE() - INTERVAL  2 DAY, '17:00', CURDATE() - INTERVAL  2 DAY, '18:30', 1,  5.0, 1),
  (6, CURDATE() - INTERVAL  1 DAY, '08:30', CURDATE() - INTERVAL  1 DAY, '11:30', 4,  8.0, 1);

-- ── 2 aktuell laufende Fahrten (nicht abgeschlossen) ─────────
INSERT INTO trips (boat_id, start_date, start_time, route_id, distance, is_completed) VALUES
  (5, CURDATE(), '14:00', 7, 6.5, 0),
  (7, CURDATE(), '15:30', 1, 5.0, 0);

-- ── Crew-Zuordnungen (vereinfacht: trip_id = AUTO_INCREMENT) ─
-- Anna fährt in vielen Fahrten, Ben/Carla auch oft, alle 10 Mitglieder verteilt
INSERT INTO trip_crew (trip_id, seat_position, member_id) VALUES
  (1,1,1),(1,2,2),
  (2,1,3),(2,2,4),(2,3,5),
  (3,1,7),(3,2,8),
  (4,1,1),
  (5,1,5),(5,2,9),
  (6,1,2),(6,2,1),
  (7,1,4),
  (8,1,8),(8,2,3),(8,3,10),
  (9,1,1),
  (10,1,9),
  (11,1,2),(11,2,5),
  (12,1,7),(12,2,4),
  (13,1,1),
  (14,1,3),(14,2,10),
  (15,1,6),
  (16,1,8),
  (17,1,1),
  (18,1,2),(18,2,9),
  (19,1,5),
  (20,1,7),(20,2,4),
  (21,1,3),
  (22,1,1),(22,2,10),
  (23,1,8),(23,2,4),
  (24,1,5),
  (25,1,9),
  -- Aktive Fahrten
  (26,1,1),(26,2,2),
  (27,1,3);

-- ── 1 Reservierung in der nächsten Woche ─────────────────────
INSERT INTO boat_reservations (boat_id, member_id, reservation_type, reservation_start, reservation_end, reason)
VALUES (3, 4, 'ONETIME',
        CONCAT(CURDATE() + INTERVAL 5 DAY, ' 14:00:00'),
        CONCAT(CURDATE() + INTERVAL 5 DAY, ' 18:00:00'),
        'Trainings-Ausfahrt Wettkampfvorbereitung');

-- ── 1 Schaden ────────────────────────────────────────────────
INSERT INTO boat_damages (boat_id, damage_description, damage_type, reporter_member_id, is_fixed)
VALUES (2, 'Kratzer am Bug, Lackierung blättert', 'Boot voll nutzbar', 5, 0);

-- ── Arbeitsdienst ────────────────────────────────────────────
INSERT INTO work_hours (member_id, hours, description, work_date)
SELECT id, FLOOR(2 + RAND()*8), 'Frühjahrsputz Bootshaus', CURDATE() - INTERVAL 30 DAY FROM members WHERE id <= 6;
