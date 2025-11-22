<?php
require_once 'config.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$username || !$password || !$confirm) {
        $error = 'Preencha todos os campos!';
    } elseif ($password !== $confirm) {
        $error = 'As senhas não conferem.';
    } else {
        // Verifica se já existe usuário
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Usuário já cadastrado!';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO usuarios (username, password_hash, is_admin, ativo) VALUES (?, ?, 0, 0)');
            if ($stmt->execute([$username, $hash])) {
                $success = 'Usuário cadastrado com sucesso!';
            } else {
                $error = 'Erro ao cadastrar usuário!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro de Usuário</title>
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
        .cadastro-box {
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.08);
            padding: 32px;
        }
        .btn-success {
            background: #10b981;
            border-color: #0ea271;
        }
        .btn-success:hover {
            background: #0ea271;
            border-color: #0d9467;
        }
        .form-label { font-weight: 600; color: #111827; }
        .form-control:focus, .btn:focus { box-shadow: 0 0 0 0.2rem rgba(16,185,129,0.25); }
        a { color: #0d9467; }
        a:hover { color: #0a7a55; }
    </style>
</head>
<body>
    <div class="cadastro-box">
        <h2 class="mb-4 text-center fw-bold">Cadastro de Usuário</h2>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label for="username" class="form-label">E-mail ou Usuário</label>
                <input type="text" class="form-control" id="username" name="username" required autofocus autocomplete="off" value="">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Senha</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password" value="">
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">Mostrar</button>
                </div>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirmar senha</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password" value="">
                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirm">Mostrar</button>
                </div>
            </div>
            <button type="submit" class="btn btn-success w-100">Cadastrar</button>
        </form>
        <div class="mt-3 text-center">
            <a href="index.php">Voltar ao Login</a>
        </div>
    </div>
    <script>
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirm = document.getElementById('toggleConfirm');
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');

        function toggleVisibility(button, input) {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.textContent = isPassword ? 'Ocultar' : 'Mostrar';
        }

        togglePassword.addEventListener('click', () => toggleVisibility(togglePassword, passwordInput));
        toggleConfirm.addEventListener('click', () => toggleVisibility(toggleConfirm, confirmInput));
    </script>
</body>
</html>
