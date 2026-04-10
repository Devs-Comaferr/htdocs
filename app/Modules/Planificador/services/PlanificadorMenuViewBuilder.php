<?php

if (!function_exists('planificadorMenuObtenerConteo')) {
    function planificadorMenuObtenerConteo($conn, string $sql, array $params = []): ?int
    {
        $stmt = @odbc_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }

        if (!@odbc_execute($stmt, $params)) {
            return null;
        }

        $fila = @odbc_fetch_array($stmt);
        if (!$fila) {
            return null;
        }

        $total = $fila['total'] ?? $fila['TOTAL'] ?? null;
        if ($total === null) {
            return null;
        }

        return (int)$total;
    }
}

if (!function_exists('planificadorMenuObtenerResumenVisitas')) {
    function planificadorMenuObtenerResumenVisitas($conn, int $codVendedor, string $fechaInicio, string $fechaFinExclusiva, int $codZona = 0): array
    {
        if ($codVendedor <= 0 || $fechaInicio === '' || $fechaFinExclusiva === '') {
            return ['total' => 0, 'realizadas' => 0, 'pendientes' => 0];
        }

        $sql = "
            SELECT
                v.id_visita,
                v.cod_cliente,
                v.estado_visita
            FROM cmf_comerciales_visitas v
            WHERE v.cod_vendedor = ?
              AND CONVERT(varchar(10), v.fecha_visita, 120) >= ?
              AND CONVERT(varchar(10), v.fecha_visita, 120) < ?
        ";

        $params = [$codVendedor, $fechaInicio, $fechaFinExclusiva];

        if ($codZona > 0) {
            $sql .= "
              AND (
                    v.cod_zona_visita = ?
                    OR EXISTS (
                        SELECT 1
                        FROM cmf_comerciales_clientes_zona cz
                        WHERE cz.cod_cliente = v.cod_cliente
                          AND (cz.zona_principal = ? OR cz.zona_secundaria = ?)
                    )
              )
            ";
            $params[] = $codZona;
            $params[] = $codZona;
            $params[] = $codZona;
        }

        $stmt = @odbc_prepare($conn, $sql);
        if (!$stmt || !@odbc_execute($stmt, $params)) {
            return ['total' => 0, 'realizadas' => 0, 'pendientes' => 0];
        }

        $visitas = [];
        $idsVisita = [];
        while ($fila = @odbc_fetch_array($stmt)) {
            $idVisita = (int)($fila['id_visita'] ?? $fila['ID_VISITA'] ?? 0);
            if ($idVisita <= 0) {
                continue;
            }

            $estado = strtolower(trim((string)($fila['estado_visita'] ?? $fila['ESTADO_VISITA'] ?? '')));
            $visitas[$idVisita] = [
                'estado' => $estado,
                'tiene_visita_pedido' => false,
            ];
            $idsVisita[] = $idVisita;
        }

        if (empty($visitas)) {
            return ['total' => 0, 'realizadas' => 0, 'pendientes' => 0];
        }

        $idsSql = implode(',', array_map('intval', array_unique($idsVisita)));
        $sqlPedidos = "
            SELECT DISTINCT vp.id_visita
            FROM cmf_comerciales_visitas_pedidos vp
            WHERE vp.id_visita IN ($idsSql)
              AND LOWER(LTRIM(RTRIM(ISNULL(vp.origen, '')))) = 'visita'
        ";
        $resultPedidos = @odbc_exec($conn, $sqlPedidos);
        if ($resultPedidos) {
            while ($filaPedido = @odbc_fetch_array($resultPedidos)) {
                $idVisita = (int)($filaPedido['id_visita'] ?? $filaPedido['ID_VISITA'] ?? 0);
                if ($idVisita > 0 && isset($visitas[$idVisita])) {
                    $visitas[$idVisita]['tiene_visita_pedido'] = true;
                }
            }
        }

        $resumen = ['total' => 0, 'realizadas' => 0, 'pendientes' => 0];
        foreach ($visitas as $visita) {
            $estado = $visita['estado'];
            $tieneVisitaPedido = !empty($visita['tiene_visita_pedido']);

            $esTotal = ($estado === 'realizada' && $tieneVisitaPedido)
                || in_array($estado, ['planificada', 'pendiente', 'no atendida'], true);
            $esRealizada = ($estado === 'realizada' && $tieneVisitaPedido)
                || $estado === 'no atendida';
            $esPendiente = in_array($estado, ['planificada', 'pendiente'], true);

            if ($esTotal) {
                $resumen['total']++;
            }
            if ($esRealizada) {
                $resumen['realizadas']++;
            }
            if ($esPendiente) {
                $resumen['pendientes']++;
            }
        }

        return $resumen;
    }
}

