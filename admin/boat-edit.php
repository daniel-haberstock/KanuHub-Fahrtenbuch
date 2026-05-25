<?php
/**
 * Fahrtenbuch - Boot bearbeiten (Eigene Seite)
 *
 * Vollständige Bearbeitungsseite für ein Boot inkl. Steckbrief (JSON)
 * und Icon-Auswahl aus der boat_icons-Tabelle.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

$boatId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isNew = !$boatId;
$boat = null;
$successMessage = null;
$errorMessage = null;

// Mitglieder für Crew-Dropdowns
$members = $db->query("SELECT id, first_name, last_name FROM members ORDER BY last_name ASC")->fetchAll();

// Icons für Auswahl
$icons = $db->query("SELECT * FROM boat_icons ORDER BY category ASC, name ASC")->fetchAll();

// Boot laden (Edit-Modus)
if ($boatId) {
    $stmt = $db->prepare("SELECT * FROM boats WHERE id = ?");
    $stmt->execute([$boatId]);
    $boat = $stmt->fetch();
    if (!$boat) {
        header('Location: /admin/boats.php');
        exit;
    }
}

// Speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $boatName = trim($_POST['boat_name'] ?? '');
        $boatType = $_POST['boat_type'] ?? '';
        $seats = (int)($_POST['seats'] ?? 1);
        $defaultCrew1 = $_POST['default_crew_1'] ?? null;
        $defaultCrew2 = $_POST['default_crew_2'] ?? null;
        $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
        $storageLocation = trim($_POST['storage_location'] ?? '');
        $noteStart = trim($_POST['note_start'] ?? '');
        $noteEnd = trim($_POST['note_end'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');
        $boatImage = trim($_POST['boat_image'] ?? '');
        $boatIconId = !empty($_POST['boat_icon_id']) ? (int)$_POST['boat_icon_id'] : null;

        // Steckbrief als JSON zusammenbauen
        $details = [];
        $detailFields = [
            'modell', 'hersteller',
            'laenge', 'breite', 'gewicht', 'material', 'steuerung', 'stauraum',
            'empfohlenes_gewicht', 'koerpergroesse', 'erfahrungslevel',
            'stabilitaet', 'geschwindigkeit', 'wendigkeit', 'wind_wellen',
            'sitzkomfort', 'eigenheiten', 'schwaechen',
        ];
        foreach ($detailFields as $field) {
            $val = trim($_POST['detail_' . $field] ?? '');
            if ($val !== '') {
                $details[$field] = $val;
            }
        }
        $boatDetails = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;

        $params = [
            'boat_name' => $boatName,
            'boat_type' => $boatType,
            'seats' => $seats,
            'default_crew_1' => $defaultCrew1 ?: null,
            'default_crew_2' => $defaultCrew2 ?: null,
            'valid_until' => $validUntil,
            'storage_location' => $storageLocation ?: null,
            'note_start' => $noteStart ?: null,
            'note_end' => $noteEnd ?: null,
            'video_url' => $videoUrl ?: null,
            'boat_image' => $boatImage ?: null,
            'boat_icon_id' => $boatIconId,
            'boat_details' => $boatDetails,
        ];

        try {
            if ($isNew) {
                $cols = implode(', ', array_keys($params));
                $placeholders = ':' . implode(', :', array_keys($params));
                $stmt = $db->prepare("INSERT INTO boats ({$cols}) VALUES ({$placeholders})");
                $stmt->execute($params);
                $boatId = $db->lastInsertId();
                $isNew = false;
                $successMessage = "Boot erfolgreich erstellt!";
            } else {
                $sets = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($params)));
                $params['id'] = $boatId;
                $stmt = $db->prepare("UPDATE boats SET {$sets} WHERE id = :id");
                $stmt->execute($params);
                $successMessage = "Boot erfolgreich gespeichert!";
            }

            // Neu laden
            $stmt = $db->prepare("SELECT * FROM boats WHERE id = ?");
            $stmt->execute([$boatId]);
            $boat = $stmt->fetch();
        } catch (\PDOException $e) {
            $errorMessage = 'Fehler: ' . $e->getMessage();
        }
    }
}

// Steckbrief-JSON parsen
$details = [];
if ($boat && $boat['boat_details']) {
    $details = json_decode($boat['boat_details'], true) ?: [];
}

$pageTitle = $isNew ? 'Neues Boot' : 'Boot bearbeiten: ' . ($boat['boat_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> - Fahrtenbuch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <style>
        textarea.wysiwyg { display: none !important; }
        .ql-container { font-size: 0.9375rem; }
        .ql-toolbar.ql-snow { border-radius: 0.375rem 0.375rem 0 0; }
        .ql-container.ql-snow { border-radius: 0 0 0.375rem 0.375rem; }
        .ql-editor { min-height: 60px !important; padding: 8px 12px !important; }
    </style>
    <style>
        .icon-choice { cursor: pointer; border: 2px solid transparent; border-radius: 8px; padding: 4px; transition: all .15s; }
        .icon-choice:hover { border-color: #0d6efd; background: rgba(13,110,253,.05); }
        .icon-choice.selected { border-color: #0d6efd; background: rgba(13,110,253,.1); }
        .icon-choice img { max-height: 50px; max-width: 60px; object-fit: contain; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4" style="max-width: 1000px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="/admin/boats.php" class="text-decoration-none text-muted">← Boote</a>
                <h2 class="mb-0">
                    <i class="bi bi-<?php echo $isNew ? 'plus-circle' : 'pencil'; ?>"></i>
                    <?php echo e($pageTitle); ?>
                </h2>
            </div>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo e($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?php echo e($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="save">

            <!-- ═══ Grunddaten ═══ -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-water"></i> Grunddaten</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Bootsname</label>
                            <input type="text" class="form-control" name="boat_name" required
                                   value="<?php echo $boat ? e($boat['boat_name']) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Typ</label>
                            <select class="form-select" name="boat_type" required>
                                <?php foreach (['Seekajak', 'Tourenkajak', 'Freizeitkajak', 'Rennkajak', 'Surf Ski', 'Faltboot', 'Modul-Kajak', 'Packraft', 'Canadier', 'Wildwasser Kajak', 'Wildwasser-Canadier', 'Outrigger', 'SUP', 'Anhänger'] as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo ($boat && $boat['boat_type'] === $type) ? 'selected' : ''; ?>>
                                        <?php echo $type; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Sitzplätze</label>
                            <select class="form-select" name="seats" id="boat_seats">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($boat && $boat['seats'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Standard-Crew Platz 1</label>
                            <select class="form-select" name="default_crew_1">
                                <option value="">-- Nicht festgelegt --</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?php echo $m['id']; ?>" <?php echo ($boat && $boat['default_crew_1'] == $m['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($m['first_name'] . ' ' . $m['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="crew_2_container">
                            <label class="form-label">Standard-Crew Platz 2</label>
                            <select class="form-select" name="default_crew_2">
                                <option value="">-- Nicht festgelegt --</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?php echo $m['id']; ?>" <?php echo ($boat && $boat['default_crew_2'] == $m['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($m['first_name'] . ' ' . $m['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bootsplatz / Lagerort</label>
                            <input type="text" class="form-control" name="storage_location"
                                   value="<?php echo $boat ? e($boat['storage_location'] ?? '') : ''; ?>"
                                   placeholder="z.B. Halle 1, Platz A3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Im Bestand bis</label>
                            <input type="date" class="form-control" name="valid_until"
                                   value="<?php echo $boat ? e($boat['valid_until'] ?? '') : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-youtube text-danger"></i> Video-URL</label>
                            <input type="url" class="form-control" name="video_url"
                                   value="<?php echo $boat ? e($boat['video_url'] ?? '') : ''; ?>"
                                   placeholder="https://youtube.com/...">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ Hinweise ═══ -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-chat-square-text"></i> Hinweise</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-play-circle text-success"></i> Hinweis bei Fahrtbeginn</label>
                            <textarea class="form-control wysiwyg" style="display:none" name="note_start" rows="3"
                                      placeholder="z.B. Steuer vor Ablegen prüfen"><?php echo $boat ? e($boat['note_start'] ?? '') : ''; ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-house-door text-primary"></i> Hinweis bei Rückkehr</label>
                            <textarea class="form-control wysiwyg" style="display:none" name="note_end" rows="3"
                                      placeholder="z.B. Lenzstopfen schließen"><?php echo $boat ? e($boat['note_end'] ?? '') : ''; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ Icon & Bild ═══ -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-image"></i> Icon & Bild</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Eigenes Bild (URL)</label>
                        <input type="text" class="form-control" name="boat_image"
                               value="<?php echo $boat ? e($boat['boat_image'] ?? '') : ''; ?>"
                               placeholder="/assets/images/boats/mein-boot.png">
                        <small class="form-text text-muted">Optional. Wenn leer, wird das Icon oder Standard-Typ-Bild verwendet.</small>
                    </div>

                    <label class="form-label">Icon aus Bibliothek</label>
                    <input type="hidden" name="boat_icon_id" id="boat_icon_id"
                           value="<?php echo $boat ? ($boat['boat_icon_id'] ?? '') : ''; ?>">

                    <?php if (empty($icons)): ?>
                        <p class="text-muted">Noch keine Icons vorhanden. <a href="/admin/boat-icons.php">Icons hochladen →</a></p>
                    <?php else: ?>
                        <?php
                        $iconsByCategory = [];
                        foreach ($icons as $icon) {
                            $iconsByCategory[$icon['category']][] = $icon;
                        }
                        ?>
                        <div class="mb-2">
                            <span class="icon-choice p-2 d-inline-block text-center" data-icon-id=""
                                  onclick="selectIcon(this, '')" style="min-width:70px;">
                                <i class="bi bi-x-circle text-muted" style="font-size:1.5rem;"></i><br>
                                <small class="text-muted">Keins</small>
                            </span>
                        </div>
                        <?php foreach ($iconsByCategory as $cat => $catIcons): ?>
                            <div class="mb-2">
                                <small class="text-muted fw-bold"><?php echo $cat === 'Zubehoer' ? 'Zubehör' : e($cat); ?></small>
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    <?php foreach ($catIcons as $icon): ?>
                                        <div class="icon-choice text-center <?php echo ($boat && $boat['boat_icon_id'] == $icon['id']) ? 'selected' : ''; ?>"
                                             data-icon-id="<?php echo $icon['id']; ?>"
                                             onclick="selectIcon(this, <?php echo $icon['id']; ?>)"
                                             title="<?php echo e($icon['name']); ?>">
                                            <img src="/assets/icons/boats/<?php echo e($icon['file_path']); ?>"
                                                 alt="<?php echo e($icon['name']); ?>">
                                            <br><small class="text-truncate d-block" style="max-width:70px;"><?php echo e($icon['name']); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══ Steckbrief ═══ -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-card-list"></i> Steckbrief</h5></div>
                <div class="card-body">
                    <!-- Allgemein -->
                    <h6 class="text-muted mb-3">Allgemein</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Modell</label>
                            <input type="text" class="form-control" name="detail_modell"
                                   value="<?php echo e($details['modell'] ?? ''); ?>" placeholder="z.B. Zegul Arrow Play">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hersteller</label>
                            <input type="text" class="form-control" name="detail_hersteller"
                                   value="<?php echo e($details['hersteller'] ?? ''); ?>" placeholder="z.B. Zegul, Lettmann, Prijon">
                        </div>
                    </div>

                    <!-- Technische Daten -->
                    <h6 class="text-muted mb-3">Technische Daten</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Länge</label>
                            <input type="text" class="form-control" name="detail_laenge"
                                   value="<?php echo e($details['laenge'] ?? ''); ?>" placeholder="z.B. 5,20 m">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Breite</label>
                            <input type="text" class="form-control" name="detail_breite"
                                   value="<?php echo e($details['breite'] ?? ''); ?>" placeholder="z.B. 55 cm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gewicht</label>
                            <input type="text" class="form-control" name="detail_gewicht"
                                   value="<?php echo e($details['gewicht'] ?? ''); ?>" placeholder="z.B. 24 kg">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Material</label>
                            <input type="text" class="form-control" name="detail_material"
                                   value="<?php echo e($details['material'] ?? ''); ?>" placeholder="z.B. PE, GFK, Carbon">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Steuerung</label>
                            <select class="form-select" name="detail_steuerung">
                                <option value="">-- nicht angegeben --</option>
                                <?php foreach (['Skeg', 'Steuer', 'Skeg + Steuer', 'keine'] as $opt): ?>
                                    <option value="<?php echo $opt; ?>" <?php echo ($details['steuerung'] ?? '') === $opt ? 'selected' : ''; ?>>
                                        <?php echo $opt; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Stauraum</label>
                            <input type="text" class="form-control" name="detail_stauraum"
                                   value="<?php echo e($details['stauraum'] ?? ''); ?>" placeholder="z.B. 2 Luken, Tagesgepäck">
                        </div>
                    </div>

                    <!-- Paddler-Eignung -->
                    <h6 class="text-muted mb-3">Paddler-Eignung</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Empfohlenes Gewicht</label>
                            <input type="text" class="form-control" name="detail_empfohlenes_gewicht"
                                   value="<?php echo e($details['empfohlenes_gewicht'] ?? ''); ?>" placeholder="z.B. 60-90 kg">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Körpergröße</label>
                            <input type="text" class="form-control" name="detail_koerpergroesse"
                                   value="<?php echo e($details['koerpergroesse'] ?? ''); ?>" placeholder="z.B. 165-185 cm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Erfahrungslevel</label>
                            <select class="form-select" name="detail_erfahrungslevel">
                                <option value="">-- nicht angegeben --</option>
                                <?php foreach (['Anfänger', 'Fortgeschritten', 'Allround', 'Experte'] as $opt): ?>
                                    <option value="<?php echo $opt; ?>" <?php echo ($details['erfahrungslevel'] ?? '') === $opt ? 'selected' : ''; ?>>
                                        <?php echo $opt; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Fahreigenschaften -->
                    <h6 class="text-muted mb-3">Fahreigenschaften</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Stabilität</label>
                            <input type="text" class="form-control" name="detail_stabilitaet"
                                   value="<?php echo e($details['stabilitaet'] ?? ''); ?>" placeholder="z.B. hoch, mittel">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Geschwindigkeit</label>
                            <input type="text" class="form-control" name="detail_geschwindigkeit"
                                   value="<?php echo e($details['geschwindigkeit'] ?? ''); ?>" placeholder="z.B. schnell">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Wendigkeit</label>
                            <input type="text" class="form-control" name="detail_wendigkeit"
                                   value="<?php echo e($details['wendigkeit'] ?? ''); ?>" placeholder="z.B. gut">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Wind/Wellen</label>
                            <input type="text" class="form-control" name="detail_wind_wellen"
                                   value="<?php echo e($details['wind_wellen'] ?? ''); ?>" placeholder="z.B. stabil bei Seitenwind">
                        </div>
                    </div>

                    <!-- Besonderheiten -->
                    <h6 class="text-muted mb-3">Besonderheiten</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Sitzkomfort</label>
                            <textarea class="form-control wysiwyg" style="display:none" name="detail_sitzkomfort" rows="3"
                                      placeholder="z.B. Sehr bequem, verstellbare Rückenlehne, Schenkelstützen"><?php echo e($details['sitzkomfort'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Typische Eigenheiten</label>
                            <textarea class="form-control wysiwyg" style="display:none" name="detail_eigenheiten" rows="3"
                                      placeholder="z.B. Kippt beim Einsteigen leicht, Steuer muss vor Ablegen ausgeklappt werden"><?php echo e($details['eigenheiten'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Bekannte Schwächen</label>
                            <textarea class="form-control wysiwyg" style="display:none" name="detail_schwaechen" rows="3"
                                      placeholder="z.B. Lack empfindlich, Süllrand hat Riss links"><?php echo e($details['schwaechen'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ Buttons ═══ -->
            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> Speichern
                </button>
                <a href="/admin/boats.php" class="btn btn-secondary btn-lg">Abbrechen</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <script>
        function selectIcon(el, id) {
            document.querySelectorAll('.icon-choice').forEach(function(c) { c.classList.remove('selected'); });
            el.classList.add('selected');
            document.getElementById('boat_icon_id').value = id;
        }

        document.getElementById('boat_seats').addEventListener('change', function() {
            var crew2 = document.getElementById('crew_2_container');
            crew2.style.display = parseInt(this.value) === 1 ? 'none' : 'block';
        });

        // WYSIWYG: Quill für alle Textareas mit Klasse "wysiwyg"
        const quillInstances = [];
        document.querySelectorAll('textarea.wysiwyg').forEach(function(textarea) {
            // Wrapper hält Toolbar + Editor zusammen im Layout
            const wrapper = document.createElement('div');
            const editorDiv = document.createElement('div');
            editorDiv.innerHTML = textarea.value;
            wrapper.appendChild(editorDiv);
            textarea.parentNode.insertBefore(wrapper, textarea);

            const quill = new Quill(editorDiv, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['clean']
                    ]
                }
            });

            quillInstances.push({ quill, textarea });
        });

        // Vor dem Absenden HTML-Inhalt in Textareas schreiben
        document.querySelector('form').addEventListener('submit', function() {
            quillInstances.forEach(function({ quill, textarea }) {
                const html = quill.getSemanticHTML();
                // Leerer Editor → leerer String
                textarea.value = html === '<p><br></p>' ? '' : html;
            });
        });
    </script>
</body>
</html>
