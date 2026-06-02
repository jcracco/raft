<?php
require_once __DIR__ . '/bootstrap.php';

// Hidden page — only accessible with the correct token
if (!isset($_GET['token']) || $_GET['token'] !== ADMIN_TOKEN) {
    http_response_code(404);
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;

    if (!$username || !$password) {
        $error = 'Username and password are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            $db   = get_db();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)');
            $stmt->execute([$username, $hash, $is_admin]);
            $success = "User \"$username\" created successfully.";
        } catch (PDOException $e) {
            $error = strpos($e->getMessage(), 'Duplicate') !== false
                ? "Username \"$username\" already exists."
                : 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch existing users
$db    = get_db();
$users = $db->query('SELECT id, username, is_admin, created_at FROM users ORDER BY created_at ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RAFT — Admin</title>
    <style>
        body { font-family: monospace; max-width: 600px; margin: 60px auto; padding: 0 20px; background: #0f0f0f; color: #e0e0e0; }
        h1 { color: #4a9eff; }
        label { display: block; margin-top: 12px; font-size: 13px; color: #aaa; }
        input[type=text], input[type=password] { width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #e0e0e0; margin-top: 4px; box-sizing: border-box; }
        input[type=checkbox] { margin-top: 4px; }
        button { margin-top: 16px; padding: 8px 20px; background: #4a9eff; color: #000; border: none; cursor: pointer; font-weight: bold; }
        button:hover { background: #6ab4ff; }
        .error   { color: #ff6b6b; margin-top: 12px; }
        .success { color: #6bff8e; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 32px; font-size: 13px; }
        th { text-align: left; border-bottom: 1px solid #333; padding: 6px 0; color: #aaa; }
        td { padding: 6px 0; border-bottom: 1px solid #1e1e1e; }
        .admin-badge { background: #4a9eff22; color: #4a9eff; padding: 2px 6px; font-size: 11px; }
    </style>
</head>
<body>
    <h1>RAFT Admin</h1>
    <h2>Create User</h2>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
    <form method="POST">
        <label>Username
            <input type="text" name="username" autocomplete="off" required>
        </label>
        <label>Password (min 8 characters)
            <input type="password" name="password" required>
        </label>
        <label><input type="checkbox" name="is_admin"> Admin user</label>
        <button type="submit">Create User</button>
    </form>

    <table>
        <thead>
            <tr><th>Username</th><th>Role</th><th>Created</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= $u['is_admin'] ? '<span class="admin-badge">admin</span>' : 'user' ?></td>
                <td><?= $u['created_at'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
