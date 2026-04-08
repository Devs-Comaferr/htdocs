# Mapa de arquitectura real del proyecto APP Comerciales

Documento de analisis arquitectonico (estado actual del repositorio).
No incluye cambios de codigo.

## 1. Nucleo del sistema (Core)

### Autenticacion

- `login.php`: formulario de acceso.
- `procesar_login.php`: valida credenciales contra `cmf_comerciales_app_usuarios`, crea sesion y redirige a `index.php`.
- `includes (legacy)/auth_bootstrap.php`: punto comun de inicializacion (carga config, inicia sesion, carga control de acceso y funciones compartidas).
- `includes (legacy)/control_acceso.php`: reglas de acceso (`requiereLogin`, `requiereActivo`, `requierePermiso`, `requierePremium`).
- `logout.php`: destruye sesion y redirige a login.

### Conexion base de datos

- `config/db_connection.php`: conexion ODBC principal usada por casi todo el sistema.
- Capa alternativa parcial: `includes (legacy)/db.php` (helpers de query), actualmente sin adopcion general.

### Funciones globales

- `includes (legacy)/funciones.php`: utilidades transversales (formato, filtros, normalizacion, consultas auxiliares).
- `funciones.php`: wrapper de compatibilidad que reexporta `includes (legacy)/funciones.php`.

### Layout compartido

- `header.php`: barra superior, accesos globales, UI compartida y cargas comunes.

### Bootstrap

- Ruta activa: `includes (legacy)/auth_bootstrap.php`.
- Ruta alternativa no consolidada: `includes (legacy)/bootstrap/app.php`, `includes (legacy)/bootstrap/auth.php`, `includes (legacy)/bootstrap/db.php`.

---

## 2. Modulos de negocio

### Visitas

- Paginas principales:
  - `pedidos_visitas.php`
  - `registrar_visita_manual.php`
  - `detalle_visita.php`
  - `historico.php`
  - `faltas.php`
  - `visita_sin_venta.php`
  - `programar_visita.php`
  - `posponer_visita.php`
- Endpoints asociados:
  - `check_visita_previa.php`
  - `get_visitas.php`
  - `buscar_cliente.php`
  - `obtener_secciones_pedidos_visitas.php`
- Funciones relacionadas:
  - `includes (legacy)/funciones.php`
  - partes de `funciones_planificacion_rutas.php` para asignacion de cliente/seccion.

### Calendario

- Paginas principales:
  - `calendario.php`
  - `mostrar_calendario.php`
  - `completar_dia.php`
  - `definir_horario.php`
  - `registrar_dia_no_laborable.php`
  - `editar_visita.php`
  - `editar_no_laborable.php`
- Endpoints asociados:
  - `get_eventos.php`
  - `obtener_eventos.php`
- Funciones relacionadas:
  - `includes (legacy)/funciones.php`

### Rutas

- Paginas principales:
  - `planificacion_rutas.php`
  - `zonas.php`
  - `zonas_rutas.php`
  - `asignacion_clientes_zonas.php`
- Endpoints/procesadores asociados:
  - `obtener_secciones.php`
  - `procesar_crear_zona.php`
  - `procesar_asignar_ruta_zona.php`
  - `procesar_asignar_cliente_zona.php`
  - `actualizar_asignacion.php`
  - `borrar_asignacion.php`
- Funciones relacionadas:
  - `funciones_planificacion_rutas.php`

### Clientes

- Paginas principales:
  - `clientes.php`
  - `cliente_detalles.php`
  - `altaClientes/alta_cliente.php`
- Endpoints asociados:
  - `calcular_promedio_visita.php`
- Funciones relacionadas:
  - `includes (legacy)/funciones.php`
  - `altaClientes/mail_config.php`

### Productos

- Paginas principales:
  - `productos.php`
- Endpoints asociados:
  - `get_marcas.php`
- Funciones relacionadas:
  - `includes (legacy)/funciones.php`

### Estadisticas

- Paginas principales:
  - `estadisticas.php`
  - `estadisticas_ventas_clasicas.php`
  - `estadisticas_ventas_comerciales.php`
  - `drilldown.php`
- Endpoints asociados:
  - `ajax/estadisticas_servicio.php`
  - `ajax/estadisticas_documentos.php`
  - `ajax/estadisticas_detalle_servicio.php`
  - `ajax/estadisticas_kpis.php` (sin consumo claro actual)
  - `ajax/detalle_pedido.php`
  - `ajax/detalle_albaran.php`
- Funciones relacionadas:
  - `includes (legacy)/funciones_estadisticas.php`
  - `includes (legacy)/funciones.php`

### Configuracion

- Paginas principales:
  - `configuracion/index.php`
  - `configuracion/usuarios.php`
  - `configuracion/aplicacion.php`
- Endpoints/procesadores asociados:
  - `configuracion/guardar_usuario.php`
- Funciones relacionadas:
  - `includes (legacy)/logs.php`
  - `includes (legacy)/control_acceso.php`

---

## 3. Endpoints y capa AJAX

### Endpoints AJAX detectados

- Carpeta `ajax/`:
  - `ajax/estadisticas_servicio.php`
  - `ajax/estadisticas_documentos.php`
  - `ajax/estadisticas_detalle_servicio.php`
  - `ajax/estadisticas_kpis.php`
  - `ajax/detalle_pedido.php`
  - `ajax/detalle_albaran.php`
