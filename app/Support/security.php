<?php
declare(strict_types=1);

if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (
            !isset($_SESSION['_csrf_token'])
            || !is_string($_SESSION['_csrf_token'])
            || $_SESSION['_csrf_token'] === ''
        ) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrfInput')) {
    function csrfInput(): string
    {
        return '<input type="hidden" name="_csrf_token" value="'
            . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
            . '">';
    }
}

if (!function_exists('appLogTechnicalError')) {
    function appLogTechnicalError(string $contexto, ?string $detalle = null): void
    {
        $mensaje = '[app] ' . trim($contexto);
        if ($detalle !== null && $detalle !== '') {
            $mensaje .= ' | ' . $detalle;
        }

        error_log($mensaje);
    }
}

if (!function_exists('appExitTextError')) {
    function appExitTextError(string $mensajeUsuario, int $statusCode = 400, ?string $contexto = null, ?string $detalle = null): void
    {
        if ($contexto !== null) {
            appLogTechnicalError($contexto, $detalle);
        }

        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $mensajeUsuario;
        exit;
    }
}

if (!function_exists('appExitJsonError')) {
    function appExitJsonError(string $mensajeUsuario, int $statusCode = 400, ?string $contexto = null, ?string $detalle = null): void
    {
        if ($contexto !== null) {
            appLogTechnicalError($contexto, $detalle);
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(
            ['ok' => false, 'message' => $mensajeUsuario],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
}

if (!function_exists('csrfValidateRequest')) {
    function csrfValidateRequest(?string $contexto = null): void
    {
        $sessionToken = csrfToken();
        $requestToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!is_string($requestToken) || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
            $contextoFinal = $contexto ?? 'csrf.invalid';

            if (function_exists('appExitJsonError')) {
                $isAjax = (
                    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
                    || (isset($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
                );

                if ($isAjax) {
                    appExitJsonError('La sesión de seguridad no es válida. Recarga la página.', 403, $contextoFinal);
                }

                appExitTextError('La sesión de seguridad no es válida. Recarga la página.', 403, $contextoFinal);
            }

            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'La sesión de seguridad no es válida. Recarga la página.';
            exit;
        }
    }
}
