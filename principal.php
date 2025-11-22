<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$isAdmin = !empty($_SESSION['is_admin']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Página Principal</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f6f9;
            display: flex;
            min-height: 100vh;
            color: #1f2937;
        }
        .layout {
            display: flex;
            flex: 1;
        }
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
        .sidebar.collapsed {
            width: 74px;
            padding: 22px 12px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 20px;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .brand .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #10b981;
            opacity: 0.95;
        }
        .sidebar.collapsed .brand-text { display: none; }
        .user-box {
            background: rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 12px 14px;
            line-height: 1.5;
            transition: opacity 0.2s ease;
            word-break: break-word;
        }
        .sidebar.collapsed .user-box {
            opacity: 0;
            pointer-events: none;
            height: 0;
            padding: 0;
            margin: 0;
        }
        .user-box .label { opacity: 0.8; font-size: 13px; }
        .user-box .value { font-weight: 700; }
        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
        }
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
        .nav-links a:hover {
            background: rgba(16,185,129,0.18);
            color: #ecfdf3;
        }
        .sidebar.collapsed .nav-links a, .sidebar.collapsed .nav-placeholder {
            text-align: center;
            padding: 10px 0;
        }
        .sidebar.collapsed .nav-placeholder {
            display: none;
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
        .logout-link:hover {
            background: rgba(16,185,129,0.18);
            color: #ecfdf3;
        }
        .content {
            flex: 1;
            padding: 28px 34px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
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
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.08);
            padding: 22px 24px;
        }
        .card h1 {
            margin: 0 0 8px 0;
            font-size: 24px;
        }
        .card p { margin: 0; color: #4b5563; }
    </style>
</head>
<body>
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
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="despesas.php">Despesas</a>
            <a href="despesa_form.php">Nova despesa</a>
            <a href="categoria_form.php">Categorias</a>
                <a href="orcamento_form.php">Novo orçamento</a>
                <a href="regra_categoria_form.php">Nova regra</a>
                <?php if ($isAdmin): ?>
                    <a href="aprovar_usuarios.php">Aprovar cadastros</a>
                <?php endif; ?>
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
    </script>
</body>
</html>
