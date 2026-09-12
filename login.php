<?php

define('BASE_URL', '');

require_once 'config/database.php';
require_once 'includes/auth.php';

startSess();

if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    if (in_array($role, ['gudang', 'gudang'])) {
        header('Location:' . BASE_URL . '/barang_masuk.php');
    } else {
        header('Location:' . BASE_URL . '/produk.php');
    }
    exit();
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    if ($u && $p) {

        $db = getDB();

        $st = $db->prepare("
            SELECT *
            FROM users
            WHERE username = ?
            AND password = MD5(?)
            LIMIT 1
        ");

        $st->bind_param('ss', $u, $p);
        $st->execute();

        $user = $st->get_result()->fetch_assoc();

        if ($user) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['username'] = $user['username'];

            $redirect = in_array($user['role'], ['gudang', 'gudang'])
             ? '/produk.php'
             : '/dashboard.php';

            header('Location:' . BASE_URL . $redirect);
            exit();

        } else {
            $err = 'Username atau password salah.';
        }

    } else {
        $err = 'Harap isi semua field.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kevatie</title>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&family=Cormorant+Garamond:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<div class="login-wrap">
    <div class="login-box">

        <div class="login-logo">KEVATIE</div>
        <div class="login-sub">Pengelolaan Stok Jas Hujan</div>

        <?php if ($err): ?>
            <div class="flash error"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <form method="POST">

            <div class="fg" style="margin-bottom:14px">
                <label>Username</label>
                <input type="text" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       autofocus required>
            </div>

            <div class="fg" style="margin-bottom:20px">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-p" style="width:100%;padding:10px;font-size:13px">
                Masuk
            </button>

        </form>
    </div>
</div>

</body>
</html>