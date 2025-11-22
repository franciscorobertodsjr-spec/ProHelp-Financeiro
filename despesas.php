<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

$categoriasLista = $pdo->query('SELECT nome FROM categorias ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);

$status = $_GET['status'] ?? '';
$categoria = trim($_GET['categoria'] ?? '');
$data_ini = $_GET['data_ini'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$recorrente = $_GET['recorrente'] ?? '';
$parcelado = $_GET['parcelado'] ?? '';

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
    <style>
        body {
            background: #f4f6f9;
            min-height: 100vh;
            padding: 24px 12px;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #1f2937;
        }
        .box {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 10px 26px rgba(0,0,0,0.08);
            padding: 22px;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .box { box-shadow: none; padding: 0; }
            a { text-decoration: none; color: #000; }
        }
        .btn-primary {
            background: #10b981;
            border-color: #0ea271;
        }
        .btn-primary:hover {
            background: #0ea271;
            border-color: #0d9467;
        }
        .form-label { font-weight: 600; color: #111827; }
        .form-control:focus, .form-select:focus, .btn:focus { box-shadow: 0 0 0 0.2rem rgba(16,185,129,0.25); }
        a { color: #0d9467; }
        a:hover { color: #0a7a55; }
        .table thead { background: #f3f4f6; }
        .table-sm th, .table-sm td { font-size: 12px; vertical-align: middle; }
        .badge { font-size: 0.75rem; }
        .summary-total {
            font-weight: 700;
            font-size: 16px;
            padding: 8px 0;
            border-top: 1px solid #e5e7eb;
            page-break-inside: avoid;
        }
        .table { page-break-inside: auto; }
        .table tr { page-break-inside: avoid; page-break-after: auto; }
    </style>
</head>
<body>
    <div class="box">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 no-print">
            <h3 class="fw-bold mb-0">Despesas</h3>
            <div class="d-flex align-items-center gap-2">
                <a href="despesa_form.php" class="btn btn-primary btn-sm">Nova despesa</a>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrint">Imprimir/PDF</button>
                <a href="principal.php" class="btn btn-link p-0">Voltar</a>
            </div>
        </div>

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
</body>
</html>
