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
    <link href="bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="theme.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap');
        body {
            background: radial-gradient(100% 120% at 0% 0%, rgba(16, 185, 129, 0.08), transparent 35%),
                        radial-gradient(80% 90% at 100% 0%, rgba(14, 165, 233, 0.08), transparent 30%),
                        var(--page-bg);
            min-height: 100vh;
            font-family: 'Poppins', 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: var(--text-color);
        }
        .layout {
            display: flex;
            min-height: 100vh;
        }
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
        body.theme-light .sidebar {
            background: linear-gradient(180deg, #0f766e 0%, #0a4f43 100%);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 19px;
            letter-spacing: 0.4px;
        }
        .brand .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #34d399;
            box-shadow: 0 0 0 6px rgba(52, 211, 153, 0.15);
        }
        .profile {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 14px 12px;
            text-align: center;
        }
        .avatar {
            width: 78px;
            height: 78px;
            margin: 0 auto 10px auto;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0.08));
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 26px;
            color: #f9fafb;
            box-shadow: 0 16px 40px rgba(0,0,0,0.25);
        }
        .profile h2 {
            margin: 0;
            font-size: 18px;
        }
        .profile p {
            margin: 2px 0 0 0;
            font-size: 12px;
            color: #d1d5db;
        }
        .menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .menu a {
            color: #ecfdf3;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.06);
            font-weight: 600;
            transition: background 0.15s ease, transform 0.08s ease;
        }
        .menu a:hover { background: rgba(255,255,255,0.12); transform: translateX(3px); }
        .menu a.active { background: rgba(52,211,153,0.18); color: #ecfdf3; }
        .sidebar-actions {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .sidebar .btn-ghost {
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: inherit;
            padding: 10px 12px;
            border-radius: 10px;
            text-decoration: none;
            text-align: center;
            font-weight: 700;
        }
        .content {
            flex: 1;
            padding: 28px 32px 34px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .page-title {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .page-title h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.4px;
        }
        .eyebrow { text-transform: uppercase; letter-spacing: 0.6px; font-size: 12px; color: var(--muted-color); margin: 0; }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            padding: 12px 12px 8px;
            border-radius: 12px;
            box-shadow: var(--shadow-soft);
        }
        .filter-form label { font-size: 12px; color: var(--muted-color); }
        .filter-form input, .filter-form select {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
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
        .button-outline { background: var(--surface-soft); color: var(--text-color); border: 1px solid var(--border-color); }
        .button-link { background: transparent; color: var(--text-color); border: 1px solid var(--border-color); }
        .button:hover { transform: translateY(-1px); }
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }
        .card-metric {
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 16px;
            box-shadow: var(--shadow-soft);
            position: relative;
            overflow: hidden;
        }
        .card-metric.success { border-color: rgba(16,185,129,0.32); box-shadow: 0 18px 32px rgba(16,185,129,0.16); }
        .card-metric.warning { border-color: rgba(245,158,11,0.28); box-shadow: 0 18px 32px rgba(245,158,11,0.12); }
        .card-metric.info { border-color: rgba(14,165,233,0.28); box-shadow: 0 18px 32px rgba(14,165,233,0.12); }
        .card-metric.dark { border-color: rgba(55,65,81,0.26); }
        .card-metric:before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.12), transparent 35%);
            opacity: 0.9;
            pointer-events: none;
        }
        .card-metric h4 { margin: 0; font-size: 13px; color: var(--muted-color); letter-spacing: 0.2px; }
        .card-metric .value { font-size: 26px; font-weight: 800; margin: 6px 0; }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #0f172a;
            background: #bbf7d0;
            padding: 6px 10px;
            border-radius: 20px;
            font-weight: 700;
        }
        .badge.warning { background: #fde68a; }
        .badge.info { background: #bfdbfe; }
        .badge.dark { background: #e5e7eb; color: #111827; }
        .panel {
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 16px;
            box-shadow: var(--shadow-soft);
        }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            gap: 10px;
        }
        .panel-title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        table thead { background: var(--surface-soft); }
        table th, table td { padding: 10px; border-bottom: 1px solid var(--border-color); }
        table tr:last-child td { border-bottom: none; }
        table th { text-transform: uppercase; font-size: 11px; letter-spacing: 0.4px; color: var(--muted-color); }
        table .text-success { color: #16a34a !important; }
        table .text-danger { color: #dc2626 !important; }
        .pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 12px;
            background: var(--surface-soft);
            font-weight: 600;
            gap: 6px;
        }
        .section-title {
            margin: 6px 0 10px;
            font-weight: 800;
            letter-spacing: -0.2px;
        }
        .chart-box {
            position: relative;
            min-height: 260px;
        }
        .select-inline {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            border-radius: 10px;
            padding: 8px 10px;
            min-width: 200px;
        }
        @media (max-width: 900px) {
            .layout { flex-direction: column; }
            .sidebar { width: 100%; border-radius: 0 0 18px 18px; box-shadow: 0 12px 30px rgba(0,0,0,0.18); }
        }
        @media (max-width: 600px) {
            .content { padding: 18px 16px 22px; }
            .filter-form { width: 100%; }
            .filter-form input, .filter-form select { width: 100%; }
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="layout">
        <aside class="sidebar">
            <div class="brand">
                <span class="dot"></span>
                <span>ProHelp Financeiro</span>
            </div>
            <div class="profile">
                <div class="avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?></div>
                <h2><?= htmlspecialchars($_SESSION['username'] ?? 'Usuário') ?></h2>
                <p>Resumo mensal</p>
            </div>
            <nav class="menu">
                <a href="principal.php">Dashboard anual</a>
                <a href="dashboard_mensal.php" class="active">Dashboard mensal</a>
                <a href="despesas.php">Despesas</a>
                <a href="orcamento_form.php">Orçamentos</a>
                <a href="categoria_form.php">Categorias</a>
                <a href="regra_categoria_form.php">Regras de categoria</a>
            </nav>
            <div class="sidebar-actions">
                <form method="post" class="d-grid gap-1">
                    <input type="hidden" name="toggle_theme" value="1">
                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'dashboard_mensal.php') ?>">
                    <button type="submit" class="btn-ghost">Tema: <?= themeLabel($theme); ?></button>
                </form>
                <a class="btn-ghost" href="documentacao.php" target="_blank" rel="noopener noreferrer">Ajuda</a>
                <a class="btn-ghost" href="logout.php">Sair</a>
            </div>
        </aside>
        <main class="content">
            <div class="page-header">
                <div class="page-title">
                    <p class="eyebrow">Resumo</p>
                    <h1>Dashboard Mensal <?= htmlspecialchars("$mes/$ano") ?></h1>
                    <span class="text-muted">Selecione mês/ano para analisar</span>
                </div>
                <form class="filter-form" method="get">
                    <div class="d-flex flex-column gap-1">
                        <label for="mes">Mês</label>
                        <select id="mes" name="mes">
                            <?php for ($m=1; $m<=12; $m++): $mm = str_pad($m,2,'0',STR_PAD_LEFT); ?>
                                <option value="<?= $mm ?>" <?= $mes===$mm?'selected':''; ?>><?= $mm ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <label for="ano">Ano</label>
                        <input type="number" id="ano" name="ano" value="<?= htmlspecialchars($ano) ?>" min="2000" max="2100">
                    </div>
                    <button type="submit" class="button button-primary">Aplicar</button>
                    <a href="dashboard_mensal.php" class="button button-outline text-decoration-none">Limpar</a>
                    <a href="principal.php" class="button button-link text-decoration-none">Dashboard anual</a>
                </form>
            </div>

            <div class="cards-grid">
                <div class="card-metric success">
                    <h4>Total Pago</h4>
                    <div class="value">R$ <?= number_format((float)$totais['total_pago'], 2, ',', '.') ?></div>
                    <span class="badge">Quitado</span>
                </div>
                <div class="card-metric warning">
                    <h4>Pendente</h4>
                    <div class="value">R$ <?= number_format((float)$totais['total_pendente'], 2, ',', '.') ?></div>
                    <span class="badge warning">Aguardando</span>
                </div>
                <div class="card-metric info">
                    <h4>Previsto</h4>
                    <div class="value">R$ <?= number_format((float)$totais['total_previsto'], 2, ',', '.') ?></div>
                    <span class="badge info">Planejado</span>
                </div>
                <div class="card-metric dark">
                    <h4>Total Geral</h4>
                    <div class="value">R$ <?= number_format((float)$totais['total'], 2, ',', '.') ?></div>
                    <span class="badge dark">Mês selecionado</span>
                </div>
            </div>

            <div class="chart-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="panel-title">Evolução diária</h3>
                        <span class="pill">Linha</span>
                    </div>
                    <div class="chart-box">
                        <canvas id="chartDia"></canvas>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="panel-title">Distribuição por status</h3>
                        <span class="pill">Barras</span>
                    </div>
                    <div class="chart-box">
                        <canvas id="chartStatus"></canvas>
                    </div>
                </div>
            </div>

            <h3 class="section-title">Categorias em destaque</h3>
            <div class="chart-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="panel-title">Top categorias (10)</h3>
                        <span class="pill">Tabela</span>
                    </div>
                    <table>
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
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="panel-title">Top categorias (gráfico)</h3>
                        <span class="pill">Barras</span>
                    </div>
                    <div class="chart-box">
                        <canvas id="chartCat"></canvas>
                    </div>
                </div>
            </div>

            <h3 class="section-title">Variação mês a mês (<?= htmlspecialchars($mesAnteriorLabel) ?> → <?= htmlspecialchars("$ano-$mes") ?>)</h3>
            <div class="chart-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="panel-title">Categorias que cresceram</h3>
                        <span class="pill">Top 10</span>
                    </div>
                    <table>
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
                                        <td class="text-success">+R$ <?= number_format((float)$c['delta'], 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="chart-box">
                        <canvas id="chartCres"></canvas>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="panel-title">Categorias que caíram</h3>
                        <span class="pill">Top 10</span>
                    </div>
                    <table>
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
                                        <td class="text-danger">-R$ <?= number_format(abs((float)$c['delta']), 2, ',', '.') ?></td>
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
        </main>
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
    </script>
</body>
</html>
