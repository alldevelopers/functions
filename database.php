<?php
/**
 * database.php
 * Responsável pela conexão com o banco de dados e inclui config.php
 */

require_once 'config.php'; // Inclui o arquivo de configuração

try {
    // Exemplo de conexão PDO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}