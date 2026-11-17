<?php
// staff/includes/staff-head.php - Shared Head for Staff Portal
if (!isset($dark_mode)) {
    $dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
}
$is_root_staff = ($portal_context ?? '') === 'root' || basename(dirname($_SERVER['PHP_SELF'])) !== 'staff';
$css_rel = $is_root_staff ? 'css/' : '../css/';
$css_v = @filemtime(__DIR__ . '/../../css/staff-portal.css') ?: time();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Staff Panel - One Palayan-Kalinga Hub'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,600;1,700&family=Poppins:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $css_rel; ?>staff-portal.css?v=<?php echo $css_v; ?>">
    <script>
        // Apply theme immediately to prevent FOUC
        (function() {
            var theme = '<?php echo $dark_mode ? 'dark' : 'light'; ?>';
            try {
                var stored = localStorage.getItem('theme');
                if (stored) theme = stored;
            } catch(e) {}
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <?php include_once __DIR__ . '/../../includes/pwa-meta.php'; ?>

