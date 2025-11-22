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
