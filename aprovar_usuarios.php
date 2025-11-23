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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <style>
        body {
            background: var(--page-bg);
            font-family: 'Segoe UI', Arial, sans-serif;
            color: var(--text-color);
            padding: 32px 12px;
        }
        .box {
            max-width: 720px;
            margin: 0 auto;
            background: var(--surface-color);
            border-radius: 14px;
            box-shadow: var(--shadow-strong);
            padding: 24px;
        }
        @media (max-width: 576px) {
            body { padding: 16px 8px; }
            .box { padding: 18px; }
            .btn, .btn-success { width: 100%; }
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="box">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h3 class="fw-bold mb-0">Aprovação de Cadastros</h3>
            <a href="principal.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
