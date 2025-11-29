<?php
session_start();
require_once 'config.php';
require_once 'theme.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Confirma papel de admin consultando o banco
$stmt = $pdo->prepare('SELECT is_admin FROM usuarios WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser || (int)$currentUser['is_admin'] !== 1) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

$theme = handleTheme($pdo);

$message = '';
$error = '';
$popupType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId > 0) {
        $update = $pdo->prepare('UPDATE usuarios SET ativo = 1 WHERE id = ?');
        if ($update->execute([$userId])) {
            $message = 'Usuário aprovado com sucesso.';
            $popupType = 'success';
        } else {
            $error = 'Não foi possível aprovar o usuário.';
            $popupType = 'error';
        }
    }
}

$pendentes = $pdo->query('SELECT id, username FROM usuarios WHERE ativo = 0 ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Aprovar Cadastros</title>
    <link href="bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <style>
        /* Fallback rápido caso theme.css não carregue no servidor */
        body {
            margin: 0;
            background: var(--page-bg, #eef3f7);
            color: var(--text-color, #1f2937);
            font-family: 'Poppins', 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #0f172a 0%, #0b1f1a 100%);
            color: #e8f7f1;
            padding: 26px 20px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            box-shadow: 12px 0 40px rgba(0, 0, 0, 0.18);
        }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 19px; letter-spacing: 0.4px; }
        .brand .dot { width: 12px; height: 12px; border-radius: 50%; background: #34d399; box-shadow: 0 0 0 6px rgba(52, 211, 153, 0.15); }
        .profile {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 14px 12px;
            text-align: center;
        }
        .avatar {
            width: 78px; height: 78px; margin: 0 auto 10px auto; border-radius: 18px;
            background: linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0.08));
            display: grid; place-items: center; font-weight: 800; font-size: 26px; color: #f9fafb;
        }
        .menu { display: flex; flex-direction: column; gap: 8px; }
        .menu a { color: #ecfdf3; text-decoration: none; padding: 10px 12px; border-radius: 10px; background: rgba(255, 255, 255, 0.06); font-weight: 600; }
        .menu a:hover, .menu a.active { background: rgba(52,211,153,0.18); color: #ecfdf3; }
        .btn-ghost { border: 1px solid rgba(255, 255, 255, 0.18); background: rgba(255, 255, 255, 0.08); color: inherit; padding: 10px 12px; border-radius: 10px; text-decoration: none; text-align: center; font-weight: 700; }
        .sidebar-actions { margin-top: auto; display: flex; flex-direction: column; gap: 10px; }
        .content { flex: 1; padding: 28px 32px 34px; display: flex; flex-direction: column; gap: 18px; }
        .page-header { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; flex-wrap: wrap; }
        .page-title h1 { margin: 0; font-size: 28px; font-weight: 800; }
        .eyebrow { text-transform: uppercase; letter-spacing: 0.6px; font-size: 12px; color: var(--muted-color, #4b5563); margin: 0; }
        .panel { background: var(--surface-color, #f9fbfd); border: 1px solid var(--border-color, #d9e1eb); border-radius: 16px; padding: 16px; box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06)); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 12px; background: var(--surface-soft, #f1f4f8); font-weight: 600; gap: 6px; }
        .list-group-item { background: var(--surface-color, #f9fbfd); color: var(--text-color, #1f2937); }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="layout">
        <?php renderSidebar('aprovar'); ?>
        <main class="content">
            <div class="page-header">
                <div class="page-title">
                    <p class="eyebrow">Administração</p>
                    <h1>Aprovação de cadastros</h1>
                    <span class="text-muted">Apenas administradores podem aprovar novos usuários</span>
                </div>
                <div class="d-flex gap-2 flex-wrap no-print">
                    <a href="principal.php" class="button button-link text-decoration-none">Dashboard</a>
                </div>
            </div>
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Usuários pendentes</h3>
                    <span class="pill">Revisar</span>
                </div>
                <?php if (empty($pendentes)): ?>
                    <div class="alert alert-info mb-0">Não há usuários pendentes de aprovação.</div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($pendentes as $p): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($p['username']) ?></strong>
                                    <div class="text-muted small">ID: <?= (int)$p['id'] ?></div>
                                </div>
                                <form method="post" class="mb-0">
                                    <input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm">Aprovar</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <div class="popup-container" id="popupContainer"></div>
    <script>
        function getPopupContainer() {
            let c = document.getElementById('popupContainer');
            if (!c) {
                c = document.createElement('div');
                c.id = 'popupContainer';
                c.className = 'popup-container';
                document.body.appendChild(c);
            }
            return c;
        }
        function showPopup(message, type = 'success') {
            const popupContainer = getPopupContainer();
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
            if (document.readyState === 'complete') {
                run();
            } else {
                window.addEventListener('load', run);
            }
        }
        <?php if ($message): ?>
        triggerPopup(<?= json_encode($message) ?>, 'success');
        <?php endif; ?>
        <?php if ($error): ?>
        triggerPopup(<?= json_encode($error) ?>, <?= json_encode($popupType ?: 'error') ?>);
        <?php endif; ?>
    </script>
    <script src="bootstrap.bundle.min.js"></script>
</body>
</html>
