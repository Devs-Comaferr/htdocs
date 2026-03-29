<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (!function_exists('registrarLog')) {
    function registrarLog(string $accion, string $detalle = ''): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $email = $_SESSION['email'] ?? 'sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';

        $conn = db();

        $sql = "INSERT INTO cmf_logs_comerciales 
                (email_usuario, accion, detalle, ip_usuario)
                VALUES (?, ?, ?, ?)";

        $stmt = odbc_prepare($conn, $sql);
        odbc_execute($stmt, [$email, $accion, $detalle, $ip]);
    }
}
