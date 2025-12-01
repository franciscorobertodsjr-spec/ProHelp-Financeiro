<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'theme.php';
require_once 'cartao_utils.php';

ensureCartaoSchema($pdo);
$theme = handleTheme($pdo);

$faturaId = isset($_GET['fatura_id']) ? (int)$_GET['fatura_id'] : 0;
$fatura = $faturaId ? fetchFaturaWithCartao($pdo, $faturaId) : null;

if (!$fatura) {
    echo 'Fatura não encontrada.';
    exit;
}

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['novo_item'])) {
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = $_POST['valor'] !== '' ? $_POST['valor'] : null;
        $categoria = trim($_POST['categoria'] ?? '') ?: null;
        $data_compra = $_POST['data_compra'] ?: null;
        $forma_pagamento = trim($_POST['forma_pagamento'] ?? '') ?: null;
        $observacao = trim($_POST['observacao'] ?? '') ?: null;

        if ($descricao === '' || $valor === null) {
            $errorMsg = 'Informe descrição e valor.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO fatura_itens (fatura_id, descricao, categoria, valor, data_compra, forma_pagamento, observacao)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$faturaId, $descricao, $categoria, $valor, $data_compra, $forma_pagamento, $observacao]);
            $successMsg = 'Item adicionado.';
        }
    } elseif (isset($_POST['excluir_item'])) {
        $itemId = (int)$_POST['item_id'];
        $del = $pdo->prepare('DELETE FROM fatura_itens WHERE id = ? AND fatura_id = ?');
        $del->execute([$itemId, $faturaId]);
        $successMsg = 'Item excluído.';
    }
}

$itensStmt = $pdo->prepare('SELECT * FROM fatura_itens WHERE fatura_id = ? ORDER BY data_compra ASC, id ASC');
$itensStmt->execute([$faturaId]);
$itens = $itensStmt->fetchAll(PDO::FETCH_ASSOC);
$totalItens = 0;
foreach ($itens as $it) {
    $totalItens += (float)$it['valor'];
}
$diferenca = (float)$fatura['valor_total'] - $totalItens;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Itens da fatura</title>
    <link href="bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <style>
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
        .content { flex: 1; padding: 28px 32px 34px; display: flex; flex-direction: column; gap: 18px; }
        .page-header { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; flex-wrap: wrap; }
        .page-title h1 { margin: 0; font-size: 26px; font-weight: 800; }
        .eyebrow { text-transform: uppercase; letter-spacing: 0.6px; font-size: 12px; color: var(--muted-color, #4b5563); margin: 0; }
        .panel { background: var(--surface-color, #f9fbfd); border: 1px solid var(--border-color, #d9e1eb); border-radius: 16px; padding: 16px; box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06)); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px; flex-wrap: wrap; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px 14px;
        }
        .button {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            cursor: pointer;
        }
        .button-primary { background: linear-gradient(135deg, #10b981, #0ea5e9); color: #fff; }
        .button-outline { background: var(--surface-soft, #f1f4f8); color: var(--text-color, #1f2937); border: 1px solid var(--border-color, #d9e1eb); }
        .pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 12px; background: var(--surface-soft, #f1f4f8); font-weight: 600; gap: 6px; }
        .table-sm th, .table-sm td { font-size: 12px; vertical-align: middle; }
        .badge { font-size: 0.75rem; }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="layout">
        <?php renderSidebar('faturas'); ?>
        <main class="content">
            <div class="page-header">
                <div class="page-title">
                    <p class="eyebrow">Itens da fatura</p>
                    <h1><?= htmlspecialchars($fatura['cartao_nome']) ?> • <?= htmlspecialchars($fatura['competencia']) ?></h1>
                    <span class="text-muted">Total previsto: R$ <?= number_format((float)$fatura['valor_total'], 2, ',', '.') ?> • Vencimento <?= htmlspecialchars($fatura['data_vencimento'] ?? '-') ?></span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="faturas_cartao.php?cartao_id=<?= (int)$fatura['cartao_id'] ?>" class="button button-outline text-decoration-none">Voltar</a>
                    <form method="post" action="faturas_cartao.php" class="d-inline">
                        <input type="hidden" name="fatura_id" value="<?= (int)$fatura['id'] ?>">
                        <button type="submit" name="gerar_despesa" class="button button-primary">Gerar/Atualizar despesa</button>
                    </form>
                </div>
            </div>

            <?php if ($successMsg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Novo item</h3>
                    <span class="pill">Preencha a composição da fatura</span>
                </div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="novo_item" value="1">
                    <div>
                        <label class="form-label">Descrição*</label>
                        <input type="text" name="descricao" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Valor*</label>
                        <input type="number" step="0.01" name="valor" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Categoria</label>
                        <input type="text" name="categoria" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Data compra</label>
                        <input type="date" name="data_compra" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Forma/Observação de pagamento</label>
                        <input type="text" name="forma_pagamento" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Observação</label>
                        <input type="text" name="observacao" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="button button-primary w-100">Adicionar item</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Itens lançados</h3>
                    <span class="pill">Total itens: R$ <?= number_format($totalItens, 2, ',', '.') ?> | Diferença vs fatura: R$ <?= number_format($diferenca, 2, ',', '.') ?></span>
                </div>
                <?php if (!$itens): ?>
                    <p class="text-muted">Nenhum item registrado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Categoria</th>
                                    <th>Valor</th>
                                    <th>Forma</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itens as $it): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($it['data_compra'] ?? '-') ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($it['descricao']) ?></strong>
                                            <?php if ($it['observacao']): ?>
                                                <div class="text-muted"><?= htmlspecialchars($it['observacao']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($it['categoria'] ?? '-') ?></td>
                                        <td>R$ <?= number_format((float)$it['valor'], 2, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($it['forma_pagamento'] ?? '-') ?></td>
                                        <td class="text-end">
                                            <form method="post" onsubmit="return confirm('Excluir este item?');">
                                                <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                                                <button type="submit" name="excluir_item" class="btn btn-sm btn-outline-danger">Excluir</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
