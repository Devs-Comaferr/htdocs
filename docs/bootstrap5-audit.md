# Auditoria Bootstrap 5

Fecha: 2026-03-31

## Punto real de carga del stack UI

- Punto de verdad actual: `resources/views/layouts/header.php`
- Normalizacion previa del stack: `app/Support/header.php`
- Decision historica detectada:
  - `app/Support/header.php` decidia entre `bs3` y `bs5`
  - `resources/views/layouts/header.php` cargaba Bootstrap 3.3.7 + jQuery 1.12.4 o Bootstrap 5 segun bandera
- Cambio aplicado en Fase 2:
  - `app/Support/header.php` fija el stack global en `bs5`
  - `resources/views/layouts/header.php` elimina la rama activa de Bootstrap 3
  - jQuery queda solo como opt-in explicito y ya no usa la version legacy `1.12.4`, sino `public/assets/vendor/jquery/jquery.min.js` (jQuery 3.7.1)

## Inventario global de restos legacy

### Critica

- `app/Support/header.php`
  - Tipo: selector global dual BS3/BS5
  - Riesgo: mezclaba stacks segun vista
- `resources/views/layouts/header.php`
  - Tipo: carga activa de Bootstrap 3.3.7 y jQuery 1.12.4
  - Riesgo: coexistencia de stacks y runtime inconsistente
- `app/Modules/Visitas/programar_visita.php`
  - Tipo: `$ui_version = 'bs3'`
  - Tipo: `btn-default`
  - Tipo: dependencia jQuery UI `autocomplete()`
  - Riesgo: depende de stack legacy y no es compatible con una eliminacion real de BS3
- `app/Modules/Visitas/visita_sin_venta.php`
  - Tipo: `$ui_version = 'bs3'`
  - Tipo: `btn-default`
  - Tipo: dependencia jQuery UI `autocomplete()`
  - Riesgo: idem
- `app/Modules/Visitas/eliminar_visita.php`
  - Tipo: `$ui_version = 'bs3'`
  - Tipo: `panel panel-danger`, `panel-heading`, `panel-body`, `panel-title`, `btn-default`
  - Riesgo: markup BS3 directo
- `app/Modules/Visitas/editar_visita_handler.php`
  - Tipo: `$ui_version = 'bs3'`
  - Tipo: `btn-default`
  - Tipo: JS jQuery `$(document).ready(...)`
  - Riesgo: depende de jQuery para UI y de clases BS3
- `app/Modules/Planificacion/editar_no_laborable.php`
  - Tipo: `$ui_version = 'bs3'`
  - Tipo: `btn-default`, `btn-block`
  - Riesgo: clases BS3 directas en formulario de negocio
- `app/Modules/Planificacion/completar_dia.php`
  - Tipo: `$ui_version = 'bs3'`
  - Tipo: `btn-default`
  - Tipo: JS jQuery `$(document).ready(...)`
  - Riesgo: depende de jQuery para UI y de clases BS3
- `app/Modules/Planificacion/procesar_crear_zona.php`
  - Tipo: `$ui_version = 'bs3'`
  - Riesgo: flujo de planificacion sigue anclado al stack legacy
- `app/Modules/Planificacion/procesar_asignar_ruta_zona.php`
  - Tipo: `$ui_version = 'bs3'`
  - Riesgo: idem
- `app/Modules/Visitas/get_visitas.php`
  - Tipo: `$ui_version = 'bs3'`
  - Riesgo: endpoint/vista legacy todavia declarada sobre BS3
- `app/Modules/Visitas/programar_visita.php`
  - Tipo: `autocomplete()` sin asset jQuery UI localizado en el stack global
  - Riesgo: dependencia oculta o rota
- `app/Modules/Visitas/visita_sin_venta.php`
  - Tipo: `autocomplete()` sin asset jQuery UI localizado en el stack global
  - Riesgo: dependencia oculta o rota

### Media

- `app/Modules/Visitas/visita_sin_venta.php`
  - Tipo: `form-group`
