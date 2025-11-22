<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

$success = '';
$error = '';

$categoriasLista = $pdo->query('SELECT nome FROM categorias ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoria = trim($_POST['categoria'] ?? '');
    $mes = $_POST['mes'] ?? '';
    $limite = $_POST['limite'] ?? '';

    if (!$categoria || !$mes || $limite === '') {
        $error = 'Preencha categoria, mês e limite.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO orcamentos (categoria, mes, limite) VALUES (?, ?, ?)');
            $stmt->execute([$categoria, $mes, $limite]);
            $success = 'Orçamento cadastrado com sucesso.';
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) {
                $error = 'Já existe orçamento para esta categoria e mês.';
            } else {
                $error = 'Erro ao salvar: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Novo Orçamento</title>
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
        .form-control:focus, .form-select:focus, .btn:focus { box-shadow: 0 0 0 0.2rem rgba(16,185,129,0.25); }
        a { color: #0d9467; }
        a:hover { color: #0a7a55; }
        @media (max-width: 576px) {
            body { padding: 16px 8px; }
            .box { padding: 18px; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0">Cadastrar Orçamento</h3>
                <div class="text-muted small">Defina um limite mensal por categoria.</div>
            </div>
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
                <label class="form-label" for="categoria">Categoria</label>
                <input list="categorias" type="text" class="form-control" id="categoria" name="categoria" required value="<?= htmlspecialchars($categoria ?? '') ?>">
                <datalist id="categorias">
                    <?php foreach ($categoriasLista as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <div class="form-text">Escolha uma já cadastrada ou digite uma nova.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="mes">Mês</label>
                <?php $mesPadrao = $mes ?: date('Y-m'); ?>
                <input type="month" class="form-control" id="mes" name="mes" required value="<?= htmlspecialchars($mesPadrao) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="limite">Limite</label>
                <input type="number" step="0.01" min="0" class="form-control" id="limite" name="limite" required value="<?= htmlspecialchars($limite ?? '') ?>">
                <div class="form-text">Use ponto para centavos (ex: 1200.50).</div>
            </div>
            <button type="submit" class="btn btn-primary">Salvar orçamento</button>
        </form>
    </div>
</body>
</html>
