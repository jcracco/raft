<?php
require_once __DIR__ . '/bootstrap.php';

// Accessible via admin token in URL, or by a logged-in admin user
$via_token = isset($_GET['token']) && $_GET['token'] === ADMIN_TOKEN;
if (!$via_token && !is_admin()) {
    http_response_code(404);
    exit;
}

// When accessed via session we know who the current user is; via token we don't
$current_user_id = $via_token ? null : current_user_id();
$token_qs        = $via_token ? '?token=' . urlencode($_GET['token']) : '';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['post_action'] ?? 'create';

    if ($act === 'create') {
        $username   = trim($_POST['username'] ?? '');
        $password   = $_POST['password'] ?? '';
        $is_admin_u = isset($_POST['is_admin']) ? 1 : 0;

        if (!$username || !$password) {
            $error = 'Username and password are required.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            try {
                $db   = get_db();
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare('INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)')->execute([$username, $hash, $is_admin_u]);
                $success = 'User "' . htmlspecialchars($username) . '" created.';
            } catch (PDOException $e) {
                $error = strpos($e->getMessage(), 'Duplicate') !== false
                    ? 'Username "' . htmlspecialchars($username) . '" already exists.'
                    : 'Database error.';
            }
        }

    } elseif ($act === 'delete') {
        $del_id   = (int) ($_POST['user_id'] ?? 0);
        $typed    = trim($_POST['confirm_username'] ?? '');
        if (!$del_id) {
            $error = 'Invalid user.';
        } elseif ($current_user_id !== null && $del_id === $current_user_id) {
            $error = 'You cannot delete your own account.';
        } else {
            $db   = get_db();
            $stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
            $stmt->execute([$del_id]);
            $u = $stmt->fetch();
            if (!$u) {
                $error = 'User not found.';
            } elseif ($typed !== $u['username']) {
                $error = 'Username did not match — deletion cancelled.';
            } else {
                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$del_id]);
                $success = 'User "' . htmlspecialchars($u['username']) . '" deleted.';
            }
        }

    } elseif ($act === 'change_password') {
        $pwd_id  = (int) ($_POST['user_id'] ?? 0);
        $new_pwd = $_POST['new_password'] ?? '';
        if (!$pwd_id) {
            $error = 'Invalid user.';
        } elseif (strlen($new_pwd) < 8) {
            $error = 'New password must be at least 8 characters.';
        } else {
            $db   = get_db();
            $stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
            $stmt->execute([$pwd_id]);
            $u = $stmt->fetch();
            if (!$u) {
                $error = 'User not found.';
            } else {
                $hash = password_hash($new_pwd, PASSWORD_DEFAULT);
                $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $pwd_id]);
                $success = 'Password updated for "' . htmlspecialchars($u['username']) . '".';
            }
        }
    }
}

