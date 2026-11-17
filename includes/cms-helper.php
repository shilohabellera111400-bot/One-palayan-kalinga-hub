<?php
/**
 * One Palayan-Kalinga Hub - Dynamic Content Management System (CMS) Helper
 * Provides high-performance, cached access to dynamic website content,
 * media assets, services, and requirements without code changes.
 */

require_once __DIR__ . '/../config/database.php';

// In-memory per-request static cache
if (!isset($GLOBALS['__CMS_CACHE__'])) {
    $GLOBALS['__CMS_CACHE__'] = null;
}

/**
 * Load all CMS content into memory once per request.
 */
function cms_load_all(): array
{
    if ($GLOBALS['__CMS_CACHE__'] !== null) {
        return $GLOBALS['__CMS_CACHE__'];
    }

    $GLOBALS['__CMS_CACHE__'] = [];
    try {
        $db = (new Database())->getConnection();
        if ($db) {
            $stmt = $db->query("SELECT content_key, content_value, section, content_type, label, sort_order FROM cms_content ORDER BY sort_order ASC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $GLOBALS['__CMS_CACHE__'][$row['content_key']] = $row;
            }
        }
    } catch (Exception $e) {
        error_log('[CMS Helper Error] ' . $e->getMessage());
    }

    return $GLOBALS['__CMS_CACHE__'];
}

/**
 * Invalidate the in-memory cache (e.g. after updates).
 */
function cms_clear_cache(): void
{
    $GLOBALS['__CMS_CACHE__'] = null;
}

/**
 * Retrieve a CMS content value with optional fallback default.
 */
function cms_get(string $key, string $default = ''): string
{
    $cache = cms_load_all();
    $val = (isset($cache[$key]) && $cache[$key]['content_value'] !== null && $cache[$key]['content_value'] !== '')
        ? $cache[$key]['content_value']
        : $default;

    // Fully decode any nested/accidental double-encoded HTML entities (e.g. &amp;amp;)
    if (is_string($val) && (strpos($val, '&amp;') !== false || strpos($val, '&#') !== false || stripos($val, 'amp;') !== false)) {
        for ($i = 0; $i < 15; $i++) {
            $prev = $val;
            $val = html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($val === $prev) break;
        }
        $val = str_ireplace(['&amp;', 'amp;'], '&', $val);
        $val = preg_replace('/&+/', '&', $val);
    }
    return $val;
}

/**
 * Retrieve a CMS boolean toggle (e.g. for section visibility).
 */
function cms_bool(string $key, bool $default = true): bool
{
    $cache = cms_load_all();
    if (isset($cache[$key]) && $cache[$key]['content_value'] !== null && $cache[$key]['content_value'] !== '') {
        $val = strtolower(trim((string)$cache[$key]['content_value']));
        return in_array($val, ['1', 'true', 'on', 'yes', 'enabled'], true);
    }
    return $default;
}

/**
 * Retrieve a CMS image path with intelligent subfolder prefix adjustment
 * and automatic cache-busting timestamp.
 */
function cms_image(string $key, string $default = 'images/logo.png'): string
{
    $raw_path = cms_get($key, $default);
    if (empty($raw_path)) {
        $raw_path = $default;
    }

    // Determine depth relative to web root
    $current_dir = $_SERVER['PHP_SELF'] ?? '';
    $in_subfolder = (strpos($current_dir, '/admin/') !== false || strpos($current_dir, '/staff/') !== false);

    $clean_path = ltrim($raw_path, './');
    $full_sys_path = __DIR__ . '/../' . $clean_path;

    $url_path = $in_subfolder ? ('../' . $clean_path) : $clean_path;

    if (file_exists($full_sys_path)) {
        $v = filemtime($full_sys_path);
        return $url_path . '?v=' . $v;
    }

    return $url_path;
}

/**
 * Format any raw asset path with appropriate relative directory prefix
 * and cache-busting timestamp.
 */
function cms_asset(string $raw_path): string
{
    if (empty($raw_path)) {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $raw_path) || strpos($raw_path, 'data:') === 0) {
        return $raw_path;
    }

    $current_dir = $_SERVER['PHP_SELF'] ?? '';
    $in_subfolder = (strpos($current_dir, '/admin/') !== false || strpos($current_dir, '/staff/') !== false);

    $clean_path = ltrim($raw_path, './');
    $full_sys_path = __DIR__ . '/../' . $clean_path;
    $url_path = $in_subfolder ? ('../' . $clean_path) : $clean_path;

    if (file_exists($full_sys_path)) {
        $v = filemtime($full_sys_path);
        return $url_path . '?v=' . $v;
    }

    return $url_path;
}

/**
 * Retrieve multiple slideshow pictures for a program card.
 * Supports JSON array in prog_{key}_slides, falling back to prog_{key}_image
 * or the built-in defaults.
 */
