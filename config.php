<?php
// Configuração do banco de dados
$host = 'localhost';
$db = 'proh5249_despesas';
$user = 'proh5249_despesas';
$pass = 'grR3CTMw8w89fdacwxTM';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erro na conexão: ' . $e->getMessage());
}
