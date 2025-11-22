<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $palavra = trim($_POST['palavra'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');

    if (!$palavra || !$categoria) {
        $error = 'Preencha palavra e categoria.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO regras_categoria (palavra, categoria) VALUES (?, ?)');
            $stmt->execute([$palavra, $categoria]);
            $success = 'Regra cadastrada com sucesso.';
        } catch (PDOException $e) {
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Nova Regra de Categoria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6f9;
            min-height: 100vh;
            padding: 32px 12px;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #1f2937;
        }
        .box {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 10px 26px rgba(0,0,0,0.08);
            padding: 24px;
        }
        .btn-primary {
            background: #10b981;
            border-color: #0ea271;
        }
        .btn-primary:hover {
            background: #0ea271;
            border-color: #0d9467;
        }
        .form-label { font-weight: 600; color: #111827; }
        .form-control:focus, .btn:focus { box-shadow: 0 0 0 0.2rem rgba(16,185,129,0.25); }
        a { color: #0d9467; }
        a:hover { color: #0a7a55; }
    </style>
</head>
<body>
    <div class="box">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold mb-0">Cadastrar Regra de Categoria</h3>
            <a href="principal.php" class="btn btn-link">Voltar</a>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label class="form-label" for="palavra">Palavra</label>
                <input type="text" class="form-control" id="palavra" name="palavra" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="categoria">Categoria</label>
                <input type="text" class="form-control" id="categoria" name="categoria" required>
            </div>
            <button type="submit" class="btn btn-primary">Salvar regra</button>
        </form>
    </div>
</body>
</html>
