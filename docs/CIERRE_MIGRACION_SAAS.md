# Cierre Migracion SaaS

Fecha de auditoria: 2026-03-21

## 1. Estructura final real

### Resumen ejecutivo

La migracion SaaS ha quedado parcialmente cerrada.

Lo que si esta consolidado:

- `/public` ya actua como superficie principal de entrada para las pantallas migradas y para AJAX.
- `/public/ajax` ya es la superficie publica AJAX consolidada.
- `/app/Modules` ya contiene la implementacion real de una parte importante del sistema migrado.
- `/app/Support` ya es la fuente activa de helpers y acceso comun a BD (`functions.php`, `db.php`).
- `bootstrap/init.php` y `bootstrap/auth.php` ya son el patron dominante de arranque y autenticacion en las entradas protegidas migradas.

Lo que no esta cerrado:

- La raiz sigue teniendo implementacion viva relevante.
- `header.php` sigue siendo una pieza compartida legacy en raiz.
- `funciones_planificacion_rutas.php` sigue siendo un nucleo funcional vivo fuera de `app/Modules`.
- Muchos modulos siguen abriendo `config/db_connection.php` de forma directa.

### Mapa real por zona

| Zona | Estado real |
| --- | --- |
| `/public` | Entrada web principal. Contiene wrappers limpios hacia `app/Modules`, algunos controladores publicos y `public/ajax`. |
| `/public/ajax` | Superficie AJAX publica consolidada. Los 13 endpoints revisados cargan implementacion interna en `app/Modules`. |
| `/app/Modules` | Implementacion real modularizada de Home, Auth, Clientes, Calendario, Estadisticas, Pedidos, Planificacion, Productos, Visitas y Zonas. |
| `/app/Support` | Helpers y acceso comun a BD activos: `functions.php` y `db.php`. |
| `/bootstrap` | Infraestructura de arranque y auth: `init.php`, `app.php`, `auth.php`. |
| `/config` | Configuracion de aplicacion y conexion. Sigue siendo usada directamente por varios modulos y legacy root. |
| `/includes` | Sigue alojando piezas legacy activas, sobre todo `funciones_estadisticas.php`, `control_acceso.php` y `permisos.php`. |
| Raiz | Mezcla de 24 shims de compatibilidad, 34 PHP con implementacion viva y piezas legacy compartidas (`header.php`, `funciones_planificacion_rutas.php`). |

### Que entra realmente por `/public`

Entradas publicas envueltas hacia `app/Modules`: 53 wrappers.

Entradas web migradas destacadas:

- `index.php` -> `app/Modules/Home/index.php`
- `login.php`, `logout.php` -> `app/Modules/Auth/*`
- `historico.php`, `clientes.php`, `cliente_detalles.php`, `seccion_detalles.php`, `buscar_cliente.php` -> `app/Modules/Clientes/*`
- `pedido.php`, `pedidos_todos.php` -> `app/Modules/Pedidos/*`
- `estadisticas.php`, `estadisticas_ventas_*` -> `app/Modules/Estadisticas/*`
- `calendario.php`, `mostrar_calendario.php` -> `app/Modules/Calendario/*`
- `faltas.php`, `faltas_todos.php`, `eventos.php`, `get_eventos.php`, `get_visitas.php`, `registrar_visita.php`, `registrar_visita_manual.php`, `programar_visita.php`, `posponer_visita.php`, `eliminar_visita.php`, `actualizar_visita.php`, `actualizar_origen.php`, `quitar_pedido.php`, `calcular_promedio_visita.php`, `obtener_secciones_pedidos_visitas.php` -> `app/Modules/Visitas/*`
- `zonas.php`, `zonas_rutas.php` -> `app/Modules/Zonas/*`
- `planificacion_rutas.php` -> `app/Modules/Planificacion/planificacion_rutas.php`

Matiz importante:

- `public/visitas.php` no es un wrapper puro. Sigue siendo un dispatcher publico con logica de redireccion y aun deriva el flujo `editar` hacia `/editar_visita.php` en raiz.

