<?php
/**
 * Configurações do Banco de Dados
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

// Configurações do MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'opapopol_07082025');
define('DB_USER', 'opapopol_07082025');
define('DB_PASS', 'Kq8v@2rB7#LfE9pX');
define('DB_CHARSET', 'utf8mb4');

// Opções do PDO
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
    PDO::ATTR_PERSISTENT         => true
]);

// DSN (Data Source Name)
define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET);