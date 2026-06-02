<?php
// RAFT — Range And Forecasting Tool
// Auth helpers — bcrypt passwords, file-based session tokens, 30-day cookie

// Token store: flat JSON file in private-backend, never web-accessible
define('TOKEN_FILE', __DIR__ . '/session_tokens.json');

function start_session(): void {
    // Not used for main auth — kept for any incidental session use
}

function load_tokens(): array {
    if (!file_exists(TOKEN_FILE)) return [];
    $data = json_decode(file_get_contents(TOKEN_FILE), true);
    return is_array($data) ? $data : [];
}

function save_tokens(array $tokens): void {
    file_put_contents(TOKEN_FILE, json_encode($tokens), LOCK_EX);
}

function purge_expired_tokens(array $tokens): array {
    $now = time();
    return array_filter($tokens, fn($t) => $t['expires'] > $now);
}

function is_logged_in(): bool {
    $token = $_COOKIE[SESSION_NAME] ?? '';
    if (!$token) return false;

    $tokens = load_tokens();
    $tokens = purge_expired_tokens($tokens);

    return isset($tokens[$token]) && $tokens[$token]['expires'] > time();
}

function require_login(): void {
    if (!is_logged_in()) {
        http_response_code(401);
        die(json_encode(['error' => 'Not authenticated']));
    }
}

function current_user_id(): int {
    $token = $_COOKIE[SESSION_NAME] ?? '';
    if (!$token) return 0;
    $tokens = load_tokens();
    return (int) ($tokens[$token]['user_id'] ?? 0);
}

function current_username(): string {
    $token = $_COOKIE[SESSION_NAME] ?? '';
    if (!$token) return '';
    $tokens = load_tokens();
    return $tokens[$token]['username'] ?? '';
}

function is_admin(): bool {
    $token = $_COOKIE[SESSION_NAME] ?? '';
    if (!$token) return false;
    $tokens = load_tokens();
    return !empty($tokens[$token]['is_admin']);
}

function login(string $username, string $password): bool {
    $db   = get_db();
    $stmt = $db->prepare('SELECT id, password, is_admin FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    // Generate token
    $token   = bin2hex(random_bytes(32));
    $expires = time() + SESSION_LIFETIME;

    $tokens          = purge_expired_tokens(load_tokens());
    $tokens[$token]  = [
        'user_id'  => $user['id'],
        'username' => $username,
        'is_admin' => (bool) $user['is_admin'],
        'expires'  => $expires,
    ];
    save_tokens($tokens);

    setcookie(SESSION_NAME, $token, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return true;
}

function logout(): void {
    $token = $_COOKIE[SESSION_NAME] ?? '';
    if ($token) {
        $tokens = load_tokens();
        unset($tokens[$token]);
        save_tokens($tokens);
    }
    setcookie(SESSION_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function is_demo(): bool {
    return strpos($_SERVER['HTTP_HOST'], IS_DEMO_DOMAIN) !== false;
}
