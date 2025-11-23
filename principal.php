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
$dataHora = (new DateTime())->format('d/m/Y H:i');

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
    <link rel="stylesheet" href="theme.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: var(--page-bg);
            min-height: 100vh;
            color: var(--text-color);
        }
        .layout { display: flex; min-height: calc(100vh - 60px); }
        .sidebar {
            width: 260px;
            background-color: #0f7b63 !important;
            background-image: linear-gradient(180deg, #0f7b63 0%, #0c6b56 100%);
            color: #e8f7f1;
            padding: 22px 18px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            box-shadow: 2px 0 14px rgba(0,0,0,0.12);
            transition: width 0.25s ease, padding 0.25s ease;
            border-right: 1px solid rgba(0,0,0,0.08);
        }
        body.theme-dark .sidebar {
            background-color: #0f7b63 !important;
            background-image: linear-gradient(180deg, #0f7b63 0%, #0c6b56 100%);
            color: #e8f7f1;
        }
        .sidebar.collapsed { width: 74px; padding: 22px 12px; }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 20px;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .brand .dot { width: 12px; height: 12px; border-radius: 50%; background: #10b981; opacity: 0.95; }
        .sidebar.collapsed .brand-text { display: none; }
        .user-box {
            background: rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 12px 14px;
            line-height: 1.5;
            transition: opacity 0.2s ease;
            word-break: break-word;
        }
        .sidebar.collapsed .user-box { opacity: 0; pointer-events: none; height: 0; padding: 0; margin: 0; }
        .user-box .label { opacity: 0.8; font-size: 13px; }
        .user-box .value { font-weight: 700; }
        .nav-links { display: flex; flex-direction: column; gap: 10px; flex: 1; }
        .nav-links a, .nav-placeholder {
            color: #e8f7f1;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.08);
            display: inline-block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .nav-links a:hover { background: rgba(0,0,0,0.15); color: #ecfdf3; }
        .sidebar a { color: #e8f7f1; }
        .sidebar.collapsed .nav-links a, .sidebar.collapsed .nav-placeholder { text-align: center; padding: 10px 0; }
        .sidebar.collapsed .nav-placeholder { display: none; }
        .nav-section {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .nav-title {
            text-transform: uppercase;
            font-size: 12px;
            opacity: 0.7;
            letter-spacing: 0.6px;
            padding: 0 4px;
        }
        .nav-parent {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.06);
            color: inherit;
            cursor: pointer;
            border: none;
            width: 100%;
        }
        .nav-parent:hover { background: rgba(16,185,129,0.18); color: #ecfdf3; }
        .nav-parent .chevron { transition: transform 0.2s ease; }
        .nav-parent.open .chevron { transform: rotate(90deg); }
        .nav-children {
            display: none;
            flex-direction: column;
            gap: 6px;
            padding-left: 10px;
            margin-top: 6px;
        }
        .nav-children.show { display: flex; }
        .menu-icon {
            font-size: 14px;
            margin-right: 8px;
        }
        .menu-label { display: inline; }
        .sidebar.collapsed .nav-title { display: none; }
        .sidebar.collapsed .menu-label { display: none; }
        .sidebar.collapsed .chevron { display: none; }
        .sidebar.collapsed .nav-parent { justify-content: center; padding: 12px 0; }
        .sidebar.collapsed .nav-links a { padding: 10px 0; text-align: center; }
        .sidebar.collapsed .nav-links a .menu-icon { margin-right: 0; }
        .sidebar.collapsed .nav-children { display: none !important; }
        .logout-link {
            color: #f3f4f6;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.25);
            text-align: center;
        }
        .logout-link:hover { background: rgba(16,185,129,0.18); color: #ecfdf3; }
        .content { flex: 1; padding: 28px 34px; display: flex; flex-direction: column; gap: 18px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .toggle-btn {
            border: none;
            background: #10b981;
            color: #0b1c2c;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(16,185,129,0.28);
            transition: background 0.15s ease, transform 0.1s ease;
        }
        .toggle-btn:hover { background: #0ea271; }
        .toggle-btn:active { transform: translateY(1px); }
        .theme-btn {
            background: rgba(255,255,255,0.12);
            color: inherit;
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 8px;
            padding: 8px 10px;
            cursor: pointer;
        }
        .theme-btn:hover { background: rgba(255,255,255,0.2); }
        .card {
            background: var(--surface-color);
            border-radius: 12px;
            box-shadow: none;
            border: 1px solid var(--border-color);
            padding: 22px 24px;
        }
        body.theme-dark .card { box-shadow: var(--shadow-strong); }
        .card h1 { margin: 0 0 8px 0; font-size: 24px; }
        .card p { margin: 0; color: var(--muted-color); }
        .alert-upcoming {
            background: rgba(16,185,129,0.12);
            border: 1px solid rgba(16,185,129,0.3);
            color: var(--text-color);
            border-radius: 10px;
            padding: 14px 16px;
        }
        .alert-upcoming ul { margin: 8px 0 0 0; padding-left: 18px; }
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
            <a href="logout.php" class="topbar-btn">Sair</a>
        </div>
    </header>
    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <span class="dot"></span>
                <span class="brand-text">ProHelp</span>
            </div>
            <div class="user-box">
                <div class="label">Usuário</div>
                <div class="value"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                <div class="label" style="margin-top:6px;">Perfil</div>
                <div class="value"><?php echo $isAdmin ? 'Administrador' : 'Padrão'; ?></div>
            </div>
            <form method="post" class="d-flex flex-column gap-2">
                <input type="hidden" name="toggle_theme" value="1">
                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'principal.php'); ?>">
                <button type="submit" class="theme-btn">
                    <span class="menu-icon">🌗</span>
                    <span class="menu-label">Tema: <?php echo themeLabel($theme); ?></span>
                </button>
            </form>
            <div class="nav-links">
                <div class="nav-section">
                    <div class="nav-title">Visão</div>
                    <button type="button" class="nav-parent" data-target="group-dash">
                        <span><span class="menu-icon">📊</span><span class="menu-label">Dashboards</span></span>
                        <span class="chevron">▶</span>
                    </button>
                    <div class="nav-children" id="group-dash">
                        <a href="dashboard.php"><span class="menu-icon">📈</span><span class="menu-label">Dashboard anual</span></a>
                        <a href="dashboard_mensal.php"><span class="menu-icon">🗓️</span><span class="menu-label">Dashboard mensal</span></a>
                    </div>
                </div>
                <div class="nav-section">
                    <div class="nav-title">Despesas</div>
                    <button type="button" class="nav-parent" data-target="group-desp">
                        <span><span class="menu-icon">💸</span><span class="menu-label">Lançamentos</span></span>
                        <span class="chevron">▶</span>
                    </button>
                    <div class="nav-children" id="group-desp">
                        <a href="despesas.php"><span class="menu-icon">📃</span><span class="menu-label">Lista de despesas</span></a>
                        <a href="despesa_form.php"><span class="menu-icon">➕</span><span class="menu-label">Nova despesa</span></a>
                    </div>
                </div>
                <div class="nav-section">
                    <div class="nav-title">Cadastros</div>
                    <button type="button" class="nav-parent" data-target="group-cad">
                        <span><span class="menu-icon">⚙️</span><span class="menu-label">Configurações</span></span>
                        <span class="chevron">▶</span>
                    </button>
                    <div class="nav-children" id="group-cad">
                        <a href="categoria_form.php"><span class="menu-icon">🏷️</span><span class="menu-label">Categorias</span></a>
                        <a href="orcamento_form.php"><span class="menu-icon">💰</span><span class="menu-label">Orçamentos</span></a>
                        <a href="regra_categoria_form.php"><span class="menu-icon">🧭</span><span class="menu-label">Regras de categoria</span></a>
                        <?php if ($isAdmin): ?>
                            <a href="aprovar_usuarios.php"><span class="menu-icon">✅</span><span class="menu-label">Aprovar cadastros</span></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </aside>
        <main class="content">
            <div class="topbar">
                <button class="toggle-btn" id="toggleSidebar">Alternar menu</button>
            </div>
            <div class="card">
                <h1>Bem-vindo, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                <p>Esta é a página principal do sistema de despesas.</p>
            </div>
            <?php if ($avisoProximo): ?>
                <div class="alert-upcoming">
                    <div class="fw-semibold mb-1">Contas próximas ao vencimento (7 dias):</div>
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
            <?php endif; ?>
        </main>
    </div>
    <script>
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });

        document.querySelectorAll('.nav-parent').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.target);
                const isOpen = btn.classList.toggle('open');
                if (target) {
                    target.classList.toggle('show', isOpen);
                }
            });
        });
    </script>
</body>
</html>
