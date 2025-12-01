<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'theme.php';
require_once 'cartao_utils.php';

ensureCartaoSchema($pdo);

$theme = handleTheme($pdo);
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_cartao'])) {
    $nome = trim($_POST['nome'] ?? '');
    $banco = trim($_POST['banco'] ?? '') ?: null;
    $final_cartao = trim($_POST['final_cartao'] ?? '') ?: null;
    $dia_fechamento = $_POST['dia_fechamento'] !== '' ? (int)$_POST['dia_fechamento'] : null;
    $dia_vencimento = $_POST['dia_vencimento'] !== '' ? (int)$_POST['dia_vencimento'] : null;
    $limite = $_POST['limite'] !== '' ? $_POST['limite'] : null;

    if ($nome === '') {
        $errorMsg = 'Informe o nome do cartão.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO cartoes (nome, banco, final_cartao, dia_fechamento, dia_vencimento, limite) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$nome, $banco, $final_cartao, $dia_fechamento, $dia_vencimento, $limite]);
            $successMsg = 'Cartão cadastrado com sucesso.';
        } catch (PDOException $e) {
            $errorMsg = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

$cartoesStmt = $pdo->query(
    'SELECT c.*, COUNT(f.id) AS total_faturas, SUM(f.valor_total) AS soma_faturas
     FROM cartoes c
     LEFT JOIN faturas_cartao f ON f.cartao_id = c.id
     GROUP BY c.id
     ORDER BY c.nome'
);
$cartoes = $cartoesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cartões</title>
    <link href="bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <style>
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
        .content { flex: 1; padding: 28px 32px 34px; display: flex; flex-direction: column; gap: 18px; }
        .page-header { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; flex-wrap: wrap; }
        .page-title h1 { margin: 0; font-size: 28px; font-weight: 800; }
        .eyebrow { text-transform: uppercase; letter-spacing: 0.6px; font-size: 12px; color: var(--muted-color, #4b5563); margin: 0; }
        .panel { background: var(--surface-color, #f9fbfd); border: 1px solid var(--border-color, #d9e1eb); border-radius: 16px; padding: 16px; box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06)); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px; flex-wrap: wrap; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px 14px;
        }
        .button {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            cursor: pointer;
        }
        .button-primary { background: linear-gradient(135deg, #10b981, #0ea5e9); color: #fff; }
        .button-outline { background: var(--surface-soft, #f1f4f8); color: var(--text-color, #1f2937); border: 1px solid var(--border-color, #d9e1eb); }
        .pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 12px; background: var(--surface-soft, #f1f4f8); font-weight: 600; gap: 6px; }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
        .summary { font-size: 13px; color: var(--muted-color, #4b5563); }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="layout">
        <?php renderSidebar('cartoes'); ?>
        <main class="content">
            <div class="page-header">
                <div class="page-title">
                    <p class="eyebrow">Cartões</p>
                    <h1>Cadastro de cartões</h1>
                    <span class="text-muted">Controle as faturas e itens de cada cartão</span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="faturas_cartao.php" class="button button-outline text-decoration-none">Faturas</a>
                    <a href="despesas.php" class="button button-outline text-decoration-none">Despesas</a>
                </div>
            </div>

            <?php if ($successMsg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Novo cartão</h3>
                    <span class="pill">Pré-lançamentos de fatura começam aqui</span>
                </div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="criar_cartao" value="1">
                    <div>
                        <label class="form-label">Nome*</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Banco/Operadora</label>
                        <input type="text" name="banco" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Final do cartão</label>
                        <input type="text" name="final_cartao" class="form-control" maxlength="8">
                    </div>
                    <div>
                        <label class="form-label">Dia do fechamento</label>
                        <input type="number" name="dia_fechamento" class="form-control" min="1" max="31">
                    </div>
                    <div>
                        <label class="form-label">Dia do vencimento</label>
                        <input type="number" name="dia_vencimento" class="form-control" min="1" max="31">
                    </div>
                    <div>
                        <label class="form-label">Limite (opcional)</label>
                        <input type="number" step="0.01" name="limite" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="button button-primary w-100">Salvar cartão</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">Cartões cadastrados</h3>
                    <span class="pill"><?= count($cartoes) ?> cartão(ões)</span>
                </div>
                <?php if (!$cartoes): ?>
                    <p class="text-muted">Nenhum cartão cadastrado.</p>
                <?php else: ?>
                    <div class="card-grid">
                        <?php foreach ($cartoes as $c): ?>
                            <div class="p-3 border rounded-3" style="background: var(--surface-soft, #f1f4f8);">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= htmlspecialchars($c['nome']) ?></strong>
                                        <?php if ($c['final_cartao']): ?>
                                            <span class="text-muted"> • Final <?= htmlspecialchars($c['final_cartao']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($c['banco']): ?>
                                            <div class="summary"><?= htmlspecialchars($c['banco']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="faturas_cartao.php?cartao_id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary">Faturas</a>
                                </div>
                                <div class="summary mt-2">
                                    Fechamento <?= $c['dia_fechamento'] ?: '-' ?> • Vencimento <?= $c['dia_vencimento'] ?: '-' ?>
                                </div>
                                <div class="summary">
                                    Faturas: <?= (int)$c['total_faturas'] ?> • Soma: R$ <?= number_format((float)$c['soma_faturas'], 2, ',', '.') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
