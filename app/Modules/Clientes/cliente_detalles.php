<?php
declare(strict_types=1);

require_once BASE_PATH . '/bootstrap/init.php';
require_once BASE_PATH . '/bootstrap/auth.php';
unset($_SESSION['origen']);


// Obtener el cÃ³digo del cliente desde la URL
if (!isset($_GET['cod_cliente']) || empty($_GET['cod_cliente'])) {
    header("Location: clientes.php");
    exit();
}

require_once BASE_PATH . '/app/Support/functions.php';
require_once BASE_PATH . '/app/Support/db.php';

$ui_version = 'bs5';

$conn = db();

$cod_cliente = trim((string) $_GET['cod_cliente']);
$cod_seccion = isset($_GET['cod_seccion']) ? trim((string) $_GET['cod_seccion']) : null;
// CÃ³digo del comisionista (para filtrar datos a las operaciones realizadas por Ã©l)
$cod_comercial = $_SESSION['codigo'] ?? null;

function execPrepared($conn, string $sql, array $params = [])
{
    $stmt = odbc_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    if (!odbc_execute($stmt, $params)) {
        return false;
    }
    return $stmt;
}

function sqlLiteral(string $value): string
{
    return str_replace("'", "''", $value);
}

function obtenerResumenPedidoCliente($conn, string $codPedido): array
{
    $res = [
        'fecha_venta' => null,
        'hora_venta' => null,
        'importe' => 0.0,
        'observacion_interna' => '',
        'numero_lineas' => 0,
        'pedido_eliminado' => 0,
        'eliminado_por_usuario' => '',
        'eliminado_por_equipo' => '',
        'eliminado_fecha' => null,
        'eliminado_hora' => null
    ];

    $sqlCabElim = "
        SELECT TOP 1 *
        FROM [integral].[dbo].[ventas_cabecera_elim] vce
        WHERE vce.cod_venta = '" . sqlLiteral($codPedido) . "'
          AND vce.tipo_venta = 1
        ORDER BY vce.fecha_venta DESC, vce.hora_venta DESC
    ";
    $cabElim = odbc_fetch_array(odbc_exec($conn, $sqlCabElim));

    if ($cabElim) {
        $res['fecha_venta'] = $cabElim['fecha_venta'] ?? $cabElim['FECHA_VENTA'] ?? null;
        $res['hora_venta'] = $cabElim['hora_venta'] ?? $cabElim['HORA_VENTA'] ?? null;
        $res['importe'] = (float)($cabElim['importe'] ?? $cabElim['IMPORTE'] ?? 0);
        $res['pedido_eliminado'] = 1;

        $sqlLog = "
            SELECT TOP 1 la.cod_usuario, la.cod_estacion, la.fecha, la.hora
            FROM [integral].[dbo].[log_acciones] la
            WHERE la.tipo = 'B'
              AND la.categoria = 'V'
              AND la.cod_n3 = '" . sqlLiteral($codPedido) . "'
            ORDER BY la.fecha DESC, la.hora DESC
        ";
        $log = odbc_fetch_array(odbc_exec($conn, $sqlLog));
        if ($log) {
            $res['eliminado_por_usuario'] = (string)($log['cod_usuario'] ?? $log['COD_USUARIO'] ?? '');
            $res['eliminado_por_equipo'] = (string)($log['cod_estacion'] ?? $log['COD_ESTACION'] ?? '');
            $res['eliminado_fecha'] = $log['fecha'] ?? $log['FECHA'] ?? null;
            $res['eliminado_hora'] = $log['hora'] ?? $log['HORA'] ?? null;
        }

        $sqlLineElim = "
            SELECT COUNT(*) AS numero_lineas
            FROM [integral].[dbo].[ventas_linea_elim] vle
            WHERE vle.cod_venta = '" . sqlLiteral($codPedido) . "'
              AND vle.tipo_venta = 1
        ";
        $rowLineElim = odbc_fetch_array(odbc_exec($conn, $sqlLineElim));
        $res['numero_lineas'] = (int)($rowLineElim['numero_lineas'] ?? $rowLineElim['NUMERO_LINEAS'] ?? 0);
    } else {
        $sqlCabHist = "
            SELECT TOP 1 hvc.fecha_venta, hvc.hora_venta, hvc.importe, avc.observacion_interna
            FROM [integral].[dbo].[hist_ventas_cabecera] hvc
            LEFT JOIN [integral].[dbo].[anexo_ventas_cabecera] avc
              ON hvc.cod_anexo = avc.cod_anexo
            WHERE hvc.cod_venta = '" . sqlLiteral($codPedido) . "'
              AND hvc.tipo_venta = 1
            ORDER BY hvc.fecha_venta DESC, hvc.hora_venta DESC
        ";
        $cabHist = odbc_fetch_array(odbc_exec($conn, $sqlCabHist));
        if ($cabHist) {
            $res['fecha_venta'] = $cabHist['fecha_venta'] ?? $cabHist['FECHA_VENTA'] ?? null;
            $res['hora_venta'] = $cabHist['hora_venta'] ?? $cabHist['HORA_VENTA'] ?? null;
            $res['importe'] = (float)($cabHist['importe'] ?? $cabHist['IMPORTE'] ?? 0);
            $res['observacion_interna'] = (string)($cabHist['observacion_interna'] ?? $cabHist['OBSERVACION_INTERNA'] ?? '');
        }

        $sqlLineHist = "
            SELECT COUNT(*) AS numero_lineas
            FROM [integral].[dbo].[hist_ventas_linea] hl
            WHERE hl.cod_venta = '" . sqlLiteral($codPedido) . "'
              AND hl.tipo_venta = 1
        ";
        $rowLineHist = odbc_fetch_array(odbc_exec($conn, $sqlLineHist));
        $res['numero_lineas'] = (int)($rowLineHist['numero_lineas'] ?? $rowLineHist['NUMERO_LINEAS'] ?? 0);
    }

    return $res;
}

// Cargar datos iniciales del cliente
// 1) Consultar los detalles del cliente
// Comentario reparado
$sql_cliente = "
    SELECT 
        cod_cliente, 
        nombre_comercial, 
        razon_social, 
        cif, 
        direccion1, 
        CP, 
        poblacion, 
        provincia, 
        telefono, 
        e_mail, 
        cod_forma_liquidacion, 
        advertencia, 
        moroso, 
        fecha_alta, 
        cod_tarifa, 
        cod_ruta, 
        telefono2, 
        telefono3
    FROM [integral].[dbo].[clientes] 
    WHERE cod_cliente = ?
