<?php

require_once BASE_PATH . '/config/app_config.php';

if (!function_exists('getDbConnectionConfig')) {
    function getDbConnectionConfig(): array
    {
        $host = appConfigValue('DB_HOST');
        $database = appConfigValue('DB_NAME');
        $dsn = appConfigValue('DB_DSN', appConfigValue('APP_DSN'));

        if ($dsn === null && $host !== null && $database !== null) {
            $dsn = sprintf(
                'Driver={ODBC Driver 17 for SQL Server};Server=%s;Database=%s;',
                $host,
                $database
            );
        }

        return [
            'host' => $host,
            'database' => $database,
            'dsn' => $dsn,
            'username' => appConfigValue('DB_USER', appConfigValue('APP_DB_USER')),
            'password' => appConfigValue('DB_PASS', appConfigValue('APP_DB_PASSWORD')),
        ];
    }
}

if (!function_exists('openOdbcConnection')) {
    function openOdbcConnection()
    {
        $config = getDbConnectionConfig();

        if (
            empty($config['dsn'])
            || empty($config['username'])
            || empty($config['password'])
        ) {
            error_log('ODBC connection config incomplete. Define DB_HOST/DB_NAME/DB_USER/DB_PASS o DB_DSN/DB_USER/DB_PASS en entorno o config local.');
            throw new Exception('Error de conexión a BD');
        }

        $connection = @odbc_connect($config['dsn'], $config['username'], $config['password']);
        if (!$connection) {
            error_log('ODBC connection error: ' . odbc_errormsg());
            throw new Exception('Error de conexión a BD');
        }

        return $connection;
    }
}

if (!function_exists('getOdbcConnection')) {
    function getOdbcConnection()
    {
        static $connection = null;

        if ($connection === null) {
            $connection = openOdbcConnection();
        }

        return $connection;
    }
}

if (!function_exists('db')) {
    function db()
    {
        static $connection = null;

        if ($connection === null) {
            $connection = getOdbcConnection();
        }

        return $connection;
    }
}

function db_query($conn, $sql, $params = [])
{
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Error en prepare");
    }

    $ok = odbc_execute($stmt, $params);
    if (!$ok) {
        throw new Exception("Error en execute");
    }

    $result = [];
    while ($row = odbc_fetch_array($stmt)) {
        $result[] = $row;
    }

    return $result;
}

function db_execute($conn, $sql, $params = [])
{
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Error en prepare");
    }

    $ok = odbc_execute($stmt, $params);
    if (!$ok) {
        throw new Exception("Error en execute");
    }

    return true;
}
