<?php
session_start();
require_once 'config.php';
require_once 'theme.php';

$errorLogin = '';

$theme = handleTheme($pdo);

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
    <link href="bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 12px;
            font-family: 'Poppins', 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: var(--text-color);
            background: radial-gradient(120% 140% at 0% 0%, rgba(16,185,129,0.12), transparent 35%),
                        radial-gradient(100% 120% at 100% 0%, rgba(14,165,233,0.12), transparent 30%),
                        var(--page-bg);
        }
        .login-layout {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            width: 100%;
            max-width: 1080px;
            background: var(--surface-color);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: var(--shadow-strong);
            border: 1px solid var(--border-color);
        }
        .hero {
            position: relative;
            padding: 48px 40px;
            color: #fff;
            background: linear-gradient(135deg, #0f766e 0%, #0ea271 40%, #10b981 70%, #34d399 100%);
        }
        .hero .shape {
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0.28;
            background:
                radial-gradient(circle at 20% 20%, rgba(255,255,255,0.18), transparent 25%),
                radial-gradient(circle at 70% 30%, rgba(255,255,255,0.12), transparent 30%),
                radial-gradient(circle at 60% 70%, rgba(255,255,255,0.1), transparent 35%);
        }
        .hero h2 {
            position: relative;
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 10px;
        }
        .hero p {
            position: relative;
            font-size: 15px;
            max-width: 360px;
            color: rgba(255,255,255,0.9);
        }
        .hero .logo {
            position: relative;
            width: 110px;
            margin-bottom: 24px;
            filter: drop-shadow(0 6px 16px rgba(0,0,0,0.35));
        }
        .form-side {
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: var(--surface-color);
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .login-card h3 {
            margin: 0;
            font-weight: 800;
            font-size: 26px;
        }
        .login-card .muted {
            color: var(--muted-color);
            font-size: 14px;
        }
        .form-label { font-weight: 600; color: var(--text-color); }
        .input-group .form-control {
            border-radius: 999px;
            border: 1px solid rgba(148,163,184,0.4);
            background: rgba(255,255,255,0.85);
            color: var(--text-color);
            padding-left: 14px;
        }
        body.theme-dark .input-group .form-control { background: #111827; border-color: #1f2937; }
        .input-group-text {
            border-radius: 999px 0 0 999px;
            border: 1px solid rgba(148,163,184,0.4);
            border-right: none;
            background: rgba(255,255,255,0.85);
            color: var(--muted-color);
        }
        body.theme-dark .input-group-text { background: #111827; border-color: #1f2937; color: var(--muted-color); }
        .input-group .btn {
            border-radius: 999px;
            border: 1px solid rgba(148,163,184,0.3);
            background: rgba(255,255,255,0.6);
        }
        body.theme-dark .input-group .btn { background: #0f172a; color: var(--text-color); border-color: #1f2937; }
        .actions-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: var(--muted-color);
        }
        .btn-primary {
            background: linear-gradient(90deg, #0ea271, #34d399);
            border: none;
            border-radius: 12px;
            padding: 11px 16px;
            font-weight: 800;
            box-shadow: 0 14px 28px rgba(16,185,129,0.35);
        }
        .btn-primary:hover { filter: brightness(1.04); box-shadow: 0 18px 32px rgba(16,185,129,0.45); }
        .btn-link {
            color: #0ea271;
            text-decoration: none;
        }
        .btn-link:hover { color: #0b8b60; text-decoration: underline; }
        @media (max-width: 900px) {
            .login-layout { grid-template-columns: 1fr; }
            .hero { display: none; }
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <form method="post" class="position-absolute" style="top:16px; right:16px; z-index: 2;">
        <input type="hidden" name="toggle_theme" value="1">
        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'index.php'); ?>">
        <button type="submit" class="btn btn-outline-secondary btn-sm rounded-4">Tema: <?php echo themeLabel($theme); ?></button>
    </form>
    <div class="login-layout">
        <div class="hero">
            <div class="shape"></div>
            <img src="login.png" alt="ProHelp" class="logo">
            <h2>Welcome back!</h2>
            <p>Você pode entrar para acompanhar suas despesas, dashboards e metas financeiras.</p>
        </div>
        <div class="form-side">
            <div class="login-card">
                <h3>Sign In</h3>
                <div class="muted">Use seu usuário e senha para continuar.</div>
                <?php if ($errorLogin): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($errorLogin) ?></div>
                <?php endif; ?>
                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuário</label>
                        <div class="input-group">
                            <span class="input-group-text">👤</span>
                            <input type="text" class="form-control" id="username" name="username" required autofocus>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Senha</label>
                        <div class="input-group">
                            <span class="input-group-text">🔒</span>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary" type="button" id="toggleLoginPassword">Mostrar</button>
                        </div>
                    </div>
                    <div class="actions-row mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="" id="rememberFake" disabled>
                            <label class="form-check-label" for="rememberFake">
                                Remember me
                            </label>
                        </div>
                        <a href="#" class="btn-link" onclick="return false;">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Sign In</button>
                </form>
                <div class="text-center small">
                    New here? <a class="btn-link" href="cadastro.php">Create an account</a>
                </div>
            </div>
        </div>
    </div>
    <script src="bootstrap.bundle.min.js"></script>
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