if (!function_exists('planificadorBuildMenuViewData')) {
    function planificadorBuildMenuViewData(): array
    {
        $conn = db();
        $codVendedorMenu = obtenerCodVendedorPlanificacionService();
        $fechaHoyMenu = date('Y-m-d');

        $resumenHoy = planificadorMenuObtenerResumenVisitas(
            $conn,
            $codVendedorMenu,
            $fechaHoyMenu,
            date('Y-m-d', strtotime($fechaHoyMenu . ' +1 day'))
        );

        $totalPedidosSinAsignar = planificadorMenuObtenerConteo(
            $conn,
            "SELECT COUNT(*) AS total
             FROM hist_ventas_cabecera h
             LEFT JOIN cmf_comerciales_visitas_pedidos vp ON h.cod_venta = vp.cod_venta
             WHERE vp.cod_venta IS NULL
               AND h.tipo_venta = 1
               AND h.fecha_venta >= '2025-01-01'
               AND h.cod_comisionista = ?",
            [$codVendedorMenu]
        );

        $zonasCiclo = obtenerZonasVisita() ?? [];
        $totalZonasActivas = count($zonasCiclo);
        $zonaActiva = obtenerZonaActivaHoyService();
        $zonaActivaId = (int)($zonaActiva['cod_zona'] ?? 0);
        $nombreZonaActiva = trim((string)($zonaActiva['nombre'] ?? ''));
        if ($nombreZonaActiva === '') {
            $nombreZonaActiva = 'No definida';
        }

        $clienteRecomendado = obtenerSiguienteClienteRecomendadoService($zonaActivaId);
        $nombreClienteRecomendado = trim((string)toUTF8($clienteRecomendado['nombre'] ?? ''));
        $motivoClienteRecomendado = trim((string)($clienteRecomendado['motivo'] ?? ''));

        $contextoZonaActiva = function_exists('obtenerZonaActivaPorFecha')
            ? obtenerZonaActivaPorFecha($conn, $codVendedorMenu, $fechaHoyMenu)
            : null;
        $inicioCicloActual = !empty($contextoZonaActiva['ciclo_actual_inicio_ts'])
            ? date('Y-m-d', (int)$contextoZonaActiva['ciclo_actual_inicio_ts'])
            : '';
        $finCicloActual = !empty($contextoZonaActiva['ciclo_actual_fin_ts'])
            ? date('Y-m-d', (int)$contextoZonaActiva['ciclo_actual_fin_ts'])
            : '';
        $resumenCicloActual = ($inicioCicloActual !== '' && $finCicloActual !== '')
            ? planificadorMenuObtenerResumenVisitas($conn, $codVendedorMenu, $inicioCicloActual, $finCicloActual)
            : ['total' => 0, 'realizadas' => 0, 'pendientes' => 0];

        $pedidosSinAsignarCriticos = ($totalPedidosSinAsignar ?? 0) > 0;
        $pendientesHoy = (int)($resumenHoy['pendientes'] ?? 0);
        $visitasHoy = (int)($resumenHoy['total'] ?? 0);
        $realizadasHoy = (int)($resumenHoy['realizadas'] ?? 0);
        $progresoHoy = (int)(($visitasHoy > 0) ? ($realizadasHoy / $visitasHoy) * 100 : 0);

        $visitasZonaActual = (int)($resumenCicloActual['total'] ?? 0);
        $realizadasZonaActual = (int)($resumenCicloActual['realizadas'] ?? 0);
        $pendientesZonaActual = (int)($resumenCicloActual['pendientes'] ?? 0);
        $progresoZonaActual = (int)(($visitasZonaActual > 0) ? ($realizadasZonaActual / $visitasZonaActual) * 100 : 0);
        $rangoCicloActualLabel = ($inicioCicloActual !== '' && $finCicloActual !== '')
            ? date('d/m', strtotime($inicioCicloActual)) . ' - ' . date('d/m', strtotime($finCicloActual . ' -1 day'))
            : 'Ciclo no definido';

        $cards = [
            [
                'key' => 'calendar',
                'href' => 'mostrar_calendario.php',
                'label' => 'Calendario',
                'icon_class' => 'fas fa-calendar-alt',
                'icon_wrapper' => 'icon-blue',
                'title' => 'Calendario',
                'description' => 'Consulta la agenda diaria y revisa la planificacion operativa.',
                'cta' => 'Abrir vista',
                'metric_value' => (string)$visitasHoy,
                'metric_label' => 'visitas hoy',
                'metric_class' => $visitasHoy > 0 ? 'metric-ok' : '',
                'status_text' => null,
                'status_class' => '',
                'card_class' => 'card-ok',
                'importance' => 2,
            ],
            [
                'key' => 'complete',
                'href' => 'completar_dia.php',
                'label' => 'Completar dia',
                'icon_class' => 'fas fa-check-circle',
                'icon_wrapper' => 'icon-red',
                'title' => 'Completar dia',
                'description' => 'Cierra la jornada y valida el trabajo pendiente.',
                'cta' => 'Finalizar jornada',
                'metric_value' => (string)$pendientesHoy,
                'metric_label' => 'sugerencias',
                'metric_class' => $pendientesHoy > 0 ? 'metric-warning' : 'metric-ok',
                'status_text' => null,
                'status_class' => '',
                'card_class' => $pendientesHoy > 0 ? 'card-warning' : 'card-ok',
                'importance' => 1,
            ],
            [
                'key' => 'orders',
                'href' => 'visita_pedido.php',
                'label' => 'Visita por pedidos',
                'icon_class' => 'fa-solid fa-pen-to-square',
                'icon_wrapper' => 'icon-green',
                'title' => 'Visita por pedidos',
                'description' => 'Relaciona pedidos con visitas desde el flujo comercial.',
                'cta' => 'Gestionar pedidos',
                'metric_value' => $totalPedidosSinAsignar !== null ? (string)$totalPedidosSinAsignar : null,
                'metric_label' => 'pedidos',
                'metric_class' => $pedidosSinAsignarCriticos ? 'metric-critical' : 'metric-ok',
                'status_text' => null,
                'status_class' => '',
                'card_class' => $pedidosSinAsignarCriticos ? 'card-critical' : 'card-ok',
                'importance' => $pedidosSinAsignarCriticos ? 1 : 3,
            ],
            [
                'key' => 'manual',
                'href' => 'visita_manual.php',
                'label' => 'Registrar visita manual',
                'icon_class' => 'fas fa-edit',
                'icon_wrapper' => 'icon-yellow',
                'title' => 'Visita manual',
                'description' => 'Crea visitas puntuales fuera del flujo automatico.',
                'cta' => 'Registrar visita',
                'metric_value' => null,
                'metric_label' => null,
                'metric_class' => '',
                'status_text' => null,
                'status_class' => '',
                'card_class' => 'card-secondary',
                'importance' => 1,
            ],
            [
                'key' => 'holiday',
                'href' => 'festivo_local.php',
                'label' => 'Festivo local',
                'icon_class' => 'fas fa-flag',
                'icon_wrapper' => 'icon-orange',
                'title' => 'Festivo local',
                'description' => 'Configura excepciones del calendario por zona.',
                'cta' => 'Configurar festivo',
                'metric_value' => null,
                'metric_label' => null,
                'metric_class' => '',
                'status_text' => null,
                'status_class' => '',
                'card_class' => 'card-tertiary card-low',
                'importance' => 3,
            ],
            [
                'key' => 'zones',
                'href' => 'zonas.php',
                'label' => 'Zonas',
                'icon_class' => 'fas fa-route',
                'icon_wrapper' => 'icon-purple',
                'title' => 'Zonas',
                'description' => 'Organiza la estructura comercial por zonas.',
                'cta' => 'Ver zonas',
                'metric_value' => $totalZonasActivas > 0 ? (string)$totalZonasActivas : null,
                'metric_label' => 'zonas',
                'metric_class' => $totalZonasActivas > 0 ? 'metric-ok' : '',
                'status_text' => null,
                'status_class' => '',
                'card_class' => 'card-secondary',
                'importance' => 3,
            ],
            [
                'key' => 'assign',
                'href' => 'zonas_clientes.php',
                'label' => 'Asignar clientes a zonas',
                'icon_class' => 'fas fa-user-plus',
                'icon_wrapper' => 'icon-cyan',
                'title' => 'Asignar clientes',
                'description' => 'Relaciona clientes y zonas para preparar rutas.',
                'cta' => 'Asignar clientes',
                'metric_value' => null,
                'metric_label' => null,
                'metric_class' => '',
                'status_text' => null,
                'status_class' => '',
                'card_class' => 'card-tertiary',
                'importance' => 3,
            ],
            [
                'key' => 'reset-cycle',
                'href' => '#',
                'label' => 'Reiniciar ciclos',
                'icon_class' => 'fas fa-rotate-left',
                'icon_wrapper' => 'icon-slate',
                'title' => 'Reiniciar ciclos',
                'description' => 'Ajusta fecha comun y orden real de zonas tras vacaciones o cambios de ruta.',
                'cta' => 'Configurar ciclo',
                'metric_value' => null,
                'metric_label' => null,
                'metric_class' => '',
                'status_text' => null,
                'status_class' => '',
                'card_class' => 'card-tertiary card-low',
                'importance' => 3,
                'modal_target' => '#reiniciarCiclosModal',
            ],
        ];

        $cardsByKey = [];
        foreach ($cards as $card) {
            $cardsByKey[$card['key']] = $card;
        }

        $ordenBase = $pedidosSinAsignarCriticos
            ? ['orders', 'manual', 'calendar', 'complete', 'zones', 'assign', 'holiday', 'reset-cycle', 'closed']
            : ['manual', 'calendar', 'complete', 'orders', 'zones', 'assign', 'holiday', 'reset-cycle', 'closed'];

        $cardsOrdenadas = [];
        foreach ($ordenBase as $key) {
            if (isset($cardsByKey[$key])) {
                $cardsOrdenadas[] = $cardsByKey[$key];
                unset($cardsByKey[$key]);
            }
        }
        foreach ($cardsByKey as $cardRestante) {
            $cardsOrdenadas[] = $cardRestante;
        }
        $cards = $cardsOrdenadas;

        foreach ($cards as &$card) {
            $importance = (int)($card['importance'] ?? 3);
            if ($importance <= 1) {
                $card['span_class'] = 'card-large';
                $card['compact'] = false;
            } elseif ($importance === 2) {
                $card['span_class'] = 'card-medium';
                $card['compact'] = false;
            } else {
                $card['span_class'] = 'card-mini';
                $card['compact'] = true;
            }
        }
        unset($card);

        usort($zonasCiclo, static function ($a, $b) {
            $ordenA = (int)($a['orden'] ?? 0);
            $ordenB = (int)($b['orden'] ?? 0);
            if ($ordenA === $ordenB) {
                return strcmp((string)($a['nombre_zona'] ?? ''), (string)($b['nombre_zona'] ?? ''));
            }
            return $ordenA <=> $ordenB;
        });

        return [
            'codVendedorMenu' => $codVendedorMenu,
            'fechaHoyMenu' => $fechaHoyMenu,
            'resumenHoy' => $resumenHoy,
            'totalPedidosSinAsignar' => $totalPedidosSinAsignar,
            'totalZonasActivas' => $totalZonasActivas,
            'zonasCiclo' => $zonasCiclo,
            'zonaActiva' => $zonaActiva,
            'zonaActivaId' => $zonaActivaId,
            'nombreZonaActiva' => $nombreZonaActiva,
            'clienteRecomendado' => $clienteRecomendado,
            'nombreClienteRecomendado' => $nombreClienteRecomendado,
            'motivoClienteRecomendado' => $motivoClienteRecomendado,
            'contextoZonaActiva' => $contextoZonaActiva,
            'inicioCicloActual' => $inicioCicloActual,
            'finCicloActual' => $finCicloActual,
            'resumenCicloActual' => $resumenCicloActual,
            'pedidosSinAsignarCriticos' => $pedidosSinAsignarCriticos,
            'pendientesHoy' => $pendientesHoy,
            'visitasHoy' => $visitasHoy,
            'realizadasHoy' => $realizadasHoy,
            'progresoHoy' => $progresoHoy,
            'visitasZonaActual' => $visitasZonaActual,
            'realizadasZonaActual' => $realizadasZonaActual,
            'pendientesZonaActual' => $pendientesZonaActual,
            'progresoZonaActual' => $progresoZonaActual,
            'rangoCicloActualLabel' => $rangoCicloActualLabel,
            'cards' => $cards,
            'flashMensaje' => trim((string)($_GET['mensaje'] ?? '')),
            'flashEstado' => trim((string)($_GET['estado'] ?? '')),
            'abrirModalReiniciar' => trim((string)($_GET['modal'] ?? '')) === 'reiniciar_ciclos',
        ];
    }
}
