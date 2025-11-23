<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'theme.php';

$theme = handleTheme($pdo);

$success = '';
$error = '';
$popupType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $palavra = trim($_POST['palavra'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');

    if (!$palavra || !$categoria) {
        $error = 'Preencha palavra e categoria.';
        $popupType = 'warning';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO regras_categoria (palavra, categoria) VALUES (?, ?)');
            $stmt->execute([$palavra, $categoria]);
            $success = 'Regra cadastrada com sucesso.';
            $popupType = 'success';
        } catch (PDOException $e) {
            $error = 'Erro ao salvar: ' . $e->getMessage();
            $popupType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Nova Regra de Categoria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <style>
        body {
            background: var(--page-bg);
            min-height: 100vh;
            padding: 32px 12px;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: var(--text-color);
        }
        .box {
            max-width: 600px;
            margin: 0 auto;
            background: var(--surface-color);
            border-radius: 14px;
            box-shadow: var(--shadow-strong);
            padding: 24px;
        }
        .form-label { font-weight: 600; color: var(--text-color); }
        @media (max-width: 576px) {
            body { padding: 16px 8px; }
            .box { padding: 18px; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="box">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h3 class="fw-bold mb-0">Cadastrar Regra de Categoria</h3>
            <a href="principal.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label class="form-label" for="palavra">Palavra</label>
                <input type="text" class="form-control" id="palavra" name="palavra" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="categoria">Categoria</label>
                <input type="text" class="form-control" id="categoria" name="categoria" required>
            </div>
            <button type="submit" class="btn btn-primary">Salvar regra</button>
        </form>
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
        <?php if ($success): ?>
        triggerPopup(<?= json_encode($success) ?>, 'success');
        <?php endif; ?>
        <?php if ($error): ?>
        triggerPopup(<?= json_encode($error) ?>, <?= json_encode($popupType ?: 'error') ?>);
        <?php endif; ?>
    </script>
</body>
</html>
