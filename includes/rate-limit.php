<?php
/**
 * Rate Limiting Helper - checkRateLimit()
 * Prevents form spam by tracking submission attempts per user per action using PHP sessions.
 *
 * Usage:
 *   require_once __DIR__ . '/rate-limit.php';
 *   checkRateLimit($_SESSION['user_id'], 'book_appointment', 3, 10);
 */
function checkRateLimit(int $user_id, string $action, int $max_attempts = 3, int $window_minutes = 10): void
{
    $session_key    = "rate_limit_{$action}_{$user_id}";
    $now            = time();
    $window_seconds = $window_minutes * 60;

    if (!isset($_SESSION[$session_key])) {
        $_SESSION[$session_key] = ['attempts' => 0, 'window_start' => $now];
    }

    $bucket = &$_SESSION[$session_key];

    // Reset window if expired
    if (($now - $bucket['window_start']) >= $window_seconds) {
        $bucket['attempts']     = 0;
        $bucket['window_start'] = $now;
    }

    $bucket['attempts']++;

    if ($bucket['attempts'] > $max_attempts) {
        $remaining_seconds = $window_seconds - ($now - $bucket['window_start']);
        $remaining_minutes = (int) ceil($remaining_seconds / 60);

        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || (isset($_SERVER['HTTP_ACCEPT'])      && strpos($_SERVER['HTTP_ACCEPT'],      'application/json') !== false)
            || (isset($_SERVER['CONTENT_TYPE'])     && strpos($_SERVER['CONTENT_TYPE'],     'application/json') !== false);

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'      => false,
                'message'      => "Masyadong mabilis ang iyong mga kahilingan. Mangyaring maghintay ng {$remaining_minutes} minuto bago subukang muli.",
                'rate_limited' => true,
            ]);
        } else {
            $_SESSION['flash_error'] = "Masyadong mabilis ang iyong mga kahilingan. Mangyaring maghintay ng {$remaining_minutes} minuto bago subukang muli.";
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../index.php'));
        }
        exit;
    }
}