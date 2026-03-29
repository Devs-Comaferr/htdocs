# AUDITORIA TSAAS CIERRE

Fecha: 2026-03-21

Alcance inspeccionado:

- `public/`: 79 PHP
- raiz del proyecto: 58 PHP
- `app/Modules/`: 20 PHP
- `bootstrap/`, `config/`, `includes/`: 11 PHP
- `resources/views/`: sin PHP

## Resumen ejecutivo

Estado actual de la migracion:

- `public/` ya actua como fachada web principal.
- La migracion a `app/Modules/` esta avanzada en `Visitas`, `Clientes` y parte de `Pedidos`.
- La mayor parte del negocio de `Planificacion`, `Estadisticas`, `Productos`, `Dashboard` y varios flujos de `Visitas` sigue viviendo en raiz.
- Existen modulos en `app/Modules/Visitas` que no son autoportantes: dependen de rutas relativas invalidas (`__DIR__ . '/bootstrap/init.php'`, `include_once 'config/db_connection.php'`) y hoy funcionan porque entran por wrappers de `public/`.
- `header.php`, `funciones_planificacion_rutas.php`, `config/db_connection.php` y `app/Support/db.php` mantienen un acoplamiento estructural fuerte via `$conn` global y efectos laterales al incluir.

Hallazgos criticos:

1. `config/runtime_secrets.php` contiene credenciales reales en claro dentro del repositorio. Esto es critico y ajeno a la logica de negocio.
2. `config/db_connection.php` abre la conexion ODBC al ser incluido; eso fuerza side effects en casi toda la aplicacion.
3. `app/Support/db.php` sigue devolviendo `$GLOBALS['conn']`; la capa "Support" aun no desacopla realmente la BD.
4. `funciones_planificacion_rutas.php` es hoy el mayor nodo legacy: mezcla auth, conexion, permisos, globals y SQL reusable.
5. `header.php` no es una vista neutra: inicia bootstrap/auth, toca BD y depende de `$conn`. Esto impide aislar modulos.
6. Hay duplicidad funcional real en detalles de pedido/documento/albaran y duplicidad parcial en flujos de visitas (`visitas.php` mezcla raiz y `app/Modules`).

## A) Inventario de entradas web

Notas:

- En `public/` hay tres tipos de entradas:
  - wrapper puro hacia raiz o `app/Modules`
  - entrada real implementada directamente en `public` (`public/ajax/estadisticas_*`, `public/visitas.php`)
  - wrapper-adaptador (`public/ajax/detalle_albaran.php`) que normaliza parametros antes de delegar
- En la columna "Implementacion actual" uso `raiz`, `app`, `public`.

