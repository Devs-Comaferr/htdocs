# Auditoría de seguridad de endpoints

Analisis estatico de endpoints PHP potencialmente ejecutables.
Alcance analizado:

- `ajax/*.php`
- archivos `get_*`, `obtener_*`, `check_*`, `buscar_*`
- scripts PHP sueltos en raiz (utilitarios)
- endpoints POST

## Endpoints protegidos

Archivos con `auth_bootstrap` y/o validacion explicita de sesion (`requiereLogin`, `requiereActivo`, `requierePermiso`):

- `actualizar_origen.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `actualizar_visita.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `ajax/detalle_pedido.php` (auth_bootstrap + requiereLogin + requiereActivo + requierePermiso)
- `ajax/estadisticas_detalle_servicio.php` (auth_bootstrap + requiereLogin + requiereActivo + requierePermiso)
- `ajax/estadisticas_documentos.php` (auth_bootstrap + requiereLogin + requiereActivo + requierePermiso)
- `ajax/estadisticas_kpis.php` (auth_bootstrap + requiereLogin + requiereActivo + requierePermiso)
- `ajax/estadisticas_servicio.php` (auth_bootstrap + requiereLogin + requiereActivo + requierePermiso)
- `asociar_visita.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `borrar_asignacion.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `check_visita_previa.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `check_visita_previa_con_logs.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `configuracion/guardar_usuario.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `get_visitas.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `pasarHistorico.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `quitar_pedido.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `registrar_dia_no_laborable.php` (auth_bootstrap + requiereLogin + requiereActivo + requierePermiso)
- `registrar_email.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `registrar_telefono.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `registrar_visita.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `registrar_visita_manual.php` (auth_bootstrap + requiereLogin + requiereActivo + requierePermiso)
- `registrar_visita_otro_dia.php` (auth_bootstrap + requiereLogin + requiereActivo + requierePermiso)
- `registrar_visita_sin_compra.php` (auth_bootstrap + requiereLogin + requiereActivo + requierePermiso)
- `registrar_web.php` (auth_bootstrap + requiereLogin + requiereActivo)
- `registrar_whatsapp.php` (auth_bootstrap + requiereLogin + requiereActivo)

## Endpoints sin protección

Archivos ejecutables donde no se detecta `auth_bootstrap` ni llamadas `requiere*` en el propio archivo:

- `actualizar_asignacion.php`
- `ajax/detalle_albaran.php`
- `buscar_cliente.php`
- `calcular_promedio_visita.php`
- `db_query.php`
- `get_eventos.php`
- `get_lineas_pedido.php`
- `get_marcas.php`
- `obtener_eventos.php`
- `obtener_secciones.php`
- `obtener_secciones_pedidos_visitas.php`
- `procesar_asignar_cliente_zona.php`
- `procesar_asignar_ruta_zona.php`
- `procesar_crear_zona.php`
- `procesar_login.php`
- `w.php`

## Endpoints con protección parcial

Archivos que cargan bootstrap pero no llaman a `requiereLogin()` en el propio archivo:

- `hora.php` (incluye `auth_bootstrap`, sin llamada `requiere*` local)

## Notas

- Este analisis es estatico.
- Pueden existir falsos positivos si la proteccion esta aplicada en includes indirectos o en el flujo de llamada superior.
- En este barrido se evaluaron 41 endpoints potencialmente ejecutables.
