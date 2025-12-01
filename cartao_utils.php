<?php
declare(strict_types=1);

function ensureCartaoSchema(PDO $pdo): void
{
    // Cria tabelas se ainda não existirem (idempotente)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cartoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            banco VARCHAR(255) NULL,
            final_cartao VARCHAR(8) NULL,
            dia_fechamento TINYINT UNSIGNED NULL,
            dia_vencimento TINYINT UNSIGNED NULL,
            limite DECIMAL(12,2) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cartoes_nome_final (nome, final_cartao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS faturas_cartao (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cartao_id INT NOT NULL,
            competencia CHAR(7) NOT NULL,
            valor_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            data_vencimento DATE NULL,
            status ENUM('previsto','fechado','lancado') NOT NULL DEFAULT 'previsto',
            despesa_id INT NULL,
            observacao TEXT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_faturas_cartao_comp (cartao_id, competencia),
            KEY idx_faturas_cartao_cartao (cartao_id),
            CONSTRAINT fk_faturas_cartao_cartao FOREIGN KEY (cartao_id) REFERENCES cartoes(id) ON DELETE CASCADE,
            CONSTRAINT fk_faturas_cartao_despesa FOREIGN KEY (despesa_id) REFERENCES despesas(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS fatura_itens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fatura_id INT NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            categoria VARCHAR(255) NULL,
            valor DECIMAL(12,2) NOT NULL,
            data_compra DATE NULL,
            forma_pagamento VARCHAR(255) NULL,
            observacao TEXT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_fatura_itens_fatura (fatura_id),
            CONSTRAINT fk_fatura_itens_fatura FOREIGN KEY (fatura_id) REFERENCES faturas_cartao(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    );
}

function fetchCartoes(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM cartoes ORDER BY nome');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchCartaoById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM cartoes WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function fetchFaturaWithCartao(PDO $pdo, int $faturaId): ?array
{
    $sql = 'SELECT f.*, c.nome AS cartao_nome, c.banco, c.final_cartao 
            FROM faturas_cartao f 
            INNER JOIN cartoes c ON c.id = f.cartao_id 
            WHERE f.id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$faturaId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
