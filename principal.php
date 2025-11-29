<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'theme.php';

$isAdmin = !empty($_SESSION['is_admin']);
$theme = handleTheme($pdo);

$avisoProximo = [];
try {
    $hoje = (new DateTime())->format('Y-m-d');
    $limite = (new DateTime('+7 days'))->format('Y-m-d');
    $stmtAviso = $pdo->prepare("
        SELECT descricao, data_vencimento, valor, status
        FROM despesas
        WHERE status IN ('Pendente','Previsto')
          AND data_vencimento BETWEEN ? AND ?
        ORDER BY data_vencimento ASC
        LIMIT 5
    ");
    $stmtAviso->execute([$hoje, $limite]);
    $avisoProximo = $stmtAviso->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $avisoProximo = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Página Principal</title>
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
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .card-metric { background: var(--surface-color, #f9fbfd); border: 1px solid var(--border-color, #d9e1eb); border-radius: 14px; padding: 16px; box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06)); position: relative; overflow: hidden; }
        .card-metric .value { font-size: 26px; font-weight: 800; margin: 6px 0; }
        .panel { background: var(--surface-color, #f9fbfd); border: 1px solid var(--border-color, #d9e1eb); border-radius: 16px; padding: 16px; box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06)); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 12px; background: var(--surface-soft, #f1f4f8); font-weight: 600; gap: 6px; }
        .badge { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #0f172a; background: #bbf7d0; padding: 6px 10px; border-radius: 20px; font-weight: 700; }
        .alert-upcoming ul { margin: 8px 0 0 0; padding-left: 18px; }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="layout">
        <?php renderSidebar('principal'); ?>
        <main class="content">
            <div class="page-header">
                <div class="page-title">
                    <p class="eyebrow">Visão geral</p>
                    <h1>Bem-vindo, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                    <span class="text-muted">Perfil: <?php echo $isAdmin ? 'Administrador' : 'Padrão'; ?></span>
                </div>
                <form method="post" class="d-flex flex-column gap-2 no-print">
                    <input type="hidden" name="toggle_theme" value="1">
                    <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'principal.php'); ?>">
                    <button type="submit" class="button button-outline">Tema: <?php echo themeLabel($theme); ?></button>
                </form>
            </div>

            <div class="cards-grid">
                <div class="card-metric success">
                    <h4>Dashboards</h4>
                    <div class="value">Visão anual</div>
                    <span class="badge"><a class="text-decoration-none text-reset" href="dashboard.php">Abrir dashboard</a></span>
                </div>
                <div class="card-metric info">
                    <h4>Despesas</h4>
                    <div class="value">Consultar</div>
                    <span class="badge info"><a class="text-decoration-none text-reset" href="despesas.php">Ver lista</a></span>
                </div>
                <div class="card-metric warning">
                    <h4>Nova despesa</h4>
                    <div class="value">Lançar</div>
                    <span class="badge warning"><a class="text-decoration-none text-reset" href="despesa_form.php">Cadastrar</a></span>
                </div>
            </div>

            <?php if ($avisoProximo): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="panel-title">Contas próximas ao vencimento (7 dias)</h3>
                        <span class="pill">Atenção</span>
                    </div>
                    <div class="alert-upcoming">
                        <ul class="mb-0">
                            <?php
                                $hojeBase = (new DateTime('today'))->setTime(0, 0, 0);
                            ?>
                            <?php foreach ($avisoProximo as $d): ?>
                                <?php
                                    $vencDt = (new DateTime($d['data_vencimento']))->setTime(0, 0, 0);
                                    $dias = (int)$hojeBase->diff($vencDt)->format('%r%a');
                                    if ($dias === 0) {
                                        $textoDias = 'Hoje';
                                    } elseif ($dias > 0) {
                                        $textoDias = $dias . ' ' . ($dias === 1 ? 'Dia do Vencimento' : 'Dias do Vencimento');
                                    } else {
                                        $diasAtraso = abs($dias);
                                        $textoDias = $diasAtraso . ' ' . ($diasAtraso === 1 ? 'Dia em atraso' : 'Dias em atraso');
                                    }
                                ?>
                                <li>
                                    <?= htmlspecialchars($d['data_vencimento']) ?> - <?= htmlspecialchars($d['descricao']) ?> |
                                    R$ <?= number_format((float)$d['valor'], 2, ',', '.') ?> (<?= htmlspecialchars($d['status']) ?>)
                                    <?= htmlspecialchars($textoDias) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
