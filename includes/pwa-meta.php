<?php
// includes/pwa-meta.php - Silent PWA meta tags & manifest (Zero layout/UI changes)
$pwa_is_sub = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false);
$pwa_prefix = $pwa_is_sub ? '../' : '';
?>
<link rel="manifest" href="<?php echo $pwa_prefix; ?>manifest.json">
<meta name="theme-color" content="#7F0303">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Kalinga Hub">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo $pwa_prefix; ?>images/pwa/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="<?php echo $pwa_prefix; ?>images/pwa/icon-192x192.png">
<script src="<?php echo $pwa_prefix; ?>js/pwa.js" async></script>