$db    = get_db();
$users = $db->query('SELECT id, username, is_admin, created_at FROM users ORDER BY created_at ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RAFT — Admin</title>
    <style>
        body { font-family: monospace; max-width: 640px; margin: 60px auto; padding: 0 20px; background: #0f0f0f; color: #e0e0e0; }
        h1 { color: #4a9eff; margin-bottom: 4px; }
        h2 { font-size: 12px; color: #aaa; letter-spacing: 0.08em; text-transform: uppercase; margin: 28px 0 12px; }
        label { display: block; margin-top: 12px; font-size: 13px; color: #aaa; }
        input[type=text], input[type=password] { width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; color: #e0e0e0; margin-top: 4px; box-sizing: border-box; }
        input[type=checkbox] { margin-top: 4px; }
        .msg { margin: 12px 0; font-size: 13px; }
        .error   { color: #ff6b6b; }
        .success { color: #6bff8e; }
        .btn { padding: 6px 14px; border: none; cursor: pointer; font-family: monospace; font-size: 13px; font-weight: bold; }
        .btn-primary { background: #4a9eff; color: #000; margin-top: 16px; }
        .btn-primary:hover { background: #6ab4ff; }
        .btn-sm { padding: 3px 10px; font-size: 12px; font-weight: normal; }
        .btn-pwd  { background: #1e3a5f; color: #4a9eff; border: 1px solid #2a5a9f; }
        .btn-pwd:hover  { background: #2a4a7f; }
        .btn-del  { background: #3f1a1a; color: #ff6b6b; border: 1px solid #6b2a2a; }
        .btn-del:hover  { background: #5f2a2a; }
        .btn-del:disabled { opacity: 0.3; cursor: default; pointer-events: none; }
        .btn-del-confirm { background: #6b2a2a; color: #ff6b6b; border: 1px solid #8b3a3a; }
        .btn-del-confirm:hover { background: #8b3a3a; }
        .btn-del-confirm:disabled { opacity: 0.3; cursor: default; pointer-events: none; }
        .btn-save { background: #4a9eff; color: #000; }
        .btn-save:hover { background: #6ab4ff; }
        .btn-cancel { background: #1e1e1e; color: #aaa; border: 1px solid #333; }
        .btn-cancel:hover { color: #e0e0e0; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        tr.user-row td { padding: 8px 4px; border-bottom: 1px solid #1e1e1e; vertical-align: middle; }
        tr.pwd-row td, tr.del-row td { padding: 8px 8px 12px; border-bottom: 1px solid #1e1e1e; background: #111; }
        tr.pwd-row, tr.del-row { display: none; }
        tr.pwd-row.open, tr.del-row.open { display: table-row; }
        .badge { padding: 2px 6px; font-size: 11px; }
        .badge-admin { background: #4a9eff22; color: #4a9eff; }
        .badge-you   { background: #ffffff0d; color: #777; margin-left: 4px; }
        .col-name    { width: 42%; }
        .col-date    { width: 30%; color: #666; font-size: 12px; }
        .col-actions { text-align: right; white-space: nowrap; }
        .col-actions form { display: inline; margin-left: 6px; }
        .inline-form { display: flex; gap: 8px; align-items: center; }
        .inline-form input { margin: 0; flex: 1; padding: 6px 8px; }
        .del-hint { font-size: 12px; color: #888; margin-bottom: 6px; }
        .del-hint strong { color: #cc8888; }
    </style>
</head>
<body>
    <h1>RAFT Admin</h1>

    <?php if ($error):   ?><p class="msg error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="msg success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <h2>Create User</h2>
    <form method="POST" action="admin.php<?= htmlspecialchars($token_qs) ?>">
        <input type="hidden" name="post_action" value="create">
        <label>Username
            <input type="text" name="username" autocomplete="off" required>
        </label>
        <label>Password (min 8 characters)
            <input type="password" name="password" required>
        </label>
        <label><input type="checkbox" name="is_admin"> Admin — can access this page and manage users</label>
        <button type="submit" class="btn btn-primary">Create User</button>
    </form>

    <h2>Existing Users</h2>
    <table>
        <tbody>
            <?php foreach ($users as $u):
                $uid     = (int) $u['id'];
                $uname   = htmlspecialchars($u['username']);
                $uname_j = json_encode($u['username']);
                $is_self = ($current_user_id !== null && $uid === $current_user_id);
            ?>
            <tr class="user-row">
                <td class="col-name">
                    <strong><?= $uname ?></strong>
                    <?php if ($u['is_admin']): ?>
                        <span class="badge badge-admin">admin</span>
                    <?php endif; ?>
                    <?php if ($is_self): ?>
                        <span class="badge badge-you">you</span>
                    <?php endif; ?>
                </td>
                <td class="col-date"><?= htmlspecialchars($u['created_at']) ?></td>
                <td class="col-actions">
                    <button type="button" class="btn btn-sm btn-pwd"
                            onclick="toggleRow('pwd', <?= $uid ?>)">Pwd</button>
                    <button type="button" class="btn btn-sm btn-del"
                            <?= $is_self ? 'disabled title="Cannot delete your own account"' : "onclick=\"toggleRow('del', $uid)\"" ?>>Delete</button>
                </td>
            </tr>
            <tr class="pwd-row" id="pwd-row-<?= $uid ?>">
                <td colspan="3">
                    <form method="POST" action="admin.php<?= htmlspecialchars($token_qs) ?>" class="inline-form">
                        <input type="hidden" name="post_action" value="change_password">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <input type="password" name="new_password"
                               placeholder="New password for <?= $uname ?> (min 8 chars)"
                               required minlength="8">
                        <button type="submit" class="btn btn-sm btn-save">Save</button>
                        <button type="button" class="btn btn-sm btn-cancel"
                                onclick="toggleRow('pwd', <?= $uid ?>)">Cancel</button>
                    </form>
                </td>
            </tr>
            <tr class="del-row" id="del-row-<?= $uid ?>">
                <td colspan="3">
                    <p class="del-hint">Type <strong><?= $uname ?></strong> to confirm deletion:</p>
                    <form method="POST" action="admin.php<?= htmlspecialchars($token_qs) ?>" class="inline-form">
                        <input type="hidden" name="post_action" value="delete">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <input type="text" name="confirm_username" id="del-input-<?= $uid ?>"
                               autocomplete="off" placeholder="<?= $uname ?>"
                               oninput="checkDel(<?= $uid ?>, <?= htmlspecialchars($uname_j) ?>)">
                        <button type="submit" id="del-btn-<?= $uid ?>"
                                class="btn btn-sm btn-del-confirm" disabled>Delete</button>
                        <button type="button" class="btn btn-sm btn-cancel"
                                onclick="toggleRow('del', <?= $uid ?>)">Cancel</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        function toggleRow(type, id) {
            // Close any other open rows of the same type
            document.querySelectorAll('.' + type + '-row.open').forEach(r => {
                if (r.id !== type + '-row-' + id) closeRow(type, r);
            });
            const row = document.getElementById(type + '-row-' + id);
            const isOpen = row.classList.toggle('open');
            if (isOpen) {
                row.querySelector('input').focus();
            } else {
                closeRow(type, row);
            }
        }

        function closeRow(type, row) {
            row.classList.remove('open');
            const input = row.querySelector('input[type=text], input[type=password]');
            if (input) input.value = '';
            // Re-disable the delete confirm button if present
            const btn = row.querySelector('button[id^="del-btn-"]');
            if (btn) btn.disabled = true;
        }

        function checkDel(id, expected) {
            const input = document.getElementById('del-input-' + id);
            document.getElementById('del-btn-' + id).disabled = (input.value !== expected);
        }
    </script>
</body>
</html>