";
$result_cliente = execPrepared($conn, $sql_cliente, [$cod_cliente]);
if (!$result_cliente) {
    error_log("Error en consulta de cliente: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
$cliente = odbc_fetch_array($result_cliente);
if (!$cliente) {
    header("Location: clientes.php");
    exit();
}

// Comentario reparado
// 2) Forma de pago
// Comentario reparado
$forma_pago = "Desconocida";
if (!empty($cliente['cod_forma_liquidacion'])) {
    $sql_forma_pago = "
        SELECT descripcion 
        FROM [integral].[dbo].[formas_liquidacion] 
        WHERE cod_forma_liquidacion = ?
    ";
    $result_forma_pago = execPrepared($conn, $sql_forma_pago, [$cliente['cod_forma_liquidacion']]);
    if ($result_forma_pago) {
        $forma_pago_data = odbc_fetch_array($result_forma_pago);
        if ($forma_pago_data) {
            $forma_pago = $forma_pago_data['descripcion'];
        }
    }
}

// Comentario reparado
// 3) Secciones del cliente
// Comentario reparado
$sql_secciones = "
    SELECT cod_seccion, nombre 
    FROM [integral].[dbo].[secciones_cliente]
    WHERE cod_cliente = ?
";
$result_secciones = execPrepared($conn, $sql_secciones, [$cod_cliente]);
if (!$result_secciones) {
    error_log("Error en consulta de secciones: " . odbc_errormsg($conn));
    echo 'Error interno';
    return;
}
$secciones = [];
while ($seccion = odbc_fetch_array($result_secciones)) {
    $secciones[] = $seccion;
}

// Comentario reparado
// 4) Contactos del cliente
// Comentario reparado
$sql_contactos = "
    SELECT TOP (1000)
        cod_contacto,
        nombre,
        departamento,
        cargo,
        telefono,
        fax,
        e_mail,
        www,
        aniversario,
        observaciones,
        autorizacion_venta,
        cif,
        facebook,
        twitter,
        whatsapp,
        telefono_movil,
        telefono_comentario,
        telefono_movil_comentario,
        numero_carne_compra_fitosanitarios,
        fecha_modificacion,
        codigo_ropo
    FROM [integral].[dbo].[contactos_cliente]
    WHERE cod_cliente = ?
";
$result_contactos = execPrepared($conn, $sql_contactos, [$cod_cliente]);
$contactos = [];
if ($result_contactos) {
    while ($rowC = odbc_fetch_array($result_contactos)) {
        $contactos[] = $rowC;
    }
}

// Comentario reparado
// Comentario reparado
// Comentario reparado
$sql_asignacion = "
    SELECT *
    FROM [integral].[dbo].[cmf_asignacion_zonas_clientes]
    WHERE cod_cliente = ?
";
$params_asignacion = [$cod_cliente];
if (is_null($cod_seccion) || $cod_seccion === '') {
    $sql_asignacion .= " AND (cod_seccion IS NULL OR cod_seccion = '')";
} else {
    $sql_asignacion .= " AND cod_seccion = ?";
    $params_asignacion[] = $cod_seccion;
}
$result_asignacion = execPrepared($conn, $sql_asignacion, $params_asignacion);
$asignacion = $result_asignacion ? odbc_fetch_array($result_asignacion) : false;

$mostrar_nueva_funcionalidad = false;
$visitas = [];
if (((isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin')
    || (isset($_SESSION['perm_planificador']) && (int)$_SESSION['perm_planificador'] === 1))
    && (is_null($cod_seccion) || $cod_seccion === '')
    && count($secciones) <= 1
) {
    $mostrar_nueva_funcionalidad = true;

    // Comentario reparado
    // Visitas del cliente (desc)
    $sql_visitas = "
        SELECT 
            v.id_visita, 
            v.observaciones, 
            v.estado_visita, 
            v.fecha_visita, 
            v.hora_inicio_visita, 
            v.hora_fin_visita
        FROM [integral].[dbo].[cmf_visitas_comerciales] v
        WHERE v.cod_cliente = ?
        ORDER BY v.fecha_visita DESC
    ";
    $result_visitas = execPrepared($conn, $sql_visitas, [$cod_cliente]);
    if (!$result_visitas) {
        error_log("Error en consulta de visitas: " . odbc_errormsg($conn));
        echo 'Error interno';
        return;
    }
    while ($visita = odbc_fetch_array($result_visitas)) {
        $id_visita = (string)$visita['id_visita'];
        $sql_pedido_principal = "
            SELECT TOP 1 vp.cod_venta, vp.origen 
            FROM [integral].[dbo].[cmf_visita_pedidos] vp
            WHERE vp.id_visita = ?
        ";
        $params_pedido_principal = [$id_visita];
            // Comentario reparado
        if ($cod_comercial !== null AND $cod_comercial === '30') { // Comentario reparado
                // Sin filtro adicional para no perder pedidos eliminados asociados a la visita.
            }

        $sql_pedido_principal .= "ORDER BY vp.id_visita_pedido ASC";

        $result_pedido_principal = execPrepared($conn, $sql_pedido_principal, $params_pedido_principal);
        $pedido_principal = odbc_fetch_array($result_pedido_principal);
        $origen_principal = isset($pedido_principal['origen']) ? $pedido_principal['origen'] : 'otros';
        $visita['color'] = determinarColorVisita($visita['estado_visita'], $origen_principal);

        // Pedidos asociados
        $sql_pedidos = "
            SELECT 
                vp.cod_venta AS cod_pedido,
                vp.origen
            FROM [integral].[dbo].[cmf_visita_pedidos] vp
            WHERE vp.id_visita = ?
        ";
        $params_pedidos = [$id_visita];
        if ($cod_comercial !== null AND $cod_comercial === '30') { // Comentario reparado
            // Sin filtro adicional para no perder pedidos eliminados asociados a la visita.
        }

        $sql_pedidos .= " ORDER BY vp.id_visita_pedido ASC";

        $result_pedidos = execPrepared($conn, $sql_pedidos, $params_pedidos);
        if ($result_pedidos) {
            $pedidos = [];
            $importe_total_visita = 0;
            $numero_lineas_total_visita = 0;
            while ($pedido = odbc_fetch_array($result_pedidos)) {
                $resumenPedido = obtenerResumenPedidoCliente($conn, (string)$pedido['cod_pedido']);
                $pedido = array_merge($pedido, $resumenPedido);

                if (empty($pedido['fecha_venta'])) {
                    $pedido['fecha_venta'] = $visita['fecha_visita'] ?? null;
                }
                if (empty($pedido['hora_venta'])) {
                    $pedido['hora_venta'] = $visita['hora_inicio_visita'] ?? null;
                }

                $importe_total_visita += (float)($pedido['importe'] ?? 0);
                $numero_lineas_total_visita += (int)($pedido['numero_lineas'] ?? 0);
                $pedidos[] = $pedido;
            }
            $visita['pedidos'] = $pedidos;
            $visita['importe_total'] = $importe_total_visita;
            $visita['numero_lineas_total'] = $numero_lineas_total_visita;
        } else {
            $visita['pedidos'] = [];
            $visita['importe_total'] = 0;
            $visita['numero_lineas_total'] = 0;
        }
        $visitas[] = $visita;
    }
}

$visitasPorPagina = 10;
$totalVisitas = count($visitas);
$totalPaginasVisitas = max(1, (int)ceil($totalVisitas / $visitasPorPagina));
$paginaVisitas = isset($_GET['pag_visitas']) ? max(1, (int)$_GET['pag_visitas']) : 1;
if ($paginaVisitas > $totalPaginasVisitas) {
    $paginaVisitas = $totalPaginasVisitas;
}
$offsetVisitas = ($paginaVisitas - 1) * $visitasPorPagina;
$visitasPaginadas = array_slice($visitas, $offsetVisitas, $visitasPorPagina);

// Comentario reparado
$pageTitle = toUTF8($cliente['nombre_comercial']);

// Comentario reparado
// Comentario reparado
// Comentario reparado

// Comentario reparado
$sqlGraficoLineas = "
    SELECT 
        YEAR(hvc.fecha_venta) AS anio,
        MONTH(hvc.fecha_venta) AS mes,
        SUM(CASE WHEN hvc.tipo_venta = 1 THEN hvc.importe ELSE 0 END) AS total_pedidos,
        SUM(CASE WHEN hvc.tipo_venta = 2 THEN hvc.importe ELSE 0 END) AS total_albaranes
    FROM [integral].[dbo].[hist_ventas_cabecera] hvc
    WHERE hvc.cod_cliente = '" . sqlLiteral($cod_cliente) . "'
      AND hvc.fecha_venta >= '2024-10-01'
      AND hvc.fecha_venta <= GETDATE()
    GROUP BY YEAR(hvc.fecha_venta), MONTH(hvc.fecha_venta)
    ORDER BY YEAR(hvc.fecha_venta), MONTH(hvc.fecha_venta)
";
$resultGraficoLineas = null;
// Comentario reparado
if (!is_null($cod_comercial) && $cod_comercial === '30') { // Comentario reparado
    $sqlGraficoLineas = str_replace("AND hvc.fecha_venta <= GETDATE()", "AND hvc.fecha_venta <= GETDATE()\n      AND hvc.cod_comisionista = '" . sqlLiteral($cod_comercial) . "'", $sqlGraficoLineas);
}
$resultGraficoLineas = odbc_exec($conn, $sqlGraficoLineas);

// Guardamos los resultados en un array asociativo con clave 'YYYY-MM'
$datosDict = [];
while ($rowG = odbc_fetch_array($resultGraficoLineas)) {
    $anio  = (int)$rowG['anio'];
    $mes   = (int)$rowG['mes'];
    $periodo = sprintf('%04d-%02d', $anio, $mes);

    $datosDict[$periodo] = [
        'pedidos'   => (float)$rowG['total_pedidos'],
        'albaranes' => (float)$rowG['total_albaranes']
    ];
}

// Rellenar con 0 los meses en los que no hay datos
$fechaInicio = new DateTime('2024-10-01');
$fechaFin    = new DateTime();
$fechaFin->modify('last day of this month'); // Hasta el final del mes actual
$intervalo = new DateInterval('P1M');
$periodo   = new DatePeriod($fechaInicio, $intervalo, $fechaFin);

$datosMensuales = [];
foreach ($periodo as $dt) {
    $mesClave = $dt->format('Y-m');
    
    if (isset($datosDict[$mesClave])) {
        $pedidos   = $datosDict[$mesClave]['pedidos'];
        $albaranes = $datosDict[$mesClave]['albaranes'];
    } else {
        $pedidos   = 0;
        $albaranes = 0;
    }

    $datosMensuales[] = [
        'periodo'   => $mesClave,
        'pedidos'   => $pedidos,
        'albaranes' => $albaranes
    ];
}

$datosMensualesJson = json_encode($datosMensuales);

// Desglose comparativo por mes y articulo (pedido vs albaran) para el modal de barras
$sqlDetalleBarras = "
    SELECT
        YEAR(hvc.fecha_venta) AS anio,
        MONTH(hvc.fecha_venta) AS mes,
        hvc.tipo_venta,
        hl.cod_articulo,
        COALESCE(ad.descripcion, hl.descripcion) AS descripcion,
        SUM(hl.cantidad) AS cantidad,
        SUM(hl.importe) AS importe
    FROM [integral].[dbo].[hist_ventas_linea] hl
    INNER JOIN [integral].[dbo].[hist_ventas_cabecera] hvc
        ON hl.cod_venta = hvc.cod_venta
       AND hl.tipo_venta = hvc.tipo_venta
    LEFT JOIN [integral].[dbo].[articulo_descripcion] ad
        ON ad.cod_articulo = hl.cod_articulo
       AND ad.cod_idioma = 'ES'
    WHERE hvc.cod_cliente = '" . sqlLiteral($cod_cliente) . "'
      AND hvc.fecha_venta >= '2024-10-01'
      AND hvc.fecha_venta <= GETDATE()
      AND hvc.tipo_venta IN (1, 2)
    GROUP BY
        YEAR(hvc.fecha_venta),
        MONTH(hvc.fecha_venta),
        hvc.tipo_venta,
        hl.cod_articulo,
        COALESCE(ad.descripcion, hl.descripcion)
    ORDER BY
        YEAR(hvc.fecha_venta),
        MONTH(hvc.fecha_venta),
        hvc.tipo_venta,
        SUM(hl.importe) DESC
";
if (!is_null($cod_comercial) && $cod_comercial === '30') {
    $sqlDetalleBarras = str_replace(
        "AND hvc.tipo_venta IN (1, 2)",
        "AND hvc.tipo_venta IN (1, 2)\n      AND hvc.cod_comisionista = '" . sqlLiteral($cod_comercial) . "'",
        $sqlDetalleBarras
    );
}

$resDetalleBarras = odbc_exec($conn, $sqlDetalleBarras);
$detalleBarras = [];
if ($resDetalleBarras) {
    while ($rowD = odbc_fetch_array($resDetalleBarras)) {
        $anio = (int)$rowD['anio'];
        $mes = (int)$rowD['mes'];
        $tipo = (int)$rowD['tipo_venta'];
        $keyMes = sprintf('%04d-%02d', $anio, $mes);
        $codArticulo = (string)$rowD['cod_articulo'];
        $descripcion = toUTF8((string)$rowD['descripcion']);
        $keyArticulo = $codArticulo . '|' . $descripcion;

        if (!isset($detalleBarras[$keyMes])) {
            $detalleBarras[$keyMes] = [];
        }
        if (!isset($detalleBarras[$keyMes][$keyArticulo])) {
            $detalleBarras[$keyMes][$keyArticulo] = [
                'cod_articulo' => $codArticulo,
                'descripcion' => $descripcion,
                'cantidad_pedido' => 0.0,
                'importe_pedido' => 0.0,
                'cantidad_albaran' => 0.0,
                'importe_albaran' => 0.0
            ];
        }

        if ($tipo === 1) {
            $detalleBarras[$keyMes][$keyArticulo]['cantidad_pedido'] += (float)$rowD['cantidad'];
            $detalleBarras[$keyMes][$keyArticulo]['importe_pedido'] += (float)$rowD['importe'];
        } elseif ($tipo === 2) {
            $detalleBarras[$keyMes][$keyArticulo]['cantidad_albaran'] += (float)$rowD['cantidad'];
            $detalleBarras[$keyMes][$keyArticulo]['importe_albaran'] += (float)$rowD['importe'];
        }
    }

    foreach ($detalleBarras as $mesKey => $items) {
        $lista = array_values($items);
        usort($lista, function($a, $b) {
            $totalA = (float)$a['importe_pedido'] + (float)$a['importe_albaran'];
            $totalB = (float)$b['importe_pedido'] + (float)$b['importe_albaran'];
            return $totalB <=> $totalA;
        });
        $detalleBarras[$mesKey] = $lista;
    }
}
$detalleBarrasJson = json_encode($detalleBarras, JSON_UNESCAPED_UNICODE);

// Comentario reparado
$sqlGraficoFamilia = "
    SELECT
        YEAR(hc.fecha_venta) AS anio,
        MONTH(hc.fecha_venta) AS mes,
        fam.cod_familia,
        fam.descripcion AS familia,
        SUM(hl.importe) AS total_familia
    FROM [integral].[dbo].[hist_ventas_linea] hl
    JOIN [integral].[dbo].[hist_ventas_cabecera] hc
      ON hl.cod_venta = hc.cod_venta
    JOIN [integral].[dbo].[articulos] art
      ON hl.cod_articulo = art.cod_articulo
    JOIN [integral].[dbo].[familias] fam
      ON art.cod_familia = fam.cod_familia
    WHERE hc.cod_cliente = '" . sqlLiteral($cod_cliente) . "'
      AND hc.tipo_venta = 2
      AND hl.tipo_venta = 2
      AND hc.fecha_venta >= '2024-10-01'
      AND hc.fecha_venta <= GETDATE()
";
if (!is_null($cod_comercial) AND $cod_comercial === '30') {
    $sqlGraficoFamilia .= " AND hc.cod_comisionista = '" . sqlLiteral($cod_comercial) . "'";
}
$sqlGraficoFamilia .= "
    GROUP BY YEAR(hc.fecha_venta), MONTH(hc.fecha_venta), fam.cod_familia, fam.descripcion
    ORDER BY anio, mes
";

$resultFamilia = odbc_exec($conn, $sqlGraficoFamilia);
$datosFamiliaMensual = [];
if ($resultFamilia) {
    while ($rowF = odbc_fetch_array($resultFamilia)) {
        $datosFamiliaMensual[] = [
            'anio'        => (int)$rowF['anio'],
            'mes'         => (int)$rowF['mes'],
            'cod_familia' => (string)$rowF['cod_familia'],
            'familia'     => toUTF8((string)$rowF['familia']),
            'importe'     => (float)$rowF['total_familia']
        ];
    }
}
$datosFamiliaMensualJson = json_encode($datosFamiliaMensual);

$sqlGraficoMarca = "
    SELECT
        YEAR(hc.fecha_venta) AS anio,
        MONTH(hc.fecha_venta) AS mes,
        mar.descripcion AS marca,
        art.cod_familia,
        SUM(hl.importe) AS total_marca
    FROM [integral].[dbo].[hist_ventas_linea] hl
    JOIN [integral].[dbo].[hist_ventas_cabecera] hc
      ON hl.cod_venta = hc.cod_venta
    JOIN [integral].[dbo].[articulos] art
      ON hl.cod_articulo = art.cod_articulo
    JOIN [integral].[dbo].[web_marcas] mar
      ON art.cod_marca_web = mar.cod_marca
    WHERE hc.cod_cliente = '" . sqlLiteral($cod_cliente) . "'
      AND hc.tipo_venta = 2
      AND hl.tipo_venta = 2
      AND hc.fecha_venta >= '2024-10-01'
      AND hc.fecha_venta <= GETDATE()
";
if (!is_null($cod_comercial) AND $cod_comercial === '30') {
    $sqlGraficoMarca .= " AND hc.cod_comisionista = '" . sqlLiteral($cod_comercial) . "'";
}
$sqlGraficoMarca .= "
    GROUP BY YEAR(hc.fecha_venta), MONTH(hc.fecha_venta), mar.descripcion, art.cod_familia
    ORDER BY anio, mes
";

$resultMarca = odbc_exec($conn, $sqlGraficoMarca);
$datosMarcaMensual = [];
if ($resultMarca) {
    while ($rowM = odbc_fetch_array($resultMarca)) {
        $datosMarcaMensual[] = [
            'anio'        => (int)$rowM['anio'],
            'mes'         => (int)$rowM['mes'],
            'marca'       => toUTF8((string)$rowM['marca']),
            'cod_familia' => (string)$rowM['cod_familia'],
            'importe'     => (float)$rowM['total_marca']
        ];
    }
}
$datosMarcaMensualJson = json_encode($datosMarcaMensual);

$sqlGraficoArticulos = "
    SELECT
        YEAR(hc.fecha_venta) AS anio,
        MONTH(hc.fecha_venta) AS mes,
        ad.descripcion AS articulo,
        art.cod_familia,
        SUM(hl.importe) AS total_articulo
    FROM [integral].[dbo].[hist_ventas_linea] hl
    JOIN [integral].[dbo].[hist_ventas_cabecera] hc
      ON hl.cod_venta = hc.cod_venta
    JOIN [integral].[dbo].[articulos] art
      ON hl.cod_articulo = art.cod_articulo
    JOIN [integral].[dbo].[articulo_descripcion] ad
      ON ad.cod_articulo = art.cod_articulo
     AND ad.cod_idioma = 'ES'
    WHERE hc.cod_cliente = '" . sqlLiteral($cod_cliente) . "'
      AND hc.tipo_venta = 2
      AND hl.tipo_venta = 2
      AND hc.fecha_venta >= '2024-10-01'
      AND hc.fecha_venta <= GETDATE()
";
if (!is_null($cod_comercial) AND $cod_comercial === '30') {
    $sqlGraficoArticulos .= " AND hc.cod_comisionista = '" . sqlLiteral($cod_comercial) . "'";
}
$sqlGraficoArticulos .= "
    GROUP BY YEAR(hc.fecha_venta), MONTH(hc.fecha_venta), ad.descripcion, art.cod_familia
    ORDER BY anio, mes
";

$resultArt = odbc_exec($conn, $sqlGraficoArticulos);
$datosArticulosMensual = [];
if ($resultArt) {
    while ($rowA = odbc_fetch_array($resultArt)) {
        $datosArticulosMensual[] = [
            'anio'        => (int)$rowA['anio'],
            'mes'         => (int)$rowA['mes'],
            'articulo'    => toUTF8((string)$rowA['articulo']),
            'cod_familia' => (string)$rowA['cod_familia'],
            'importe'     => (float)$rowA['total_articulo']
        ];
    }
}
$datosArticulosMensualJson = json_encode($datosArticulosMensual);

// Comentario reparado
$pageTitle = toUTF8($cliente['nombre_comercial']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles del Cliente - <?= htmlspecialchars((string)$pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap 5 CSS (local via Composer assets) -->

    <!-- Se asume que en header.php se incluye Font Awesome 6.4 -->
    <style>
        body { padding-top: 20px; background-color: #f8f9fa; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .container { max-width: 1200px; margin: 20px auto; background-color: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #f4f4f4; }
        td { background-color: #f9f9f9; }
        a { text-decoration: none; color: #007BFF; }
        a:hover { text-decoration: underline; }
        .back-button { background-color: #007bff; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none; font-size: 16px; margin-top: 20px; display: inline-block; }
        .back-button:hover { background-color: #0056b3; }
        .faltas-button, .historico-button { display: inline-block; padding: 10px 20px; border-radius: 25px; font-size: 14px; font-weight: bold; text-align: center; text-decoration: none; transition: all 0.3s ease; margin-right: 10px; }
        .faltas-button { background-color: #ff4d4d; color: white; }
        .faltas-button:hover { background-color: #e63939; transform: translateY(-2px); }
        .historico-button { background-color: #28a745; color: white; }
        .historico-button:hover { background-color: #218838; transform: translateY(-2px); }
        .button-container { text-align: center; margin-top: 30px; }
        .moroso { color: red; font-weight: bold; text-align: center; font-size: 18px; }

        .visitas-container { margin-top: 40px; }
        .visita-item { padding: 15px; margin-bottom: 20px; border-radius: 5px; color: #fff; display: block; transition: background-color 0.3s ease, transform 0.2s; cursor: pointer; }
        .visita-item:hover { opacity: 0.9; transform: scale(1.01); }
        .visita-linea { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; }
        .visita-linea span { margin-right: 20px; font-size: 16px; }
        .visita-observaciones { display: block; margin-top: 5px; font-style: italic; color: #ffffff; }
        @media screen and (max-width: 1024px) {
            .visita-linea { flex-direction: column; align-items: flex-start; }
            .visita-linea span { margin-right: 0; margin-bottom: 5px; }
        }
        .pedido-item { position: relative; background: #fff; padding: 15px 20px; margin-left: 40px; margin-bottom: 15px; border-radius: 5px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); transition: transform 0.2s; cursor: pointer; }
        .pedido-item:hover { transform: scale(1.02); }
        .pedido-item::before { content: ""; position: absolute; left: 0; top: 0; width: 8px; height: 100%; border-radius: 5px 0 0 5px; background-color: #6c757d; }
        .pedido-content { margin-left: 15px; }
        .pedido-info { display: flex; flex-wrap: wrap; margin-bottom: 10px; }
        .pedido-info > div { margin-right: 20px; margin-bottom: 5px; }
        .pedido-observaciones { font-style: italic; color: #007bff; }
        .label { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 700; line-height: 1.2; color: #fff; vertical-align: middle; }
        .label-warning { background: #f0ad4e; }
        .pedido-actions { position: absolute; top: 10px; right: 10px; z-index: 10; }
        .btn-circle { border-radius: 50%; width: 45px; height: 45px; font-size: 18px; padding: 0; display: inline-flex; align-items: center; justify-content: center; margin-left: 5px; border: none; outline: none; }
        .btn-visita   { background-color: #28a745; color: #fff; }
        .btn-telefono { background-color: #ffc107; color: #fff; }
        .btn-whatsapp { background-color: #25D366; color: #fff; }
        .btn-email    { background-color: #17a2b8; color: #fff; }
        .btn-eliminar { background-color: #dc3545; color: #fff; }
        .btn[disabled] { background-color: grey !important; color: #fff !important; cursor: not-allowed; }
        @media screen and (max-width: 1024px) { .pedido-actions { right: 70px; } }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 1200px; border-radius: 10px; position: relative; }
        .close { color: #aaa; position: absolute; top: 15px; right: 25px; font-size: 30px; font-weight: bold; cursor: pointer; z-index: 9999; }
        .close:hover, .close:focus { color: black; text-decoration: none; cursor: pointer; }
        .modal-table-container { width: 100%; overflow-x: auto; }
        .modal-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        .modal-table th, .modal-table td { padding: 10px; text-align: left; border: 1px solid #ddd; vertical-align: top; }
        .modal-table th { background-color: #f4f4f4; }
        .modal-table tr.diff-cantidad-pedido-mayor td { background-color: #ffe5e5 !important; }
        .modal-table tr.diff-cantidad-albaran-mayor td { background-color: #e7f7ea !important; }
        .modal-table tr.diff-importe-pedido-mayor td { background-color: #fff6d6 !important; }
        .modal-table tr.diff-importe-albaran-mayor td { background-color: #e8f1ff !important; }
        .leyenda-diff { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 14px; font-size: 13px; }
        .leyenda-item { display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 14px; background: #fff; }
        .leyenda-color { width: 12px; height: 12px; border-radius: 3px; border: 1px solid rgba(0,0,0,.15); }
        .descripcion-con-observacion { position: relative; }
        .descripcion-con-observacion .observacion { display: block; color: #007bff; font-style: italic; margin-top: 5px; }

/* Comentario reparado */
        @media (max-width: 767px) {
            .chart-col { margin-bottom: 30px; }
        }
    </style>

    <!-- Chart.js (local via Composer assets) -->
    <script src="<?= BASE_URL ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
    <!-- ChartDataLabels plugin (local via Composer assets) -->
    <script src="<?= BASE_URL ?>/assets/vendor/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js"></script>
    <script>
        // Registramos el plugin de DataLabels
        Chart.register(ChartDataLabels);

        // Comentario reparado
        const colorFamilias = {
            'A': '#F39C12', // Comentario reparado
            'B': '#8B5A2B',  // Madera
            'C': '#F1C40F',  // Electricidad
            'D': '#E74C3C',  // Herramientas
            'E': '#3498DB', // Comentario reparado
            'F': '#E84393',  // Cocina
            'G': '#27AE60', // Comentario reparado
            'H': '#95A5A6', // Comentario reparado
            'I': '#8E44AD',  // Pinturas
            'J': '#FFC0CB', // Comentario reparado
            'K': '#D2B48C',  // Mobiliario
            'L': '#00BCD4', // Comentario reparado
            '1': '#000000',  // No tangibles
            '99': '#424242', // Varios sin clasificar
            '2': '#B6C002'   // Nuevos de Cooperativa
        };

        // Datos en JSON (desde PHP)
        var datosMensuales = <?php echo $datosMensualesJson; ?>;
        var detalleBarrasMap = <?php echo $detalleBarrasJson; ?>;
        var datosFamiliaMensual = <?php echo $datosFamiliaMensualJson; ?>;
        var datosMarcaMensual = <?php echo $datosMarcaMensualJson; ?>;
        var datosArticulosMensual = <?php echo $datosArticulosMensualJson; ?>;

        // Modal
window.onclick = function(event) {
            var modals = document.getElementsByClassName('modal');
            for(var i=0; i<modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = "none";
                }
            }
        }

        // Asignar eventos a visita-item y pedido-item
        function asignarEventos() {
            var visitas = document.getElementsByClassName('visita-item');
            for (var i = 0; i < visitas.length; i++) {
                visitas[i].onclick = function() {
                    var id_visita = this.getAttribute('data-id-visita');
                    abrirModal('modal-visita-' + id_visita);
                };
            }
            var pedidos = document.getElementsByClassName('pedido-item');
            for (var i = 0; i < pedidos.length; i++) {
                pedidos[i].onclick = function(event) {
                    if (event.target.classList.contains('observacion')) return;
                    var cod_pedido = this.getAttribute('data-cod-pedido');
                    abrirModal('modal-pedido-' + cod_pedido);
                };
            }
        }

        function cargarPaginaVisitas(url, pushState) {
            var container = document.querySelector('.visitas-container');
            if (!container) return;

            var xhr = null;
            if (window.XMLHttpRequest) {
                xhr = new XMLHttpRequest();
            } else if (window.ActiveXObject) {
                xhr = new ActiveXObject("Microsoft.XMLHTTP");
            }
            if (xhr == null) {
                window.location.href = url;
                return;
            }

            container.style.opacity = '0.6';
            xhr.open("GET", url, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState !== 4) return;
                container.style.opacity = '';
                if (xhr.status !== 200) {
                    window.location.href = url;
                    return;
                }

                if (typeof DOMParser === 'undefined') {
                    window.location.href = url;
                    return;
                }

                var doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
                var nuevoContainer = doc.querySelector('.visitas-container');
                if (!nuevoContainer) {
                    window.location.href = url;
                    return;
                }

                container.innerHTML = nuevoContainer.innerHTML;
                asignarEventos();

                if (pushState !== false && window.history && window.history.pushState) {
                    window.history.pushState({ pagVisitas: true }, '', url);
                }
            };
            xhr.send(null);
        }

        function activarPaginacionVisitasAjax() {
            var container = document.querySelector('.visitas-container');
            if (!container) return;

            container.addEventListener('click', function(event) {
                var target = event.target;
                var link = null;
                if (target && typeof target.closest === 'function') {
                    link = target.closest('a[href*="pag_visitas="]');
                }
                if (!link) return;

                event.preventDefault();
                cargarPaginaVisitas(link.href, true);
            });

            if (window.history && window.history.pushState) {
                window.addEventListener('popstate', function() {
                    cargarPaginaVisitas(window.location.href, false);
                });
            }
        }

        // FunciÃ³n para quitar pedido y actualizar origen
        function quitarPedido(codPedido, e) {
    e.stopPropagation();
    if (!confirm("Â¿Deseas quitar este pedido de la visita?")) {
        return;
    }
    $.ajax({
        url: '<?= BASE_URL ?>/ajax/quitar_pedido.php',
        type: 'POST',
        data: { cod_pedido: codPedido },
        success: function(response) {
            if (response.indexOf("OK") === 0) {
                location.reload();
            } else {
                alert("Error al eliminar el pedido: " + response);
            }
        },
        error: function() {
            alert("Error al eliminar el pedido (AJAX).");
        }
    });
}

function actualizarOrigen(codPedido, nuevoOrigen, e) {
    e.stopPropagation();
    $.ajax({
        url: '<?= BASE_URL ?>/ajax/actualizar_origen.php',
        type: 'POST',
        data: { cod_pedido: codPedido, origen: nuevoOrigen },
        success: function(response) {
            if (response.indexOf("OK") === 0) {
                location.reload();
            } else {
                alert("Error al actualizar el origen: " + response);
            }
        },
        error: function() {
            alert("Error al actualizar el origen (AJAX).");
        }
    });
}

        var graficoComparativa = null;
        var graficoFamilia = null;
        var graficoMarca = null;
        var graficoArticulos = null;

        function escapeHtml(valor) {
            return String(valor)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function mostrarDetalleBarra(periodoTxt, items) {
            var titulo = document.getElementById('detalleBarrasTitulo');
            var contenido = document.getElementById('detalleBarrasContenido');
            if (!titulo || !contenido) return;

            titulo.innerHTML = 'Detalle mensual (Pedido vs Albaran) - ' + escapeHtml(periodoTxt);

            if (!Array.isArray(items) || items.length === 0) {
                contenido.innerHTML = '<p>No hay lineas para ese periodo.</p>';
                abrirModal('modal-detalle-barras');
                return;
            }

            var filas = '';
            var totalCantPedido = 0;
            var totalImpPedido = 0;
            var totalCantAlbaran = 0;
            var totalImpAlbaran = 0;
            var EPS = 0.0001;
            items.forEach(function(item) {
                var cantidadPedido = Number(item.cantidad_pedido || 0);
                var importePedido = Number(item.importe_pedido || 0);
                var cantidadAlbaran = Number(item.cantidad_albaran || 0);
                var importeAlbaran = Number(item.importe_albaran || 0);

                totalCantPedido += cantidadPedido;
                totalImpPedido += importePedido;
                totalCantAlbaran += cantidadAlbaran;
                totalImpAlbaran += importeAlbaran;

                var claseFila = '';
                if (Math.abs(cantidadPedido - cantidadAlbaran) > EPS) {
                    if (cantidadPedido > cantidadAlbaran) {
                        claseFila = ' class="diff-cantidad-pedido-mayor"';
                    } else {
                        claseFila = ' class="diff-cantidad-albaran-mayor"';
                    }
                } else if (Math.abs(importePedido - importeAlbaran) > EPS) {
                    if (importePedido > importeAlbaran) {
                        claseFila = ' class="diff-importe-pedido-mayor"';
                    } else {
                        claseFila = ' class="diff-importe-albaran-mayor"';
                    }
                }

                filas += '<tr' + claseFila + '>' +
                    '<td>' + escapeHtml(item.cod_articulo || '') + '</td>' +
                    '<td>' + escapeHtml(item.descripcion || '') + '</td>' +
                    '<td>' + cantidadPedido.toFixed(2) + '</td>' +
                    '<td>' + importePedido.toFixed(2) + ' &euro;</td>' +
                    '<td>' + cantidadAlbaran.toFixed(2) + '</td>' +
                    '<td>' + importeAlbaran.toFixed(2) + ' &euro;</td>' +
                    '</tr>';
            });

            contenido.innerHTML =
                '<p><strong>Total Pedidos:</strong> ' + totalImpPedido.toFixed(2) + ' &euro; | ' +
                '<strong>Total Albaranes:</strong> ' + totalImpAlbaran.toFixed(2) + ' &euro;</p>' +
                '<div class="leyenda-diff">' +
                '<span class="leyenda-item"><span class="leyenda-color" style="background:#ffe5e5;"></span>Cantidad: Pedido &gt; Albaran</span>' +
                '<span class="leyenda-item"><span class="leyenda-color" style="background:#e7f7ea;"></span>Cantidad: Albaran &gt; Pedido</span>' +
                '<span class="leyenda-item"><span class="leyenda-color" style="background:#fff6d6;"></span>Importe: Pedido &gt; Albaran (misma cantidad)</span>' +
                '<span class="leyenda-item"><span class="leyenda-color" style="background:#e8f1ff;"></span>Importe: Albaran &gt; Pedido (misma cantidad)</span>' +
                '</div>' +
                '<div class="modal-table-container">' +
                '<table class="modal-table">' +
                '<thead><tr>' +
                '<th>ArtÃ­culo</th><th>DescripciÃ³n</th>' +
                '<th>Cant. Pedido</th><th>Importe Pedido</th>' +
                '<th>Cant. Albaran</th><th>Importe Albaran</th>' +
                '</tr></thead>' +
                '<tbody>' + filas +
                '<tr>' +
                '<td colspan="2"><strong>Totales</strong></td>' +
                '<td><strong>' + totalCantPedido.toFixed(2) + '</strong></td>' +
                '<td><strong>' + totalImpPedido.toFixed(2) + ' &euro;</strong></td>' +
                '<td><strong>' + totalCantAlbaran.toFixed(2) + '</strong></td>' +
                '<td><strong>' + totalImpAlbaran.toFixed(2) + ' &euro;</strong></td>' +
                '</tr>' +
                '</tbody>' +
                '</table>' +
                '</div>';

            abrirModal('modal-detalle-barras');
        }

        function obtenerAnosSeleccionados() {
            var selector = document.getElementById('yearsWindow');
            var yearsWindow = selector ? selector.value : '2';
            var years = [];

            if (!Array.isArray(datosMensuales)) return years;
            datosMensuales.forEach(function(item) {
                var p = String(item.periodo || '');
                var parts = p.split('-');
                if (parts.length !== 2) return;
                var anio = parseInt(parts[0], 10);
                if (!isNaN(anio) && years.indexOf(anio) === -1) {
                    years.push(anio);
                }
            });
            years.sort(function(a, b) { return a - b; });

            if (yearsWindow !== 'all') {
                var n = parseInt(yearsWindow, 10);
                if (!isNaN(n) && n > 0 && years.length > n) {
                    years = years.slice(years.length - n);
                }
            }
            return years;
        }

        // GRAFICO DE BARRAS COMPARATIVO
        function dibujarGraficoLineas() {
            if (!Array.isArray(datosMensuales) || datosMensuales.length === 0) return;
            var etiquetas = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            var yearsMap = {};

            datosMensuales.forEach(function(item) {
                var p = String(item.periodo || '');
                var parts = p.split('-');
                if (parts.length !== 2) return;
                var anio = parseInt(parts[0], 10);
                var mes = parseInt(parts[1], 10);
                if (isNaN(anio) || isNaN(mes) || mes < 1 || mes > 12) return;
                if (!yearsMap[anio]) {
                    yearsMap[anio] = { pedidos: Array(12).fill(0), albaranes: Array(12).fill(0) };
                }
                yearsMap[anio].pedidos[mes - 1] = Number(item.pedidos || 0);
                yearsMap[anio].albaranes[mes - 1] = Number(item.albaranes || 0);
            });

            var years = obtenerAnosSeleccionados();
            var paleta = [
                { pedidos: '#0B5FFF', albaranes: '#7FAAFF' },
                { pedidos: '#E85D04', albaranes: '#FFB380' },
                { pedidos: '#2B9348', albaranes: '#95D5B2' },
                { pedidos: '#6A4C93', albaranes: '#CDB4DB' },
                { pedidos: '#B02A37', albaranes: '#F1AEB5' }
            ];

            var datasets = [];
            years.forEach(function(anio, idx) {
                if (!yearsMap[anio]) return;
                var colores = paleta[idx % paleta.length];
                datasets.push({ label: anio + ' - Pedidos', data: yearsMap[anio].pedidos, backgroundColor: colores.pedidos });
                datasets.push({ label: anio + ' - Albaranes', data: yearsMap[anio].albaranes, backgroundColor: colores.albaranes });
            });

            var ctx = document.getElementById('graficoLineas').getContext('2d');
            if (graficoComparativa) {
                graficoComparativa.destroy();
            }
            graficoComparativa = new Chart(ctx, {
                type: 'bar',
                data: { labels: etiquetas, datasets: datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        title: { display: true, text: 'Comparativa mensual por anio (Pedidos vs Albaranes)' },
                        datalabels: { display: false }
                    },
                    onClick: function(evt) {
                        var points = graficoComparativa.getElementsAtEventForMode(
                            evt,
                            'nearest',
                            { intersect: true },
                            true
                        );
                        if (!points || points.length === 0) return;

                        var p = points[0];
                        var dataset = graficoComparativa.data.datasets[p.datasetIndex];
                        var monthIndex = p.index;
                        var monthNum = String(monthIndex + 1).padStart(2, '0');
                        var monthName = graficoComparativa.data.labels[monthIndex];
                        var rawLabel = String(dataset.label || '');
                        var parts = rawLabel.split(' - ');
                        if (parts.length < 2) return;

                        var anio = parts[0].trim();
                        var keyMes = anio + '-' + monthNum;
                        var items = Array.isArray(detalleBarrasMap[keyMes]) ? detalleBarrasMap[keyMes] : [];

                        mostrarDetalleBarra(monthName + ' ' + anio, items);
                    },
                    scales: {
                        x: { title: { display: true, text: 'Mes' } },
                        y: { title: { display: true, text: 'Importe (EUR)' } }
                    }
                }
            });
        }

        // GRAFICO DE FAMILIA (pastel)
        function dibujarGraficoFamilia() {
            if (!Array.isArray(datosFamiliaMensual)) return;
            var years = obtenerAnosSeleccionados();
            var yearsSet = {};
            years.forEach(function(y) { yearsSet[y] = true; });

            var agrupado = {};
            datosFamiliaMensual.forEach(function(item) {
                var anio = parseInt(item.anio, 10);
                if (!yearsSet[anio]) return;
                var cod = String(item.cod_familia || '');
                if (!agrupado[cod]) {
                    agrupado[cod] = { familia: String(item.familia || cod), importe: 0, cod_familia: cod };
                }
                agrupado[cod].importe += Number(item.importe || 0);
            });

            var lista = Object.values(agrupado).sort(function(a, b) { return b.importe - a.importe; });
            var etiquetas = lista.map(function(x) { return x.familia; });
            var valores = lista.map(function(x) { return x.importe; });
            var bgColors = lista.map(function(x) { return colorFamilias[x.cod_familia] || '#CCCCCC'; });

            var ctx = document.getElementById('graficoFamilia').getContext('2d');
            if (graficoFamilia) {
                graficoFamilia.destroy();
            }
            graficoFamilia = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: etiquetas,
                    datasets: [{ data: valores, backgroundColor: bgColors }]
                },
                plugins: [ChartDataLabels],
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Ventas por Familia' },
                        datalabels: {
                            formatter: function (value, context) {
                                var idx = context.dataIndex;
                                if (idx < 3) {
                                    var label = context.chart.data.labels[idx];
                                    return label + '\n' + value.toFixed(2) + ' \u20AC';
                                }
                                return '';
                            },
                            color: '#FFFFFF',
                            align: 'center',
                            anchor: 'center',
                            font: { weight: 'bold', size: 12 }
                        }
                    }
                }
            });
        }

        // GRAFICO DE MARCA (Top 10)
        function dibujarGraficoMarca() {
            if (!Array.isArray(datosMarcaMensual)) return;
            var years = obtenerAnosSeleccionados();
            var yearsSet = {};
            years.forEach(function(y) { yearsSet[y] = true; });

            var agrupado = {};
            datosMarcaMensual.forEach(function(item) {
                var anio = parseInt(item.anio, 10);
                if (!yearsSet[anio]) return;
                var marca = String(item.marca || 'SIN MARCA');
                if (!agrupado[marca]) {
                    agrupado[marca] = { marca: marca, importe: 0, familias: {} };
                }
                var importe = Number(item.importe || 0);
                var cod = String(item.cod_familia || '');
                agrupado[marca].importe += importe;
                agrupado[marca].familias[cod] = (agrupado[marca].familias[cod] || 0) + importe;
            });

            var lista = Object.values(agrupado).sort(function(a, b) { return b.importe - a.importe; }).slice(0, 10);
            var etiquetas = [];
            var valores = [];
            var bgColors = [];

            lista.forEach(function(item) {
                etiquetas.push(item.marca);
                valores.push(item.importe);
                var codDominante = '';
                var maxFam = -1;
                Object.keys(item.familias).forEach(function(cod) {
                    if (item.familias[cod] > maxFam) {
                        maxFam = item.familias[cod];
                        codDominante = cod;
                    }
                });
                bgColors.push(colorFamilias[codDominante] || '#CCCCCC');
            });

            var ctx = document.getElementById('graficoMarca').getContext('2d');
            if (graficoMarca) {
                graficoMarca.destroy();
            }
            graficoMarca = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: etiquetas,
                    datasets: [{ data: valores, backgroundColor: bgColors }]
                },
                plugins: [ChartDataLabels],
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Marcas (Top 10)' },
                        datalabels: {
                            formatter: function (value, context) {
                                var idx = context.dataIndex;
                                if (idx < 3) {
                                    var label = context.chart.data.labels[idx];
                                    return label + '\n' + value.toFixed(2) + ' \u20AC';
                                }
                                return '';
                            },
                            color: '#FFFFFF',
                            align: 'center',
                            anchor: 'center',
                            font: { weight: 'bold', size: 12 }
                        }
                    }
                }
            });
        }

        // Comentario reparado
        function dibujarGraficoArticulos() {
            if (!Array.isArray(datosArticulosMensual)) return;
            var years = obtenerAnosSeleccionados();
            var yearsSet = {};
            years.forEach(function(y) { yearsSet[y] = true; });

            var agrupado = {};
            datosArticulosMensual.forEach(function(item) {
                var anio = parseInt(item.anio, 10);
                if (!yearsSet[anio]) return;
                var articulo = String(item.articulo || 'SIN ARTICULO');
                if (!agrupado[articulo]) {
                    agrupado[articulo] = { articulo: articulo, importe: 0, familias: {} };
                }
                var importe = Number(item.importe || 0);
                var cod = String(item.cod_familia || '');
                agrupado[articulo].importe += importe;
                agrupado[articulo].familias[cod] = (agrupado[articulo].familias[cod] || 0) + importe;
            });

            var lista = Object.values(agrupado).sort(function(a, b) { return b.importe - a.importe; }).slice(0, 10);
            var etiquetas = [];
            var valores = [];
            var bgColors = [];

            lista.forEach(function(item) {
                etiquetas.push(item.articulo);
                valores.push(item.importe);
                var codDominante = '';
                var maxFam = -1;
                Object.keys(item.familias).forEach(function(cod) {
                    if (item.familias[cod] > maxFam) {
                        maxFam = item.familias[cod];
                        codDominante = cod;
                    }
                });
                bgColors.push(colorFamilias[codDominante] || '#CCCCCC');
            });

            var ctx = document.getElementById('graficoArticulos').getContext('2d');
            if (graficoArticulos) {
                graficoArticulos.destroy();
            }
            graficoArticulos = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: etiquetas,
                    datasets: [{ data: valores, backgroundColor: bgColors }]
                },
                plugins: [ChartDataLabels],
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'ArtÃ­culos (Top 10)' },
                        datalabels: {
                            formatter: function (value, context) {
                                var idx = context.dataIndex;
                                if (idx < 3) {
                                    var label = context.chart.data.labels[idx];
                                    var maxLen = 15;
                                    if (label.length > maxLen) {
                                        label = label.substring(0, maxLen) + '...';
                                    }
                                    return label + '\n' + value.toFixed(2) + ' \u20AC';
                                }
                                return '';
                            },
                            color: '#FFFFFF',
                            align: 'center',
                            anchor: 'center',
                            font: { weight: 'bold', size: 12 }
                        }
                    }
                }
            });
        }

        // Comentario reparado
        function calcularPromedioVisita(cod_cliente, cod_seccion) {
            var xhr = null;
            if (window.XMLHttpRequest) {
                xhr = new XMLHttpRequest();
            } else if (window.ActiveXObject) {
                xhr = new ActiveXObject("Microsoft.XMLHTTP");
            }
            if (xhr == null) {
                return;
            }

            var url = '<?= BASE_URL ?>/ajax/calcular_promedio_visita.php?cod_cliente=' + encodeURIComponent(cod_cliente) +
                      '&cod_seccion=' + encodeURIComponent(cod_seccion || '');
            xhr.open("GET", url, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    var elem = document.getElementById("promedio_valor");
                    if (elem) {
                        elem.innerHTML = xhr.responseText;
                    }
                }
            };
            xhr.send(null);
        }

        window.onload = function() {
            asignarEventos();
            activarPaginacionVisitasAjax();
            var selector = document.getElementById('yearsWindow');
            if (selector) {
                selector.addEventListener('change', function() {
                    dibujarGraficoLineas();
                    dibujarGraficoFamilia();
                    dibujarGraficoMarca();
                    dibujarGraficoArticulos();
                });
            }
            dibujarGraficoLineas();
            dibujarGraficoFamilia();
            dibujarGraficoMarca();
            dibujarGraficoArticulos();
        };
    </script>
</head>
<body>
<?php include_once BASE_PATH . '/resources/views/layouts/header.php'; ?>
<div class="container">

    <?php if ($cliente['moroso'] === 'S'): ?>
        <p class="moroso">CLIENTE BLOQUEADO</p>
    <?php endif; ?>

    <!-- DATOS DEL CLIENTE -->
    <table>
        <tr>
            <th>C&oacute;digo</th>
            <td><?= htmlspecialchars((string)$cliente['cod_cliente']) ?></td>
            <th>Tarifa</th>
            <td><?= htmlspecialchars((string)$cliente['cod_tarifa']) ?></td>
        </tr>
        <tr>
            <th>Forma de Pago</th>
            <td><?= htmlspecialchars((string)$forma_pago) ?></td>
            <th>CIF</th>
            <td><?= htmlspecialchars((string)$cliente['cif']) ?></td>
        </tr>
        <tr>
            <th>Raz&oacute;n Social</th>
            <td colspan="3"><?= htmlspecialchars(toUTF8((string)$cliente['razon_social'])) ?></td>
        </tr>
        <tr>
            <th>Direcci&oacute;n</th>
            <td colspan="3"><?= htmlspecialchars((string)$cliente['direccion1']) ?></td>
        </tr>
        <tr>
            <th>Poblaci&oacute;n</th>
            <td><?= htmlspecialchars((string)$cliente['CP']) ?> - <?= htmlspecialchars((string)$cliente['poblacion']) ?></td>
            <th>Provincia</th>
            <td><?= htmlspecialchars((string)$cliente['provincia']) ?></td>
        </tr>
<!-- Comentario reparado -->
        <tr>
            <th>Tel&eacute;fonos</th>
            <td colspan="3">
                <?php 
                    $telefonos = [];
                    if (!empty($cliente['telefono'])) {
                        $telefonos[] = htmlspecialchars((string)$cliente['telefono']);
                    }
                    if (!empty($cliente['telefono2'])) {
                        $telefonos[] = htmlspecialchars((string)$cliente['telefono2']);
                    }
                    if (!empty($cliente['telefono3'])) {
                        $telefonos[] = htmlspecialchars((string)$cliente['telefono3']);
                    }
                    echo implode(', ', $telefonos);
                ?>
            </td>
        </tr>
        <tr>
            <th>Email</th>
            <td colspan="3">
                <a href="mailto:<?= htmlspecialchars((string)$cliente['e_mail']) ?>">
                    <?= htmlspecialchars((string)$cliente['e_mail']) ?>
                </a>
            </td>
        </tr>
        <?php if (!empty($cliente['advertencia'])): ?>
            <tr>
                <th>Advertencia</th>
                <td colspan="3"><?= htmlspecialchars((string)$cliente['advertencia']) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <?php if ($asignacion && count($secciones) <= 1): ?>
        <?php
        $nombreZonaPrincipal = '';
        if (!empty($asignacion['zona_principal'])) {
            $resZonaP = execPrepared(
                $conn,
                "SELECT nombre_zona FROM [integral].[dbo].[cmf_zonas_visita] WHERE cod_zona = ?",
                [(string)$asignacion['zona_principal']]
            );
            $zonaP = $resZonaP ? odbc_fetch_array($resZonaP) : false;
            $nombreZonaPrincipal = $zonaP ? (string)($zonaP['nombre_zona'] ?? '') : (string)$asignacion['zona_principal'];
        }

        $nombreZonaSecundaria = '';
        if (!empty($asignacion['zona_secundaria'])) {
            $resZonaS = execPrepared(
                $conn,
                "SELECT nombre_zona FROM [integral].[dbo].[cmf_zonas_visita] WHERE cod_zona = ?",
                [(string)$asignacion['zona_secundaria']]
            );
            $zonaS = $resZonaS ? odbc_fetch_array($resZonaS) : false;
            $nombreZonaSecundaria = $zonaS ? (string)($zonaS['nombre_zona'] ?? '') : (string)$asignacion['zona_secundaria'];
        }

        $freq = strtolower((string)($asignacion['frecuencia_visita'] ?? ''));
        switch ($freq) {
            case 'todos':
                $frecuenciaTexto = 'Todos los meses';
                break;
            case 'cada2':
                $frecuenciaTexto = 'Cada 2 meses';
                break;
            case 'cada3':
                $frecuenciaTexto = 'Cada 3 meses';
                break;
            case 'nunca':
                $frecuenciaTexto = 'No se visita normalmente';
                break;
            default:
                $frecuenciaTexto = htmlspecialchars((string)($asignacion['frecuencia_visita'] ?? ''));
                break;
        }

        $pref = strtolower((string)($asignacion['preferencia_horaria'] ?? ''));
        $estiloManana = ($pref === 'm' || $pref === 'maÃ±ana' || $pref === 'manana') ? 'background-color: #ffc107; padding:2px 4px;' : '';
        $estiloTarde  = ($pref === 't' || $pref === 'tarde') ? 'background-color: #007bff; color:#fff; padding:2px 4px;' : '';

        $tp = (float)($asignacion['tiempo_promedio_visita'] ?? 0);
        $horas = floor($tp);
        $minutos = round(($tp - $horas) * 60);
        if ($horas == 0 && $minutos > 0) {
            $tiempoPromedioTexto = $minutos . ' minutos';
        } elseif ($horas > 0) {
            $tiempoPromedioTexto = $horas . ' horas ' . $minutos . ' minutos';
        } else {
            $tiempoPromedioTexto = '0 minutos';
        }

        $horaInicioManana = substr((string)($asignacion['hora_inicio_manana'] ?? ''), 0, 5);
        $horaFinManana = substr((string)($asignacion['hora_fin_manana'] ?? ''), 0, 5);
        $horaInicioTarde = substr((string)($asignacion['hora_inicio_tarde'] ?? ''), 0, 5);
        $horaFinTarde = substr((string)($asignacion['hora_fin_tarde'] ?? ''), 0, 5);
        $observacionesAsign = trim((string)($asignacion['observaciones'] ?? ''));
        ?>
        <table style="width:100%; border:1px solid #ddd; background:#fdfdfd; margin-top:20px;">
            <tr>
                <th style="padding:8px;">Zona Principal</th>
                <td style="padding:8px;"><?= htmlspecialchars($nombreZonaPrincipal) ?></td>
            </tr>
            <?php if ($nombreZonaSecundaria !== ''): ?>
            <tr>
                <th style="padding:8px;">Zona Secundaria</th>
                <td style="padding:8px;"><?= htmlspecialchars($nombreZonaSecundaria) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th style="padding:8px;">Tiempo Promedio / Frecuencia</th>
                <td style="padding:8px;">
                    Tiempo Promedio:
                    <span id="promedio_valor"><?= htmlspecialchars($tiempoPromedioTexto) ?></span>
                    <button type="button" class="btn btn-sm btn-info" onclick="calcularPromedioVisita('<?= addslashes((string)$cod_cliente) ?>','<?= addslashes((string)($cod_seccion ?? '')) ?>')">Calcular</button>
                    &nbsp; | &nbsp;
                    Frecuencia: <strong><?= $frecuenciaTexto ?></strong>
                </td>
            </tr>
            <tr>
                <th style="padding:8px;">Horarios</th>
                <td style="padding:8px;">
                    <span style="<?= $estiloManana ?>">Ma&ntilde;ana: <?= htmlspecialchars($horaInicioManana) ?> - <?= htmlspecialchars($horaFinManana) ?></span>
                    &nbsp; | &nbsp;
                    <span style="<?= $estiloTarde ?>">Tarde: <?= htmlspecialchars($horaInicioTarde) ?> - <?= htmlspecialchars($horaFinTarde) ?></span>
                </td>
            </tr>
            <?php if ($observacionesAsign !== ''): ?>
            <tr>
                <th style="padding:8px;">Observaciones</th>
                <td style="padding:8px;"><?= htmlspecialchars($observacionesAsign) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td colspan="2" align="center" style="padding:8px;">
                    <a href="registrar_visita_manual.php?cod_cliente=<?= urlencode((string)$cod_cliente) ?>&cod_seccion=<?= urlencode((string)($cod_seccion ?? '')) ?>" class="btn btn-primary">Registrar Visita Manual</a>
                </td>
            </tr>
        </table>
    <?php endif; ?>

    <?php
    // Contactos: ver quÃ© campos tienen algÃºn dato
    $campos = [
        'nombre'           => 'Nombre',
        'cargo'            => 'Cargo',
        'telefono'         => 'TelÃ©fono',
        'telefono_movil'   => 'MÃ³vil',
        'e_mail'           => 'Email',
        'observaciones'    => 'Observaciones'
    ];
    $camposConDatos = [];
    foreach ($campos as $campo => $titulo) {
        foreach ($contactos as $contacto) {
            if (!empty($contacto[$campo])) {
                $camposConDatos[$campo] = $titulo;
                break;
            }
        }
    }
    ?>
    <?php if (count($camposConDatos) > 0): ?>
        <h3>Contactos</h3>
        <table>
            <thead>
                <tr>
                    <?php foreach ($camposConDatos as $titulo): ?>
                        <th><?= htmlspecialchars($titulo) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contactos as $contacto): ?>
                    <tr>
                        <?php foreach ($camposConDatos as $campo => $titulo): ?>
                            <td>
                                <?php
                                $valor = toUTF8($contacto[$campo] ?? '');
                                if ($campo === 'telefono_movil' && !empty($valor)) {
                                    // Enlazar a WhatsApp
                                    $numeroWhatsapp = preg_replace('/\D/', '', $valor);
                                    if (substr($numeroWhatsapp, 0, 2) !== "34") {
                                        $numeroWhatsapp = "34" . $numeroWhatsapp;
                                    }
                                    echo htmlspecialchars($valor) . ' ';
                                    echo '<a href="https://wa.me/' . htmlspecialchars($numeroWhatsapp) . '" target="_blank">';
                                    echo '<i class="fa-brands fa-whatsapp"></i>';
                                    echo '</a>';
                                } else {
                                    echo htmlspecialchars($valor);
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Secciones -->
    <?php if (count($secciones) > 0): ?>
        <table>
            <tr>
                <th>Secci&oacute;n</th>
                <th>Acciones</th>
            </tr>
            <?php foreach ($secciones as $sec): ?>
                <tr>
                    <td>
                        <a href="seccion_detalles.php?cod_cliente=<?= urlencode($cod_cliente) ?>&cod_seccion=<?= urlencode($sec['cod_seccion']) ?>">
                            <?= htmlspecialchars((string)$sec['nombre']) ?>
                        </a>
                    </td>
                    <td>
                        <a href="faltas.php?origen=cliente_detalles.php&cod_cliente=<?= urlencode($cod_cliente) ?>&cod_seccion=<?= urlencode($sec['cod_seccion']) ?>" class="faltas-button">
                           Faltas
                        </a>
                        <a href="historico.php?cod_cliente=<?= urlencode($cod_cliente) ?>&cod_seccion=<?= urlencode($sec['cod_seccion']) ?>" class="historico-button">
                           Hist&oacute;rico de Ventas
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <div class="button-container">
            <a href="faltas.php?origen=cliente_detalles.php&cod_cliente=<?= urlencode($cod_cliente) ?>" class="faltas-button">
               Faltas
            </a>
            <a href="historico.php?cod_cliente=<?= urlencode($cod_cliente) ?>" class="historico-button">
               Hist&oacute;rico de Ventas
            </a>
        </div>
    <?php endif; ?>


    <!-- GRAFICO COMPARATIVO MENSUAL -->
    <div style="margin-top: 40px;">
        <div style="display:flex;justify-content:flex-end;align-items:center;gap:8px;margin-bottom:10px;">
            <label for="yearsWindow" style="font-weight:600;">A&ntilde;os:</label>
                        <select id="yearsWindow" class="form-select form-select-sm" style="width:auto;">
                <option value="2" selected>Ultimos 2</option>
                <option value="3">Ultimos 3</option>
                <option value="4">Ultimos 4</option>
                <option value="all">Todos</option>
            </select>
        </div>
        <div style="position:relative;height:420px;">
            <canvas id="graficoLineas"></canvas>
        </div>
    </div>
    <div id="modal-detalle-barras" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modal-detalle-barras')">&times;</span>
            <h3 id="detalleBarrasTitulo">Detalle</h3>
            <div id="detalleBarrasContenido"></div>
        </div>
    </div>

<!-- Comentario reparado -->
    <div class="row" style="margin-top: 30px;">
<!-- Comentario reparado -->
        <div class="col-12 col-md-4 chart-col">
            <h4 style="text-align:center;"><br></h4>
            <canvas id="graficoFamilia" width="300" height="300"></canvas>
        </div>

<!-- Comentario reparado -->
        <div class="col-12 col-md-4 chart-col">
            <h4 style="text-align:center;"><br></h4>
            <canvas id="graficoMarca" width="300" height="300"></canvas>
        </div>

<!-- Comentario reparado -->
        <div class="col-12 col-md-4 chart-col">
            <h4 style="text-align:center;"><br></h4>
            <canvas id="graficoArticulos" width="300" height="300"></canvas>
        </div>
    </div>

    <div class="button-container">
        <a href="clientes.php" class="back-button">&larr; Volver a la lista de clientes</a>
    </div>

    <!-- VISITAS y PEDIDOS (funcionalidad extra, con modales) -->
    <?php if ($mostrar_nueva_funcionalidad): ?>
        <div class="visitas-container">
            <h2>Visitas del Cliente (<?= (int)$totalVisitas ?>)</h2>
            <?php if ($totalVisitas > 0): ?>
                <?php foreach ($visitasPaginadas as $visita): ?>
                    <div class="visita-item" style="background-color: <?= htmlspecialchars((string)$visita['color']) ?>;" data-id-visita="<?= htmlspecialchars((string)$visita['id_visita']) ?>">
                        <div class="visita-linea">
                            <span class="visita-fecha">
                                &#128197; <?= htmlspecialchars(date("d/m/Y", strtotime((string)$visita['fecha_visita']))) ?>
                                (<?= obtenerDiaSemana((string)$visita['fecha_visita']) ?>)
                            </span>
                            <span class="visita-horas">
                                &#9200; <?= htmlspecialchars(date("H:i", strtotime((string)$visita['hora_inicio_visita']))) ?>
                                - <?= htmlspecialchars(date("H:i", strtotime((string)$visita['hora_fin_visita']))) ?>
                            </span>
                            <span class="visita-importe">
                                &#128176; <?= number_format((float)$visita['importe_total'], 2, ',', '.') ?> &euro;
                            </span>
                            <span class="visita-lineas">
                                &#128221; <?= htmlspecialchars((string)$visita['numero_lineas_total']) ?>
                            </span>
                        </div>
                        <?php if (!empty($visita['observaciones'])): ?>
                            <br>
                            <span class="visita-observaciones">
                                &#9999; <?= htmlspecialchars((string)$visita['observaciones']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Modal de Visita -->
                    <div id="modal-visita-<?= htmlspecialchars((string)$visita['id_visita']) ?>" class="modal">
                        <div class="modal-content">
                            <span class="close" onclick="cerrarModal('modal-visita-<?= htmlspecialchars((string)$visita['id_visita']) ?>')">&times;</span>
                            <h3>Detalles de la Visita <?= htmlspecialchars((string)$visita['id_visita']) ?></h3>
                            <p><strong>&#128197; Fecha:</strong> <?= htmlspecialchars(date("d/m/Y", strtotime((string)$visita['fecha_visita']))) ?> (<?= obtenerDiaSemana((string)$visita['fecha_visita']) ?>)</p>
                            <p><strong>&#9200; Hora de Inicio:</strong> <?= htmlspecialchars(date("H:i", strtotime((string)$visita['hora_inicio_visita']))) ?></p>
                            <p><strong>&#9200; Hora de Fin:</strong> <?= htmlspecialchars(date("H:i", strtotime((string)$visita['hora_fin_visita']))) ?></p>
                            <p><strong>&#128176; Importe Total:</strong> <?= number_format((float)$visita['importe_total'], 2, ',', '.') ?> &euro;</p>
                            <p><strong>&#128221; N&uacute;mero de L&iacute;neas:</strong> <?= htmlspecialchars((string)$visita['numero_lineas_total']) ?></p>
                            <p><strong>&#128221; Observaciones:</strong> <?= htmlspecialchars((string)$visita['observaciones']) ?></p>
                            
                            <h4>L&iacute;neas de Pedidos Asociados</h4>
                            <?php
                            // Comentario reparado
                            $sql_lineas_visita = "
                                SELECT 
                                    hl.cod_articulo,
                                    hl.descripcion,
                                    hl.precio AS precio,
                                    hl.cantidad,
                                    hl.dto1,
                                    hl.dto2,
                                    hl.importe,
                                    elv.cantidad AS cantidad_servida,
                                    hvc_dest.fecha_venta AS fecha_entrega
                                FROM [integral].[dbo].[hist_ventas_linea] hl
                                INNER JOIN [integral].[dbo].[cmf_visita_pedidos] vp
                                   ON hl.cod_venta = vp.cod_venta
                                INNER JOIN [integral].[dbo].[hist_ventas_cabecera] hvc 
                                   ON hl.cod_venta = hvc.cod_venta
                                LEFT JOIN [integral].[dbo].[entrega_lineas_venta] elv
                                   ON hl.cod_venta = elv.cod_venta_origen 
                                  AND hl.linea = elv.linea_origen
                                LEFT JOIN [integral].[dbo].[hist_ventas_cabecera] hvc_dest
                                   ON elv.cod_venta_destino = hvc_dest.cod_venta 
                                  AND elv.tipo_venta_destino = hvc_dest.tipo_venta
                                WHERE vp.id_visita = '" . sqlLiteral((string)$visita['id_visita']) . "'
                                  AND hl.tipo_venta = 1
                                ORDER BY hl.descripcion
                            ";
                            $result_lineas_visita = odbc_exec($conn, $sql_lineas_visita);
                            if ($result_lineas_visita):
                            ?>
                                <div class="modal-table-container">
                                    <table class="modal-table">
                                        <thead>
                                            <tr>
                                                <th>Art&iacute;culo</th>
                                                <th>Descripci&oacute;n</th>
                                                <th>Cantidad</th>
                                                <th>Cantidad Servida</th>
                                                <th>Precio (&euro;)</th>
                                                <th>Dto1 (%)</th>
                                                <th>Dto2 (%)</th>
                                                <th>Importe (&euro;)</th>
                                                <th>Fecha de Entrega</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $linea_ids = [];
                                            while ($linea = odbc_fetch_array($result_lineas_visita)):
                                                $unique_id = $linea['cod_articulo'].'-'.$linea['descripcion'].'-'.$linea['cantidad'];
                                                if (in_array($unique_id, $linea_ids)) continue;
                                                $linea_ids[] = $unique_id;
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string)$linea['cod_articulo']) ?></td>
                                                    <td class="descripcion-con-observacion">
                                                        <?= htmlspecialchars((string)$linea['descripcion']) ?>
                                                    </td>
                                                    <td><?= number_format((float)$linea['cantidad'], 2, ',', '.') ?></td>
                                                    <td style="<?= ((float)$linea['cantidad_servida'] != (float)$linea['cantidad']) ? 'color: red;' : '' ?>">
                                                        <?= number_format((float)$linea['cantidad_servida'], 2, ',', '.') ?>
                                                    </td>
                                                    <td><?= number_format((float)$linea['precio'], 2, ',', '.') . " &euro;" ?></td>
                                                    <td><?= ((float)$linea['dto1'] != 0) ? htmlspecialchars((string)$linea['dto1']) . " %" : "-" ?></td>
                                                    <td><?= ((float)$linea['dto2'] != 0) ? htmlspecialchars((string)$linea['dto2']) . " %" : "-" ?></td>
                                                    <td><?= number_format((float)$linea['importe'], 2, ',', '.') . " &euro;" ?></td>
                                                    <td><?= !empty($linea['fecha_entrega']) ? htmlspecialchars(date("d/m/Y", strtotime((string)$linea['fecha_entrega']))) : "-" ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p>No hay l&iacute;neas asociadas a esta visita.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pedidos Asociados -->
                    <?php if (isset($visita['pedidos']) && count($visita['pedidos']) > 0): ?>
                        <?php foreach ($visita['pedidos'] as $pedido): ?>
                            <div class="pedido-item" style="border-left: 8px solid <?= htmlspecialchars((string)determinarColorPedido($pedido['origen'])) ?>;" data-cod-pedido="<?= htmlspecialchars((string)$pedido['cod_pedido']) ?>">
                                <div class="pedido-content">
                                    <div class="pedido-info">
                                        <div>
                                            <?= iconoDeOrigen((string)$pedido['origen']) ?>
                                            <?= htmlspecialchars((string)$pedido['cod_pedido']) ?>
                                            <?php if (((int)($pedido['pedido_eliminado'] ?? 0)) === 1): ?>
                                                <span class="label label-warning" style="margin-left:8px;">Eliminado</span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong>&#128197;</strong> <?= htmlspecialchars(date("d/m/Y", strtotime((string)$pedido['fecha_venta']))) ?> (<?= obtenerDiaSemana((string)$pedido['fecha_venta']) ?>)
                                        </div>
                                        <div>
                                            <strong>&#9200;</strong> <?= htmlspecialchars(date("H:i", strtotime((string)$pedido['hora_venta']))) ?>
                                        </div>
                                        <div>
                                            <strong>&#128176;</strong> <?= number_format((float)$pedido['importe'], 2, ',', '.') . " &euro;" ?>
                                        </div>
                                        <div>
                                            <strong>&#128221;</strong> <?= htmlspecialchars((string)$pedido['numero_lineas']) ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($pedido['observacion_interna'])): ?>
                                        <div class="pedido-observaciones">
                                            &#9999; <?= htmlspecialchars((string)$pedido['observacion_interna']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Modal de Pedido -->
                            <div id="modal-pedido-<?= htmlspecialchars((string)$pedido['cod_pedido']) ?>" class="modal">
                                <div class="modal-content">
                                    <span class="close" onclick="cerrarModal('modal-pedido-<?= htmlspecialchars((string)$pedido['cod_pedido']) ?>')">&times;</span>
                                    <div class="pedido-actions">
                                        <button class="btn btn-circle btn-eliminar" title="Quitar pedido de la visita" onclick="quitarPedido('<?= htmlspecialchars((string)$pedido['cod_pedido']) ?>', event)">
                                            <i class="fa fa-calendar-times"></i>
                                        </button>
                                        <?php 
                                        $origen_actual = strtolower((string)$pedido['origen']);
                                        $opciones = [
                                            'visita'    => 'btn-visita',
                                            'telefono'  => 'btn-telefono',
                                            'whatsapp'  => 'btn-whatsapp',
                                            'email'     => 'btn-email'
                                        ];
                                        foreach ($opciones as $opcion => $btn_class):
                                            $disabled = ($origen_actual === $opcion) ? 'disabled style="background-color: grey;"' : '';
                                        ?>
                                            <button class="btn btn-circle <?= $btn_class ?>" title="Cambiar origen a <?= ucfirst($opcion) ?>" onclick="actualizarOrigen('<?= htmlspecialchars((string)$pedido['cod_pedido']) ?>', '<?= $opcion ?>', event)" <?= $disabled; ?>>
                                                <?php 
                                                $iconos = [
                                                    'visita'   => 'fa-solid fa-calendar',
                                                    'telefono' => 'fa-solid fa-phone',
                                                    'whatsapp' => 'fa-brands fa-whatsapp',
                                                    'email'    => 'fa-solid fa-envelope'
                                                ];
                                                echo '<i class="'.$iconos[$opcion].'"></i>';
                                                ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <h3>Detalles del Pedido <?= htmlspecialchars((string)$pedido['cod_pedido']) ?></h3>
                                    <?php if (((int)($pedido['pedido_eliminado'] ?? 0)) === 1): ?>
                                        <p><span class="label label-warning">Pedido eliminado</span></p>
                                    <?php endif; ?>
                                    <p>
                                        <strong>&#128197; Fecha de Venta:</strong> <?= htmlspecialchars(date("d/m/Y", strtotime((string)$pedido['fecha_venta']))) ?> (<?= obtenerDiaSemana((string)$pedido['fecha_venta']) ?>)
                                    </p>
                                    <p>
                                        <strong>&#9200; Hora de Venta:</strong> <?= htmlspecialchars(date("H:i", strtotime((string)$pedido['hora_venta']))) ?>
                                    </p>
                                    <p>
                                        <strong>&#128176; Importe:</strong> <?= number_format((float)$pedido['importe'], 2, ',', '.') . " &euro;" ?>
                                    </p>
                                    <p>
                                        <strong>&#128221; N&uacute;mero de L&iacute;neas:</strong> <?= htmlspecialchars((string)$pedido['numero_lineas']) ?>
                                    </p>
                                    <?php if (!empty($pedido['observacion_interna'])): ?>
                                        <p>
                                            <strong>&#9999; Observaciones Internas:</strong> <?= htmlspecialchars((string)$pedido['observacion_interna']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <h4>L&iacute;neas del Pedido</h4>
                                    <?php
                                    $pedidoEliminado = ((int)($pedido['pedido_eliminado'] ?? 0) === 1);
                                    if ($pedidoEliminado) {
                                        $sql_lineas_pedido = "
                                            SELECT 
                                                vle.cod_articulo,
                                                vle.descripcion,
                                                vle.precio,
                                                vle.cantidad,
                                                vle.dto1,
                                                vle.dto2,
                                                vle.importe,
                                                NULL AS cantidad_servida,
                                                NULL AS fecha_entrega
                                            FROM [integral].[dbo].[ventas_linea_elim] vle
                                            WHERE vle.cod_venta = '" . sqlLiteral((string)$pedido['cod_pedido']) . "'
                                              AND vle.tipo_venta = 1
                                            ORDER BY vle.descripcion
                                        ";
                                    } else {
                                        $sql_lineas_pedido = "
                                            SELECT 
                                                hl.cod_articulo,
                                                hl.descripcion,
                                                hl.precio AS precio,
                                                hl.cantidad,
                                                hl.dto1,
                                                hl.dto2,
                                                hl.importe,
                                                elv.cantidad AS cantidad_servida,
                                                hvc_dest.fecha_venta AS fecha_entrega
                                            FROM [integral].[dbo].[hist_ventas_linea] hl
                                            LEFT JOIN [integral].[dbo].[entrega_lineas_venta] elv
                                               ON hl.cod_venta = elv.cod_venta_origen 
                                              AND hl.linea     = elv.linea_origen
                                            LEFT JOIN [integral].[dbo].[hist_ventas_cabecera] hvc_dest
                                               ON elv.cod_venta_destino = hvc_dest.cod_venta 
                                              AND elv.tipo_venta_destino = hvc_dest.tipo_venta
                                            WHERE hl.cod_venta = '" . sqlLiteral((string)$pedido['cod_pedido']) . "'
                                              AND hl.tipo_venta = 1
                                            ORDER BY hl.descripcion
                                        ";
                                    }
                                    $result_lineas_pedido = odbc_exec($conn, $sql_lineas_pedido);
                                    if ($result_lineas_pedido):
                                    ?>
                                        <div class="modal-table-container">
                                            <table class="modal-table">
                                                <thead>
                                                    <tr>
                                                        <th>Art&iacute;culo</th>
                                                        <th>Descripci&oacute;n</th>
                                                        <th>Cantidad</th>
                                                        <th>Cantidad Servida</th>
                                                        <th>Precio (&euro;)</th>
                                                        <th>Dto1 (%)</th>
                                                        <th>Dto2 (%)</th>
                                                        <th>Importe (&euro;)</th>
                                                        <th>Fecha de Entrega</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $linea_ids_pedido = [];
                                                    while ($linea = odbc_fetch_array($result_lineas_pedido)):
                                                        $unique_id_pedido = $linea['cod_articulo'].'-'.$linea['descripcion'].'-'.$linea['cantidad'];
                                                        if (in_array($unique_id_pedido, $linea_ids_pedido)) continue;
                                                        $linea_ids_pedido[] = $unique_id_pedido;
                                                    ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars((string)$linea['cod_articulo']) ?></td>
                                                            <td class="descripcion-con-observacion">
                                                                <?= htmlspecialchars((string)$linea['descripcion']) ?>
                                                            </td>
                                                            <td><?= number_format((float)$linea['cantidad'], 2, ',', '.') ?></td>
                                                            <td style="<?= (!$pedidoEliminado && (float)$linea['cantidad_servida'] != (float)$linea['cantidad']) ? 'color: red;' : '' ?>">
                                                                <?= $pedidoEliminado ? '-' : number_format((float)$linea['cantidad_servida'], 2, ',', '.') ?>
                                                            </td>
                                                            <td><?= number_format((float)$linea['precio'], 2, ',', '.') . " &euro;" ?></td>
                                                            <td><?= ((float)$linea['dto1'] != 0) ? htmlspecialchars((string)$linea['dto1']) . " %" : "-" ?></td>
                                                            <td><?= ((float)$linea['dto2'] != 0) ? htmlspecialchars((string)$linea['dto2']) . " %" : "-" ?></td>
                                                            <td><?= number_format((float)$linea['importe'], 2, ',', '.') . " &euro;" ?></td>
                                                            <td><?= !$pedidoEliminado && !empty($linea['fecha_entrega']) ? htmlspecialchars(date("d/m/Y", strtotime((string)$linea['fecha_entrega']))) : "-" ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p>No hay l&iacute;neas asociadas a este pedido.</p>
                                    <?php endif; ?>
                                    <?php if ($pedidoEliminado): ?>
                                        <p style="margin-top:10px;color:#b91c1c;font-weight:600;">
                                            VENTA ELIMINADA
                                            <?= !empty($pedido['eliminado_por_usuario']) ? ' POR ' . htmlspecialchars((string)$pedido['eliminado_por_usuario']) : '' ?>
                                            <?= !empty($pedido['eliminado_por_equipo']) ? ' | EQUIPO: ' . htmlspecialchars((string)$pedido['eliminado_por_equipo']) : '' ?>
                                            <?= !empty($pedido['eliminado_fecha']) ? ' | FECHA: ' . htmlspecialchars(date("d/m/Y", strtotime((string)$pedido['eliminado_fecha']))) . ' (' . obtenerDiaSemana((string)$pedido['eliminado_fecha']) . ')' : '' ?>
                                            <?= !empty($pedido['eliminado_hora']) ? ' | HORA: ' . htmlspecialchars(date("H:i", strtotime((string)$pedido['eliminado_hora']))) : '' ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($totalPaginasVisitas > 1): ?>
                    <?php
                    $queryVisitas = $_GET;
                    unset($queryVisitas['pag_visitas']);
                    $baseVisitas = basename((string)($_SERVER['PHP_SELF'] ?? 'cliente_detalles.php'));
                    ?>
                    <div class="button-container" style="margin-top: 10px;">
                        <?php if ($paginaVisitas > 1): ?>
                            <a class="back-button" style="margin-right:8px;" href="<?= htmlspecialchars($baseVisitas . '?' . http_build_query(array_merge($queryVisitas, ['pag_visitas' => $paginaVisitas - 1]))) ?>">&larr; Anterior</a>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $totalPaginasVisitas; $p++): ?>
                            <?php if ($p === $paginaVisitas): ?>
                                <span class="back-button" style="background:#6c757d;cursor:default;margin-right:8px;"><?= $p ?></span>
                            <?php else: ?>
                                <a class="back-button" style="margin-right:8px;" href="<?= htmlspecialchars($baseVisitas . '?' . http_build_query(array_merge($queryVisitas, ['pag_visitas' => $p]))) ?>"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($paginaVisitas < $totalPaginasVisitas): ?>
                            <a class="back-button" href="<?= htmlspecialchars($baseVisitas . '?' . http_build_query(array_merge($queryVisitas, ['pag_visitas' => $paginaVisitas + 1]))) ?>">Siguiente &rarr;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="button-container">
                    <a href="clientes.php" class="back-button">&larr; Volver a la lista de clientes</a>
                </div>
            <?php else: ?>
                <p>No hay visitas registradas para este cliente.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- jQuery (local via Composer assets) -->
<!-- Bootstrap 5 JS Bundle (includes Popper, local via Composer assets) -->
<?php
// Comentario reparado
?>
<script src="<?= BASE_URL ?>/assets/js/app-ui.js"></script>
</body>
</html>