### Que vive realmente en `/app/Modules`

Modulos reales detectados: 11

- `Auth`
- `Calendario`
- `Clientes`
- `Configuracion`
- `Estadisticas`
- `Home`
- `Pedidos`
- `Planificacion`
- `Productos`
- `Visitas`
- `Zonas`

PHP dentro de `app/Modules`: 47

### Que vive realmente en `/app/Support`

Piezas activas y correctas como base compartida:

- `app/Support/functions.php`
- `app/Support/db.php`

Estado:

- `functions.php` ya es la fuente comun activa de helpers genericos.
- `db.php` ya encapsula el acceso comun a conexion.
- Aun no se ha eliminado el uso directo de `config/db_connection.php` desde multiples modulos.

### Que queda en raiz y por que

#### Shims de compatibilidad en raiz

Cantidad: 24

Estos archivos ya no son fuente de verdad; existen para compatibilidad temporal:

- `actualizar_origen.php`
- `calcular_promedio_visita.php`
- `calendario.php`
- `detalle_albaran.php`
- `detalle_pedido.php`
- `estadisticas.php`
- `estadisticas_ventas_clasicas.php`
- `estadisticas_ventas_comerciales.php`
- `eventos.php`
- `faltas.php`
- `faltas_todos.php`
- `get_eventos.php`
- `get_marcas.php`
- `historico.php`
- `index.php`
- `login.php`
- `logout.php`
- `mostrar_calendario.php`
- `pedidos_todos.php`
- `planificacion_rutas.php`
- `quitar_pedido.php`
- `seccion_detalles.php`
- `zonas.php`
- `zonas_rutas.php`

#### Implementacion viva en raiz

Cantidad: 34

Estos archivos siguen ejecutando negocio, consultas o flujos reales:

- `actualizar_asignacion.php`
- `alerta.php`
- `asignacion_clientes_zonas.php`
- `asociar_visita.php`
- `borrar_asignacion.php`
- `check_visita_previa_con_logs.php`
- `completar_dia.php`
- `debug_phpinfo.php`
- `detalle_visita.php`
- `drilldown.php`
- `editar_asignacion.php`
- `editar_no_laborable.php`
- `editar_visita.php`
- `funciones_planificacion_rutas.php`
- `header.php`
- `hora.php`
- `obtener_secciones.php`
- `pagina_de_confirmacion.php`
- `pasarHistorico.php`
- `procesar_asignar_cliente_zona.php`
- `procesar_asignar_ruta_zona.php`
- `procesar_crear_zona.php`
- `procesar_login.php`
- `productos.php`
- `registrar_dia_no_laborable.php`
- `registrar_email.php`
- `registrar_telefono.php`
- `registrar_visita_otro_dia.php`
- `registrar_visita_sin_compra.php`
- `registrar_web.php`
- `registrar_whatsapp.php`
- `test_conexion.php`
- `test_iconos.php`
- `visita_sin_venta.php`

## 2. Residuos restantes

### Residuos cuantificados

| Metrica | Valor |
| --- | ---: |
| Wrappers publicos en `/public` | 53 |
| Implementaciones vivas en raiz | 34 |
| Modulos reales en `/app/Modules` | 11 |
| Endpoints AJAX publicos en `/public/ajax` | 13 |
| Residuos detectados (ocurrencias estructurales) | 106 |

Notas sobre "residuos detectados":

- 24 shims de compatibilidad en raiz
- 34 implementaciones vivas en raiz
- 20 modulos que siguen incluyendo `config/db_connection.php`
- 18 modulos que siguen incluyendo `header.php` desde raiz
- 3 modulos que siguen dependiendo de `funciones_planificacion_rutas.php`
- 7 modulos que siguen dependiendo de `includes (legacy)/funciones_estadisticas.php`

### PHP en raiz que siguen siendo implementacion viva

El cierre SaaS no esta completado mientras la siguiente lista siga fuera de `app/Modules`:

