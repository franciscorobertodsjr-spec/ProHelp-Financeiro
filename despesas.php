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

$meses = [
    1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
    5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
    9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
];

$countDataIni = $data_ini ?: $primeiroMes;
$countDataFim = $data_fim ?: $ultimoMes;

$mesReferencia = DateTime::createFromFormat('Y-m-d', $countDataIni) ?: $hoje;
$mesAtualNome = $meses[(int)$mesReferencia->format('n')] ?? '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM despesas WHERE data_vencimento BETWEEN ? AND ?');
$countStmt->execute([$countDataIni, $countDataFim]);
$qtdDespesasMes = (int)$countStmt->fetchColumn();

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
    $cat = $d['categoria'] ?: 'Sem categoria';
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
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
            background: var(--surface-color, #f9fbfd);
            border: 1px solid var(--border-color, #d9e1eb);
            padding: 12px 12px 8px;
            border-radius: 12px;
            box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06));
        }
        .filter-form label { font-size: 12px; color: var(--muted-color, #4b5563); }
        .filter-form input, .filter-form select {
            background: var(--input-bg, #fff);
            border: 1px solid var(--border-color, #d9e1eb);
            color: var(--text-color, #1f2937);
            border-radius: 10px;
            padding: 10px 12px;
            min-width: 120px;
        }
        .button {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.08s ease, box-shadow 0.12s ease, background 0.12s ease;
        }
        .button-primary { background: linear-gradient(135deg, #10b981, #0ea5e9); color: #fff; box-shadow: 0 14px 24px rgba(16,185,129,0.25); }
        .button-outline { background: var(--surface-soft, #f1f4f8); color: var(--text-color, #1f2937); border: 1px solid var(--border-color, #d9e1eb); }
        .button-link { background: transparent; color: var(--text-color, #1f2937); border: 1px solid var(--border-color, #d9e1eb); }
        .button:hover { transform: translateY(-1px); }
        .panel { background: var(--surface-color, #f9fbfd); border: 1px solid var(--border-color, #d9e1eb); border-radius: 16px; padding: 16px; box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06)); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 12px; background: var(--surface-soft, #f1f4f8); font-weight: 600; gap: 6px; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; color: #000; }
            .sidebar { display: none; }
            .content { padding: 0; }
            .panel { border: none; box-shadow: none; }
            a { text-decoration: none; color: #000; }
        }
        .table-sm th, .table-sm td { font-size: 12px; vertical-align: middle; }
        .badge { font-size: 0.75rem; }
        .summary-total {
            font-weight: 700;
            font-size: 16px;
            padding: 8px 0;
            border-top: 1px solid var(--border-color);
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="layout">
        <?php renderSidebar('despesas'); ?>
        <main class="content">
            <div class="page-header">
                <div class="page-title">
                    <p class="eyebrow">Listagem</p>
                    <h1>Despesas</h1>
                    <span class="text-muted">Consulta agrupada por categoria • Atualizado em <?= htmlspecialchars($dataHora) ?></span>
                    <div class="d-flex flex-wrap gap-2 mt-1">
                        <span class="pill">
                            <?= htmlspecialchars(ucfirst($mesAtualNome)) ?>: <?= $qtdDespesasMes ?> <?= $qtdDespesasMes === 1 ? 'despesa' : 'despesas' ?>!
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 no-print flex-wrap">
                    <a href="despesa_form.php" class="button button-primary text-decoration-none">Nova despesa</a>
                    <a href="principal.php" class="button button-outline text-decoration-none">Dashboard</a>
                    <button type="button" id="btnPrint" class="button button-link">Imprimir</button>
                </div>
            </div>

            <?php if ($successMsg): ?>
                <div class="alert alert-success no-print"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div class="alert alert-danger no-print"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <form class="filter-form no-print" method="get">
                <div class="d-flex flex-column gap-1">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Todos</option>
                        <option value="Pendente" <?= $status === 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="Pago" <?= $status === 'Pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="Previsto" <?= $status === 'Previsto' ? 'selected' : '' ?>>Previsto</option>
                    </select>
                </div>
                <div class="d-flex flex-column gap-1">
                    <label for="categoria">Categoria</label>
                    <select id="categoria" name="categoria">
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
                <div class="d-flex flex-column gap-1">
                    <label for="data_ini">Vencimento a partir de</label>
                    <input type="date" id="data_ini" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>">
                </div>
                <div class="d-flex flex-column gap-1">
                    <label for="data_fim">Vencimento até</label>
                    <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
                </div>
                <div class="d-flex flex-column gap-1">
                    <label for="recorrente">Recorrente</label>
                    <select id="recorrente" name="recorrente">
                        <option value="">Todos</option>
                        <option value="1" <?= $recorrente === '1' ? 'selected' : '' ?>>Sim</option>
                        <option value="0" <?= $recorrente === '0' ? 'selected' : '' ?>>Não</option>
                    </select>
                </div>
                <div class="d-flex flex-column gap-1">
                    <label for="parcelado">Parcelado</label>
                    <select id="parcelado" name="parcelado">
                        <option value="">Todos</option>
                        <option value="1" <?= $parcelado === '1' ? 'selected' : '' ?>>Sim</option>
                        <option value="0" <?= $parcelado === '0' ? 'selected' : '' ?>>Não</option>
                    </select>
                </div>
                <button type="submit" class="button button-primary">Filtrar</button>
                <a href="despesas.php" class="button button-outline text-decoration-none">Limpar</a>
            </form>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Resultados</h3>
                    <span class="pill">Agrupado por categoria</span>
                </div>
                <div class="table-responsive">
                    <?php if (!$despesas): ?>
                        <div class="text-center text-muted">Nenhuma despesa encontrada.</div>
                    <?php else: ?>
                        <?php foreach ($agrupadas as $catNome => $grupo): ?>
                            <h5 class="mt-3 mb-2"><?= htmlspecialchars($catNome) ?> <span class="text-muted">(Subtotal R$ <?= number_format($grupo['subtotal'], 2, ',', '.') ?>)</span></h5>
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
                                                    $badgeClass = 'bg-secondary';
                                                    if ($d['status'] === 'Pago') {
                                                        $badgeClass = 'bg-success';
                                                    } elseif ($d['status'] === 'Previsto') {
                                                        $badgeClass = 'bg-info text-dark';
                                                    } elseif ($d['status'] === 'Pendente') {
                                                        $badgeClass = 'bg-warning text-dark';
                                                    }
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
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Marcar como pago?');">
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
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                        <div class="summary-total mt-3">Total geral: R$ <?= number_format($totalGeral, 2, ',', '.') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script>
        const btnPrint = document.getElementById('btnPrint');
        if (btnPrint) {
            btnPrint.addEventListener('click', () => window.print());
        }
    </script>
    <div class="popup-container" id="popupContainer"></div>
    <script>
        const popupContainer = document.getElementById('popupContainer');
        function showPopup(message, type = 'success') {
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
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', run);
            } else {
                run();
            }
        }
        <?php if ($successMsg): ?>
        triggerPopup(<?= json_encode($successMsg) ?>, 'success');
        <?php endif; ?>
        <?php if ($errorMsg): ?>
        triggerPopup(<?= json_encode($errorMsg) ?>, 'error');
        <?php endif; ?>
    </script>
</body>
</html>
