<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuracion global de la aplicacion
|--------------------------------------------------------------------------
|
| Centraliza entorno, debug y politica de errores sin romper los includes
| existentes. El comportamiento por defecto es seguro para produccion.
|
*/

if (!function_exists('appConfigEnvValue')) {
    function appConfigEnvValue(string $key): ?string
    {
        $value = $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}

if (!function_exists('appConfigLoadExternalSecrets')) {
    function appConfigLoadExternalSecrets(): array
    {
        static $loadedSecrets = null;
        if (is_array($loadedSecrets)) {
            return $loadedSecrets;
        }

        $loadedSecrets = [];
        $candidateFile = null;

        /*
        |----------------------------------------------------------------------
        | Prioridad de credenciales/configuracion sensible
        |----------------------------------------------------------------------
        |
        | 1) Variables de entorno (APP_DSN, APP_DB_USER, APP_DB_PASSWORD)
        | 2) Archivo externo indicado por APP_CONFIG_FILE
        | 3) Fallback local config/runtime_secrets.local.php
        |
        | appConfigValue() mantiene el mismo contrato: primero ENV por clave y,
        | si no existe, consulta el origen externo/local cargado aqui.
        |
        */
        $envConfigFile = appConfigEnvValue('APP_CONFIG_FILE');
        if ($envConfigFile !== null && is_file($envConfigFile) && is_readable($envConfigFile)) {
            $candidateFile = $envConfigFile;
        } elseif ($envConfigFile === null) {
            $localFallbackFile = __DIR__ . '/runtime_secrets.local.php';
            if (is_file($localFallbackFile) && is_readable($localFallbackFile)) {
                $candidateFile = $localFallbackFile;
            }
        }

        if (is_string($candidateFile) && $candidateFile !== '') {
            $secrets = require_once $candidateFile;
            if (is_array($secrets)) {
                $loadedSecrets = array_replace($loadedSecrets, $secrets);
            }
        }

        return $loadedSecrets;
    }
}

if (!function_exists('appConfigValue')) {
    function appConfigValue(string $key, ?string $legacyFallback = null): ?string
    {
        $envValue = appConfigEnvValue($key);
        if ($envValue !== null) {
            return $envValue;
        }

        $externalSecrets = appConfigLoadExternalSecrets();
        if (array_key_exists($key, $externalSecrets)) {
            $value = trim((string)$externalSecrets[$key]);
            return $value === '' ? null : $value;
        }

        return $legacyFallback;
    }
}

if (!function_exists('appConfigBoolValue')) {
    function appConfigBoolValue(string $key, bool $default = false): bool
    {
        $value = appConfigEnvValue($key);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'on', 'yes', 'si'], true);
    }
}

if (!function_exists('appConfigNormalizeEnv')) {
    function appConfigNormalizeEnv(?string $env): string
    {
        $normalized = strtolower(trim((string)$env));
        $aliases = [
            'prod' => 'production',
            'production' => 'production',
            'local' => 'local',
            'dev' => 'development',
            'development' => 'development',
            'stage' => 'staging',
            'staging' => 'staging',
            'test' => 'testing',
            'testing' => 'testing',
        ];

        return $aliases[$normalized] ?? 'production';
    }
}

if (!defined('APP_ENV')) {
    define('APP_ENV', appConfigNormalizeEnv(appConfigEnvValue('APP_ENV') ?? 'production'));
}

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', appConfigBoolValue('APP_DEBUG', false));
}

if (!function_exists('appConfigurePhpRuntime')) {
    function appConfigurePhpRuntime(): void
    {
        ini_set('display_errors', APP_DEBUG ? '1' : '0');
        ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        $defaultLogPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'php_debug.log';
        $logPath = appConfigEnvValue('APP_ERROR_LOG') ?? $defaultLogPath;
        $logDirectory = dirname($logPath);

        if (is_dir($logDirectory) || @mkdir($logDirectory, 0775, true)) {
            ini_set('error_log', $logPath);
        }
    }
}

if (!function_exists('appDebugAccessAllowed')) {
    function appDebugAccessAllowed(bool $requireAdmin = true): bool
    {
        if (APP_DEBUG !== true) {
            return false;
        }

        if (PHP_SAPI === 'cli') {
            return true;
        }

        if (!$requireAdmin) {
            return true;
        }

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        return isset($_SESSION['email'], $_SESSION['rol']) && $_SESSION['rol'] === 'admin';
    }
}

appConfigurePhpRuntime();
