<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

if (!function_exists('getDbConnectionConfig')) {
    function getDbConnectionConfig(): array
    {
        return [
            // Variables esperadas:
            // - APP_DSN
            // - APP_DB_USER
            // - APP_DB_PASSWORD
            // Fuente preferente: entorno o config externa ignorada por git.
            'dsn' => appConfigValue('APP_DSN'),
            'username' => appConfigValue('APP_DB_USER'),
            'password' => appConfigValue('APP_DB_PASSWORD'),
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
            error_log('ODBC connection config incomplete. Define APP_DSN, APP_DB_USER y APP_DB_PASSWORD en entorno o config externa.');
            die('Error de configuracion de base de datos.');
        }

        $connection = @odbc_connect($config['dsn'], $config['username'], $config['password']);
        if (!$connection) {
            error_log('ODBC connection error: ' . odbc_errormsg());
            die('Error de conexion con la base de datos.');
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
