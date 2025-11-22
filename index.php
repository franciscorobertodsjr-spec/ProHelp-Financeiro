<?php
session_start();
require_once 'config.php';

$errorLogin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        if ((int)$user['ativo'] !== 1) {
            $errorLogin = 'Usuário aguardando aprovação do administrador.';
        } else {
            session_regenerate_id(true); // evita fixation
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (int)$user['is_admin'];
            header('Location: principal.php');
            exit;
        }
    } else {
        $errorLogin = 'Usuário ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - ProHelp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 12px;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #1f2937;
        }
        .login-box {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.08);
            padding: 32px;
        }
        .logo {
            width: 80px;
            margin-bottom: 16px;
        }
        .btn-primary {
            background: #10b981;
            border-color: #0ea271;
        }
        .btn-primary:hover {
            background: #0ea271;
            border-color: #0d9467;
        }
        .form-label { font-weight: 600; color: #111827; }
        .form-control:focus, .btn:focus { box-shadow: 0 0 0 0.2rem rgba(16,185,129,0.25); }
        .btn-link { color: #0d9467; }
        .btn-link:hover { color: #0a7a55; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="text-center mb-4">
            <img src="login.png" alt="ProHelp" class="logo">
            <h2 class="fw-bold">ProHelp Financeiro</h2>
        </div>
        <?php if ($errorLogin): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorLogin) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label for="username" class="form-label">Usuário</label>
                <input type="text" class="form-control" id="username" name="username" required autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Senha</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required>
                    <button class="btn btn-outline-secondary" type="button" id="toggleLoginPassword">Mostrar</button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
        <div class="mt-3 text-center">
            <a class="btn btn-link" href="cadastro.php">Criar novo usuário</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const toggleLoginPassword = document.getElementById('toggleLoginPassword');
        const loginPasswordInput = document.getElementById('password');
        toggleLoginPassword.addEventListener('click', () => {
            const isPassword = loginPasswordInput.type === 'password';
            loginPasswordInput.type = isPassword ? 'text' : 'password';
            toggleLoginPassword.textContent = isPassword ? 'Ocultar' : 'Mostrar';
        });
    </script>
</body>
</html>
