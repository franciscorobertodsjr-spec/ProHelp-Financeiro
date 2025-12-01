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
$successMsg = '';
$errorMsg = '';
$statusMap = [
    'previsto' => 'Previsto',
    'fechado' => 'Fechado',
    'lancado' => 'Lançado',
];

$cartaoFiltro = isset($_GET['cartao_id']) ? (int)$_GET['cartao_id'] : null;
$statusFiltro = $_GET['status'] ?? '';

function gerarDespesaDaFatura(PDO $pdo, int $faturaId): array
{
    try {
        $pdo->beginTransaction();
        $fatura = fetchFaturaWithCartao($pdo, $faturaId);
        if (!$fatura) {
            throw new RuntimeException('Fatura não encontrada.');
        }
        if (!$fatura['data_vencimento']) {
            throw new RuntimeException('Defina o vencimento da fatura antes de gerar a despesa.');
        }
        if ((float)$fatura['valor_total'] <= 0) {
            throw new RuntimeException('Informe o valor total da fatura antes de gerar a despesa.');
        }

        $descricao = 'Fatura Cartão ' . $fatura['cartao_nome'] . ' (' . $fatura['competencia'] . ')';
        $categoria = 'Cartões';
        $observacao = 'Despesa criada a partir da fatura de cartão.';

        if ($fatura['despesa_id']) {
            $upd = $pdo->prepare(
                'UPDATE despesas SET descricao = ?, data_vencimento = ?, valor = ?, status = ?, categoria = ?, observacao = ? WHERE id = ?'
            );
            $upd->execute([
                $descricao,
                $fatura['data_vencimento'],
                $fatura['valor_total'],
                'Previsto',
                $categoria,
                $observacao,
                $fatura['despesa_id']
            ]);
            $despesaId = (int)$fatura['despesa_id'];
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO despesas (descricao, data_vencimento, valor, juros, total_pago, status, recorrente, parcelado, categoria, observacao)
                 VALUES (?, ?, ?, 0, NULL, ?, 0, 0, ?, ?)'
            );
            $ins->execute([
                $descricao,
                $fatura['data_vencimento'],
                $fatura['valor_total'],
                'Previsto',
                $categoria,
                $observacao
            ]);
            $despesaId = (int)$pdo->lastInsertId();
        }

        $updFatura = $pdo->prepare('UPDATE faturas_cartao SET despesa_id = ?, status = ? WHERE id = ?');
        $updFatura->execute([$despesaId, 'lancado', $faturaId]);

        $pdo->commit();
        return [true, 'Despesa da fatura gerada/atualizada com sucesso.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [false, $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['nova_fatura'])) {
        $cartao_id = (int)($_POST['cartao_id'] ?? 0);
        $competencia = trim($_POST['competencia'] ?? '');
        $valor_total = $_POST['valor_total'] !== '' ? $_POST['valor_total'] : 0;
        $data_vencimento = $_POST['data_vencimento'] ?: null;
        $observacao = trim($_POST['observacao'] ?? '') ?: null;
        $statusSel = $_POST['status'] ?? 'previsto';

        if (!$cartao_id || !$competencia) {
            $errorMsg = 'Informe cartão e competência.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO faturas_cartao (cartao_id, competencia, valor_total, data_vencimento, observacao, status)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$cartao_id, $competencia, $valor_total, $data_vencimento, $observacao, $statusSel]);
                $successMsg = 'Fatura registrada.';
                $cartaoFiltro = $cartao_id;
            } catch (PDOException $e) {
                $errorMsg = 'Erro ao salvar: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['atualizar_status'])) {
        $faturaId = (int)$_POST['fatura_id'];
        $novoStatus = $_POST['novo_status'] ?? '';
        if (!isset($statusMap[$novoStatus])) {
            $errorMsg = 'Status inválido.';
        } else {
            $upd = $pdo->prepare('UPDATE faturas_cartao SET status = ? WHERE id = ?');
            $upd->execute([$novoStatus, $faturaId]);
            $successMsg = 'Status atualizado.';
        }
    } elseif (isset($_POST['gerar_despesa'])) {
        $faturaId = (int)$_POST['fatura_id'];
        [$ok, $msg] = gerarDespesaDaFatura($pdo, $faturaId);
        if ($ok) {
            $successMsg = $msg;
        } else {
            $errorMsg = $msg;
        }
    }
}

$cartoes = fetchCartoes($pdo);

$where = [];
$params = [];
if ($cartaoFiltro) {
    $where[] = 'f.cartao_id = ?';
    $params[] = $cartaoFiltro;
}
if ($statusFiltro !== '' && isset($statusMap[$statusFiltro])) {
    $where[] = 'f.status = ?';
    $params[] = $statusFiltro;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT f.*, c.nome AS cartao_nome,
        (SELECT SUM(valor) FROM fatura_itens fi WHERE fi.fatura_id = f.id) AS total_itens
        FROM faturas_cartao f
        INNER JOIN cartoes c ON c.id = f.cartao_id
        $whereSql
        ORDER BY (f.data_vencimento IS NULL), f.data_vencimento DESC, f.competencia DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$faturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Faturas de cartão</title>
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
        .page-title h1 { margin: 0; font-size: 28px; font-weight: 800; }
        .eyebrow { text-transform: uppercase; letter-spacing: 0.6px; font-size: 12px; color: var(--muted-color, #4b5563); margin: 0; }
        .panel { background: var(--surface-color, #f9fbfd); border: 1px solid var(--border-color, #d9e1eb); border-radius: 16px; padding: 16px; box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06)); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px; flex-wrap: wrap; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
                    <p class="eyebrow">Faturas</p>
                    <h1>Pré-lançamento de cartão</h1>
                    <span class="text-muted">Cadastre competências, itens e gere a despesa para pagamento</span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="cartoes.php" class="button button-outline text-decoration-none">Cartões</a>
                    <a href="despesas.php" class="button button-outline text-decoration-none">Despesas</a>
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
                    <h3 class="panel-title">Nova fatura</h3>
                    <span class="pill">Etapa 1: registre o total e vencimento</span>
                </div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="nova_fatura" value="1">
                    <div>
                        <label class="form-label">Cartão*</label>
                        <select name="cartao_id" class="form-select" required>
                            <option value="">Selecione</option>
                            <?php foreach ($cartoes as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $cartaoFiltro === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Competência (YYYY-MM)*</label>
                        <input type="month" name="competencia" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Valor total</label>
                        <input type="number" step="0.01" name="valor_total" class="form-control" value="0">
                    </div>
                    <div>
                        <label class="form-label">Vencimento</label>
                        <input type="date" name="data_vencimento" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach ($statusMap as $key => $label): ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Observação</label>
                        <input type="text" name="observacao" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="button button-primary w-100">Salvar fatura</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Faturas</h3>
                    <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
                        <select name="cartao_id" class="form-select">
                            <option value="">Todos cartões</option>
                            <?php foreach ($cartoes as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $cartaoFiltro === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status" class="form-select">
                            <option value="">Todos status</option>
                            <?php foreach ($statusMap as $key => $label): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= $statusFiltro === $key ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button button-outline">Filtrar</button>
                        <a href="faturas_cartao.php" class="button button-outline text-decoration-none">Limpar</a>
                    </form>
                </div>
                <?php if (!$faturas): ?>
                    <p class="text-muted">Nenhuma fatura encontrada.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Cartão</th>
                                    <th>Comp.</th>
                                    <th>Vencimento</th>
                                    <th>Total fatura</th>
                                    <th>Itens</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faturas as $f): ?>
                                    <?php
                                        $badgeClass = 'bg-secondary';
                                        if ($f['status'] === 'fechado') { $badgeClass = 'bg-info text-dark'; }
                                        if ($f['status'] === 'lancado') { $badgeClass = 'bg-success'; }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($f['cartao_nome']) ?></td>
                                        <td><?= htmlspecialchars($f['competencia']) ?></td>
                                        <td><?= htmlspecialchars($f['data_vencimento'] ?? '-') ?></td>
                                        <td>R$ <?= number_format((float)$f['valor_total'], 2, ',', '.') ?></td>
                                        <td>R$ <?= number_format((float)$f['total_itens'], 2, ',', '.') ?></td>
                                        <td>
                                            <form method="post" class="d-flex gap-1 align-items-center">
                                                <input type="hidden" name="fatura_id" value="<?= (int)$f['id'] ?>">
                                                <select name="novo_status" class="form-select form-select-sm">
                                                    <?php foreach ($statusMap as $key => $label): ?>
                                                        <option value="<?= htmlspecialchars($key) ?>" <?= $f['status'] === $key ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($label) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="atualizar_status" class="btn btn-sm btn-outline-secondary">Salvar</button>
                                            </form>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end flex-wrap gap-1">
                                                <a class="btn btn-sm btn-outline-primary" href="fatura_itens.php?fatura_id=<?= (int)$f['id'] ?>">Itens</a>
                                                <form method="post" onsubmit="return confirm('Gerar/atualizar despesa desta fatura?');">
                                                    <input type="hidden" name="fatura_id" value="<?= (int)$f['id'] ?>">
                                                    <button type="submit" name="gerar_despesa" class="btn btn-sm btn-success">
                                                        <?= $f['despesa_id'] ? 'Atualizar despesa' : 'Gerar despesa' ?>
                                                    </button>
                                                </form>
                                            </div>
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