| Entrada web | Apunta realmente a | Implementacion actual | Tipo |
| --- | --- | --- | --- |
| `public/actualizar_asignacion.php` | `actualizar_asignacion.php` | raiz | accion |
| `public/actualizar_origen.php` | `actualizar_origen.php` | raiz | accion |
| `public/actualizar_visita.php` | `app/Modules/Visitas/actualizar_visita.php` | app | accion |
| `public/ajax/detalle_albaran.php` | `detalle_albaran.php` tras normalizar `cod_venta_tipo` | raiz | ajax |
| `public/ajax/detalle_documento.php` | `app/Modules/Pedidos/ajax/detalle_documento.php` | app | ajax |
| `public/ajax/detalle_pedido.php` | `app/Modules/Pedidos/ajax/detalle_pedido.php` | app | ajax |
| `public/ajax/estadisticas_detalle_servicio.php` | implementacion local en `public/ajax/estadisticas_detalle_servicio.php` | public | ajax |
| `public/ajax/estadisticas_documentos.php` | implementacion local en `public/ajax/estadisticas_documentos.php` | public | ajax |
| `public/ajax/estadisticas_kpis.php` | implementacion local en `public/ajax/estadisticas_kpis.php` | public | ajax |
| `public/ajax/estadisticas_servicio.php` | implementacion local en `public/ajax/estadisticas_servicio.php` | public | ajax |
| `public/alerta.php` | `alerta.php` | raiz | pagina |
| `public/asignacion_clientes_zonas.php` | `asignacion_clientes_zonas.php` | raiz | pagina |
| `public/asociar_visita.php` | `asociar_visita.php` | raiz | accion |
| `public/borrar_asignacion.php` | `borrar_asignacion.php` | raiz | accion |
| `public/buscar_cliente.php` | `app/Modules/Clientes/buscar_cliente.php` | app | endpoint |
| `public/calcular_promedio_visita.php` | `calcular_promedio_visita.php` | raiz | endpoint |
| `public/calendario.php` | `calendario.php` | raiz | pagina |
| `public/check_visita_previa.php` | `app/Modules/Visitas/check_visita_previa.php` | app | endpoint |
| `public/check_visita_previa_con_logs.php` | `check_visita_previa_con_logs.php` | raiz | endpoint |
| `public/cliente_detalles.php` | `app/Modules/Clientes/cliente_detalles.php` | app | pagina |
| `public/clientes.php` | `app/Modules/Clientes/clientes.php` | app | pagina |
| `public/completar_dia.php` | `completar_dia.php` | raiz | accion |
| `public/debug_phpinfo.php` | `debug_phpinfo.php` | raiz | endpoint |
| `public/definir_horario.php` | `app/Modules/Visitas/definir_horario.php` | app | accion |
| `public/detalle_albaran.php` | `detalle_albaran.php` | raiz | endpoint |
| `public/detalle_pedido.php` | `detalle_pedido.php` | raiz | endpoint |
| `public/detalle_visita.php` | `detalle_visita.php` | raiz | pagina |
| `public/drilldown.php` | `drilldown.php` | raiz | pagina |
| `public/editar_asignacion.php` | `editar_asignacion.php` | raiz | pagina |
| `public/editar_no_laborable.php` | `editar_no_laborable.php` | raiz | pagina |
| `public/editar_visita.php` | `editar_visita.php` | raiz | pagina |
| `public/eliminar_visita.php` | `app/Modules/Visitas/eliminar_visita.php` | app | pagina/accion |
| `public/estadisticas.php` | `estadisticas.php` | raiz | pagina |
| `public/estadisticas_ventas_clasicas.php` | `estadisticas_ventas_clasicas.php` | raiz | pagina |
| `public/estadisticas_ventas_comerciales.php` | `estadisticas_ventas_comerciales.php` | raiz | pagina |
| `public/eventos.php` | `eventos.php` | raiz | endpoint |
| `public/faltas.php` | `faltas.php` | raiz | pagina |
| `public/faltas_todos.php` | `faltas_todos.php` | raiz | pagina |
| `public/get_eventos.php` | `get_eventos.php` | raiz | endpoint |
| `public/get_marcas.php` | `get_marcas.php` | raiz | endpoint |
| `public/get_visitas.php` | `app/Modules/Visitas/get_visitas.php` | app | endpoint |
| `public/historico.php` | `historico.php` | raiz | pagina |
| `public/hora.php` | `hora.php` | raiz | endpoint |
| `public/index.php` | `index.php` | raiz | pagina |
| `public/login.php` | `login.php` | raiz | auth |
| `public/logout.php` | `logout.php` | raiz | auth |
| `public/mostrar_calendario.php` | `mostrar_calendario.php` | raiz | pagina |
| `public/obtener_secciones.php` | `obtener_secciones.php` | raiz | endpoint |
| `public/obtener_secciones_pedidos_visitas.php` | `app/Modules/Visitas/obtener_secciones_pedidos_visitas.php` | app | endpoint |
| `public/pagina_de_confirmacion.php` | `pagina_de_confirmacion.php` | raiz | pagina |
| `public/pasarHistorico.php` | `pasarHistorico.php` | raiz | accion |
| `public/pedido.php` | `app/Modules/Pedidos/pedido.php` | app | pagina |
| `public/pedidos_todos.php` | `pedidos_todos.php` | raiz | pagina |
| `public/pedidos_visitas.php` | `app/Modules/Visitas/pedidos_visitas.php` | app | pagina |
| `public/planificacion_rutas.php` | `planificacion_rutas.php` | raiz | pagina |
| `public/posponer_visita.php` | `app/Modules/Visitas/posponer_visita.php` | app | accion |
| `public/procesar_asignar_cliente_zona.php` | `procesar_asignar_cliente_zona.php` | raiz | accion |
| `public/procesar_asignar_ruta_zona.php` | `procesar_asignar_ruta_zona.php` | raiz | accion |
| `public/procesar_crear_zona.php` | `procesar_crear_zona.php` | raiz | accion |
| `public/procesar_login.php` | `procesar_login.php` | raiz | auth |
| `public/productos.php` | `productos.php` | raiz | pagina |
| `public/programar_visita.php` | `app/Modules/Visitas/programar_visita.php` | app | pagina/accion |
| `public/quitar_pedido.php` | `quitar_pedido.php` | raiz | accion |
| `public/registrar_dia_no_laborable.php` | `registrar_dia_no_laborable.php` | raiz | accion |
| `public/registrar_email.php` | `registrar_email.php` | raiz | accion |
| `public/registrar_telefono.php` | `registrar_telefono.php` | raiz | accion |
| `public/registrar_visita.php` | `app/Modules/Visitas/registrar_visita.php` | app | accion |
| `public/registrar_visita_manual.php` | `app/Modules/Visitas/registrar_visita_manual.php` | app | pagina/accion |
| `public/registrar_visita_otro_dia.php` | `registrar_visita_otro_dia.php` | raiz | accion |
| `public/registrar_visita_sin_compra.php` | `registrar_visita_sin_compra.php` | raiz | accion |
| `public/registrar_web.php` | `registrar_web.php` | raiz | accion |
| `public/registrar_whatsapp.php` | `registrar_whatsapp.php` | raiz | accion |
| `public/seccion_detalles.php` | `seccion_detalles.php` | raiz | pagina/endpoint |
| `public/test_conexion.php` | `test_conexion.php` | raiz | endpoint |
| `public/test_iconos.php` | `test_iconos.php` | raiz | endpoint |
| `public/visita_sin_venta.php` | `visita_sin_venta.php` | raiz | pagina/accion |
| `public/visitas.php` | router interno que combina `app/Modules/Visitas/registrar_visita_manual.php`, `editar_visita.php` y `public/eliminar_visita.php` | mixto | pagina/router |
| `public/zonas.php` | `zonas.php` | raiz | pagina |
| `public/zonas_rutas.php` | `zonas_rutas.php` | raiz | pagina |

Lectura estructural:

- `public/` esta bien encaminado como unica superficie web.
- A fecha de la auditoria, 14 entradas publicas ya delegan a `app/Modules`.
- `public/visitas.php` es el unico front controller parcial: concentra la intencion correcta, pero aun enruta a raiz para `editar_visita.php`.
- `public/ajax/estadisticas_*` aun no tiene implementacion equivalente en `app/Modules`.

## B) Inventario de PHP en raiz

Estado de la raiz:

- No hay wrappers puros en raiz listos para borrar hoy.
- La raiz sigue siendo implementacion real para la mayoria de modulos.
- Hay dos piezas que no son "pantallas" ni "endpoints", sino soporte transversal legacy: `header.php` y `funciones_planificacion_rutas.php`.

### B.1 Tabla completa de raiz

| PHP en raiz | Estado actual | Clasificacion | Destino sugerido |
| --- | --- | --- | --- |
| `actualizar_asignacion.php` | implementacion real | deberia vivir en `app/Modules/Planificacion` | `app/Modules/Planificacion/actualizar_asignacion.php` |
| `actualizar_origen.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/actualizar_origen.php` |
| `alerta.php` | implementacion real | deberia vivir en `app/Modules/Pedidos` | `app/Modules/Pedidos/alerta.php` |
| `asignacion_clientes_zonas.php` | implementacion real | deberia vivir en `app/Modules/Planificacion` | `app/Modules/Planificacion/asignacion_clientes_zonas.php` |
| `asociar_visita.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/asociar_visita.php` |
| `borrar_asignacion.php` | implementacion real | deberia vivir en `app/Modules/Planificacion` | `app/Modules/Planificacion/borrar_asignacion.php` |
| `calcular_promedio_visita.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/calcular_promedio_visita.php` |
| `calendario.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/calendario.php` |
| `check_visita_previa_con_logs.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/check_visita_previa_con_logs.php` |
| `completar_dia.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/completar_dia.php` |
| `debug_phpinfo.php` | implementacion real | deberia vivir en `app/Http` | `app/Http/Debug/debug_phpinfo.php` |
| `detalle_albaran.php` | implementacion real | deberia vivir en `app/Modules/Pedidos/ajax` | `app/Modules/Pedidos/ajax/detalle_albaran.php` |
| `detalle_pedido.php` | implementacion real | deberia vivir en `app/Modules/Pedidos/ajax` | consolidar en `app/Modules/Pedidos/ajax/detalle_pedido.php` |
| `detalle_visita.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/detalle_visita.php` |
| `drilldown.php` | implementacion real | deberia vivir en `app/Modules/Estadisticas` | `app/Modules/Estadisticas/drilldown.php` |
| `editar_asignacion.php` | implementacion real | deberia vivir en `app/Modules/Planificacion` | `app/Modules/Planificacion/editar_asignacion.php` |
| `editar_no_laborable.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/editar_no_laborable.php` |
| `editar_visita.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/editar_visita.php` |
| `estadisticas.php` | implementacion real | deberia vivir en `app/Modules/Estadisticas` | `app/Modules/Estadisticas/estadisticas.php` |
| `estadisticas_ventas_clasicas.php` | implementacion real | deberia vivir en `app/Modules/Estadisticas` | `app/Modules/Estadisticas/estadisticas_ventas_clasicas.php` |
| `estadisticas_ventas_comerciales.php` | implementacion real | deberia vivir en `app/Modules/Estadisticas` | `app/Modules/Estadisticas/estadisticas_ventas_comerciales.php` |
| `eventos.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/eventos.php` |
| `faltas.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/faltas.php` |
| `faltas_todos.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/faltas_todos.php` |
| `funciones_planificacion_rutas.php` | soporte legacy real | deberia vivir en `app/Support` | partir en `app/Support/PlanificacionRutas.php` |
| `get_eventos.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/get_eventos.php` |
| `get_marcas.php` | implementacion real | deberia vivir en `app/Modules/Productos` | `app/Modules/Productos/get_marcas.php` |
| `header.php` | soporte legacy real | deberia vivir en `resources/views` y soporte | vista en `resources/views/partials/header.php` mas helpers en `app/Support` |
| `historico.php` | implementacion real | deberia vivir en `app/Modules/Clientes` | `app/Modules/Clientes/historico.php` |
| `hora.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/hora.php` |
| `index.php` | implementacion real | deberia vivir en `app/Modules/Dashboard` | `app/Modules/Dashboard/index.php` |
| `login.php` | implementacion real | deberia vivir en `app/Http` | `app/Http/Auth/login.php` |
| `logout.php` | implementacion real | deberia vivir en `app/Http` | `app/Http/Auth/logout.php` |
| `mostrar_calendario.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/mostrar_calendario.php` |
| `obtener_secciones.php` | implementacion real | deberia vivir en `app/Modules/Planificacion` | `app/Modules/Planificacion/obtener_secciones.php` |
| `pagina_de_confirmacion.php` | implementacion real | deberia vivir en `app/Http` | `app/Http/Public/pagina_de_confirmacion.php` |
| `pasarHistorico.php` | implementacion real | deberia vivir en `app/Modules/Pedidos` | `app/Modules/Pedidos/pasarHistorico.php` |
| `pedidos_todos.php` | implementacion real | deberia vivir en `app/Modules/Pedidos` | `app/Modules/Pedidos/pedidos_todos.php` |
| `planificacion_rutas.php` | implementacion real | deberia vivir en `app/Modules/Planificacion` | `app/Modules/Planificacion/planificacion_rutas.php` |
| `procesar_asignar_cliente_zona.php` | implementacion real | deberia vivir en `app/Modules/Planificacion` | `app/Modules/Planificacion/procesar_asignar_cliente_zona.php` |
| `procesar_asignar_ruta_zona.php` | implementacion real | deberia vivir en `app/Modules/Planificacion` | `app/Modules/Planificacion/procesar_asignar_ruta_zona.php` |
| `procesar_crear_zona.php` | implementacion real | deberia vivir en `app/Modules/Planificacion` | `app/Modules/Planificacion/procesar_crear_zona.php` |
| `procesar_login.php` | implementacion real | deberia vivir en `app/Http` | `app/Http/Auth/procesar_login.php` |
| `productos.php` | implementacion real | deberia vivir en `app/Modules/Productos` | `app/Modules/Productos/productos.php` |
| `quitar_pedido.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/quitar_pedido.php` |
| `registrar_dia_no_laborable.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/registrar_dia_no_laborable.php` |
| `registrar_email.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/registrar_email.php` |
| `registrar_telefono.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/registrar_telefono.php` |
| `registrar_visita_otro_dia.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/registrar_visita_otro_dia.php` |
| `registrar_visita_sin_compra.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/registrar_visita_sin_compra.php` |
| `registrar_web.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/registrar_web.php` |
| `registrar_whatsapp.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/registrar_whatsapp.php` |
| `seccion_detalles.php` | implementacion real | deberia vivir en `app/Modules/Clientes` | `app/Modules/Clientes/seccion_detalles.php` |
| `test_conexion.php` | implementacion real | deberia vivir en `app/Http` | `app/Http/Debug/test_conexion.php` |
| `test_iconos.php` | implementacion real | deberia vivir en `app/Http` | `app/Http/Debug/test_iconos.php` |
| `visita_sin_venta.php` | implementacion real | deberia vivir en `app/Modules/Visitas` | `app/Modules/Visitas/visita_sin_venta.php` |
| `zonas.php` | implementacion real | deberia vivir en `app/Modules/Planificacion` | `app/Modules/Planificacion/zonas.php` |
| `zonas_rutas.php` | implementacion real | deberia vivir en `app/Modules/Planificacion` | `app/Modules/Planificacion/zonas_rutas.php` |