- `actualizar_asignacion.php`
- `alerta.php`
- `asignacion_clientes_zonas.php`
- `asociar_visita.php`
- `borrar_asignacion.php`
- `check_visita_previa_con_logs.php`
- `completar_dia.php`
- `detalle_visita.php`
- `drilldown.php`
- `editar_asignacion.php`
- `editar_no_laborable.php`
- `editar_visita.php`
- `funciones_planificacion_rutas.php`
- `hora.php`
- `obtener_secciones.php`
- `pasarHistorico.php`
- `procesar_asignar_cliente_zona.php`
- `procesar_asignar_ruta_zona.php`
- `procesar_crear_zona.php`
- `procesar_login.php`
- `productos.php`
- `registrar_dia_no_laborable.php`
- `registrar_email.php`
- `registrar_telefono.php`
- `registrar_visita_otro_dia.php`
- `registrar_visita_sin_compra.php`
- `registrar_web.php`
- `registrar_whatsapp.php`
- `visita_sin_venta.php`

Adicionalmente, hay archivos de soporte/operacion en raiz que siguen siendo sensibles:

- `header.php`
- `funciones_planificacion_rutas.php`
- `debug_phpinfo.php`
- `test_conexion.php`
- `test_iconos.php`
- `pagina_de_confirmacion.php`

### Rutas legacy

Rutas legacy aun vivas o parcialmente vivas:

- `public/visitas.php?action=editar` sigue enviando a `/editar_visita.php` en raiz.
- Los 24 shims de compatibilidad en raiz siguen siendo accesibles por URL si el servidor expone la raiz del proyecto.
- Varias acciones legacy de visitas, asignaciones y registros siguen naciendo en raiz y no en `public`.
- Persisten referencias a paginas root compartidas como `header.php`.

### Includes impropios

Acoplamientos impropios aun activos:

- 20 archivos en `app/Modules` siguen abriendo `config/db_connection.php` de forma directa.
- 18 archivos en `app/Modules` siguen incluyendo `header.php` desde raiz.
- 3 archivos en `app/Modules` siguen dependiendo de `funciones_planificacion_rutas.php` en raiz:
  - `app/Modules/Zonas/zonas.php`
  - `app/Modules/Zonas/zonas_rutas.php`
  - `app/Modules/Visitas/obtener_secciones_pedidos_visitas.php`
- 7 archivos en `app/Modules` siguen dependiendo de `includes (legacy)/funciones_estadisticas.php`:
  - `app/Modules/Estadisticas/estadisticas_ventas_comerciales.php`
  - `app/Modules/Estadisticas/ajax/estadisticas_servicio.php`
  - `app/Modules/Estadisticas/ajax/estadisticas_kpis.php`
  - `app/Modules/Estadisticas/ajax/estadisticas_documentos.php`
  - `app/Modules/Estadisticas/ajax/estadisticas_detalle_servicio.php`
  - `app/Modules/Pedidos/ajax/detalle_pedido.php`
  - `app/Modules/Pedidos/ajax/detalle_documento.php`

### Duplicidades

Duplicidades que siguen existiendo:

- Doble capa de URL en archivos ya migrados: wrapper en `/public` y shim de compatibilidad en raiz.
- Persistencia de helpers/infra legacy en raiz (`header.php`, `funciones_planificacion_rutas.php`) mientras ya existe `app/Support`.
- Apertura de BD por doble patron: `app/Support/db.php` y `config/db_connection.php` directo.

### Deuda tecnica pendiente

Deuda tecnica real y no cosmetica:

- `funciones_planificacion_rutas.php` sigue mezclando conexion, auth, sesion y funciones de negocio.
- `header.php` sigue siendo una pieza shared legacy en raiz, aunque ya esta menos acoplada.
- `bootstrap/app.php` sigue cargando `config/db_connection.php` al arrancar.
- El modulo de configuracion sigue fuera de una migracion completa y todavia acoplado a raiz/config legacy.
- La superficie de raiz aun es demasiado grande para considerar la migracion cerrada.

## 3. Riesgos operativos

### Puntos de ruptura probables

