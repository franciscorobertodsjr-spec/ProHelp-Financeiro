<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'theme.php';

$theme = handleTheme($pdo);

$categoriasLista = $pdo->query('SELECT nome FROM categorias ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);

$success = '';
$error = '';
$popupType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descricao = trim($_POST['descricao'] ?? '');
    $data_vencimento = $_POST['data_vencimento'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $data_pagamento = $_POST['data_pagamento'] ?? null;
    $juros = $_POST['juros'] ?? 0;
    $total_pago = $_POST['total_pago'] ?? null;
    $status = $_POST['status'] ?? 'Pendente';
    $recorrente = !empty($_POST['recorrente']) ? 1 : 0;
    $parcelado = !empty($_POST['parcelado']) ? 1 : 0;
    $numero_parcela = $_POST['numero_parcela'] !== '' ? $_POST['numero_parcela'] : null;
    $total_parcelas = $_POST['total_parcelas'] !== '' ? $_POST['total_parcelas'] : null;
    $grupo_parcelas = trim($_POST['grupo_parcelas'] ?? '') ?: null;
    $categoriaSelect = $_POST['categoria_select'] ?? '';
    $categoriaCustom = trim($_POST['categoria_custom'] ?? '');
    $categoria = $categoriaSelect === 'custom' ? ($categoriaCustom ?: null) : ($categoriaSelect ?: null);
    $forma_pagamento = trim($_POST['forma_pagamento'] ?? '') ?: null;
    $observacao = trim($_POST['observacao'] ?? '') ?: null;
    $local = trim($_POST['local'] ?? '') ?: null;

    if (!$descricao || !$data_vencimento || $valor === '') {
        $error = 'Preencha pelo menos descrição, data de vencimento e valor.';
        $popupType = 'warning';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO despesas 
                (descricao, data_vencimento, valor, data_pagamento, juros, total_pago, status, recorrente, parcelado, numero_parcela, total_parcelas, grupo_parcelas, categoria, forma_pagamento, observacao, local)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $descricao,
                $data_vencimento,
                $valor,
                $data_pagamento ?: null,
                $juros ?: 0,
                $total_pago !== '' ? $total_pago : null,
                $status,
                $recorrente,
                $parcelado,
                $numero_parcela,
                $total_parcelas,
                $grupo_parcelas,
                $categoria,
                $forma_pagamento,
                $observacao,
                $local
            ]);
            $success = 'Despesa cadastrada com sucesso.';
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
    <title>Nova Despesa</title>
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
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .panel { background: var(--surface-color, #f9fbfd); border: 1px solid var(--border-color, #d9e1eb); border-radius: 16px; padding: 16px; box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06)); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 12px; background: var(--surface-soft, #f1f4f8); font-weight: 600; gap: 6px; }
        @media (max-width: 600px) {
            .actions .button { width: 100%; text-align: center; }
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="layout">
        <?php renderSidebar('despesa_form'); ?>
        <main class="content">
            <div class="page-header">
                <div class="page-title">
                    <p class="eyebrow">Cadastro</p>
                    <h1>Nova despesa</h1>
                    <span class="text-muted">Inclua os dados principais e financeiros</span>
                </div>
                <div class="actions no-print">
                    <a href="despesas.php" class="button button-outline text-decoration-none">Listagem</a>
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
                    <h3 class="panel-title">Dados da despesa</h3>
                    <span class="pill">Obrigatórios marcados</span>
                </div>
                <form method="post" autocomplete="off" class="form-grid">
                    <div>
                        <label class="form-label" for="descricao">Descrição</label>
                        <input type="text" class="form-control" id="descricao" name="descricao" required>
                    </div>
                    <div>
                        <label class="form-label" for="data_vencimento">Data vencimento</label>
                        <input type="date" class="form-control" id="data_vencimento" name="data_vencimento" required>
                    </div>
                    <div>
                        <label class="form-label" for="valor">Valor</label>
                        <input type="number" step="0.01" class="form-control" id="valor" name="valor" required>
                    </div>

                    <div>
                        <label class="form-label" for="data_pagamento">Data pagamento</label>
                        <input type="date" class="form-control" id="data_pagamento" name="data_pagamento">
                    </div>
                    <div>
                        <label class="form-label" for="juros">Juros</label>
                        <input type="number" step="0.01" class="form-control" id="juros" name="juros" value="0">
                    </div>
                    <div>
                        <label class="form-label" for="total_pago">Total pago</label>
                        <input type="number" step="0.01" class="form-control" id="total_pago" name="total_pago">
                    </div>
                    <div>
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="Pendente">Pendente</option>
                            <option value="Pago">Pago</option>
                            <option value="Previsto">Previsto</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Recorrente</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="recorrente" name="recorrente" value="1">
                            <label class="form-check-label" for="recorrente">Sim</label>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Parcelado</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="parcelado" name="parcelado" value="1">
                            <label class="form-check-label" for="parcelado">Sim</label>
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="numero_parcela">Nº parcela</label>
                        <input type="number" class="form-control" id="numero_parcela" name="numero_parcela">
                    </div>
                    <div>
                        <label class="form-label" for="total_parcelas">Total parcelas</label>
                        <input type="number" class="form-control" id="total_parcelas" name="total_parcelas">
                    </div>

                    <div>
                        <label class="form-label" for="grupo_parcelas">Grupo parcelas</label>
                        <input type="text" class="form-control" id="grupo_parcelas" name="grupo_parcelas">
                    </div>
                    <div>
                        <label class="form-label" for="categoria_select">Categoria</label>
                        <select class="form-select" id="categoria_select" name="categoria_select">
                            <option value="">Selecione</option>
                            <?php foreach ($categoriasLista as $catNome): ?>
                                <option value="<?= htmlspecialchars($catNome) ?>"><?= htmlspecialchars($catNome) ?></option>
                            <?php endforeach; ?>
                            <option value="custom">Outra...</option>
                        </select>
                        <input type="text" class="form-control mt-2 d-none" id="categoria_custom" name="categoria_custom" placeholder="Digite a categoria">
                    </div>
                    <div>
                        <label class="form-label" for="forma_pagamento">Forma de pagamento</label>
                        <input type="text" class="form-control" id="forma_pagamento" name="forma_pagamento">
                    </div>

                    <div>
                        <label class="form-label" for="local">Local</label>
                        <input type="text" class="form-control" id="local" name="local">
                    </div>
                    <div>
                        <label class="form-label" for="observacao">Observação</label>
                        <textarea class="form-control" id="observacao" name="observacao" rows="2"></textarea>
                    </div>
                    <div class="actions" style="grid-column: 1 / -1;">
                        <button type="submit" class="button button-primary">Salvar</button>
                        <a href="despesas.php" class="button button-outline text-decoration-none">Voltar</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
        const selectCat = document.getElementById('categoria_select');
        const inputCustom = document.getElementById('categoria_custom');
        selectCat.addEventListener('change', () => {
            if (selectCat.value === 'custom') {
                inputCustom.classList.remove('d-none');
            } else {
                inputCustom.classList.add('d-none');
                inputCustom.value = '';
            }
        });
    </script>
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