### B.2 Clasificacion pedida, resumida

Sigue siendo implementacion real hoy:

- Los 58 PHP de raiz listados arriba, excepto que `header.php` y `funciones_planificacion_rutas.php` son soporte compartido y no endpoints.

Es wrapper/disponible para eliminar:

- Ninguno en raiz a fecha de auditoria.
- La superficie "wrapper" esta en `public/`, no en raiz.

Deberia vivir en `app/Modules/<Modulo>/`:

- Planificacion: `actualizar_asignacion.php`, `asignacion_clientes_zonas.php`, `borrar_asignacion.php`, `editar_asignacion.php`, `obtener_secciones.php`, `planificacion_rutas.php`, `procesar_asignar_cliente_zona.php`, `procesar_asignar_ruta_zona.php`, `procesar_crear_zona.php`, `zonas.php`, `zonas_rutas.php`
- Visitas: `actualizar_origen.php`, `asociar_visita.php`, `calcular_promedio_visita.php`, `calendario.php`, `check_visita_previa_con_logs.php`, `completar_dia.php`, `detalle_visita.php`, `editar_no_laborable.php`, `editar_visita.php`, `eventos.php`, `faltas.php`, `faltas_todos.php`, `get_eventos.php`, `hora.php`, `mostrar_calendario.php`, `quitar_pedido.php`, `registrar_dia_no_laborable.php`, `registrar_email.php`, `registrar_telefono.php`, `registrar_visita_otro_dia.php`, `registrar_visita_sin_compra.php`, `registrar_web.php`, `registrar_whatsapp.php`, `visita_sin_venta.php`
- Pedidos: `alerta.php`, `detalle_albaran.php`, `detalle_pedido.php`, `pasarHistorico.php`, `pedidos_todos.php`
- Clientes: `historico.php`, `seccion_detalles.php`
- Productos: `get_marcas.php`, `productos.php`
- Estadisticas: `drilldown.php`, `estadisticas.php`, `estadisticas_ventas_clasicas.php`, `estadisticas_ventas_comerciales.php`
- Dashboard: `index.php`

Deberia vivir en `app/Http` o similar:

- `login.php`, `logout.php`, `procesar_login.php`
- `debug_phpinfo.php`, `test_conexion.php`, `test_iconos.php`
- `pagina_de_confirmacion.php`

Soporte compartido fuera de categorias pedidas, pero imprescindible para cerrar la migracion:

- `header.php` -> `resources/views/partials/header.php` mas helper/s de soporte en `app/Support`
- `funciones_planificacion_rutas.php` -> `app/Support/PlanificacionRutas.php`

## Estado de `app/Modules`

### `app/Modules/Clientes`

- `buscar_cliente.php`: modulo ya consumido desde `public/buscar_cliente.php`
- `clientes.php`: pagina real ya migrada a modulo
- `cliente_detalles.php`: pagina real ya migrada a modulo

Estado: migracion utilizable, pero aun mezcla SQL + HTML + logica de request en el mismo archivo.

### `app/Modules/Pedidos`

- `pedido.php`: pagina real ya migrada a modulo
- `ajax/detalle_documento.php`: fuente de verdad actual para detalle de documento
- `ajax/detalle_pedido.php`: fuente de verdad actual para detalle de pedido

Estado: migracion parcial buena, pero incompleta porque `detalle_albaran.php`, `pedidos_todos.php`, `alerta.php` y `pasarHistorico.php` siguen en raiz.

### `app/Modules/Visitas`

Entrypoints actuales:

- `actualizar_visita.php`
- `check_visita_previa.php`
- `definir_horario.php`
- `eliminar_visita.php`
- `get_visitas.php`
- `obtener_secciones_pedidos_visitas.php`
- `pedidos_visitas.php`
- `posponer_visita.php`
- `programar_visita.php`
- `registrar_visita.php`
- `registrar_visita_manual.php`

