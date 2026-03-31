# Auditoria de codigo muerto

Analisis estatico del repositorio (PHP) sin modificar codigo.
Criterio: referencias detectadas por `include/require`, enlaces (`href`/`Location`), `action` de formularios y llamadas AJAX en PHP/JS.

## Archivos sin referencias

Sin ninguna referencia entrante detectada desde otros archivos del proyecto (pueden existir accesos directos por URL o llamadas dinamicas):

- `actualizar_visita.php`
- `alerta.php`
- `asociar_visita.php`
- `check_visita_previa_con_logs.php`
- `db_query.php`
- `detalle_visita.php`
- `editar_no_laborable.php`
- `eventos.php`
- `get_lineas_pedido.php`
- `historico.php`
- `hora.php`
- `obtener_eventos.php`
- `pagina_de_confirmacion.php`
- `pasarHistorico.php`
- `pedido.php`
- `registrar_visita_otro_dia.php`
- `registrar_visita_sin_compra.php`
- `w.php`
- `altaClientes/mailchimp_subscribe.php`
- `altaClientes/mailchimp_sdk_subscribe.php`
- `ajax/detalle_pedido.php`
- `ajax/estadisticas_kpis.php`
- `includes (legacy)/bootstrap/app.php`
- `includes (legacy)/bootstrap/auth.php`
- `includes (legacy)/bootstrap/db.php`
- `includes (legacy)/db.php`
- `includes (legacy)/logs.php`

## Endpoints AJAX no utilizados

Sin llamadas detectadas por `fetch()`, `XMLHttpRequest`, `$.ajax`, `$.get`, `$.post`:

- `ajax/detalle_albaran.php`
- `ajax/detalle_pedido.php`
- `ajax/estadisticas_detalle_servicio.php`
- `ajax/estadisticas_kpis.php`
- `buscar_cliente.php`
- `calcular_promedio_visita.php`
- `db_query.php`
- `hora.php`
- `w.php`

## Scripts de test o debug

- `debug_phpinfo.php`
- `test_conexion.php`
- `test_iconos.php`
- `test/index.php`
- `storage/_manual_tests/index.php`
- `storage/_manual_tests/test/test_odbc.php`
- `storage/_manual_tests/altaClientes/test_mailchimp.php`
- `legacy/_debug_tools/debug_phpinfo.php`
- `legacy/_debug_tools/test_conexion.php`
- `legacy/_debug_tools/test_iconos.php`

## Archivos legacy

- `backups/index.php`
- `legacy/index.php`
- `legacy/backups/index.php`
- `legacy/backups/estadisticas_checkpoint_20260301_1939.php`
- `legacy/copies/index.php`
- `legacy/copies/estadisticas copy.php`
- `legacy/copies/faltas_copy.php`
- `legacy/copies/historico_copy.php`
- `legacy/copies/registrar_visita_manual_copy.php`
- `legacy/copies/visitas_comerciales_copy.php`
- `legacy/pruebas/index.php`
- `legacy/pruebas/pedidos_visitas_antes.php`
- `legacy/pruebas/pedidos_visitas_prueba.php`
- `legacy/_debug_tools/index.php`
- `legacy/_debug_tools/eventos.php`
- `legacy/_debug_tools/debug_phpinfo.php`
- `legacy/_debug_tools/test_conexion.php`
- `legacy/_debug_tools/test_iconos.php`
- `storage/index.php`
- `storage/logs/index.php`
- `storage/_manual_tests/index.php`
- `storage/_manual_tests/test/test_odbc.php`
- `storage/_manual_tests/altaClientes/test_mailchimp.php`

## Funciones posiblemente no utilizadas

Criterio: funcion declarada con 0 o 1 coincidencia estatica de llamada global (1 suele ser solo la propia declaracion). Puede haber falsos positivos por llamadas dinamicas, callbacks o uso desde JS/HTML inline.

- `altaClientes/mailchimp_subscribe.php::subscribeMailchimp`
- `db_query.php::obtenerPedidos`
- `db_query.php::contarPedidos`
- `estadisticas_ventas_clasicas.php::timestampToSpanish`
- `faltas_todos.php::timestampToSpanish`
- `funciones_planificacion_rutas.php::actualizarAsignacion`
- `funciones_planificacion_rutas.php::compararNombreCliente`
- `funciones_planificacion_rutas.php::compararNombreClienteYSeccion`
- `funciones_planificacion_rutas.php::obtenerSeccionesPorCliente`
- `header.php::adaptTitleFont`
- `header.php::cambiarFecha`
- `header.php::syncGlobalBars`
- `includes (legacy)/control_acceso.php::requierePremium`
- `includes (legacy)/db.php::dbScalar`
- `includes (legacy)/db.php::dbSelectIndexed`
- `includes (legacy)/db.php::dbSelectPairs`
- `includes (legacy)/funciones.php::cmpGrupo`
- `includes (legacy)/funciones.php::cmpStart`
- `includes (legacy)/funciones.php::compararClientes`
- `includes (legacy)/funciones.php::getSliderDates`
- `includes (legacy)/funciones.php::normalizarArrayUtf8`
- `includes (legacy)/funciones.php::renderDateSlider`
- `includes (legacy)/funciones.php::sqlSliderDateFilter`
- `includes (legacy)/funciones_estadisticas.php::obtenerOpcionesMarcaVentas`
- `legacy/backups/estadisticas_checkpoint_20260301_1939.php::obtenerAlbaranesSinRelacion`
- `legacy/backups/estadisticas_checkpoint_20260301_1939.php::obtenerDetalleDiferenciaDocumentalLineas`
- `legacy/backups/estadisticas_checkpoint_20260301_1939.php::obtenerKpiAlbaranesEjecutanPedidosPeriodo`
- `legacy/backups/estadisticas_checkpoint_20260301_1939.php::obtenerResumenAlbaranesClasificados`
- `mostrar_calendario.php::cambiarVista`

