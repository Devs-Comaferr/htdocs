# Mapa de endpoints activos

Inventario actualizado tras consolidacion tecnica.

## Endpoints AJAX (modulo interno)

- `ajax/detalle_albaran.php` (auth_bootstrap: si)
- `ajax/detalle_pedido.php` (auth_bootstrap: si)
- `ajax/estadisticas_detalle_servicio.php` (auth_bootstrap: si)
- `ajax/estadisticas_documentos.php` (auth_bootstrap: si)
- `ajax/estadisticas_kpis.php` (auth_bootstrap: si)
- `ajax/estadisticas_servicio.php` (auth_bootstrap: si)

## Endpoints de lectura/consulta

- `buscar_cliente.php` (auth_bootstrap: si)
- `calcular_promedio_visita.php` (auth_bootstrap: si)
- `check_visita_previa.php` (auth_bootstrap: si)
- `check_visita_previa_con_logs.php` (auth_bootstrap: si)
- `get_eventos.php` (auth_bootstrap: si)
- `get_marcas.php` (auth_bootstrap: si)
- `get_visitas.php` (auth_bootstrap: si)
- `obtener_secciones.php` (auth_bootstrap: si)
- `obtener_secciones_pedidos_visitas.php` (auth_bootstrap: si)
- `hora.php` (auth_bootstrap: si)

## Endpoints POST / procesadores internos

- `actualizar_asignacion.php` (auth_bootstrap: si)
- `actualizar_origen.php` (auth_bootstrap: si)
- `actualizar_visita.php` (auth_bootstrap: si)
- `asociar_visita.php` (auth_bootstrap: si)
- `borrar_asignacion.php` (auth_bootstrap: si)
- `pasarHistorico.php` (auth_bootstrap: si)
- `procesar_asignar_cliente_zona.php` (auth_bootstrap: si)
- `procesar_asignar_ruta_zona.php` (auth_bootstrap: si)
- `procesar_crear_zona.php` (auth_bootstrap: si)
- `quitar_pedido.php` (auth_bootstrap: si)
- `registrar_dia_no_laborable.php` (auth_bootstrap: si)
- `registrar_email.php` (auth_bootstrap: si)
- `registrar_telefono.php` (auth_bootstrap: si)
- `registrar_visita.php` (auth_bootstrap: si)
- `registrar_visita_manual.php` (auth_bootstrap: si)
- `registrar_visita_otro_dia.php` (auth_bootstrap: si)
- `registrar_visita_sin_compra.php` (auth_bootstrap: si)
- `registrar_web.php` (auth_bootstrap: si)
- `registrar_whatsapp.php` (auth_bootstrap: si)

## Endpoint publico (inicio de sesion)

- `procesar_login.php` (auth_bootstrap: no, esperado por diseno al ser login inicial)

## Endpoints retirados por falta de uso detectado

- `get_lineas_pedido.php` (eliminado)
- `obtener_eventos.php` (eliminado)
- `w.php` (eliminado)
- `db_query.php` (eliminado)