Servicios actuales:

- `EliminarVisita.php`
- `PosponerVisita.php`
- `RegistrarVisita.php`

Estado:

- Es el modulo mas avanzado.
- Aun conserva archivos legacy dentro del propio modulo (`eliminar_visita.php`, `posponer_visita.php`, `registrar_visita_manual.php`).
- Hay varios entrypoints no autoportantes porque usan rutas relativas invalidas:
  - `app/Modules/Visitas/actualizar_visita.php`
  - `app/Modules/Visitas/check_visita_previa.php`
  - `app/Modules/Visitas/definir_horario.php`
  - `app/Modules/Visitas/eliminar_visita.php`
  - `app/Modules/Visitas/obtener_secciones_pedidos_visitas.php`
  - `app/Modules/Visitas/programar_visita.php`
  - `app/Modules/Visitas/registrar_visita.php`
- El patron repetido es:
  - `require_once __DIR__ . '/bootstrap/init.php'`
  - `include_once 'config/db_connection.php'`
- Esos includes no resuelven correctamente desde `app/Modules/Visitas`; hoy quedan ocultos porque `public/*` ya hizo bootstrap antes.

## C) Inventario de duplicidades

### C.1 Duplicidades funcionales directas

| Zona duplicada | Archivo 1 | Archivo 2 | Diagnostico | Fuente de verdad que debe quedar |
| --- | --- | --- | --- | --- |
| detalle pedido ajax | `public/ajax/detalle_pedido.php` | `app/Modules/Pedidos/ajax/detalle_pedido.php` | `public/ajax/detalle_pedido.php` es wrapper puro | `app/Modules/Pedidos/ajax/detalle_pedido.php` |
| detalle documento ajax | `public/ajax/detalle_documento.php` | `app/Modules/Pedidos/ajax/detalle_documento.php` | `public/ajax/detalle_documento.php` es wrapper puro | `app/Modules/Pedidos/ajax/detalle_documento.php` |
| detalle albaran ajax | `public/ajax/detalle_albaran.php` | `detalle_albaran.php` | `public/ajax/detalle_albaran.php` es adaptador; la implementacion real sigue en raiz | mover la verdad a `app/Modules/Pedidos/ajax/detalle_albaran.php` |
| pedido detalle legacy vs nuevo | `public/detalle_pedido.php` -> `detalle_pedido.php` | `public/ajax/detalle_pedido.php` -> `app/Modules/Pedidos/ajax/detalle_pedido.php` | dos implementaciones distintas del mismo dominio funcional | debe quedar solo la version de `app/Modules/Pedidos/ajax/detalle_pedido.php` |

### C.2 Duplicidades parciales o solapes operativos

| Dominio | Archivos implicados | Solape | Fuente de verdad recomendada |
| --- | --- | --- | --- |
| gestion de visitas | `public/visitas.php`, `editar_visita.php`, `app/Modules/Visitas/eliminar_visita.php`, `app/Modules/Visitas/registrar_visita_manual.php` | el router unifica el flujo, pero aun depende de raiz para editar y de archivos legacy del modulo para crear/eliminar | `public/visitas.php` como entrada unica y `app/Modules/Visitas/*` como implementacion |
| validacion de visita previa | `app/Modules/Visitas/check_visita_previa.php`, `check_visita_previa_con_logs.php` | ambos validan conflictos de visita; uno con logging y otro sin logging | consolidar en `app/Modules/Visitas/check_visita_previa.php` con logging encapsulado en `app/Support` |
| detalles de cliente/seccion | `app/Modules/Clientes/cliente_detalles.php`, `seccion_detalles.php`, `historico.php` | misma area funcional repartida entre modulo y raiz | `app/Modules/Clientes/*` |
| estadisticas | `public/ajax/estadisticas_*`, `estadisticas.php`, `estadisticas_ventas_*.php`, `drilldown.php` | paginas y endpoints ya comparten helpers, pero la implementacion esta dividida entre `public/ajax`, raiz e `includes` | `app/Modules/Estadisticas/*` + `app/Support` para utilidades |

### C.3 Duplicidad de infraestructura

- `config/db_connection.php` + `app/Support/db.php` + `global $conn`
  - no son tres capas distintas; son la misma dependencia presentada con tres fachadas.
- `header.php` actua como vista y bootstrap a la vez.
- `funciones_planificacion_rutas.php` actua como helper, repositorio, policy y gateway de BD al mismo tiempo.

## D) Dependencias peligrosas

### D.1 Includes/requires cruzados peligrosos

Cruces raiz -> soporte legacy:

- muchos scripts de raiz incluyen `config/db_connection.php`
- muchos scripts de raiz incluyen `header.php`
- toda planificacion depende de `funciones_planificacion_rutas.php`

Cruces `app/Modules` -> raiz/legacy:

- `app/Modules/Visitas/obtener_secciones_pedidos_visitas.php` incluye `funciones_planificacion_rutas.php`
- `app/Modules/Visitas/pedidos_visitas.php`, `app/Modules/Clientes/clientes.php`, `app/Modules/Clientes/cliente_detalles.php`, `app/Modules/Pedidos/pedido.php`, `app/Modules/Visitas/registrar_visita_manual.php` incluyen `BASE_PATH . '/header.php'`
- `app/Modules/Pedidos/ajax/detalle_documento.php` y `app/Modules/Pedidos/ajax/detalle_pedido.php` dependen de `includes/funciones_estadisticas.php`

Cruces con rutas incorrectas dentro de `app/Modules/Visitas`:

- `app/Modules/Visitas/actualizar_visita.php`
- `app/Modules/Visitas/check_visita_previa.php`
- `app/Modules/Visitas/definir_horario.php`
- `app/Modules/Visitas/eliminar_visita.php`
- `app/Modules/Visitas/obtener_secciones_pedidos_visitas.php`
- `app/Modules/Visitas/programar_visita.php`
- `app/Modules/Visitas/registrar_visita.php`

Problema:

- usan includes pensados para raiz y no para modulo
- eso impide ejecutar o testear esos archivos como implementaciones independientes

### D.2 Rutas hardcodeadas

Patrones repetidos:

- `define('BASE_URL', '/public')` repetido en gran parte de raiz y en varios archivos de `app/Modules/Visitas`
- includes relativos tipo:
  - `include_once 'config/db_connection.php'`
  - `include('header.php')`
  - `include_once('funciones_planificacion_rutas.php')`
- redirecciones legacy hardcodeadas:
  - `header('Location: index.php')`
  - `header('Location: login.php')`
  - `header('Location: pedidos_visitas.php?...')`

Impacto:

- la migracion depende del layout fisico actual
- mover implementaciones a `app/Modules` sin adaptar includes rompe inmediatamente

### D.3 Dependencia a variables globales

Uso explicito de `global $conn` detectado en:

- `borrar_asignacion.php`
- `obtener_secciones.php`
- `includes/logs.php`
- `funciones_planificacion_rutas.php`
- `app/Support/functions.php`

Uso implicito de globales:

- `app/Support/db.php` expone `$GLOBALS['conn']`
- `config/db_connection.php` crea `$conn` en el include
- gran parte de raiz y modulos confian en que `$conn` ya exista

Conclusiones:

- la aplicacion ya usa `db()` nominalmente, pero la dependencia real sigue siendo global
- mientras `db()` delegue a `$GLOBALS['conn']`, la migracion no esta cerrada estructuralmente

### D.4 Archivos que mezclan HTML + SQL + acciones GET/POST

Archivos claramente mezclados:

- `app/Modules/Clientes/clientes.php`
- `app/Modules/Clientes/cliente_detalles.php`
- `app/Modules/Pedidos/pedido.php`
- `app/Modules/Visitas/pedidos_visitas.php`
- `app/Modules/Visitas/registrar_visita_manual.php`
- `app/Modules/Visitas/programar_visita.php`
- `app/Modules/Visitas/get_visitas.php`
- `app/Modules/Visitas/eliminar_visita.php`
- `alerta.php`
- `asignacion_clientes_zonas.php`
- `detalle_pedido.php`
- `detalle_albaran.php`
- `drilldown.php`
- `editar_visita.php`
- `faltas.php`
- `faltas_todos.php`
- `historico.php`
- `index.php`
- `pedidos_todos.php`
- `productos.php`
- `seccion_detalles.php`
- `visita_sin_venta.php`
- `zonas.php`
- `zonas_rutas.php`

No es un problema funcional inmediato, pero si el principal indicador de que la migracion SaaS esta incompleta a nivel estructural.

### D.5 Dependencias especialmente delicadas

`config/runtime_secrets.php`

- expone DSN, usuario, password ODBC y credenciales SMTP en claro
- debe salir del repositorio o convertirse en fichero local ignorado

`config/db_connection.php`

- al incluirlo abre conexion inmediatamente
- cada include tiene efecto lateral

`header.php`

- no es solo presentacion
- fuerza bootstrap/auth
- toca BD si `$conn` no existe

`funciones_planificacion_rutas.php`

- fuerza bootstrap/auth/permisos al incluirlo
- define utilidades reutilizables, pero con dependencia de sesion y globals

## E) Propuesta final de destino

Principio de cierre:

- `public/` se queda como unica entrada web
- `app/Modules/` se queda con implementaciones de pantallas, endpoints y acciones
- `app/Support/` absorbe helpers reutilizables y servicios transversales
- `bootstrap/` y `config/` quedan solo para infraestructura
- `resources/views/` solo para vistas/partials sin side effects

### E.1 Entradas publicas que deben permanecer en `public/`

Se quedan en `public/` como fachada estable:

- todos los `public/*.php`
- todos los `public/ajax/*.php`

Regla final:

- si hoy son wrapper, siguen siendo wrapper
- si hoy contienen implementacion (`public/ajax/estadisticas_*`, `public/visitas.php`), deben pasar a delegar a `app/Modules` sin cambiar la URL publica

### E.2 Destino exacto de implementaciones de raiz

Mapa final propuesto:

