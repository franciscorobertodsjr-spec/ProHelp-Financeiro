<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Confirma papel de admin consultando o banco
$stmt = $pdo->prepare('SELECT is_admin FROM usuarios WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser || (int)$currentUser['is_admin'] !== 1) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId > 0) {
        $update = $pdo->prepare('UPDATE usuarios SET ativo = 1 WHERE id = ?');
        if ($update->execute([$userId])) {
            $message = 'Usuário aprovado com sucesso.';
        } else {
            $error = 'Não foi possível aprovar o usuário.';
        }
    }
}

$pendentes = $pdo->query('SELECT id, username FROM usuarios WHERE ativo = 0 ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Aprovar Cadastros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #1f2937;
            padding: 32px 12px;
        }
        .box {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 10px 26px rgba(0,0,0,0.08);
            padding: 24px;
        }
        .btn-success {
            background: #10b981;
            border-color: #0ea271;
        }
        .btn-success:hover {
            background: #0ea271;
            border-color: #0d9467;
        }
        .btn-link { color: #0d9467; }
        .btn-link:hover { color: #0a7a55; }
    </style>
</head>
<body>
    <div class="box">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold mb-0">Aprovação de Cadastros</h3>
            <a href="principal.php" class="btn btn-link">Voltar</a>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($pendentes)): ?>
            <div class="alert alert-info mb-0">Não há usuários pendentes de aprovação.</div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($pendentes as $p): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars($p['username']) ?></strong>
                            <div class="text-muted small">ID: <?= (int)$p['id'] ?></div>
                        </div>
                        <form method="post" class="mb-0">
                            <input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn btn-success btn-sm">Aprovar</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