- Endpoints estilo `get_` / `obtener_` / `check_`:
  - `get_eventos.php`, `get_visitas.php`, `get_marcas.php`, `get_lineas_pedido.php`
  - `obtener_eventos.php`, `obtener_secciones.php`, `obtener_secciones_pedidos_visitas.php`
  - `check_visita_previa.php`, `check_visita_previa_con_logs.php`
  - `buscar_cliente.php`, `calcular_promedio_visita.php`

### Consumidores principales

- `estadisticas_ventas_comerciales.php` consume `ajax/estadisticas_servicio.php`, `ajax/estadisticas_documentos.php`, `ajax/estadisticas_detalle_servicio.php`.
- `calendario.php` consume `get_eventos.php`.
- `registrar_visita_manual.php` consume `get_visitas.php`.
- `productos.php` consume `get_marcas.php`.
- `asignacion_clientes_zonas.php` consume `obtener_secciones.php`.
- `programar_visita.php`, `posponer_visita.php`, `visita_sin_venta.php` consumen `buscar_cliente.php` y `obtener_secciones_pedidos_visitas.php`.
- `pedidos_visitas.php` consume `check_visita_previa.php`.

### Datasets/funciones habituales

- Estadisticas: `includes (legacy)/funciones_estadisticas.php` para agregaciones y KPIs.
- Resto de endpoints: consultas directas ODBC o helpers de `includes (legacy)/funciones.php` / `funciones_planificacion_rutas.php`.

---

## 4. Flujo de autenticacion

Flujo real observado:

1. `login.php` envia formulario por POST a `procesar_login.php`.
2. `procesar_login.php` consulta `cmf_comerciales_app_usuarios` en ERP, valida password (hash moderno o texto plano legado), setea variables de sesion y redirige a `index.php`.
3. Las paginas protegidas cargan `includes (legacy)/auth_bootstrap.php`.
4. `auth_bootstrap.php` inicia sesion, carga `includes (legacy)/control_acceso.php` y `includes (legacy)/funciones.php`.
5. Las paginas invocan `requiereLogin()` y/o `requiereActivo()` (y en casos concretos `requierePermiso()`), aplicando redireccion a `/login.php` o `/index.php`.

---

## 5. Capa de acceso a datos

Archivos responsables:

- `config/db_connection.php`: conexion ODBC base.
- `includes (legacy)/funciones.php`: funciones de acceso/transformacion usadas por modulos generales.
- `includes (legacy)/funciones_estadisticas.php`: consultas y agregaciones especificas de estadisticas.
- `funciones_planificacion_rutas.php`: capa SQL del dominio rutas/asignacion.
- Endpoints y paginas con SQL embebido adicional (`procesar_login.php`, `get_*`, `obtener_*`, etc.).

Datasets/tabla de trabajo destacada (por uso observable):

- `cmf_comerciales_app_usuarios` (autenticacion/permisos).
- Documentos comerciales (pedidos, albaranes y lineas) en estadisticas y detalle.
- Datos de clientes, visitas y asignaciones por ruta/seccion en modulos de clientes/visitas/rutas.

---

## 6. Dependencias principales

Patron dominante por pagina de negocio:

- `require_once includes (legacy)/auth_bootstrap.php`
- `require/include config/db_connection.php`
- `require/include funciones.php` (wrapper o `includes (legacy)/funciones.php`)
- `include header.php` (cuando corresponde vista completa)

Patrones por dominio:

- Estadisticas: suma `includes (legacy)/funciones_estadisticas.php`.
- Rutas: usa `funciones_planificacion_rutas.php`.
- Configuracion: usa `includes (legacy)/logs.php` en guardado de usuarios.

---

## 7. Zonas legacy o refactors incompletos

### Carpetas legacy/backups

- `legacy/**` (copias, pruebas y checkpoints).
- `backups/**`.
- `storage/_manual_tests/**`.

### Intentos de arquitectura parcialmente adoptados

- `includes (legacy)/bootstrap/app.php`, `includes (legacy)/bootstrap/auth.php`, `includes (legacy)/bootstrap/db.php` existen pero no son el flujo principal.
- `includes (legacy)/db.php` define una capa de acceso de mayor nivel, pero no esta integrada de forma transversal.

### Refactors incompletos / referencias rotas

- Endpoint `ajax/estadisticas_kpis.php` existe pero no presenta consumo claro activo.

---

## 8. Riesgos de arquitectura detectados

- Responsabilidades mezcladas en paginas grandes (UI + validacion + SQL + logica de negocio en un mismo archivo).
- Arquitectura hibrida (legacy + nuevas capas) con convenciones mixtas (`funciones.php` wrapper, includes directos, bootstrap alternativo).
- Endpoints y rutas con visibilidad incierta (archivos sin referencias estaticas, posible codigo muerto).
- Duplicidad funcional potencial (funciones con nombres/objetivos similares en distintas capas y legacy).
- Dependencia de enlaces hardcodeados y algunas referencias a archivos inexistentes.
- Seguridad de acceso heterogenea: algunos endpoints no muestran guardas de login de forma consistente.

