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

$categoriasLista = $pdo->query('SELECT nome FROM categorias ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoria = trim($_POST['categoria'] ?? '');
    $mes = $_POST['mes'] ?? '';
    $limite = $_POST['limite'] ?? '';

    if (!$categoria || !$mes || $limite === '') {
        $error = 'Preencha categoria, mês e limite.';
        $popupType = 'warning';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO orcamentos (categoria, mes, limite) VALUES (?, ?, ?)');
            $stmt->execute([$categoria, $mes, $limite]);
            $success = 'Orçamento cadastrado com sucesso.';
            $popupType = 'success';
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) {
                $error = 'Já existe orçamento para esta categoria e mês.';
                $popupType = 'error';
            } else {
                $error = 'Erro ao salvar: ' . $e->getMessage();
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
    <title>Novo Orçamento</title>
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
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px 16px;
        }
        .form-grid .button { width: 100%; text-align: center; padding: 12px 14px; }
        .panel { background: var(--surface-color, #f9fbfd); border: 1px solid var(--border-color, #d9e1eb); border-radius: 16px; padding: 16px; box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06)); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 12px; background: var(--surface-soft, #f1f4f8); font-weight: 600; gap: 6px; }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="layout">
        <?php renderSidebar('orcamento'); ?>
        <main class="content">
            <div class="page-header">
                <div class="page-title">
                    <p class="eyebrow">Planejamento</p>
                    <h1>Cadastrar orçamento</h1>
                    <span class="text-muted">Defina um limite mensal por categoria</span>
                </div>
                <div class="d-flex gap-2 flex-wrap no-print">
                    <a href="principal.php" class="button button-link text-decoration-none">Dashboard</a>
                </div>
            </div>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Dados do orçamento</h3>
                    <span class="pill">Campos obrigatórios</span>
                </div>
                <form method="post" autocomplete="off" class="form-grid">
                    <div>
                        <label class="form-label" for="categoria">Categoria</label>
                        <input list="categorias" type="text" class="form-control" id="categoria" name="categoria" required value="<?= htmlspecialchars($categoria ?? '') ?>">
                        <datalist id="categorias">
                            <?php foreach ($categoriasLista as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <div class="form-text">Escolha uma já cadastrada ou digite uma nova.</div>
                    </div>
                    <div>
                        <label class="form-label" for="mes">Mês</label>
                        <?php $mesPadrao = $mes ?: date('Y-m'); ?>
                        <input type="month" class="form-control" id="mes" name="mes" required value="<?= htmlspecialchars($mesPadrao) ?>">
                    </div>
                    <div>
                        <label class="form-label" for="limite">Limite</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="limite" name="limite" required value="<?= htmlspecialchars($limite ?? '') ?>">
                        <div class="form-text">Use ponto para centavos (ex: 1200.50).</div>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <button type="submit" class="button button-primary">Salvar orçamento</button>
                    </div>
                </form>
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
        <?php if ($success): ?>
        triggerPopup(<?= json_encode($success) ?>, 'success');
        <?php endif; ?>
        <?php if ($error): ?>
        triggerPopup(<?= json_encode($error) ?>, <?= json_encode($popupType ?: 'error') ?>);
        <?php endif; ?>
    </script>
</body>
</html>