- Cualquier intento de mover o eliminar `header.php` hoy rompera al menos 18 pantallas/modulos.
- Cualquier cambio brusco en `funciones_planificacion_rutas.php` afecta Zonas, asignaciones y partes de Visitas.
- Cualquier endurecimiento de la exposicion web de la raiz puede cortar flujos que aun dependen de `editar_visita.php`, `productos.php`, `registrar_*`, `procesar_*` y demas entradas vivas.
- Quitar el include directo a `config/db_connection.php` sin una pasada ordenada por modulo puede romper 20 archivos de `app/Modules` y multiples legacy root.
- La coexistencia de shims root + wrappers publicos puede ocultar rutas antiguas aun consumidas fuera del frontend principal.

### Modulos mas sensibles

- `Visitas`: mayor volumen de endpoints, formularios y residuos de raiz.
- `Zonas` y `Planificacion`: dependencia directa de `funciones_planificacion_rutas.php`.
- `Clientes`: varias pantallas ya modularizadas pero aun con dependencia de `header.php` y `config/db_connection.php`.
- `Estadisticas` y `Pedidos`: AJAX ya normalizado, pero siguen dependiendo de `includes (legacy)/funciones_estadisticas.php`.
- `Configuracion`: no esta cerrada como modulo SaaS consistente.

## 4. Veredicto honesto

### Estado

**BASE CORRECTA PERO INCOMPLETA**

### Motivo

La base SaaS ya existe y es valida:

- `public` ya es la superficie principal para buena parte de la aplicacion.
- `public/ajax` ya esta consolidado.
- `app/Modules` ya contiene la implementacion real de una porcion sustancial del producto.
- `app/Support` y `bootstrap` ya son una base creible.

Pero la migracion no puede darse por cerrada porque:

- la raiz sigue alojando 34 implementaciones vivas;
- la compatibilidad temporal en raiz sigue siendo amplia;
- persisten dependencias estructurales hacia `header.php`, `funciones_planificacion_rutas.php` y `config/db_connection.php`.

No es un proyecto "NO APTO". Tampoco es un "SaaS interno solido" todavia, porque la frontera arquitectonica aun no esta realmente cerrada.

## 5. Proximos pasos

Maximo 10 tareas, ordenadas por impacto real:

1. Migrar toda la familia viva de raiz de `Visitas` a `app/Modules/Visitas` y dejar `public` como unica entrada.
2. Extraer `funciones_planificacion_rutas.php` a una implementacion modular real dentro de `app/Modules/Zonas` o `app/Modules/Planificacion`.
3. Cerrar el flujo `public/visitas.php?action=editar` para que deje de depender de `/editar_visita.php` en raiz.
4. Sustituir en `app/Modules` las 20 aperturas directas de `config/db_connection.php` por el helper comun `app/Support/db.php`.
5. Introducir una capa de layout compartido fuera de raiz para reemplazar gradualmente `header.php`.
6. Migrar `productos.php` y su flujo asociado a `app/Modules/Productos`.
7. Migrar la familia root de asignaciones y procesos (`asignacion_*`, `actualizar_*`, `borrar_*`, `procesar_*`) a modulos funcionales reales.
8. Encerrar `Configuracion` como modulo coherente en `app/Modules/Configuracion` y reducir sus dependencias directas a `config/db_connection.php`.
9. Reducir y eliminar los 24 shims de compatibilidad en raiz una vez confirmadas las rutas efectivamente consumidas.
10. Retirar o aislar endpoints de diagnostico y prueba expuestos (`debug_phpinfo.php`, `test_conexion.php`, `test_iconos.php`).

## Certificacion practica

### Parte realmente cerrada

- Frontera publica AJAX: razonablemente cerrada.
- Frontera de muchas pantallas migradas `public -> app/Modules`: razonablemente cerrada.
- Base comun `bootstrap` + `app/Support`: valida como fundamento.

### Parte no cerrada

- Eliminacion de implementacion viva en raiz: no cerrada.
- Cierre total de dependencias shared legacy (`header.php`, `funciones_planificacion_rutas.php`): no cerrado.
- Unificacion completa de acceso a BD y helpers: no cerrada.

