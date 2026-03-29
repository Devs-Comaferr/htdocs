<?php

require_once BASE_PATH . '/app/Support/db.php';

function posponerVisita(int $id_visita, array $data): bool
{
    $conn = db();

    $sql = "INSERT INTO cmf_visitas_comerciales 
            (estado_visita, cod_vendedor, cod_cliente, cod_seccion, fecha_visita, hora_inicio_visita, hora_fin_visita, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    return odbc_execute($stmt, [
        $data['estado_visita'],
        $data['cod_vendedor'],
        $data['cod_cliente'],
        $data['cod_seccion'],
        $data['fecha_visita'],
        $data['hora_inicio_visita'],
        $data['hora_fin_visita'],
        $data['observaciones'],
    ]);
}
