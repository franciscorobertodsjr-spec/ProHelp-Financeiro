<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

$ano = $_GET['ano'] ?? date('Y');
$ano = preg_match('/^\d{4}$/', $ano) ? $ano : date('Y');

// Totais gerais do ano
$totaisStmt = $pdo->prepare("
    SELECT
        SUM(valor) AS total,
        SUM(CASE WHEN status = 'Pago' THEN valor ELSE 0 END) AS total_pago,
        SUM(CASE WHEN status = 'Pendente' THEN valor ELSE 0 END) AS total_pendente,
        SUM(CASE WHEN status = 'Previsto' THEN valor ELSE 0 END) AS total_previsto
    FROM despesas
    WHERE YEAR(data_vencimento) = ?
");
$totaisStmt->execute([$ano]);
$totais = $totaisStmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'total_pago'=>0,'total_pendente'=>0,'total_previsto'=>0];

// Por categoria
$catStmt = $pdo->prepare("
    SELECT COALESCE(categoria, 'Sem categoria') AS categoria, SUM(valor) AS total_cat
    FROM despesas
    WHERE YEAR(data_vencimento) = ?
    GROUP BY categoria
    ORDER BY total_cat DESC
    LIMIT 10
");
$catStmt->execute([$ano]);
$porCategoria = $catStmt->fetchAll(PDO::FETCH_ASSOC);
$totalAno = (float)$totais['total'] ?: 0;

// Por mês
$mesStmt = $pdo->prepare("
    SELECT DATE_FORMAT(data_vencimento, '%Y-%m') AS mes, SUM(valor) AS total_mes
    FROM despesas
    WHERE YEAR(data_vencimento) = ?
    GROUP BY mes
    ORDER BY mes ASC
");
$mesStmt->execute([$ano]);
$porMes = $mesStmt->fetchAll(PDO::FETCH_ASSOC);

$labelsMes = array_column($porMes, 'mes');
$valoresMes = array_map(static fn($row) => (float)$row['total_mes'], $porMes);

$labelsCat = array_column($porCategoria, 'categoria');
$valoresCat = array_map(static fn($row) => (float)$row['total_cat'], $porCategoria);
$statusLabels = ['Pago', 'Pendente', 'Previsto'];
$statusValores = [
    (float)$totais['total_pago'],
    (float)$totais['total_pendente'],
    (float)$totais['total_previsto']
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        .card-metric {
            background: #0d6efd;
            color: #fff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 8px 18px rgba(13,110,253,0.25);
        }
        .card-metric h4 { margin: 0; font-size: 14px; opacity: 0.85; }
        .card-metric .value { font-size: 24px; font-weight: 700; }
        .table thead { background: #f3f4f6; }
        a { color: #0d9467; }
        a:hover { color: #0a7a55; }
        .chart-box {
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
        }
        .chart-row { row-gap: 16px; }
    </style>
</head>
<body>
    <div class="box">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h3 class="fw-bold mb-0">Dashboard <?= htmlspecialchars($ano) ?></h3>
                <div class="text-muted small">Indicadores do ano selecionado</div>
            </div>
            <form class="d-flex align-items-end gap-2 no-print" method="get">
                <div>
                    <label class="form-label mb-1" for="ano">Ano</label>
                    <input type="number" class="form-control form-control-sm" id="ano" name="ano" value="<?= htmlspecialchars($ano) ?>" min="2000" max="2100" style="width:110px;">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Limpar</a>
                <a href="despesas.php" class="btn btn-outline-secondary btn-sm">Ver despesas</a>
                <a href="principal.php" class="btn btn-link p-0">Voltar</a>
            </form>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card-metric bg-success">
                    <h4>Total Pago</h4>
                    <div class="value">R$ <?= number_format((float)$totais['total_pago'], 2, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-metric bg-warning text-dark">
                    <h4>Pendente</h4>
                    <div class="value">R$ <?= number_format((float)$totais['total_pendente'], 2, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-metric bg-info text-dark">
                    <h4>Previsto</h4>
                    <div class="value">R$ <?= number_format((float)$totais['total_previsto'], 2, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-metric bg-dark">
                    <h4>Total Geral</h4>
                    <div class="value">R$ <?= number_format((float)$totais['total'], 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 chart-row">
            <div class="col-md-6">
                <h5 class="fw-bold">Top categorias (10)</h5>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Categoria</th><th>Valor</th><th>%</th></tr></thead>
                    <tbody>
                        <?php if (!$porCategoria): ?>
                            <tr><td colspan="3" class="text-muted text-center">Sem dados</td></tr>
                        <?php else: ?>
                            <?php foreach ($porCategoria as $cat): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cat['categoria']) ?></td>
                                    <td>R$ <?= number_format((float)$cat['total_cat'], 2, ',', '.') ?></td>
                                    <td>
                                        <?php
                                            $perc = $totalAno > 0 ? ($cat['total_cat'] / $totalAno) * 100 : 0;
                                        ?>
                                        <?= number_format($perc, 1, ',', '.') ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <h5 class="fw-bold">Despesas por mês</h5>
                <div class="chart-box">
                    <canvas id="chartMes"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <h5 class="fw-bold">Distribuição por status</h5>
                <div class="chart-box">
                    <canvas id="chartStatus"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <h5 class="fw-bold">Top categorias (gráfico)</h5>
                <div class="chart-box">
                    <canvas id="chartCat"></canvas>
                </div>
            </div>
        </div>
    </div>
    <script>
        const ctx = document.getElementById('chartMes');
        const labelsMes = <?= json_encode($labelsMes, JSON_UNESCAPED_UNICODE) ?>;
        const valoresMes = <?= json_encode($valoresMes, JSON_UNESCAPED_UNICODE) ?>;
        if (ctx && labelsMes.length > 0) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labelsMes,
                    datasets: [{
                        label: 'Despesas por mês (R$)',
                        data: valoresMes,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.15)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => 'R$ ' + v.toLocaleString('pt-BR') } }
                    }
                }
            });
        }

        const ctxStatus = document.getElementById('chartStatus');
        const statusLabels = <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>;
        const statusValores = <?= json_encode($statusValores, JSON_UNESCAPED_UNICODE) ?>;
        if (ctxStatus) {
            new Chart(ctxStatus, {
                type: 'bar',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        label: 'Valor (R$)',
                        data: statusValores,
                        backgroundColor: ['#10b981', '#f59e0b', '#0ea5e9']
                    }]
                },
                options: {
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => 'R$ ' + v.toLocaleString('pt-BR') } } }
                }
            });
        }

        const ctxCat = document.getElementById('chartCat');
        const labelsCat = <?= json_encode($labelsCat, JSON_UNESCAPED_UNICODE) ?>;
        const valoresCat = <?= json_encode($valoresCat, JSON_UNESCAPED_UNICODE) ?>;
        if (ctxCat && labelsCat.length > 0) {
            new Chart(ctxCat, {
                type: 'doughnut',
                data: {
                    labels: labelsCat,
                    datasets: [{
                        data: valoresCat,
                        backgroundColor: ['#10b981','#0ea5e9','#f59e0b','#6366f1','#ef4444','#14b8a6','#8b5cf6','#ec4899','#22c55e','#a855f7']
                    }]
                },
                options: {
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    </script>
</body>
</html>
