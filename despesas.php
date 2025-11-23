<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'theme.php';

$theme = handleTheme($pdo);
$dataHora = (new DateTime())->format('d/m/Y H:i');

$categoriasLista = $pdo->query('SELECT nome FROM categorias ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);

$status = $_GET['status'] ?? '';
$categoria = trim($_GET['categoria'] ?? '');
$hoje = new DateTime();
$primeiroMes = (clone $hoje)->modify('first day of this month')->format('Y-m-d');
$ultimoMes = (clone $hoje)->modify('last day of this month')->format('Y-m-d');
$data_ini = $_GET['data_ini'] ?? $primeiroMes;
$data_fim = $_GET['data_fim'] ?? $ultimoMes;
$recorrente = $_GET['recorrente'] ?? '';
$parcelado = $_GET['parcelado'] ?? '';

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $idDelete = (int)$_POST['delete_id'];
        try {
            $del = $pdo->prepare('DELETE FROM despesas WHERE id = ?');
            $del->execute([$idDelete]);
            if ($del->rowCount() > 0) {
                $successMsg = 'Despesa excluída com sucesso.';
            } else {
                $errorMsg = 'Despesa não encontrada.';
            }
        } catch (Exception $e) {
            $errorMsg = 'Erro ao excluir: ' . $e->getMessage();
        }
    } elseif (isset($_POST['pagar_id'])) {
        $idPagar = (int)$_POST['pagar_id'];
        try {
            $pdo->beginTransaction();
            $sel = $pdo->prepare('SELECT * FROM despesas WHERE id = ? FOR UPDATE');
            $sel->execute([$idPagar]);
            $registro = $sel->fetch(PDO::FETCH_ASSOC);
            if (!$registro) {
                throw new Exception('Despesa não encontrada.');
            }
            if ($registro['status'] === 'Pago') {
                throw new Exception('Esta despesa já está paga.');
            }

            $upd = $pdo->prepare('UPDATE despesas SET status = ?, data_pagamento = CURDATE() WHERE id = ?');
            $upd->execute(['Pago', $idPagar]);

            if ((int)$registro['recorrente'] === 1) {
                $dt = new DateTime($registro['data_vencimento']);
                $dt->modify('+1 month');
                $proxVenc = $dt->format('Y-m-d');

                $dupCheck = $pdo->prepare('SELECT COUNT(*) FROM despesas WHERE descricao = ? AND data_vencimento = ? AND recorrente = 1');
                $dupCheck->execute([$registro['descricao'], $proxVenc]);
                $exists = $dupCheck->fetchColumn();

                if (!$exists) {
                    $ins = $pdo->prepare(
                        'INSERT INTO despesas (descricao, data_vencimento, valor, data_pagamento, juros, total_pago, status, recorrente, parcelado, numero_parcela, total_parcelas, grupo_parcelas, categoria, forma_pagamento, observacao, local)
                         VALUES (?, ?, ?, NULL, ?, NULL, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $ins->execute([
                        $registro['descricao'],
                        $proxVenc,
                        $registro['valor'],
                        $registro['juros'],
                        'Previsto',
                        $registro['parcelado'],
                        $registro['numero_parcela'],
                        $registro['total_parcelas'],
                        $registro['grupo_parcelas'],
                        $registro['categoria'],
                        $registro['forma_pagamento'],
                        $registro['observacao'],
                        $registro['local']
                    ]);
                }
            }

            $pdo->commit();
            $successMsg = 'Despesa marcada como paga e próxima previsão criada (se recorrente).';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMsg = $e->getMessage();
        }
    }
}

$where = [];
$params = [];

if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($categoria !== '') {
    $where[] = 'categoria LIKE ?';
    $params[] = '%' . $categoria . '%';
}
if ($data_ini !== '') {
    $where[] = 'data_vencimento >= ?';
    $params[] = $data_ini;
}
if ($data_fim !== '') {
    $where[] = 'data_vencimento <= ?';
    $params[] = $data_fim;
}
if ($recorrente === '1' || $recorrente === '0') {
    $where[] = 'recorrente = ?';
    $params[] = (int)$recorrente;
}
if ($parcelado === '1' || $parcelado === '0') {
    $where[] = 'parcelado = ?';
    $params[] = (int)$parcelado;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT * FROM despesas $whereSql ORDER BY categoria ASC, data_vencimento ASC, id ASC");
$stmt->execute($params);
$despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$agrupadas = [];
$totalGeral = 0;
foreach ($despesas as $d) {
    $cat = $d['categoria'] ?? 'Sem categoria';
    if (!isset($agrupadas[$cat])) {
        $agrupadas[$cat] = ['itens' => [], 'subtotal' => 0];
    }
    $agrupadas[$cat]['itens'][] = $d;
    $agrupadas[$cat]['subtotal'] += (float)$d['valor'];
    $totalGeral += (float)$d['valor'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Despesas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <style>
        body {
            background: var(--page-bg);
            min-height: 100vh;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: var(--text-color);
        }
        .page-container { padding: 18px; }
        .box {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--surface-color);
            border-radius: 12px;
            box-shadow: none;
            border: 1px solid var(--border-color);
            padding: 18px;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; color: #000; }
            .box { box-shadow: none; padding: 0; background: #fff; color: #000; }
            a { text-decoration: none; color: #000; }
        }
        .form-label { font-weight: 600; color: var(--text-color); }
        .table thead { background: var(--surface-soft); }
        .table-sm th, .table-sm td { font-size: 12px; vertical-align: middle; }
        .badge { font-size: 0.75rem; }
        .summary-total {
            font-weight: 700;
            font-size: 16px;
            padding: 8px 0;
            border-top: 1px solid var(--border-color);
            page-break-inside: avoid;
        }
        .table { page-break-inside: auto; }
        .table tr { page-break-inside: avoid; page-break-after: auto; }
        @media (max-width: 576px) {
            body { padding: 16px 8px; }
            .box { padding: 18px; }
            .btn { width: 100%; }
            .d-flex.align-items-center.gap-2 { flex-wrap: wrap; }
            .d-flex.align-items-center.gap-2 a,
            .d-flex.align-items-center.gap-2 button { width: auto; }
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <header class="topbar-global">
        <div class="brand">
            <span class="brand-dot"></span>
            <span>ProHelp Financeiro</span>
        </div>
        <div class="actions">
            <span class="small">Data/Hora: <?php echo htmlspecialchars($dataHora); ?></span>
            <a href="#" class="topbar-btn" onclick="return false;">Ajuda</a>
            <a href="principal.php" class="topbar-btn">Menu</a>
        </div>
    </header>
    <div class="page-container">
    <div class="box">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 no-print">
            <h3 class="fw-bold mb-0">Despesas</h3>
            <div class="d-flex align-items-center gap-2">
                <a href="despesa_form.php" class="btn btn-primary btn-sm">Nova despesa</a>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrint">Imprimir/PDF</button>
                <a href="principal.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
            </div>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert alert-success no-print"><?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-danger no-print"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <form class="row g-3 mb-3 no-print align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <option value="Pendente" <?= $status === 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="Pago" <?= $status === 'Pago' ? 'selected' : '' ?>>Pago</option>
                    <option value="Previsto" <?= $status === 'Previsto' ? 'selected' : '' ?>>Previsto</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="categoria">Categoria</label>
                <select class="form-select" id="categoria" name="categoria">
                    <option value="">Todas</option>
                    <?php foreach ($categoriasLista as $catNome): ?>
                        <option value="<?= htmlspecialchars($catNome) ?>" <?= $categoria === $catNome ? 'selected' : '' ?>>
                            <?= htmlspecialchars($catNome) ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($categoria && !in_array($categoria, $categoriasLista, true)): ?>
                        <option value="<?= htmlspecialchars($categoria) ?>" selected><?= htmlspecialchars($categoria) ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="data_ini">Vencimento a partir de</label>
                <input type="date" class="form-control" id="data_ini" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="data_fim">Vencimento até</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="recorrente">Recorrente</label>
                <select class="form-select" id="recorrente" name="recorrente">
                    <option value="">Todos</option>
                    <option value="1" <?= $recorrente === '1' ? 'selected' : '' ?>>Sim</option>
                    <option value="0" <?= $recorrente === '0' ? 'selected' : '' ?>>Não</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="parcelado">Parcelado</label>
                <select class="form-select" id="parcelado" name="parcelado">
                    <option value="">Todos</option>
                    <option value="1" <?= $parcelado === '1' ? 'selected' : '' ?>>Sim</option>
                    <option value="0" <?= $parcelado === '0' ? 'selected' : '' ?>>Não</option>
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="despesas.php" class="btn btn-outline-secondary">Limpar</a>
            </div>
        </form>

        <div class="table-responsive">
            <?php if (!$despesas): ?>
                <div class="text-center text-muted">Nenhuma despesa encontrada.</div>
            <?php else: ?>
                <?php foreach ($agrupadas as $catNome => $grupo): ?>
                    <h5 class="mt-3 mb-2"><?= htmlspecialchars($catNome) ?></h5>
                    <table class="table table-sm align-middle mb-1">
                        <thead>
                            <tr>
                                <th>Vencimento</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Forma pgto</th>
                                <th>Parcela</th>
                        <th>Recorrente</th>
                        <th class="no-print text-end">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grupo['itens'] as $d): ?>
                        <tr>
                                    <td><?= htmlspecialchars($d['data_vencimento']) ?></td>
                                    <td>
                                        <span class="fw-semibold"><?= htmlspecialchars($d['descricao']) ?></span>
                                        <?php if (!empty($d['observacao'])): ?>
                                            <span class="text-muted"> - <?= htmlspecialchars($d['observacao']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>R$ <?= number_format((float)$d['valor'], 2, ',', '.') ?></td>
                                    <td>
                                        <?php
                                            $badgeClass = match ($d['status']) {
                                                'Pago' => 'bg-success',
                                                'Previsto' => 'bg-info',
                                                default => 'bg-secondary'
                                            };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($d['status']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($d['forma_pagamento'] ?? '') ?></td>
                                    <td>
                                        <?php
                                            if ($d['parcelado']) {
                                                echo htmlspecialchars(($d['numero_parcela'] ?? '-') . '/' . ($d['total_parcelas'] ?? '-'));
                                            } else {
                                                echo '-';
                                            }
                                        ?>
                                    </td>
                                    <td><?= $d['recorrente'] ? 'Sim' : 'Não' ?></td>
                                    <td class="no-print">
                                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                                            <a href="despesa_edit.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                            <?php if ($d['status'] !== 'Pago'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="pagar_id" value="<?= (int)$d['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">Marcar pago</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Deseja excluir esta despesa?');">
                                                <input type="hidden" name="delete_id" value="<?= (int)$d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-semibold">
                                <td colspan="2">Subtotal</td>
                                <td colspan="5">R$ <?= number_format($grupo['subtotal'], 2, ',', '.') ?></td>
                            </tr>
                        </tbody>
                    </table>
                <?php endforeach; ?>
                <div class="summary-total mt-3">Total geral: R$ <?= number_format($totalGeral, 2, ',', '.') ?></div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.getElementById('btnPrint').addEventListener('click', () => {
            window.print();
        });
    </script>
    <div class="toast-container" id="toastContainer"></div>
    <script>
        const toastContainer = document.getElementById('toastContainer');
        function showToast(message, type = 'success') {
            if (!toastContainer) return;
            const config = {
                success: { cls: 'toast-success', icon: '✓', title: 'Sucesso' },
                error: { cls: 'toast-error', icon: '×', title: 'Erro' },
                warning: { cls: 'toast-warning', icon: '!', title: 'Alerta' }
            };
            const conf = config[type] || config.success;
            const el = document.createElement('div');
            el.className = `toast show ${conf.cls}`;
            el.innerHTML = `
                <div class="toast-icon">${conf.icon}</div>
                <div class="toast-body">
                    <div class="toast-title">${conf.title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" aria-label="Fechar">&times;</button>
            `;
            el.querySelector('.toast-close').addEventListener('click', () => el.remove());
            toastContainer.appendChild(el);
            setTimeout(() => {
                el.classList.add('hide');
                setTimeout(() => el.remove(), 300);
            }, 3200);
        }
        function triggerToast(message, type) {
            const run = () => showToast(message, type);
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', run);
            } else {
                run();
            }
        }
        <?php if ($successMsg): ?>
        triggerToast(<?= json_encode($successMsg) ?>, 'success');
        <?php endif; ?>
        <?php if ($errorMsg): ?>
        triggerToast(<?= json_encode($errorMsg) ?>, 'error');
        <?php endif; ?>
    </script>
</body>
</html>
