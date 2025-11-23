<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'theme.php';

$theme = handleTheme($pdo);

$hoje = new DateTime();
$ano = $_GET['ano'] ?? $hoje->format('Y');
$mes = $_GET['mes'] ?? $hoje->format('m');
$ano = preg_match('/^\d{4}$/', $ano) ? $ano : $hoje->format('Y');
$mes = preg_match('/^\d{2}$/', $mes) ? $mes : $hoje->format('m');

$inicio = "$ano-$mes-01";
$fim = (new DateTime($inicio))->modify('last day of this month')->format('Y-m-d');

// Totais do mês
$totaisStmt = $pdo->prepare("
    SELECT
        SUM(valor) AS total,
        SUM(CASE WHEN status = 'Pago' THEN valor ELSE 0 END) AS total_pago,
        SUM(CASE WHEN status = 'Pendente' THEN valor ELSE 0 END) AS total_pendente,
        SUM(CASE WHEN status = 'Previsto' THEN valor ELSE 0 END) AS total_previsto
    FROM despesas
    WHERE data_vencimento BETWEEN ? AND ?
");
$totaisStmt->execute([$inicio, $fim]);
$totais = $totaisStmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'total_pago'=>0,'total_pendente'=>0,'total_previsto'=>0];

// Por categoria
$catStmt = $pdo->prepare("
    SELECT COALESCE(categoria, 'Sem categoria') AS categoria, SUM(valor) AS total_cat
    FROM despesas
    WHERE data_vencimento BETWEEN ? AND ?
    GROUP BY categoria
    ORDER BY total_cat DESC
    LIMIT 10
");
$catStmt->execute([$inicio, $fim]);
$porCategoria = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Por dia (linha)
$diaStmt = $pdo->prepare("
    SELECT DATE_FORMAT(data_vencimento, '%Y-%m-%d') AS dia, SUM(valor) AS total_dia
    FROM despesas
    WHERE data_vencimento BETWEEN ? AND ?
    GROUP BY dia
    ORDER BY dia ASC
");
$diaStmt->execute([$inicio, $fim]);
$porDia = $diaStmt->fetchAll(PDO::FETCH_ASSOC);

$labelsDia = array_column($porDia, 'dia');
$valoresDia = array_map(static fn($row) => (float)$row['total_dia'], $porDia);
$labelsCat = array_column($porCategoria, 'categoria');
$valoresCat = array_map(static fn($row) => (float)$row['total_cat'], $porCategoria);
$totalMes = (float)$totais['total'] ?: 0;

$statusLabels = ['Pago', 'Pendente', 'Previsto'];
$statusValores = [
    (float)$totais['total_pago'],
    (float)$totais['total_pendente'],
    (float)$totais['total_previsto']
];

$recStmt = $pdo->prepare("SELECT recorrente, SUM(valor) AS total FROM despesas WHERE data_vencimento BETWEEN ? AND ? GROUP BY recorrente");
$recStmt->execute([$inicio, $fim]);
$recData = $recStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$recLabels = ['Recorrente', 'Não recorrente'];
$recValores = [
    isset($recData[1]) ? (float)$recData[1] : 0,
    isset($recData[0]) ? (float)$recData[0] : 0
];

$parStmt = $pdo->prepare("SELECT parcelado, SUM(valor) AS total FROM despesas WHERE data_vencimento BETWEEN ? AND ? GROUP BY parcelado");
$parStmt->execute([$inicio, $fim]);
$parData = $parStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$parLabels = ['Parcelado', 'Não parcelado'];
$parValores = [
    isset($parData[1]) ? (float)$parData[1] : 0,
    isset($parData[0]) ? (float)$parData[0] : 0
];

$prevIniDt = new DateTime($inicio);
$prevIniDt->modify('first day of last month');
$prevFimDt = new DateTime($prevIniDt->format('Y-m-d'));
$prevFimDt->modify('last day of this month');
$inicioAnterior = $prevIniDt->format('Y-m-d');
$fimAnterior = $prevFimDt->format('Y-m-d');
$mesAnteriorLabel = $prevIniDt->format('Y-m');

$varStmt = $pdo->prepare("
    SELECT
        COALESCE(categoria, 'Sem categoria') AS categoria,
        SUM(CASE WHEN data_vencimento BETWEEN ? AND ? THEN valor ELSE 0 END) AS total_atual,
        SUM(CASE WHEN data_vencimento BETWEEN ? AND ? THEN valor ELSE 0 END) AS total_anterior
    FROM despesas
    WHERE (data_vencimento BETWEEN ? AND ?) OR (data_vencimento BETWEEN ? AND ?)
    GROUP BY categoria
");
$varStmt->execute([$inicio, $fim, $inicioAnterior, $fimAnterior, $inicio, $fim, $inicioAnterior, $fimAnterior]);
$variacoes = [];
foreach ($varStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $atual = (float)$row['total_atual'];
    $anterior = (float)$row['total_anterior'];
    $delta = $atual - $anterior;
    $variacoes[] = [
        'categoria' => $row['categoria'],
        'atual' => $atual,
        'anterior' => $anterior,
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
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Mensal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="theme.css">
    <style>
        body { background: var(--page-bg); min-height: 100vh; padding: 24px 12px; font-family: 'Segoe UI', Arial, sans-serif; color: var(--text-color); }
        .box { max-width: 1200px; margin: 0 auto; background: var(--surface-color); border-radius: 14px; box-shadow: var(--shadow-strong); padding: 22px; }
        .card-metric { background: #0d6efd; color: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 8px 18px rgba(13,110,253,0.25); }
        .card-metric h4 { margin: 0; font-size: 14px; opacity: 0.85; }
        .card-metric .value { font-size: 22px; font-weight: 700; }
        .table thead { background: var(--surface-soft); }
        .chart-box { background: var(--surface-color); border-radius: 12px; padding: 16px; box-shadow: var(--shadow-soft); }
        @media (max-width: 576px) {
            body { padding: 16px 8px; }
            .box { padding: 18px; }
            .d-flex.align-items-end.gap-2 { flex-wrap: wrap; }
            .d-flex.align-items-end.gap-2 .btn { width: auto; }
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="box">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-0">Dashboard Mensal</h3>
                <div class="text-muted small">Selecione mês/ano para analisar</div>
            </div>
            <div class="d-flex align-items-end gap-2 flex-wrap">
                <form class="d-flex align-items-end gap-2 flex-wrap" method="get">
                    <div>
                        <label class="form-label mb-1" for="mes">Mês</label>
                        <select class="form-select form-select-sm" id="mes" name="mes" style="width:120px;">
                            <?php for ($m=1; $m<=12; $m++): $mm = str_pad($m,2,'0',STR_PAD_LEFT); ?>
                                <option value="<?= $mm ?>" <?= $mes===$mm?'selected':''; ?>><?= $mm ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label mb-1" for="ano">Ano</label>
                        <input type="number" class="form-control form-control-sm" id="ano" name="ano" value="<?= htmlspecialchars($ano) ?>" min="2000" max="2100" style="width:110px;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
                    <a href="dashboard_mensal.php" class="btn btn-outline-secondary btn-sm">Limpar</a>
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

        <div class="row g-4">
            <div class="col-md-6">
                <h5 class="fw-bold">Top categorias (10)</h5>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Categoria</th><th>Valor</th><th>%</th></tr></thead>
                    <tbody>
                        <?php if (!$porCategoria): ?>
                            <tr><td colspan="3" class="text-muted text-center">Sem dados</td></tr>
                        <?php else: ?>
                            <?php foreach ($porCategoria as $cat): ?>
                                <?php $perc = $totalMes > 0 ? ($cat['total_cat'] / $totalMes) * 100 : 0; ?>
                                <tr>
                                    <td><?= htmlspecialchars($cat['categoria']) ?></td>
                                    <td>R$ <?= number_format((float)$cat['total_cat'], 2, ',', '.') ?></td>
                                    <td><?= number_format($perc, 1, ',', '.') ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <h5 class="fw-bold">Evolução diária</h5>
                <div class="chart-box">
                    <canvas id="chartDia"></canvas>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6">
                <h5 class="fw-bold">Top 10 categorias que cresceram (<?= htmlspecialchars($mesAnteriorLabel) ?> → <?= htmlspecialchars("$ano-$mes") ?>)</h5>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Categoria</th><th>Atual</th><th>Anterior</th><th>Variação</th></tr></thead>
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
                <h5 class="fw-bold">Top 10 categorias que caíram (<?= htmlspecialchars($mesAnteriorLabel) ?> → <?= htmlspecialchars("$ano-$mes") ?>)</h5>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Categoria</th><th>Atual</th><th>Anterior</th><th>Variação</th></tr></thead>
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
        <div class="row g-4">
            <div class="col-md-6">
                <h5 class="fw-bold">Distribuição por status</h5>
                <div class="chart-box">
                    <canvas id="chartStatus"></canvas>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6">
                <h5 class="fw-bold">Distribuição por status</h5>
                <div class="chart-box">
                    <canvas id="chartStatus"></canvas>
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

        const ctxDia = document.getElementById('chartDia');
        const labelsDia = <?= json_encode($labelsDia, JSON_UNESCAPED_UNICODE) ?>;
        const valoresDia = <?= json_encode($valoresDia, JSON_UNESCAPED_UNICODE) ?>;
        if (ctxDia && labelsDia.length > 0) {
            new Chart(ctxDia, {
                type: 'line',
                data: {
                    labels: labelsDia,
                    datasets: [{
                        label: 'Despesas por dia (R$)',
                        data: valoresDia,
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
    </script>
</body>
</html>