- `actualizar_asignacion.php` -> `app/Modules/Planificacion/actualizar_asignacion.php`
- `actualizar_origen.php` -> `app/Modules/Visitas/actualizar_origen.php`
- `alerta.php` -> `app/Modules/Pedidos/alerta.php`
- `asignacion_clientes_zonas.php` -> `app/Modules/Planificacion/asignacion_clientes_zonas.php`
- `asociar_visita.php` -> `app/Modules/Visitas/asociar_visita.php`
- `borrar_asignacion.php` -> `app/Modules/Planificacion/borrar_asignacion.php`
- `calcular_promedio_visita.php` -> `app/Modules/Visitas/calcular_promedio_visita.php`
- `calendario.php` -> `app/Modules/Visitas/calendario.php`
- `check_visita_previa_con_logs.php` -> `app/Modules/Visitas/check_visita_previa_con_logs.php`
- `completar_dia.php` -> `app/Modules/Visitas/completar_dia.php`
- `detalle_albaran.php` -> `app/Modules/Pedidos/ajax/detalle_albaran.php`
- `detalle_pedido.php` -> consolidar en `app/Modules/Pedidos/ajax/detalle_pedido.php`
- `detalle_visita.php` -> `app/Modules/Visitas/detalle_visita.php`
- `drilldown.php` -> `app/Modules/Estadisticas/drilldown.php`
- `editar_asignacion.php` -> `app/Modules/Planificacion/editar_asignacion.php`
- `editar_no_laborable.php` -> `app/Modules/Visitas/editar_no_laborable.php`
- `editar_visita.php` -> `app/Modules/Visitas/editar_visita.php`
- `estadisticas.php` -> `app/Modules/Estadisticas/estadisticas.php`
- `estadisticas_ventas_clasicas.php` -> `app/Modules/Estadisticas/estadisticas_ventas_clasicas.php`
- `estadisticas_ventas_comerciales.php` -> `app/Modules/Estadisticas/estadisticas_ventas_comerciales.php`
- `eventos.php` -> `app/Modules/Visitas/eventos.php`
- `faltas.php` -> `app/Modules/Visitas/faltas.php`
- `faltas_todos.php` -> `app/Modules/Visitas/faltas_todos.php`
- `get_eventos.php` -> `app/Modules/Visitas/get_eventos.php`
- `get_marcas.php` -> `app/Modules/Productos/get_marcas.php`
- `historico.php` -> `app/Modules/Clientes/historico.php`
- `hora.php` -> `app/Modules/Visitas/hora.php`
- `index.php` -> `app/Modules/Dashboard/index.php`
- `mostrar_calendario.php` -> `app/Modules/Visitas/mostrar_calendario.php`
- `obtener_secciones.php` -> `app/Modules/Planificacion/obtener_secciones.php`
- `pasarHistorico.php` -> `app/Modules/Pedidos/pasarHistorico.php`
- `pedidos_todos.php` -> `app/Modules/Pedidos/pedidos_todos.php`
- `planificacion_rutas.php` -> `app/Modules/Planificacion/planificacion_rutas.php`
- `procesar_asignar_cliente_zona.php` -> `app/Modules/Planificacion/procesar_asignar_cliente_zona.php`
- `procesar_asignar_ruta_zona.php` -> `app/Modules/Planificacion/procesar_asignar_ruta_zona.php`
- `procesar_crear_zona.php` -> `app/Modules/Planificacion/procesar_crear_zona.php`
- `productos.php` -> `app/Modules/Productos/productos.php`
- `quitar_pedido.php` -> `app/Modules/Visitas/quitar_pedido.php`
- `registrar_dia_no_laborable.php` -> `app/Modules/Visitas/registrar_dia_no_laborable.php`
- `registrar_email.php` -> `app/Modules/Visitas/registrar_email.php`
- `registrar_telefono.php` -> `app/Modules/Visitas/registrar_telefono.php`
- `registrar_visita_otro_dia.php` -> `app/Modules/Visitas/registrar_visita_otro_dia.php`
- `registrar_visita_sin_compra.php` -> `app/Modules/Visitas/registrar_visita_sin_compra.php`
- `registrar_web.php` -> `app/Modules/Visitas/registrar_web.php`
- `registrar_whatsapp.php` -> `app/Modules/Visitas/registrar_whatsapp.php`
- `seccion_detalles.php` -> `app/Modules/Clientes/seccion_detalles.php`
- `visita_sin_venta.php` -> `app/Modules/Visitas/visita_sin_venta.php`
- `zonas.php` -> `app/Modules/Planificacion/zonas.php`
- `zonas_rutas.php` -> `app/Modules/Planificacion/zonas_rutas.php`

### E.3 Destino exacto de infraestructura y soporte

- `header.php`
  - vista: `resources/views/partials/header.php`
  - utilidades de composicion/configuracion visual: `app/Support/ViewHeader.php` o funciones equivalentes en `app/Support/functions.php`
- `funciones_planificacion_rutas.php`
  - consultas reutilizables: `app/Support/PlanificacionRutas.php`
  - cualquier control de acceso debe salir de aqui y quedarse en los entrypoints
- `includes/funciones_estadisticas.php`
  - mantener temporalmente
  - destino final: `app/Support/Estadisticas.php`
- `includes/logs.php`
  - destino final: `app/Support/Logs.php`
- `config/db_connection.php`
  - mantener en `config/`
  - pero sin abrir conexion en el include
- `app/Support/db.php`
  - mantener en `app/Support/`
  - pero sin depender de `$GLOBALS['conn']`

### E.4 Destino exacto de auth/http

- `login.php` -> `app/Http/Auth/login.php`
- `logout.php` -> `app/Http/Auth/logout.php`
- `procesar_login.php` -> `app/Http/Auth/procesar_login.php`
- `debug_phpinfo.php` -> `app/Http/Debug/debug_phpinfo.php`
- `test_conexion.php` -> `app/Http/Debug/test_conexion.php`
- `test_iconos.php` -> `app/Http/Debug/test_iconos.php`
- `pagina_de_confirmacion.php` -> `app/Http/Public/pagina_de_confirmacion.php`

## F) Orden optimo de ejecucion

Objetivo de las fases:

- cada fase debe ser desplegable sin romper produccion
- no mover URLs publicas
- no exigir big bang

### Fase 1. Cerrar infraestructura lateral sin mover entradas

Acciones:

