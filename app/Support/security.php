<?php
declare(strict_types=1);

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
