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
            display: flex;
            min-height: 100vh;
            color: var(--text-color);
        }
        .layout { display: flex; flex: 1; }
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
            color: #f3f4f6;
            padding: 22px 18px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            box-shadow: 2px 0 14px rgba(0,0,0,0.16);
            transition: width 0.25s ease, padding 0.25s ease;
        }
        body.theme-dark .sidebar {
            background: linear-gradient(180deg, #0f172a 0%, #0b1220 100%);
            color: #e5e7eb;
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
            color: #f3f4f6;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.06);
            display: inline-block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .nav-links a:hover { background: rgba(16,185,129,0.18); color: #ecfdf3; }
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
            box-shadow: var(--shadow-strong);
            padding: 22px 24px;
        }
        body.theme-dark .card { box-shadow: var(--shadow-strong); }
        .card h1 { margin: 0 0 8px 0; font-size: 24px; }
        .card p { margin: 0; color: var(--muted-color); }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
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
                <button type="submit" class="theme-btn">Tema: <?php echo themeLabel($theme); ?></button>
            </form>
            <div class="nav-links">
                <div class="nav-section">
                    <div class="nav-title">Visão</div>
                    <button type="button" class="nav-parent" data-target="group-dash">
                        <span><span class="menu-icon">📊</span>Dashboards</span>
                        <span class="chevron">▶</span>
                    </button>
                    <div class="nav-children" id="group-dash">
                        <a href="dashboard.php"><span class="menu-icon">📈</span>Dashboard anual</a>
                        <a href="dashboard_mensal.php"><span class="menu-icon">🗓️</span>Dashboard mensal</a>
                    </div>
                </div>
                <div class="nav-section">
                    <div class="nav-title">Despesas</div>
                    <button type="button" class="nav-parent" data-target="group-desp">
                        <span><span class="menu-icon">💸</span>Lançamentos</span>
                        <span class="chevron">▶</span>
                    </button>
                    <div class="nav-children" id="group-desp">
                        <a href="despesas.php"><span class="menu-icon">📃</span>Lista de despesas</a>
                        <a href="despesa_form.php"><span class="menu-icon">➕</span>Nova despesa</a>
                    </div>
                </div>
                <div class="nav-section">
                    <div class="nav-title">Cadastros</div>
                    <button type="button" class="nav-parent" data-target="group-cad">
                        <span><span class="menu-icon">⚙️</span>Configurações</span>
                        <span class="chevron">▶</span>
                    </button>
                    <div class="nav-children" id="group-cad">
                        <a href="categoria_form.php"><span class="menu-icon">🏷️</span>Categorias</a>
                        <a href="orcamento_form.php"><span class="menu-icon">💰</span>Orçamentos</a>
                        <a href="regra_categoria_form.php"><span class="menu-icon">🧭</span>Regras de categoria</a>
                        <?php if ($isAdmin): ?>
                            <a href="aprovar_usuarios.php"><span class="menu-icon">✅</span>Aprovar cadastros</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <a class="logout-link" href="logout.php">Sair</a>
        </aside>
        <main class="content">
            <div class="topbar">
                <button class="toggle-btn" id="toggleSidebar">Alternar menu</button>
            </div>
            <div class="card">
                <h1>Bem-vindo, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                <p>Esta é a página principal do sistema de despesas.</p>
            </div>
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
