<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'theme.php';

$theme = handleTheme($pdo);

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

$recStmt = $pdo->prepare("SELECT recorrente, SUM(valor) AS total FROM despesas WHERE YEAR(data_vencimento)=? GROUP BY recorrente");
$recStmt->execute([$ano]);
$recData = $recStmt->fetchAll(PDO::FETCH_KEY_PAIR); // recorrente => total
$recLabels = ['Recorrente', 'Não recorrente'];
$recValores = [
    isset($recData[1]) ? (float)$recData[1] : 0,
    isset($recData[0]) ? (float)$recData[0] : 0
];

$parStmt = $pdo->prepare("SELECT parcelado, SUM(valor) AS total FROM despesas WHERE YEAR(data_vencimento)=? GROUP BY parcelado");
$parStmt->execute([$ano]);
$parData = $parStmt->fetchAll(PDO::FETCH_KEY_PAIR); // parcelado => total
$parLabels = ['Parcelado', 'Não parcelado'];
$parValores = [
    isset($parData[1]) ? (float)$parData[1] : 0,
    isset($parData[0]) ? (float)$parData[0] : 0
];

$anoAnterior = (string)((int)$ano - 1);
$variacaoStmt = $pdo->prepare("
    SELECT
        COALESCE(categoria, 'Sem categoria') AS categoria,
        SUM(CASE WHEN YEAR(data_vencimento) = ? THEN valor ELSE 0 END) AS total_atual,
        SUM(CASE WHEN YEAR(data_vencimento) = ? THEN valor ELSE 0 END) AS total_anterior
    FROM despesas
    WHERE YEAR(data_vencimento) IN (?, ?)
    GROUP BY categoria
");
$variacaoStmt->execute([$ano, $anoAnterior, $ano, $anoAnterior]);
$variacoes = [];
foreach ($variacaoStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $totalAtual = (float)$row['total_atual'];
    $totalAnterior = (float)$row['total_anterior'];
    $delta = $totalAtual - $totalAnterior;
    $variacoes[] = [
        'categoria' => $row['categoria'],
        'atual' => $totalAtual,
        'anterior' => $totalAnterior,
        'delta' => $delta
    ];
}
$crescentes = array_values(array_filter($variacoes, static fn($v) => $v['delta'] > 0));
usort($crescentes, static fn($a, $b) => $b['delta'] <=> $a['delta']);
$crescentes = array_slice($crescentes, 0, 10);

$decrescentes = array_values(array_filter($variacoes, static fn($v) => $v['delta'] < 0));
usort($decrescentes, static fn($a, $b) => $a['delta'] <=> $b['delta']);
$decrescentes = array_slice($decrescentes, 0, 10);

$cresLabels = array_column($crescentes, 'categoria');
$cresValores = array_map(static fn($v) => round($v['delta'], 2), $crescentes);
$quedaLabels = array_column($decrescentes, 'categoria');
$quedaValores = array_map(static fn($v) => round($v['delta'], 2), $decrescentes);

$catMesStmt = $pdo->prepare("
    SELECT COALESCE(categoria, 'Sem categoria') AS categoria,
           DATE_FORMAT(data_vencimento, '%Y-%m') AS mes,
           SUM(valor) AS total
    FROM despesas
    WHERE YEAR(data_vencimento) = ?
    GROUP BY categoria, mes
    ORDER BY categoria, mes
");
$catMesStmt->execute([$ano]);
$catMesRows = $catMesStmt->fetchAll(PDO::FETCH_ASSOC);
$catMesData = [];
$mesLabelsCat = [];
foreach ($catMesRows as $row) {
    $cat = $row['categoria'];
    $mes = $row['mes'];
    $mesLabelsCat[$mes] = true;
    $catMesData[$cat][$mes] = (float)$row['total'];
}
$mesLabelsCat = array_keys($mesLabelsCat);
sort($mesLabelsCat);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        .card-metric {
            background: #0d6efd;
            color: #fff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 8px 18px rgba(13,110,253,0.25);
        }
        .card-metric h4 { margin: 0; font-size: 14px; opacity: 0.85; }
        .card-metric .value { font-size: 24px; font-weight: 700; }
        .table thead { background: var(--surface-soft); }
        .chart-box {
            background: var(--surface-color);
            border-radius: 12px;
            padding: 16px;
            box-shadow: var(--shadow-soft);
        }
        .chart-row { row-gap: 16px; }
        @media (max-width: 576px) {
            .page-container { padding: 12px; }
            .box { padding: 16px; }
            .d-flex.align-items-end.gap-2 { flex-wrap: wrap; }
            .d-flex.align-items-end.gap-2 .btn { width: auto; }
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="page-container">
    <div class="box">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-0">Dashboard <?= htmlspecialchars($ano) ?></h3>
                <div class="text-muted small">Indicadores do ano selecionado</div>
            </div>
            <div class="d-flex align-items-end gap-2 no-print flex-wrap">
                <form class="d-flex align-items-end gap-2 flex-wrap" method="get">
                    <div>
                        <label class="form-label mb-1" for="ano">Ano</label>
                        <input type="number" class="form-control form-control-sm" id="ano" name="ano" value="<?= htmlspecialchars($ano) ?>" min="2000" max="2100" style="width:110px;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Limpar</a>
                    <a href="despesas.php" class="btn btn-outline-secondary btn-sm">Ver despesas</a>
                    <a href="principal.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
                </form>
            </div>
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
        </div>
        <div class="row g-4 chart-row">
            <div class="col-md-6">
                <h5 class="fw-bold">Top 10 categorias que cresceram (<?php echo htmlspecialchars($anoAnterior); ?> → <?php echo htmlspecialchars($ano); ?>)</h5>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Categoria</th><th>Atual (<?php echo htmlspecialchars($ano); ?>)</th><th>Anterior (<?php echo htmlspecialchars($anoAnterior); ?>)</th><th>Variação</th></tr></thead>
                    <tbody>
                        <?php if (!$crescentes): ?>
                            <tr><td colspan="4" class="text-muted text-center">Sem dados</td></tr>
                        <?php else: ?>
                            <?php foreach ($crescentes as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['categoria']) ?></td>
                                    <td>R$ <?= number_format((float)$c['atual'], 2, ',', '.') ?></td>
                                    <td>R$ <?= number_format((float)$c['anterior'], 2, ',', '.') ?></td>
                                    <td class="text-success fw-semibold">+R$ <?= number_format((float)$c['delta'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="chart-box">
                    <canvas id="chartCres"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <h5 class="fw-bold">Top 10 categorias que caíram (<?php echo htmlspecialchars($anoAnterior); ?> → <?php echo htmlspecialchars($ano); ?>)</h5>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Categoria</th><th>Atual (<?php echo htmlspecialchars($ano); ?>)</th><th>Anterior (<?php echo htmlspecialchars($anoAnterior); ?>)</th><th>Variação</th></tr></thead>
                    <tbody>
                        <?php if (!$decrescentes): ?>
                            <tr><td colspan="4" class="text-muted text-center">Sem dados</td></tr>
                        <?php else: ?>
                            <?php foreach ($decrescentes as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['categoria']) ?></td>
                                    <td>R$ <?= number_format((float)$c['atual'], 2, ',', '.') ?></td>
                                    <td>R$ <?= number_format((float)$c['anterior'], 2, ',', '.') ?></td>
                                    <td class="text-danger fw-semibold">-R$ <?= number_format(abs((float)$c['delta']), 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="chart-box">
                    <canvas id="chartQueda"></canvas>
                </div>
            </div>
        </div>
        <div class="row g-4 chart-row">
            <div class="col-md-6">
                <h5 class="fw-bold">Top categorias (gráfico)</h5>
                <div class="chart-box">
                    <canvas id="chartCat"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <h5 class="fw-bold">Distribuição por status</h5>
                <div class="chart-box">
                    <canvas id="chartStatus"></canvas>
                </div>
            </div>
        </div>
        <div class="row g-4 chart-row">
            <div class="col-12">
                <h5 class="fw-bold d-flex align-items-center gap-2 flex-wrap">
                    Despesas por mês e categoria (<?php echo htmlspecialchars($ano); ?>)
                    <select id="catMesSelect" class="form-select form-select-sm" style="width:auto; min-width:180px;">
                        <?php foreach (array_keys($catMesData) as $catNome): ?>
                            <option value="<?= htmlspecialchars($catNome) ?>"><?= htmlspecialchars($catNome) ?></option>
                        <?php endforeach; ?>
                    </select>
                </h5>
                <div class="chart-box">
                    <canvas id="chartCatMes"></canvas>
                </div>
            </div>
        </div>
    </div>
    <script>
        const themeStyles = getComputedStyle(document.body);
        const chartText = themeStyles.getPropertyValue('--text-color').trim() || '#1f2937';
        const chartBorder = themeStyles.getPropertyValue('--border-color').trim() || '#e5e7eb';
        Chart.defaults.color = chartText;
        Chart.defaults.borderColor = chartBorder;
        const valuePlugin = {
            id: 'valuePlugin',
            afterDatasetsDraw(chart) {
                const {ctx} = chart;
                ctx.save();
                ctx.fillStyle = chartText;
                ctx.font = '12px sans-serif';
                chart.data.datasets.forEach((dataset, i) => {
                    const meta = chart.getDatasetMeta(i);
                    if (!chart.isDatasetVisible(i)) return;
                    meta.data.forEach((element, index) => {
                        const raw = dataset.data[index];
                        if (raw === null || raw === undefined) return;
                        const value = typeof raw === 'number' ? raw : Number(raw) || 0;
                        const label = 'R$ ' + value.toLocaleString('pt-BR');
                        const pos = element.tooltipPosition();
                        const y = pos.y - 6;
                        ctx.fillText(label, pos.x, y);
                    });
                });
                ctx.restore();
            }
        };
        Chart.register(valuePlugin);

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
                type: 'bar',
                data: {
                    labels: labelsCat,
                    datasets: [{
                        data: valoresCat,
                        backgroundColor: ['#10b981','#0ea5e9','#f59e0b','#6366f1','#ef4444','#14b8a6','#8b5cf6','#ec4899','#22c55e','#a855f7']
                    }]
                },
                options: {
                    plugins: { legend: { display: false } },
                    indexAxis: 'y',
                    scales: {
                        x: { ticks: { callback: v => 'R$ ' + v.toLocaleString('pt-BR') }, beginAtZero: true }
                    }
                }
            });
        }


        const ctxCres = document.getElementById('chartCres');
        const cresLabels = <?= json_encode($cresLabels, JSON_UNESCAPED_UNICODE) ?>;
        const cresValores = <?= json_encode($cresValores, JSON_UNESCAPED_UNICODE) ?>;
        if (ctxCres && cresLabels.length > 0) {
            new Chart(ctxCres, {
                type: 'bar',
                data: {
                    labels: cresLabels,
                    datasets: [{
                        label: 'Variação (R$)',
                        data: cresValores,
                        backgroundColor: '#10b981'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: { x: { ticks: { callback: v => 'R$ ' + v.toLocaleString('pt-BR') } } }
                }
            });
        }

        const ctxQueda = document.getElementById('chartQueda');
        const quedaLabels = <?= json_encode($quedaLabels, JSON_UNESCAPED_UNICODE) ?>;
        const quedaValores = <?= json_encode($quedaValores, JSON_UNESCAPED_UNICODE) ?>;
        if (ctxQueda && quedaLabels.length > 0) {
            new Chart(ctxQueda, {
                type: 'bar',
                data: {
                    labels: quedaLabels,
                    datasets: [{
                        label: 'Variação (R$)',
                        data: quedaValores,
                        backgroundColor: '#ef4444'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: { x: { ticks: { callback: v => 'R$ ' + v.toLocaleString('pt-BR') } } }
                }
            });
        }

        const catMesData = <?= json_encode($catMesData, JSON_UNESCAPED_UNICODE) ?>;
        const mesLabelsCat = <?= json_encode($mesLabelsCat, JSON_UNESCAPED_UNICODE) ?>;
        const catMesSelect = document.getElementById('catMesSelect');
        const ctxCatMes = document.getElementById('chartCatMes');
        let catMesChart = null;

        function renderCatMesChart(cat) {
            if (!ctxCatMes) return;
            const dataCat = mesLabelsCat.map(m => (catMesData[cat] && catMesData[cat][m]) ? catMesData[cat][m] : 0);
            if (catMesChart) catMesChart.destroy();
            catMesChart = new Chart(ctxCatMes, {
                type: 'line',
                data: {
                    labels: mesLabelsCat,
                    datasets: [{
                        label: `Despesas de ${cat} (R$)`,
                        data: dataCat,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14,165,233,0.18)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 4
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

        if (catMesSelect && ctxCatMes && Object.keys(catMesData).length > 0) {
            renderCatMesChart(catMesSelect.value);
            catMesSelect.addEventListener('change', () => renderCatMesChart(catMesSelect.value));
        }
    </script>
    </div>
    </div>
</body>
</html>
