<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'theme.php';

$theme = handleTheme($pdo);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    if (!$nome) {
        $error = 'Informe o nome da categoria.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO categorias (nome) VALUES (?)');
            $stmt->execute([$nome]);
            $success = 'Categoria cadastrada com sucesso.';
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) {
                $error = 'Categoria já existe.';
            } else {
                $error = 'Erro ao salvar: ' . $e->getMessage();
            }
        }
    }
}

$categorias = $pdo->query('SELECT id, nome FROM categorias ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Categorias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <style>
        body {
            background: var(--page-bg);
            min-height: 100vh;
            padding: 32px 12px;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: var(--text-color);
        }
        .box {
            max-width: 720px;
            margin: 0 auto;
            background: var(--surface-color);
            border-radius: 14px;
            box-shadow: var(--shadow-strong);
            padding: 24px;
        }
        .form-label { font-weight: 600; color: var(--text-color); }
        @media (max-width: 576px) {
            body { padding: 16px 8px; }
            .box { padding: 18px; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="box">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h3 class="fw-bold mb-0">Categorias</h3>
            <a href="principal.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" class="mb-3" autocomplete="off">
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="nome">Nome</label>
                    <input type="text" class="form-control" id="nome" name="nome" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">Adicionar</button>
                </div>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>#</th><th>Nome</th></tr></thead>
                <tbody>
                    <?php if (!$categorias): ?>
                        <tr><td colspan="2" class="text-muted text-center">Nenhuma categoria cadastrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categorias as $c): ?>
                            <tr>
                                <td><?= (int)$c['id'] ?></td>
                                <td><?= htmlspecialchars($c['nome']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
