# Arquitectura del proyecto APP Comerciales

Documento de auditoria tecnica (Fase 1). Alcance: inventario estructural de archivos PHP y relaciones estaticas (navegacion, endpoints, includes).

## 1. Paginas principales

- `index.php`
- `clientes.php`
- `cliente_detalles.php`
- `productos.php`
- `pedidos_todos.php`
- `pedidos_visitas.php`
- `pedido.php`
- `historico.php`
- `faltas.php`
- `faltas_todos.php`
- `calendario.php`
- `mostrar_calendario.php`
- `planificacion_rutas.php`
- `zonas.php`
- `zonas_rutas.php`
- `asignacion_clientes_zonas.php`
- `registrar_visita_manual.php`
- `completar_dia.php`
- `registrar_dia_no_laborable.php`
- `definir_horario.php`
- `detalle_visita.php`
- `detalle_pedido.php`
- `detalle_albaran.php`
- `estadisticas.php`
- `estadisticas_ventas_clasicas.php`
- `estadisticas_ventas_comerciales.php`
- `drilldown.php`
- `configuracion/index.php`
- `configuracion/usuarios.php`
- `configuracion/aplicacion.php`
- `altaClientes/alta_cliente.php`
- `login.php`

## 2. Endpoints AJAX

- `ajax/estadisticas_servicio.php`
- `ajax/estadisticas_documentos.php`
- `ajax/estadisticas_detalle_servicio.php`
- `ajax/estadisticas_kpis.php`
- `ajax/detalle_pedido.php`
- `ajax/detalle_albaran.php`
- `get_eventos.php`
- `get_visitas.php`
- `get_marcas.php`
- `get_lineas_pedido.php`
- `obtener_eventos.php`
- `obtener_secciones.php`
- `obtener_secciones_pedidos_visitas.php`
- `check_visita_previa.php`
- `check_visita_previa_con_logs.php`
- `buscar_cliente.php`
- `calcular_promedio_visita.php`
- `hora.php`
- `w.php`
- `db_query.php`

## 3. Procesadores POST

- `procesar_login.php`
- `procesar_crear_zona.php`
- `procesar_asignar_ruta_zona.php`
- `procesar_asignar_cliente_zona.php`
- `configuracion/guardar_usuario.php`
- `actualizar_visita.php`
- `actualizar_origen.php`
- `actualizar_asignacion.php`
- `borrar_asignacion.php`
- `quitar_pedido.php`
- `pasarHistorico.php`
- `registrar_visita.php`
- `registrar_telefono.php`
- `registrar_whatsapp.php`
- `registrar_email.php`
- `registrar_web.php`
- `registrar_visita_otro_dia.php`
- `registrar_visita_sin_compra.php`
- `registrar_dia_no_laborable.php`
- `asociar_visita.php`

## 4. Helpers y librerias

- `funciones.php` (wrapper de compatibilidad)
- `includes (legacy)/funciones.php`
- `includes (legacy)/funciones_estadisticas.php`
- `funciones_planificacion_rutas.php`
- `config/db_connection.php`
- `config/app_config.php`
- `includes (legacy)/db.php`
- `includes (legacy)/logs.php`
- `header.php`
- `includes (legacy)/bootstrap/app.php`
- `includes (legacy)/bootstrap/db.php`
- `includes (legacy)/bootstrap/auth.php`

## 5. Bootstrap y autenticacion

- `includes (legacy)/auth_bootstrap.php`
- `includes (legacy)/control_acceso.php`
- `login.php`
- `procesar_login.php`
- `logout.php`

## 6. Modulos de negocio

- Visitas:
  - `pedidos_visitas.php`
  - `registrar_visita*.php`
  - `visita_sin_venta.php`
  - `programar_visita.php`
  - `posponer_visita.php`
  - `detalle_visita.php`
  - `historico.php`
  - `faltas.php`
- Calendario:
  - `calendario.php`
  - `mostrar_calendario.php`
  - `get_eventos.php`
  - `obtener_eventos.php`
  - `completar_dia.php`
  - `definir_horario.php`
  - `registrar_dia_no_laborable.php`
- Rutas:
  - `planificacion_rutas.php`
  - `zonas.php`
  - `zonas_rutas.php`
  - `asignacion_clientes_zonas.php`
  - `funciones_planificacion_rutas.php`
  - `procesar_*` de asignacion
- Clientes:
  - `clientes.php`
  - `cliente_detalles.php`
  - `altaClientes/*`
- Estadisticas:
  - `estadisticas*.php`
  - `drilldown.php`
  - `ajax/estadisticas_*.php`
  - `includes (legacy)/funciones_estadisticas.php`
- Productos:
  - `productos.php`
  - `get_marcas.php`
- Configuracion:
  - `configuracion/*`
  - `includes (legacy)/auth_bootstrap.php`
  - `includes (legacy)/control_acceso.php`
  - `login.php`, `logout.php`

## 7. Archivos legacy o sospechosos

- `legacy/**` (copias, pruebas y backups)
- `storage/_manual_tests/**`
- `test/index.php`
- `test_conexion.php`
- `test_iconos.php`
- `debug_phpinfo.php`
- `eventos.php`
- `db_query.php`
- `w.php`
- `hora.php`
- `backups/index.php`

