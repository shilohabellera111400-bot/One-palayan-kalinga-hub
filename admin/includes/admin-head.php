<?php
// admin/includes/admin-head.php - Shared HTML Head for Admin Panel
if (!isset($dark_mode)) {
    $dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
}
$site_title = isset($page_title) ? htmlspecialchars($page_title) . ' | One Palayan-Kalinga Hub' : 'Admin Control Panel | One Palayan-Kalinga Hub';
$css_version = @filemtime(__DIR__ . '/../../css/admin-portal.css') ?: time();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo.png?v=<?php echo @filemtime(__DIR__ . '/../../images/logo.png') ?: time(); ?>">
    
    <!-- Fonts & Icons Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    
    <!-- Admin Portal Stylesheet -->
    <link rel="stylesheet" href="../css/admin-portal.css?v=<?php echo $css_version; ?>">
    
    <!-- Immediate Theme Sync Script -->
    <script>
        (function() {
            try {
                const savedTheme = localStorage.getItem('theme');
                if (savedTheme) {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                }
            } catch(e) {}
        })();
    </script>
    <?php include_once __DIR__ . '/../../includes/pwa-meta.php'; ?>
</head>
<body class="admin-body">
<div class="app-container">
