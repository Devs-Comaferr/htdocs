<?php

require_once BASE_PATH . '/app/Support/db.php';

function puedeEliminarVisita(int $id_visita, int $cod_vendedor): bool
{
    $conn = db();

    $sqlVisita = "
        SELECT id_visita, cod_vendedor
        FROM [integral].[dbo].[cmf_visitas_comerciales]
        WHERE id_visita = ?
    ";

    $visitas = db_query($conn, $sqlVisita, [$id_visita]);
    if (empty($visitas)) {
        return false;
    }

    $visita = $visitas[0];
    $codVendedorVisita = (int)($visita['cod_vendedor'] ?? $visita['COD_VENDEDOR'] ?? 0);

    return $codVendedorVisita === $cod_vendedor;
}

function eliminarVisita(int $id_visita, int $cod_vendedor): bool
{
    $conn = db();

    if (!puedeEliminarVisita($id_visita, $cod_vendedor)) {
        return false;
    }

    try {
        odbc_autocommit($conn, false);

        $sqlDeletePedidos = "
            DELETE FROM [integral].[dbo].[cmf_visita_pedidos]
            WHERE id_visita = ?
        ";
        db_execute($conn, $sqlDeletePedidos, [$id_visita]);

        $sqlDeleteVisita = "
            DELETE FROM [integral].[dbo].[cmf_visitas_comerciales]
            WHERE id_visita = ?
        ";
        db_execute($conn, $sqlDeleteVisita, [$id_visita]);

        odbc_commit($conn);
        return true;
    } catch (Throwable $e) {
        odbc_rollback($conn);
        return false;
    }
}