function cms_get_slides(string $programKey, array $defaultSlides = []): array
{
    if (empty($defaultSlides)) {
        $builtin_defaults = [
            'housing'      => ['images/san-lorenzo.png', 'images/housing-sanlorenzo1.png', 'images/housing-sanlorenzo2.png'],
            'aics'         => ['images/Aics1.png', 'images/aics2.png', 'images/aics3.png'],
            'senior'       => ['images/senior2.png', 'images/senior1.png', 'images/senior4.png'],
            'pwd'          => ['images/PWD1.png', 'images/PWD2.png', 'images/PWD3.png'],
            'appointments' => ['images/ap1.png', 'images/ap2.png', 'images/ap3.png'],
            'documents'    => ['images/doc2.png', 'images/doc1.jpg', 'images/doc3.jpg'],
        ];
        $defaultSlides = $builtin_defaults[$programKey] ?? [];
    }

    $json = cms_get("prog_{$programKey}_slides", '');
    if (!empty($json)) {
        $decoded = json_decode($json, true);
        if (is_array($decoded) && !empty($decoded)) {
            $cleaned = array_values(array_filter(array_map('trim', $decoded)));
            if (!empty($cleaned)) {
                return $cleaned;
            }
        }
    }

    // Check if legacy single image was saved
    $single = cms_get("prog_{$programKey}_image", '');
    if (!empty($single)) {
        $slides = !empty($defaultSlides) ? $defaultSlides : [$single];
        $slides[0] = $single;
        return array_values(array_unique($slides));
    }

    return $defaultSlides;
}

/**
 * Retrieve all CMS content items, optionally filtered by section.
 */
function cms_get_all(?string $section = null): array
{
    $cache = cms_load_all();
    if ($section === null) {
        return $cache;
    }
    $filtered = [];
    foreach ($cache as $k => $item) {
        if (($item['section'] ?? '') === $section) {
            $filtered[$k] = $item;
        }
    }
    return $filtered;
}

/**
 * Update or insert a CMS content value with optional audit logging.
 */
function cms_set(string $key, string $value, ?int $userId = null): bool
{
    try {
        // Normalize any encoded entities before saving to DB
        for ($i = 0; $i < 15; $i++) {
            $prev = $value;
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($value === $prev) break;
        }
        $value = preg_replace('/(and)?amp;?/i', '&', $value);
        $value = preg_replace('/&+/', '&', $value);

        $db = (new Database())->getConnection();
        $stmt = $db->prepare("INSERT INTO cms_content (content_key, content_value, updated_by, section, content_type, label)
            VALUES (:key, :val, :uid, 'general', 'text', :key_label)
            ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_by = VALUES(updated_by)");
        $stmt->execute([
            ':key' => $key,
            ':val' => $value,
            ':uid' => $userId ?? ($_SESSION['user_id'] ?? null),
            ':key_label' => ucwords(str_replace('_', ' ', $key))
        ]);

        cms_clear_cache();
        return true;
    } catch (Exception $e) {
        error_log('[CMS Set Error] ' . $e->getMessage());
        return false;
    }
}

/**
 * Retrieve services from the database with active filtering and ordering.
 */
function cms_get_services(bool $activeOnly = true, ?string $category = null): array
{
    try {
        $db = (new Database())->getConnection();
        $sql = "SELECT * FROM services WHERE 1=1";
        $params = [];

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        if (!empty($category)) {
            $sql .= " AND category = :cat";
            $params[':cat'] = $category;
        }

        $sql .= " ORDER BY sort_order ASC, service_id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('[CMS Get Services Error] ' . $e->getMessage());
        return [];
    }
}

/**
 * Retrieve featured services for the homepage grid.
 */
function cms_get_featured_services(int $limit = 4): array
{
    try {
        $db = (new Database())->getConnection();
        $stmt = $db->prepare("SELECT * FROM services WHERE is_active = 1 AND is_featured = 1 ORDER BY sort_order ASC, service_id ASC LIMIT :lim");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            return $rows;
        }
        // Fallback to any active services if none marked featured
        $stmt = $db->prepare("SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC, service_id ASC LIMIT :lim");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('[CMS Get Featured Services Error] ' . $e->getMessage());
        return [];
    }
}

/**
 * Retrieve AICS documentary requirements by assistance type.
 */
function cms_get_aics_requirements(?string $request_type = null): array
{
    try {
        $db = (new Database())->getConnection();
        $sql = "SELECT * FROM aics_document_requirements WHERE 1=1";
        $params = [];
        if (!empty($request_type)) {
            $sql .= " AND request_type = :type";
            $params[':type'] = $request_type;
        }
        $sql .= " ORDER BY request_type ASC, sort_order ASC, req_id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('[CMS AICS Requirements Error] ' . $e->getMessage());
        return [];
    }
}

/**
 * Retrieve Housing programs with requirements and status.
 */
function cms_get_housing_programs(bool $activeOnly = true): array
{
    try {
        $db = (new Database())->getConnection();
        $sql = "SELECT * FROM housing_programs";
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        $sql .= " ORDER BY program_id ASC";
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('[CMS Housing Programs Error] ' . $e->getMessage());
        return [];
    }
}

/**
 * Retrieve media library assets with category filtering.
 */
function cms_get_media(?string $category = null, int $limit = 100): array
{
    try {
        $db = (new Database())->getConnection();
        $sql = "SELECT * FROM media_library";
        $params = [];
        if (!empty($category) && $category !== 'all') {
            $sql .= " WHERE category = :cat";
            $params[':cat'] = $category;
        }
        $sql .= " ORDER BY media_id DESC LIMIT :lim";
        $stmt = $db->prepare($sql);
        if (!empty($category) && $category !== 'all') {
            $stmt->bindValue(':cat', $category, PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('[CMS Media Error] ' . $e->getMessage());
        return [];
    }
}
