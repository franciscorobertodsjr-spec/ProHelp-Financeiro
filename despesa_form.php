<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'theme.php';

$theme = handleTheme($pdo);

$categoriasLista = $pdo->query('SELECT nome FROM categorias ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descricao = trim($_POST['descricao'] ?? '');
    $data_vencimento = $_POST['data_vencimento'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $data_pagamento = $_POST['data_pagamento'] ?? null;
    $juros = $_POST['juros'] ?? 0;
    $total_pago = $_POST['total_pago'] ?? null;
    $status = $_POST['status'] ?? 'Pendente';
    $recorrente = !empty($_POST['recorrente']) ? 1 : 0;
    $parcelado = !empty($_POST['parcelado']) ? 1 : 0;
    $numero_parcela = $_POST['numero_parcela'] !== '' ? $_POST['numero_parcela'] : null;
    $total_parcelas = $_POST['total_parcelas'] !== '' ? $_POST['total_parcelas'] : null;
    $grupo_parcelas = trim($_POST['grupo_parcelas'] ?? '') ?: null;
    $categoriaSelect = $_POST['categoria_select'] ?? '';
    $categoriaCustom = trim($_POST['categoria_custom'] ?? '');
    $categoria = $categoriaSelect === 'custom' ? ($categoriaCustom ?: null) : ($categoriaSelect ?: null);
    $forma_pagamento = trim($_POST['forma_pagamento'] ?? '') ?: null;
    $observacao = trim($_POST['observacao'] ?? '') ?: null;
    $local = trim($_POST['local'] ?? '') ?: null;

    if (!$descricao || !$data_vencimento || $valor === '') {
        $error = 'Preencha pelo menos descrição, data de vencimento e valor.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO despesas 
                (descricao, data_vencimento, valor, data_pagamento, juros, total_pago, status, recorrente, parcelado, numero_parcela, total_parcelas, grupo_parcelas, categoria, forma_pagamento, observacao, local)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $descricao,
                $data_vencimento,
                $valor,
                $data_pagamento ?: null,
                $juros ?: 0,
                $total_pago !== '' ? $total_pago : null,
                $status,
                $recorrente,
                $parcelado,
                $numero_parcela,
                $total_parcelas,
                $grupo_parcelas,
                $categoria,
                $forma_pagamento,
                $observacao,
                $local
            ]);
            $success = 'Despesa cadastrada com sucesso.';
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
    <title>Nova Despesa</title>
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
            max-width: 900px;
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
            <h3 class="fw-bold mb-0">Cadastrar Despesa</h3>
            <a href="principal.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="descricao">Descrição</label>
                    <input type="text" class="form-control" id="descricao" name="descricao" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="data_vencimento">Data vencimento</label>
                    <input type="date" class="form-control" id="data_vencimento" name="data_vencimento" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="valor">Valor</label>
                    <input type="number" step="0.01" class="form-control" id="valor" name="valor" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="data_pagamento">Data pagamento</label>
                    <input type="date" class="form-control" id="data_pagamento" name="data_pagamento">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="juros">Juros</label>
                    <input type="number" step="0.01" class="form-control" id="juros" name="juros" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="total_pago">Total pago</label>
                    <input type="number" step="0.01" class="form-control" id="total_pago" name="total_pago">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="Pendente">Pendente</option>
                        <option value="Pago">Pago</option>
                        <option value="Previsto">Previsto</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Recorrente</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="recorrente" name="recorrente" value="1">
                        <label class="form-check-label" for="recorrente">Sim</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Parcelado</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="parcelado" name="parcelado" value="1">
                        <label class="form-check-label" for="parcelado">Sim</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="numero_parcela">Nº parcela</label>
                    <input type="number" class="form-control" id="numero_parcela" name="numero_parcela">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="total_parcelas">Total parcelas</label>
                    <input type="number" class="form-control" id="total_parcelas" name="total_parcelas">
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="grupo_parcelas">Grupo parcelas</label>
                    <input type="text" class="form-control" id="grupo_parcelas" name="grupo_parcelas">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="categoria_select">Categoria</label>
                    <select class="form-select" id="categoria_select" name="categoria_select">
                        <option value="">Selecione</option>
                        <?php foreach ($categoriasLista as $catNome): ?>
                            <option value="<?= htmlspecialchars($catNome) ?>"><?= htmlspecialchars($catNome) ?></option>
                        <?php endforeach; ?>
                        <option value="custom">Outra...</option>
                    </select>
                    <input type="text" class="form-control mt-2 d-none" id="categoria_custom" name="categoria_custom" placeholder="Digite a categoria">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="forma_pagamento">Forma de pagamento</label>
                    <input type="text" class="form-control" id="forma_pagamento" name="forma_pagamento">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="local">Local</label>
                    <input type="text" class="form-control" id="local" name="local">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="observacao">Observação</label>
                    <textarea class="form-control" id="observacao" name="observacao" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Salvar despesa</button>
            </div>
        </form>
    </div>
    <script>
        const selectCat = document.getElementById('categoria_select');
        const inputCustom = document.getElementById('categoria_custom');
        selectCat.addEventListener('change', () => {
            if (selectCat.value === 'custom') {
                inputCustom.classList.remove('d-none');
            } else {
                inputCustom.classList.add('d-none');
                inputCustom.value = '';
            }
        });
    </script>
</body>
</html>
