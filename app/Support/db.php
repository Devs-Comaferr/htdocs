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
            throw new Exception('Error de conexión a BD: ' . print_r($config, true));
        }

        $dsnOriginal = (string)$config['dsn'];
        $dsnDriver17 = preg_replace(
            '/Driver=\{ODBC Driver\s+(17|18)\s+for SQL Server\};/i',
            'Driver={ODBC Driver 17 for SQL Server};',
            $dsnOriginal,
            1,
            $replacements17
        );
        if (!is_string($dsnDriver17) || $dsnDriver17 === '') {
            $dsnDriver17 = $dsnOriginal;
        }
        if ((int)$replacements17 === 0 && stripos($dsnDriver17, 'Driver={ODBC Driver') === false) {
            $dsnDriver17 = 'Driver={ODBC Driver 17 for SQL Server};' . ltrim($dsnDriver17);
        }

        $attempts = [
            ['driver' => '17', 'dsn' => $dsnDriver17],
        ];

        $dsnDriver18 = preg_replace(
            '/Driver=\{ODBC Driver\s+17\s+for SQL Server\};/i',
            'Driver={ODBC Driver 18 for SQL Server};',
            $dsnDriver17,
            1,
            $replacements18
        );
        if (is_string($dsnDriver18) && $dsnDriver18 !== '' && $dsnDriver18 !== $dsnDriver17 && (int)$replacements18 > 0) {
            $attempts[] = ['driver' => '18', 'dsn' => $dsnDriver18];
        }

        $lastError = '';
        foreach ($attempts as $attempt) {
            $connection = @odbc_connect($attempt['dsn'], $config['username'], $config['password']);
            $lastError = (string)odbc_errormsg();
            dbLogOdbcAttempt($attempt['dsn'], $attempt['driver'], $connection ? 'OK' : 'ERROR', $lastError);
            if ($connection) {
                return $connection;
            }
        }

        error_log('ODBC connection error: ' . $lastError);
        echo '<pre>';
        echo 'ERROR ODBC:' . PHP_EOL;
        echo odbc_errormsg();
        echo '</pre>';
        exit;
        throw new Exception('Error de conexión a BD: ' . $lastError);
    }
}

if (!function_exists('dbLogOdbcAttempt')) {
    function dbLogOdbcAttempt(string $dsn, string $driver, string $result, string $errorMessage = ''): void
    {
        $logPath = BASE_PATH . '/storage/logs/php_debug.log';
        $safeDsn = preg_replace('/(Pwd|Password)\s*=\s*[^;]*/i', '$1=***', $dsn);
        if (!is_string($safeDsn) || $safeDsn === '') {
            $safeDsn = $dsn;
        }

        $line = sprintf(
            "[%s] ODBC driver=%s result=%s dsn=%s%s%s",
            date('Y-m-d H:i:s'),
            $driver,
            $result,
            $safeDsn,
            $errorMessage !== '' ? ' error=' : '',
            $errorMessage !== '' ? $errorMessage : ''
        );

        @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND);
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