- `app/Modules/Visitas/programar_visita.php`
  - Tipo: `form-group`
- `app/Modules/Visitas/editar_visita_handler.php`
  - Tipo: `form-group`
- `app/Modules/Planificacion/editar_no_laborable.php`
  - Tipo: `form-group`, `btn-block`
- `app/Modules/Planificacion/completar_dia.php`
  - Tipo: `form-inline`, `form-group`
- `app/Modules/Planificacion/registrar_dia_no_laborable.php`
  - Tipo: `btn-default`, `btn-block`
- `app/Modules/Visitas/visita_sin_venta.php`
  - Tipo: comentarios y estructura pensados para stack BS3
- `app/Modules/Planificacion/asignacion_clientes_zonas.php`
  - Tipo: comentario residual "Bootstrap 3.3.7", inclusion del header fuera de una estructura de layout limpia
- `app/Modules/Zonas/zonas_rutas.php`
  - Tipo: comentario residual "Bootstrap 3.3.7", inclusion del header fuera de una estructura de layout limpia

### Baja

- `app/Modules/Clientes/clientes.php`
  - Tipo: comentario residual "Bootstrap 5 CSS/JS"
- `app/Modules/Clientes/cliente_detalles.php`
  - Tipo: comentario residual "Bootstrap 5 CSS/JS"
- `app/Modules/Clientes/seccion_detalles.php`
  - Tipo: comentario residual "Bootstrap 3 CSS"
- `app/Modules/Estadisticas/estadisticas_ventas_comerciales.php`
  - Tipo: nombres CSS propios con prefijo `panel-*`
  - Riesgo: no son necesariamente clases Bootstrap, pero conviene revisarlas para evitar confusion semantica
- `app/Modules/Home/index.php`
  - Tipo: nombres CSS propios `panel-toolbar`
  - Riesgo: no son clases Bootstrap

## Vistas ya alineadas con BS5

- `app/Modules/Home/index.php`
- `app/Modules/Pedidos/pedidos_todos.php`
- `app/Modules/Pedidos/pedido.php`
- `app/Modules/Pedidos/alerta.php`
- `app/Modules/Clientes/clientes.php`
- `app/Modules/Clientes/cliente_detalles.php`
- `app/Modules/Visitas/pedidos_visitas.php`
- `app/Modules/Visitas/registrar_visita_manual.php`
- `public/assets/js/app-ui.js`
- `app/Modules/Pedidos/Views/modal_documento.php`

## Dobles cargas o cargas contradictorias

- No se detectaron dobles cargas activas de Bootstrap dentro de `clientes.php` o `cliente_detalles.php`; los comentarios existen pero la carga real se delega al header global.
- Si existen vistas fuera del header comun, deben migrarse a `resources/views/layouts/header.php` antes de dar por cerrada la unificacion.
- Hay assets legacy aun presentes en disco:
  - `public/assets/vendor/legacy/bootstrap-3.3.7/**`
  - `public/assets/vendor/legacy/jquery-1.7.2.min.js`
  - `public/assets/vendor/legacy/jquery-1.11.3.min.js`
  - `public/assets/vendor/legacy/jquery-1.12.4.min.js`
  - No deben borrarse hasta confirmar ausencia total de referencias activas

## Orden recomendado para Fase 3

1. Migrar Visitas:
   - `programar_visita.php`
   - `visita_sin_venta.php`
   - `editar_visita_handler.php`
   - `eliminar_visita.php`
2. Migrar Planificacion:
   - `editar_no_laborable.php`
   - `registrar_dia_no_laborable.php`
   - `completar_dia.php`
   - `procesar_crear_zona.php`
   - `procesar_asignar_ruta_zona.php`
3. Revisar wrappers no estandar:
   - `app/Modules/Zonas/zonas_rutas.php`
   - `app/Modules/Planificacion/asignacion_clientes_zonas.php`
4. Eliminar referencias residuales y assets legacy solo al final
