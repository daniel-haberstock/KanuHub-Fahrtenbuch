<?php
/**
 * BadgeRenderer – Rendert Badges als <img>-Tags
 *
 * Alle Badge-Metadaten (Bild, Label, Milestone, Kategorie) kommen
 * ausschließlich aus config/badges.php. Hier steht nur noch Render-Logik.
 */
class BadgeRenderer
{
    // =========================================================
    // Config-Zugriff (gecacht)
    // =========================================================

    private static ?array $config = null;

    private static function getConfig(): array
    {
        if (self::$config === null) {
            $cfgFile = __DIR__ . '/../config/badges.php';
            self::$config = file_exists($cfgFile) ? require $cfgFile : [];
        }
        return self::$config;
    }

    // =========================================================
    // Public API
    // =========================================================

    /**
     * Rendert ein einzelnes Badge als HTML.
     */
    public static function renderBadge(string $key, bool $earned, bool $isNew = false): string
    {
        $cfg = self::getConfig();
        if (!isset($cfg[$key])) return '';

        $def     = $cfg[$key];
        $classes = 'badge-item';
        if (!$earned) $classes .= ' badge-locked';
        if ($isNew)   $classes .= ' badge-new';

        [$l1, $l2] = $def['label'];
        $tipTitle  = trim($l1 . ' ' . $l2);
        $tipDesc   = $earned
            ? htmlspecialchars($def['ms'])
            : '🔒 ' . htmlspecialchars($def['ms']);

        $imgUrl  = self::getBadgeImageUrl($key);
        $newAttr = $isNew ? ' data-badge-new="1"' : '';

        return "<div class=\"{$classes}\" data-badge=\"{$key}\"{$newAttr}>"
             . "<span class=\"badge-tooltip\">" . htmlspecialchars($tipTitle) . "<br><small>{$tipDesc}</small></span>"
             . "<img class=\"badge-svg\" src=\"{$imgUrl}\" alt=\"" . htmlspecialchars($tipTitle) . "\" height=\"120\" loading=\"lazy\">"
             . "</div>\n";
    }

    /**
     * Rendert alle Badges als gruppierten Grid.
     * Kategorien werden dynamisch aus der Config abgeleitet (Reihenfolge = Reihenfolge in config/badges.php).
     */
    public static function renderGrid(array $earnedKeys = [], array $newKeys = []): string
    {
        $cfg       = self::getConfig();
        $earnedSet = array_flip($earnedKeys);
        $newSet    = array_flip($newKeys);

        // Kategorien dynamisch aus Config aufbauen (Insertion Order bleibt erhalten)
        $byCategory = [];
        foreach ($cfg as $key => $def) {
            $cat = $def['category'] ?? 'Sonstige';
            $byCategory[$cat][] = $key;
        }

        $html = '<div class="badges-container">';
        foreach ($byCategory as $catName => $keys) {
            $html .= "<div class=\"badge-category\">"
                  .  "<div class=\"badge-category-title\">" . htmlspecialchars($catName) . "</div>"
                  .  "<div class=\"badges-grid\">";
            foreach ($keys as $key) {
                $html .= self::renderBadge($key, isset($earnedSet[$key]), isset($newSet[$key]));
            }
            $html .= "</div></div>\n";
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Kleiner Inline-Grid nur für übergebene Badge-Keys (Post-Trip-Anzeige).
     */
    public static function renderBadgeList(array $keys): string
    {
        $html = '<div class="badges-grid badges-grid--compact">';
        foreach ($keys as $key) {
            $html .= self::renderBadge($key, true, true);
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Gibt alle bekannten Badge-Keys zurück.
     */
    public static function getAllKeys(): array
    {
        return array_keys(self::getConfig());
    }

    /**
     * Gibt die vollständige Badge-Config zurück (key => Definition).
     */
    public static function getAll(): array
    {
        return self::getConfig();
    }

    /**
     * Gibt den zusammengesetzten Label-Text eines Badges zurück.
     */
    public static function getLabel(string $key): string
    {
        $cfg = self::getConfig();
        if (!isset($cfg[$key])) return $key;
        return trim(implode(' ', $cfg[$key]['label']));
    }

    /**
     * Gibt die Bild-URL eines Badges zurück.
     * Fallback auf lokalen Pfad wenn kein Eintrag in der Config.
     */
    public static function getBadgeImageUrl(string $key): string
    {
        $cfg = self::getConfig();
        if (isset($cfg[$key]['image'])) {
            return $cfg[$key]['image'];
        }

        $baseUrl = defined('REDAXO_BADGE_BASE_URL') && !empty(REDAXO_BADGE_BASE_URL)
            ? rtrim(REDAXO_BADGE_BASE_URL, '/') . '/'
            : '/assets/badges/';
        return $baseUrl . 'badge-' . $key . '.svg';
    }

    /**
     * Gibt die Kategorie eines Badges zurück.
     */
    public static function getBadgeCategory(string $key): string
    {
        $cfg = self::getConfig();
        return $cfg[$key]['category'] ?? '';
    }
}
