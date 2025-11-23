<?php
session_start();
require_once 'config.php';
require_once 'theme.php';

$success = '';
$error = '';
$popupType = '';

$theme = handleTheme($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$username || !$password || !$confirm) {
        $error = 'Preencha todos os campos!';
        $popupType = 'warning';
    } elseif ($password !== $confirm) {
        $error = 'As senhas não conferem.';
        $popupType = 'warning';
    } else {
        // Verifica se já existe usuário
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Usuário já cadastrado!';
            $popupType = 'error';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO usuarios (username, password_hash, is_admin, ativo, tema) VALUES (?, ?, 0, 0, 0)');
            if ($stmt->execute([$username, $hash])) {
                $success = 'Usuário cadastrado com sucesso!';
                $popupType = 'success';
            } else {
                $error = 'Erro ao cadastrar usuário!';
                $popupType = 'error';
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
    <link rel="stylesheet" href="theme.css">
    <style>
        body {
            background: var(--page-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 12px;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: var(--text-color);
        }
        .cadastro-box {
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
            background: var(--surface-color);
            border-radius: 14px;
            box-shadow: var(--shadow-strong);
            padding: 32px;
        }
        .form-label { font-weight: 600; color: var(--text-color); }
        @media (max-width: 576px) {
            body { padding: 16px 8px; }
            .cadastro-box { padding: 24px; margin: 16px auto; }
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
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
            <a href="index.php" class="btn btn-outline-secondary w-100">Voltar ao Login</a>
        </div>
    </div>
    <div class="popup-container" id="popupContainer"></div>
    <script>
        const popupContainer = document.getElementById('popupContainer');
        function showPopup(message, type = 'success') {
            if (!popupContainer) return;
            const config = {
                success: { cls: 'popup-success', icon: '✓', title: 'Sucesso' },
                error: { cls: 'popup-error', icon: '×', title: 'Erro' },
                warning: { cls: 'popup-warning', icon: '!', title: 'Alerta' }
            };
            const conf = config[type] || config.success;
            const el = document.createElement('div');
            el.className = `popup show ${conf.cls}`;
            el.innerHTML = `
                <div class="popup-icon">${conf.icon}</div>
                <div class="popup-body">
                    <div class="popup-title">${conf.title}</div>
                    <div class="popup-message">${message}</div>
                </div>
                <button class="popup-close" aria-label="Fechar">&times;</button>
            `;
            el.querySelector('.popup-close').addEventListener('click', () => el.remove());
            popupContainer.appendChild(el);
            setTimeout(() => {
                el.classList.add('hide');
                setTimeout(() => el.remove(), 300);
            }, 3200);
        }
        function triggerPopup(message, type) {
            const run = () => showPopup(message, type);
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', run);
            } else {
                run();
            }
        }
        <?php if ($success): ?>
        triggerPopup(<?= json_encode($success) ?>, 'success');
        <?php endif; ?>
        <?php if ($error): ?>
        triggerPopup(<?= json_encode($error) ?>, <?= json_encode($popupType ?: 'error') ?>);
        <?php endif; ?>
    </script>
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
