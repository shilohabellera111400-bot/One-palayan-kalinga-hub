<?php
/**
 * CSRF Protection Helpers
 * 
 * Usage:
 *   In forms:  <?php echo csrf_field(); ?>
 *   On POST:   csrf_verify();  // redirects/dies on failure
 */

/**
 * Generate (or reuse) a CSRF token for the current session.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Return an HTML hidden input field with the CSRF token.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify the CSRF token submitted with a POST request.
 * Calls the $on_fail callback (or dies) if verification fails.
 *
 * @param callable|null $on_fail  Optional callback to run on failure instead of die().
 */
function csrf_verify(?callable $on_fail = null): void {
    $token       = $_POST['csrf_token'] ?? '';
    $valid_token = $_SESSION['csrf_token'] ?? '';

    if (!$token || !hash_equals($valid_token, $token)) {
        if ($on_fail !== null) {
            call_user_func($on_fail);
        } else {
            http_response_code(403);
            die('<h3 style="font-family:sans-serif;color:#c00;padding:2rem;">
                ⚠️ Invalid request. CSRF token mismatch.<br>
                <small><a href="javascript:history.back()">Go back</a></small>
            </h3>');
        }
    }

    // Rotate the token after successful verification for extra safety
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
