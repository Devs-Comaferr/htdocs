<?php

function obtenerEventosVisitasService(): array
{
    $conn = db();
    if (!$conn) {
        throw new Exception(odbc_errormsg());
    }

    $sql = "
    SELECT
        v.id_visita,
        v.cod_cliente,
        v.cod_seccion,
        v.fecha_visita,
        v.hora_inicio_visita,
        v.hora_fin_visita,
        v.estado_visita,
        c.nombre_comercial,
        s.nombre AS nombre_seccion,
        vp.origen
    FROM cmf_comerciales_visitas v
    LEFT JOIN clientes c ON v.cod_cliente = c.cod_cliente
    LEFT JOIN secciones_cliente s ON v.cod_seccion = s.cod_seccion AND v.cod_cliente = s.cod_cliente
    LEFT JOIN (
        SELECT vp1.id_visita, vp1.origen
        FROM cmf_comerciales_visitas_pedidos vp1
        INNER JOIN (
            SELECT id_visita, MIN(id_visita_pedido) AS min_id
            FROM cmf_comerciales_visitas_pedidos
            GROUP BY id_visita
        ) vp2 ON vp1.id_visita = vp2.id_visita AND vp1.id_visita_pedido = vp2.min_id
    ) vp ON v.id_visita = vp.id_visita
    ";

    $result = odbc_exec($conn, $sql);
    if (!$result) {
        throw new Exception(odbc_errormsg($conn));
    }

    $events = [];
    while ($row = odbc_fetch_array($result)) {
        if (strtolower((string)$row['estado_visita']) === 'descartada') {
            continue;
        }

        $nombreComercial = isset($row['nombre_comercial']) ? $row['nombre_comercial'] : ('Cliente ' . $row['cod_cliente']);
        $nombreComercial = toUTF8($nombreComercial);

        $title = $nombreComercial;
        if ($row['cod_seccion'] !== null && isset($row['nombre_seccion']) && $row['nombre_seccion'] !== null && $row['nombre_seccion'] !== '') {
            $title .= ' - ' . toUTF8($row['nombre_seccion']);
        }

        $origen = isset($row['origen']) ? $row['origen'] : '';
        $color = determinarColorVisita($row['estado_visita'], $origen);

        $events[] = [
            'id' => $row['id_visita'],
            'title' => $title,
            'start' => $row['fecha_visita'] . 'T' . $row['hora_inicio_visita'],
            'end' => $row['fecha_visita'] . 'T' . $row['hora_fin_visita'],
            'color' => $color,
        ];
    }

    return $events;
}
