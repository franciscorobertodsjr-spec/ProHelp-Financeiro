<?php
declare(strict_types=1);

function ensureTheme(PDO $pdo = null): int
{
    if (!isset($_SESSION['theme'])) {
        if (isset($_SESSION['user_id']) && $pdo) {
            $stmt = $pdo->prepare('SELECT tema FROM usuarios WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $_SESSION['theme'] = (int)$row['tema'];
            }
        }

        if (!isset($_SESSION['theme'])) {
            $_SESSION['theme'] = 0;
        }
    }

    return (int)$_SESSION['theme'];
}

function toggleTheme(PDO $pdo = null): int
{
    $theme = ensureTheme($pdo) === 1 ? 0 : 1;
    $_SESSION['theme'] = $theme;

    if (isset($_SESSION['user_id']) && $pdo) {
        $upd = $pdo->prepare('UPDATE usuarios SET tema = ? WHERE id = ?');
        $upd->execute([$theme, $_SESSION['user_id']]);
    }

    return $theme;
}

function handleTheme(PDO $pdo = null): int
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_theme'])) {
        $theme = toggleTheme($pdo);
        $redirect = $_POST['redirect_to'] ?? ($_SERVER['REQUEST_URI'] ?? '');
        if ($redirect) {
            header('Location: ' . $redirect);
            exit;
        }
        return $theme;
    }

    return ensureTheme($pdo);
}

function themeClass(int $theme): string
{
    return $theme === 1 ? 'theme-dark' : 'theme-light';
}

function themeLabel(int $theme): string
{
    return $theme === 1 ? 'Escuro' : 'Claro';
}

function renderSidebar(string $active = ''): void
{
    $username = $_SESSION['username'] ?? 'Usuário';
    $initial = strtoupper(substr($username, 0, 1));
    $items = [
        'principal' => ['href' => 'dashboard.php', 'label' => 'Dashboard anual'],
        'dashboard_mensal' => ['href' => 'dashboard_mensal.php', 'label' => 'Dashboard mensal'],
        'despesas' => ['href' => 'despesas.php', 'label' => 'Despesas'],
        'despesa_form' => ['href' => 'despesa_form.php', 'label' => 'Nova despesa'],
        'orcamento' => ['href' => 'orcamento_form.php', 'label' => 'Orçamentos'],
        'categorias' => ['href' => 'categoria_form.php', 'label' => 'Categorias'],
        'regras' => ['href' => 'regra_categoria_form.php', 'label' => 'Regras de categoria'],
        'aprovar' => ['href' => 'aprovar_usuarios.php', 'label' => 'Aprovar usuários'],
    ];
    ?>
    <aside class="sidebar">
        <div class="brand">
            <span class="dot"></span>
            <span>ProHelp Financeiro</span>
        </div>
        <div class="profile">
            <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
            <h2><?php echo htmlspecialchars($username); ?></h2>
            <p>Controle financeiro</p>
        </div>
        <nav class="menu">
            <?php foreach ($items as $key => $item): ?>
                <a href="<?php echo htmlspecialchars($item['href']); ?>" class="<?php echo $key === $active ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-actions">
            <form method="post" class="d-grid gap-1">
                <input type="hidden" name="toggle_theme" value="1">
                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'principal.php'); ?>">
                <button type="submit" class="btn-ghost">Tema: <?php echo themeLabel((int)($_SESSION['theme'] ?? 0)); ?></button>
            </form>
            <a class="btn-ghost" href="documentacao.php" target="_blank" rel="noopener noreferrer">Ajuda</a>
            <a class="btn-ghost" href="logout.php">Sair</a>
        </div>
    </aside>
    <?php
}