1. Neutralizar `config/db_connection.php` para que no abra conexion al incluirlo.
2. Hacer que `app/Support/db.php` cree/obtenga conexion sin depender de `$GLOBALS['conn']`.
3. Extraer la logica no visual de `header.php` a `app/Support`.
4. Dejar `header.php` como wrapper de compatibilidad hacia `resources/views/partials/header.php`.

Resultado esperado:

- se puede seguir usando raiz y `app/Modules` sin cambiar URLs
- se reduce el riesgo de mover archivos despues

### Fase 2. Resolver el nodo legacy de planificacion

Acciones:

1. Crear `app/Modules/Planificacion/`
2. Copiar primero implementaciones desde raiz sin rerutar `public/` todavia:
   - `zonas.php`
   - `zonas_rutas.php`
   - `asignacion_clientes_zonas.php`
   - `editar_asignacion.php`
   - `actualizar_asignacion.php`
   - `borrar_asignacion.php`
   - `obtener_secciones.php`
   - `procesar_asignar_cliente_zona.php`
   - `procesar_asignar_ruta_zona.php`
   - `procesar_crear_zona.php`
   - `planificacion_rutas.php`
3. Extraer consultas reutilizables de `funciones_planificacion_rutas.php` a `app/Support/PlanificacionRutas.php`.

Resultado esperado:

- se desmonta el mayor bloque legacy sin tocar negocio

### Fase 3. Terminar Visitas

Acciones:

1. Mover a modulo los PHP de raiz pendientes de `Visitas`.
2. Corregir dentro de `app/Modules/Visitas` todos los includes relativos invalidos.
3. Hacer que `public/visitas.php` solo delegue a implementaciones dentro de `app/Modules/Visitas`.
4. Declarar legacy interno:
   - `app/Modules/Visitas/eliminar_visita.php`
   - `app/Modules/Visitas/posponer_visita.php`
   - `app/Modules/Visitas/registrar_visita_manual.php`

Resultado esperado:

- el dominio de visitas queda ya todo fuera de raiz

### Fase 4. Terminar Pedidos y detalles AJAX

Acciones:

1. Llevar `alerta.php`, `pedidos_todos.php`, `pasarHistorico.php`, `detalle_albaran.php` a `app/Modules/Pedidos`
2. Consolidar `detalle_pedido.php` en una sola implementacion
3. Hacer que todo `/public/ajax/*` delegue a `app/Modules/*/ajax/*`

Resultado esperado:

- desaparecen las duplicidades mas claras

### Fase 5. Terminar Estadisticas y Productos

Acciones:

1. Crear `app/Modules/Estadisticas/`
2. Mover:
   - `estadisticas.php`
   - `estadisticas_ventas_clasicas.php`
   - `estadisticas_ventas_comerciales.php`
   - `drilldown.php`
   - `public/ajax/estadisticas_*`
3. Crear `app/Modules/Productos/`
4. Mover:
   - `productos.php`
   - `get_marcas.php`
5. Extraer `includes/funciones_estadisticas.php` a `app/Support/Estadisticas.php`

Resultado esperado:

- paginas y endpoints estadisticos ya no quedan mezclados entre raiz, `public/ajax` e `includes`

### Fase 6. Auth, debug y dashboard

Acciones:

1. Crear `app/Http/Auth/` y mover `login.php`, `logout.php`, `procesar_login.php`
2. Crear `app/Http/Debug/` y mover `debug_phpinfo.php`, `test_conexion.php`, `test_iconos.php`
3. Crear `app/Modules/Dashboard/` y mover `index.php`

Resultado esperado:

- la raiz deja de tener responsabilidad aplicativa

### Fase 7. Conmutacion final y limpieza

Acciones:

1. Cambiar todos los wrappers de `public/` a sus destinos finales en `app/Modules` o `app/Http`
2. Verificar que no queda ningun `public/*` apuntando a raiz
3. Convertir la raiz en compatibilidad temporal o vaciarla
4. Eliminar duplicidades ya no usadas

Resultado esperado:

- migracion SaaS cerrada sin cambiar URLs ni logica

## Conclusiones operativas

Archivos analizados:

- 79 PHP en `public/`
- 58 PHP en raiz
- 20 PHP en `app/Modules/`
- 11 PHP en `bootstrap/`, `config/`, `includes/`
- total alcance auditado: 168 PHP

Hallazgos criticos de cierre:

1. `public/` ya es la entrada correcta, pero no toda la implementacion esta detras de `app/Modules`.
2. El cuello de botella principal no es `public/`; es el soporte legacy: `header.php`, `funciones_planificacion_rutas.php`, `config/db_connection.php`, `$conn` global.
3. `Visitas` esta migrado a medias: mejor dominio para cerrar despues de estabilizar infraestructura.
4. `Planificacion` sigue practicamente entero en raiz y es el segundo gran bloque a resolver.
5. `Pedidos` ya tiene una senda clara porque `app/Modules/Pedidos/ajax/*` existe y puede absorber los detalles restantes.
6. Hay un riesgo estructural y de seguridad externo a la migracion: secretos comprometidos en `config/runtime_secrets.php`.

Propuesta exacta del siguiente paso:

- Siguiente paso unico recomendado: ejecutar la **Fase 1 completa** antes de mover ningun endpoint.
- Motivo: mientras `db_connection.php`, `db.php`, `header.php` y `funciones_planificacion_rutas.php` sigan con side effects y globals, cualquier movimiento de archivos a `app/Modules` seguira siendo cosmetico.
- Primer bloque concreto a intervenir despues de esta auditoria:
  - `config/db_connection.php`
  - `app/Support/db.php`
  - `header.php`
  - `funciones_planificacion_rutas.php`
