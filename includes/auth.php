<?php
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            // Read from the database each request so role changes and deletions apply at once.
            $user = fetch_one('SELECT user_id, name, email, role FROM users WHERE user_id = ?', 'i', [(int) $_SESSION['uid']]);
            if (!$user) {
                unset($_SESSION['uid']);
            }
        }
    }
    return $user;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    unset($_SESSION['csrf']);
    $_SESSION['uid'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], false, true);
    }
    session_destroy();
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('error', 'Sign in to continue.');
        redirect(url('login.php') . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        fail_page(403, 'Admins only', 'Your account does not have access to the admin panel.');
    }
    return $u;
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every handler that changes data. */
function require_post(): void
{
    $ok = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''));
    if (!$ok) {
        fail_page(400, 'Request not accepted', 'The form expired or was not submitted correctly. Go back, reload the page and try again.');
    }
}
