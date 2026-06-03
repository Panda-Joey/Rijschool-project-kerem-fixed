<?php

function getDbConnection(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    $dbName = 'Eend';
    $username = 'root';
    $password = 'password';

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $isDocker = file_exists('/.dockerenv') || getenv('DB_HOST') === 'mysql';
    $dbHostEnv = getenv('DB_HOST') ?: '';

    $hostsToTry = [];
    if ($dbHostEnv !== '') {
        $hostsToTry[] = $dbHostEnv;
    }
    if ($isDocker) {
        $hostsToTry[] = 'mysql';
    }
    $hostsToTry[] = '127.0.0.1';
    $hostsToTry[] = 'localhost';

    $hostsToTry = array_values(array_unique($hostsToTry));
    $lastConnectError = null;

    foreach ($hostsToTry as $host) {
        try {
            $tryConn = mysqli_init();
            $tryConn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
            $tryConn->real_connect($host, $username, $password, $dbName);
            $conn = $tryConn;
            return $conn;
        } catch (mysqli_sql_exception $e) {
            $lastConnectError = $e->getMessage();
        }
    }

    http_response_code(500);
    die(
        'Database connectie mislukt. Start MySQL (Docker: docker compose up -d mysql, of XAMPP MySQL) '
        . 'en controleer of database "Eend" bestaat.<br><br>'
        . htmlspecialchars((string) $lastConnectError)
    );
}
