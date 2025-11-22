<?php
session_start();
require_once 'config.php';
require_once 'theme.php';

$errorLogin = '';

$theme = handleTheme($pdo);

$avisoProximo = [];
try {
    $hoje = (new DateTime())->format('Y-m-d');
    $limite = (new DateTime('+7 days'))->format('Y-m-d');
    $stmtAviso = $pdo->prepare("
        SELECT descricao, data_vencimento, valor, status
        FROM despesas
        WHERE status IN ('Pendente','Previsto')
          AND data_vencimento BETWEEN ? AND ?
        ORDER BY data_vencimento ASC
        LIMIT 5
    ");
    $stmtAviso->execute([$hoje, $limite]);
    $avisoProximo = $stmtAviso->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $avisoProximo = [];
}

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
            $_SESSION['theme'] = isset($user['tema']) ? (int)$user['tema'] : 0;
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
        .login-box {
            width: 100%;
            max-width: 420px;
            background: var(--surface-color);
            border-radius: 14px;
            box-shadow: var(--shadow-strong);
            padding: 32px;
        }
        .logo {
            width: 80px;
            margin-bottom: 16px;
        }
        .form-label { font-weight: 600; color: var(--text-color); }
        .alert-upcoming {
            background: rgba(16,185,129,0.12);
            border: 1px solid rgba(16,185,129,0.3);
            color: var(--text-color);
        }
        @media (max-width: 576px) {
            body { padding: 16px 8px; }
            .login-box { padding: 24px; margin: 24px auto; }
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <form method="post" class="position-absolute" style="top:16px; right:16px; z-index: 2;">
        <input type="hidden" name="toggle_theme" value="1">
        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'index.php'); ?>">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Tema: <?php echo themeLabel($theme); ?></button>
    </form>
    <div class="login-box">
        <div class="text-center mb-3">
            <img src="login.png" alt="ProHelp" class="logo">
            <h2 class="fw-bold">ProHelp Financeiro</h2>
        </div>
        <?php if ($avisoProximo): ?>
            <div class="alert alert-upcoming">
                <div class="fw-semibold mb-2">Contas próximas ao vencimento (7 dias):</div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($avisoProximo as $d): ?>
                        <li>
                            <?= htmlspecialchars($d['data_vencimento']) ?> - <?= htmlspecialchars($d['descricao']) ?> |
                            R$ <?= number_format((float)$d['valor'], 2, ',', '.') ?> (<?= htmlspecialchars($d['status']) ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
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