## 8. Referencias a archivos inexistentes

- `festivo_local.php` (referido desde `planificacion_rutas.php` y `mostrar_calendario.php`)
- `eliminar_visita.php` (referido desde `editar_visita.php`)
- `legacy/backups/estadisticas_ventas_comerciales_v2.php` (solo referencia legacy)
- Multiples referencias faltantes dentro de `legacy/copies/*` y `legacy/pruebas/*`

## 9. Mapa de navegacion

- `index.php` -> `productos.php`, `clientes.php`, `faltas_todos.php`, `pedidos_todos.php`, `planificacion_rutas.php`, `estadisticas.php`, `altaClientes/alta_cliente.php`
- `header.php` -> `index.php`, `productos.php`, `clientes.php`, `faltas_todos.php`, `pedidos_todos.php`, `planificacion_rutas.php`, `estadisticas.php`, `configuracion/index.php`, `logout.php`, `altaClientes/alta_cliente.php`
- `planificacion_rutas.php` -> `mostrar_calendario.php`, `pedidos_visitas.php`, `registrar_visita_manual.php`, `completar_dia.php`, `registrar_dia_no_laborable.php`, `zonas.php`, `asignacion_clientes_zonas.php`
- `estadisticas.php` -> `estadisticas_ventas_clasicas.php`, `estadisticas_ventas_comerciales.php`
- `clientes.php` -> `cliente_detalles.php`
- `cliente_detalles.php` -> `seccion_detalles.php`, `historico.php`, `faltas.php`, `registrar_visita_manual.php`
- `configuracion/index.php` -> `usuarios.php`, `aplicacion.php`

## 10. Mapa de endpoints

- `estadisticas_ventas_comerciales.php` -> `/ajax/estadisticas_servicio.php`
- `estadisticas_ventas_comerciales.php` -> `/ajax/estadisticas_documentos.php`
- `estadisticas_ventas_comerciales.php` -> `/ajax/estadisticas_detalle_servicio.php`
- `calendario.php` -> `get_eventos.php`
- `registrar_visita_manual.php` -> `get_visitas.php`
- `productos.php` -> `get_marcas.php`
- `asignacion_clientes_zonas.php` -> `obtener_secciones.php`
- `programar_visita.php` -> `buscar_cliente.php`, `obtener_secciones_pedidos_visitas.php`
- `posponer_visita.php` -> `buscar_cliente.php`, `obtener_secciones_pedidos_visitas.php`
- `visita_sin_venta.php` -> `buscar_cliente.php`, `obtener_secciones_pedidos_visitas.php`
- `pedidos_visitas.php` -> `check_visita_previa.php`

## 11. Mapa de dependencias (includes)

- `includes (legacy)/auth_bootstrap.php` -> `config/app_config.php`, `includes (legacy)/control_acceso.php`, `includes (legacy)/funciones.php`
- `funciones.php` -> `includes (legacy)/funciones.php`
- `funciones_planificacion_rutas.php` -> `config/db_connection.php`, `includes (legacy)/auth_bootstrap.php`
- `ajax/estadisticas_*.php` -> `includes (legacy)/auth_bootstrap.php`, `config/db_connection.php`, `includes (legacy)/funciones.php`, `includes (legacy)/funciones_estadisticas.php`
- Patron general de paginas principales -> `includes (legacy)/auth_bootstrap.php`, `config/db_connection.php`, `funciones.php`, `header.php`

---

Notas:
- Este documento es informativo y no implica cambios funcionales.
- No se han aplicado refactors ni modificaciones de logica en esta fase.

## 12. Estructura base de transicion

Se ha preparado una estructura base en `app/` para evolucionar el proyecto de forma progresiva, sin mover archivos existentes ni modificar rutas actuales.

Carpetas base:

- `app/Modules/`
- `app/Modules/Visitas/`
- `app/Modules/Clientes/`
- `app/Modules/Pedidos/`
- `app/Http/`
- `app/Http/Controllers/`
- `app/Http/Middleware/`

Objetivo de cada carpeta:

- `app/Modules/`: agrupacion funcional por dominio del negocio.
- `app/Modules/Visitas/`: logica y piezas futuras relacionadas con visitas.
- `app/Modules/Clientes/`: logica y piezas futuras relacionadas con clientes.
- `app/Modules/Pedidos/`: logica y piezas futuras relacionadas con pedidos.
- `app/Http/`: punto comun para capas HTTP incorporadas en fases posteriores.
- `app/Http/Controllers/`: controladores futuros para endpoints o pantallas migradas.
- `app/Http/Middleware/`: middleware futuro para autenticacion, permisos o validaciones transversales.

Aclaracion de transicion:

- Esta estructura es preparatoria.
- No implica migracion inmediata.
- No se han movido `index.php`, `clientes.php` ni otros archivos actuales.
- La raiz del proyecto y los includes existentes siguen siendo validos.
- La migracion, si se realiza, sera incremental y compatible con el funcionamiento actual.
