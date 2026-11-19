<?php
declare(strict_types=1);

$host = getenv('INTERCLASS_DB_HOST') ?: '127.0.0.1';
$port = getenv('INTERCLASS_DB_PORT') ?: '3306';
$database = getenv('INTERCLASS_DB_NAME') ?: 'interclass';
$username = getenv('INTERCLASS_DB_USER') ?: 'root';
$password = getenv('INTERCLASS_DB_PASSWORD') !== false
    ? getenv('INTERCLASS_DB_PASSWORD')
    : '';

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $host,
    $port,
    $database
);

$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

/*

<?php
declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=interclass;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

*/