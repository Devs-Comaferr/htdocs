# Auditoría Planificador

## Resumen ejecutivo

El módulo `app/Modules/Planificador` ya tiene una separación inicial en `controllers/`, `views/` y `services/`, pero todavía conviven varias capas de compatibilidad y responsabilidades mezcladas.

El estado actual real es:

- Hay una doble capa de wrappers correcta y útil para compatibilidad:
  - `public/*.php` como entry points públicos.
  - `app/Modules/Planificador/*.php` como puente interno hacia `views/` o `controllers/`.
- El core real vive sobre todo en:
  - `services/planificador_service.php`
  - `services/PlanificadorZonasRepository.php`
  - `services/PlanificadorAsignacionesRepository.php`
  - `services/PlanificadorViewDataService.php`
  - varias `views/*.php`
- La deuda principal no está en `public/`, sino en:
  - vistas con lógica de acceso a datos y reglas de cálculo incrustadas,
  - naming y organización inconsistentes,
  - controladores POST con render HTML directo,
  - una fachada `planificador_service.php` demasiado grande y multipropósito.

No se ha detectado necesidad de cambios inmediatos de limpieza en esta auditoría. La recomendación es documentar y refactorizar por fases pequeñas.

## Mapa real del módulo

Estructura observada:

- `app/Modules/Planificador/`
  - wrappers internos de compatibilidad hacia `views/` y `controllers/`
  - `ajax/` vacía
  - `controllers/`
  - `services/`
  - `views/`

Mapa funcional actual:

- Panel principal:
  - [app/Modules/Planificador/views/planificador_menu.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/planificador_menu.php)
- Gestión de zonas:
  - [app/Modules/Planificador/views/zonas.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas.php)
  - [app/Modules/Planificador/views/zonas_rutas.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas_rutas.php)
  - [app/Modules/Planificador/views/zonas_clientes.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas_clientes.php)
- Cierre / ciclo:
  - [app/Modules/Planificador/views/completar_dia.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/completar_dia.php)
  - [app/Modules/Planificador/views/reiniciar_ciclos.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/reiniciar_ciclos.php)
- Pantallas placeholder o puente:
  - [app/Modules/Planificador/views/registrar_dia_no_laborable.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/registrar_dia_no_laborable.php)
  - [app/Modules/Planificador/views/editar_no_laborable.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/editar_no_laborable.php)

## Entry points públicos relacionados

Wrappers públicos detectados:

- [public/planificador_menu.php](/c:/MAMP/htdocs/public/planificador_menu.php)
- [public/zonas.php](/c:/MAMP/htdocs/public/zonas.php)
- [public/zonas_clientes.php](/c:/MAMP/htdocs/public/zonas_clientes.php)
- [public/zonas_rutas.php](/c:/MAMP/htdocs/public/zonas_rutas.php)
- [public/completar_dia.php](/c:/MAMP/htdocs/public/completar_dia.php)
- [public/reiniciar_ciclos.php](/c:/MAMP/htdocs/public/reiniciar_ciclos.php)
- [public/registrar_dia_no_laborable.php](/c:/MAMP/htdocs/public/registrar_dia_no_laborable.php)
- [public/editar_no_laborable.php](/c:/MAMP/htdocs/public/editar_no_laborable.php)
- [public/actualizar_asignacion.php](/c:/MAMP/htdocs/public/actualizar_asignacion.php)
- [public/borrar_asignacion.php](/c:/MAMP/htdocs/public/borrar_asignacion.php)
- [public/eliminar_ruta_zona.php](/c:/MAMP/htdocs/public/eliminar_ruta_zona.php)
- [public/eliminar_zona.php](/c:/MAMP/htdocs/public/eliminar_zona.php)
- [public/procesar_asignar_cliente_zona.php](/c:/MAMP/htdocs/public/procesar_asignar_cliente_zona.php)
- [public/procesar_asignar_ruta_zona.php](/c:/MAMP/htdocs/public/procesar_asignar_ruta_zona.php)
- [public/procesar_crear_zona.php](/c:/MAMP/htdocs/public/procesar_crear_zona.php)
- [public/procesar_reiniciar_ciclos.php](/c:/MAMP/htdocs/public/procesar_reiniciar_ciclos.php)

Criterio:

- Estos wrappers públicos son correctos y sanos.
- Mantienen estables las rutas públicas.
- No son la deuda prioritaria del módulo.

## Controladores/acciones detectados

Controladores reales en `controllers/`:

- [app/Modules/Planificador/controllers/actualizar_asignacion.php](/c:/MAMP/htdocs/app/Modules/Planificador/controllers/actualizar_asignacion.php)
- [app/Modules/Planificador/controllers/borrar_asignacion.php](/c:/MAMP/htdocs/app/Modules/Planificador/controllers/borrar_asignacion.php)
- [app/Modules/Planificador/controllers/eliminar_ruta_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/controllers/eliminar_ruta_zona.php)
- [app/Modules/Planificador/controllers/eliminar_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/controllers/eliminar_zona.php)
- [app/Modules/Planificador/controllers/procesar_asignar_cliente_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/controllers/procesar_asignar_cliente_zona.php)
- [app/Modules/Planificador/controllers/procesar_asignar_ruta_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/controllers/procesar_asignar_ruta_zona.php)
- [app/Modules/Planificador/controllers/procesar_crear_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/controllers/procesar_crear_zona.php)
- [app/Modules/Planificador/controllers/procesar_reiniciar_ciclos.php](/c:/MAMP/htdocs/app/Modules/Planificador/controllers/procesar_reiniciar_ciclos.php)

Observaciones:

- `actualizar_asignacion.php`, `eliminar_ruta_zona.php`, `eliminar_zona.php` y `procesar_reiniciar_ciclos.php` están relativamente bien orientados a acción + redirección.
- `procesar_crear_zona.php` y `procesar_asignar_ruta_zona.php` mezclan acción POST con render completo de HTML de éxito/error.
- `procesar_asignar_cliente_zona.php` sigue teniendo consulta SQL directa en controlador.
- `borrar_asignacion.php` también ejecuta SQL directa desde controlador.

## Views detectadas

Vistas reales:

- [app/Modules/Planificador/views/planificador_menu.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/planificador_menu.php)
- [app/Modules/Planificador/views/zonas.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas.php)
- [app/Modules/Planificador/views/zonas_clientes.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas_clientes.php)
- [app/Modules/Planificador/views/zonas_rutas.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas_rutas.php)
- [app/Modules/Planificador/views/completar_dia.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/completar_dia.php)
- [app/Modules/Planificador/views/reiniciar_ciclos.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/reiniciar_ciclos.php)
- [app/Modules/Planificador/views/registrar_dia_no_laborable.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/registrar_dia_no_laborable.php)
- [app/Modules/Planificador/views/editar_no_laborable.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/editar_no_laborable.php)

Hallazgos:

- Hay lógica de negocio incrustada en vistas, especialmente en:
  - [app/Modules/Planificador/views/planificador_menu.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/planificador_menu.php)
  - [app/Modules/Planificador/views/completar_dia.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/completar_dia.php)
- Hay CSS inline abundante en casi todas las vistas.
- Hay scripts inline en algunas vistas.
- Las vistas no son solo plantillas: varias hacen bootstrapping, permisos, consulta de datos y cálculo previo.

## Services/helpers detectados

Servicios y helpers del módulo:

- [app/Modules/Planificador/services/planificador_service.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/planificador_service.php)
- [app/Modules/Planificador/services/PlanificadorZonasRepository.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/PlanificadorZonasRepository.php)
- [app/Modules/Planificador/services/PlanificadorAsignacionesRepository.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/PlanificadorAsignacionesRepository.php)
- [app/Modules/Planificador/services/PlanificadorViewDataService.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/PlanificadorViewDataService.php)
- [app/Modules/Planificador/services/funciones_planificador.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/funciones_planificador.php)

Lectura arquitectónica:

- `PlanificadorZonasRepository.php` y `PlanificadorAsignacionesRepository.php` son el núcleo de acceso a datos más claro del módulo.
- `PlanificadorViewDataService.php` centraliza parte del armado de datos para vistas, lo cual es una señal positiva.
- `planificador_service.php` funciona como fachada de compatibilidad, capa de aplicación y motor de recomendación a la vez.
- `funciones_planificador.php` parece un wrapper/alias legacy, no un servicio real.

## Dependencias externas del módulo

Dependencias directas observadas:

- Bootstrap de app:
  - `bootstrap/init.php`
  - `bootstrap/auth.php`
- Support compartido:
  - [app/Support/functions.php](/c:/MAMP/htdocs/app/Support/functions.php)
  - [app/Support/header.php](/c:/MAMP/htdocs/app/Support/header.php)
  - `app/Support/db.php` en un controlador puntual
- Layout común:
  - [resources/views/layouts/header.php](/c:/MAMP/htdocs/resources/views/layouts/header.php)
- DB / runtime:
  - `db()`
  - `odbc_exec`, `odbc_prepare`, `odbc_execute`, `odbc_fetch_array`
- Seguridad / utilidades:
  - `requierePermiso('perm_planificador')`
  - `csrfValidateRequest(...)`
  - `csrfInput()`
  - `appExitTextError(...)`
  - `toUTF8(...)`
  - `esDiaLaborable(...)`
  - `obtenerZonaActivaPorFecha(...)`

Acoplamientos sanos:

- Reutilización de `bootstrap/init.php` y `bootstrap/auth.php`.
- Reutilización del layout común.
- Repositorios específicos de zonas y asignaciones.

Acoplamientos con olor a deuda:

- Dependencia fuerte de funciones globales de `app/Support/functions.php`.
- Dependencia implícita de sesión en repositorios y servicios.
- Mezcla de acceso a datos directo en vistas/controladores en paralelo a repositorios ya existentes.

## Archivos raíz que solo actúan como wrapper o puente

Wrappers internos correctos:

- [app/Modules/Planificador/planificador_menu.php](/c:/MAMP/htdocs/app/Modules/Planificador/planificador_menu.php)
- [app/Modules/Planificador/zonas.php](/c:/MAMP/htdocs/app/Modules/Planificador/zonas.php)
- [app/Modules/Planificador/zonas_clientes.php](/c:/MAMP/htdocs/app/Modules/Planificador/zonas_clientes.php)
- [app/Modules/Planificador/zonas_rutas.php](/c:/MAMP/htdocs/app/Modules/Planificador/zonas_rutas.php)
- [app/Modules/Planificador/completar_dia.php](/c:/MAMP/htdocs/app/Modules/Planificador/completar_dia.php)
- [app/Modules/Planificador/reiniciar_ciclos.php](/c:/MAMP/htdocs/app/Modules/Planificador/reiniciar_ciclos.php)
- [app/Modules/Planificador/registrar_dia_no_laborable.php](/c:/MAMP/htdocs/app/Modules/Planificador/registrar_dia_no_laborable.php)
- [app/Modules/Planificador/editar_no_laborable.php](/c:/MAMP/htdocs/app/Modules/Planificador/editar_no_laborable.php)
- [app/Modules/Planificador/actualizar_asignacion.php](/c:/MAMP/htdocs/app/Modules/Planificador/actualizar_asignacion.php)
- [app/Modules/Planificador/borrar_asignacion.php](/c:/MAMP/htdocs/app/Modules/Planificador/borrar_asignacion.php)
- [app/Modules/Planificador/eliminar_ruta_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/eliminar_ruta_zona.php)
- [app/Modules/Planificador/eliminar_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/eliminar_zona.php)
- [app/Modules/Planificador/procesar_asignar_cliente_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/procesar_asignar_cliente_zona.php)
- [app/Modules/Planificador/procesar_asignar_ruta_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/procesar_asignar_ruta_zona.php)
- [app/Modules/Planificador/procesar_crear_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/procesar_crear_zona.php)
- [app/Modules/Planificador/procesar_reiniciar_ciclos.php](/c:/MAMP/htdocs/app/Modules/Planificador/procesar_reiniciar_ciclos.php)

Conclusión:

- Son redundantes como capa técnica, pero hoy son wrappers de compatibilidad válidos.
- No conviene borrarlos todavía porque hay otra capa pública que depende de ellos.

## Archivos con responsabilidad mezclada

Más claros:

- [app/Modules/Planificador/views/planificador_menu.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/planificador_menu.php)
  - hace bootstrap,
  - valida acceso,
  - consulta BD,
  - calcula métricas,
  - arma estructuras de navegación,
  - renderiza HTML y CSS.
- [app/Modules/Planificador/services/planificador_service.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/planificador_service.php)
  - fachada de compatibilidad,
  - view helpers,
  - reglas de negocio,
  - logging debug,
  - motor recomendador.
- [app/Modules/Planificador/views/zonas_clientes.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas_clientes.php)
  - renderiza,
  - usa datos complejos,
  - contiene mucha estructura interactiva y estilos inline.
- [app/Modules/Planificador/controllers/procesar_crear_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/controllers/procesar_crear_zona.php)
  - acción POST,
  - validación,
  - ejecución,
  - render de pantallas de resultado.
- [app/Modules/Planificador/controllers/procesar_asignar_ruta_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/controllers/procesar_asignar_ruta_zona.php)
  - mismo patrón.

## Pantallas o archivos demasiado grandes

Prioridad por tamaño e impacto:

1. [app/Modules/Planificador/views/planificador_menu.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/planificador_menu.php)
   - 1310 líneas
   - mayor impacto visual y funcional
   - mezcla consulta, cálculo y render
2. [app/Modules/Planificador/services/planificador_service.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/planificador_service.php)
   - 931 líneas
   - núcleo transversal del módulo
   - alto riesgo de efectos colaterales
3. [app/Modules/Planificador/views/zonas_clientes.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas_clientes.php)
   - 708 líneas
   - mucha UI y densidad de formularios/acciones
4. [app/Modules/Planificador/services/PlanificadorAsignacionesRepository.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/PlanificadorAsignacionesRepository.php)
   - 409 líneas
   - capa crítica de datos
5. [app/Modules/Planificador/services/PlanificadorZonasRepository.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/PlanificadorZonasRepository.php)
   - 405 líneas
   - capa crítica de datos
6. [app/Modules/Planificador/views/zonas_rutas.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas_rutas.php)
   - 314 líneas
7. [app/Modules/Planificador/views/reiniciar_ciclos.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/reiniciar_ciclos.php)
   - 283 líneas

## Riesgos técnicos actuales

- Duplicidad de rutas por doble wrapper:
  - `public/*.php` -> `app/Modules/Planificador/*.php` -> `views/` o `controllers/`
- Vistas con SQL o cálculo embebido:
  - especialmente `planificador_menu.php`
- Naming inconsistente:
  - `services/planificador_service.php` convive con `PlanificadorZonasRepository.php`
  - snake_case y PascalCase mezclados sin criterio uniforme
- Assets no modularizados:
  - el módulo no tiene JS/CSS públicos propios claramente separados
  - la mayoría del estilo está inline dentro de vistas
- Carpeta vacía:
  - `app/Modules/Planificador/ajax/` no aporta nada hoy
- Código heredado/compatibilidad:
  - `services/funciones_planificador.php`
- Señales de deuda de encoding:
  - aparecen textos mojibake en varios archivos
- Reglas repartidas:
  - parte en repositorios,
  - parte en `planificador_service.php`,
  - parte en vistas,
  - parte en controladores.

## Refactorización recomendada por fases

### Fase 1. Inventario y encapsulación sin impacto

- Mantener `public/*.php` y wrappers internos.
- Congelar rutas públicas actuales.
- Documentar wrappers legacy y ownership por carpeta.
- Marcar `ajax/` como carpeta vacía sin uso real.

### Fase 2. Extraer preparación de datos de vistas críticas

- Prioridad absoluta:
  - `views/planificador_menu.php`
- Prioridad siguiente:
  - `views/zonas_clientes.php`
  - `views/zonas_rutas.php`
  - `views/completar_dia.php`
- Objetivo:
  - que las vistas reciban datos preparados,
  - sin mover todavía lógica de negocio sensible.

### Fase 3. Separar acciones POST de pantallas de resultado

- Convertir gradualmente:
  - `procesar_crear_zona.php`
  - `procesar_asignar_ruta_zona.php`
- Objetivo:
  - POST -> valida -> ejecuta -> redirige con flash.

### Fase 4. Adelgazar `planificador_service.php`

- Separar:
  - fachada legacy,
  - motor recomendador,
  - helpers de vista.
- Mantener funciones legacy públicas como wrappers finos.

### Fase 5. Modularización visual

- Extraer CSS inline de vistas pesadas.
- Crear assets públicos de módulo si se decide intervenir UI.
- No tocar esto antes de estabilizar datos y acciones.

## Quick wins seguros

- Documentar que `public/*.php` son wrappers públicos correctos.
- Documentar que `app/Modules/Planificador/*.php` en raíz son wrappers internos de compatibilidad.
- Marcar `app/Modules/Planificador/ajax/` como prescindible si sigue vacía.
- Marcar [app/Modules/Planificador/services/funciones_planificador.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/funciones_planificador.php) como alias legacy.
- Priorizar extracción de datos de `views/planificador_menu.php` antes que tocar repositorios.

## Qué NO tocar todavía

- No eliminar wrappers de `public/`.
- No eliminar wrappers internos de `app/Modules/Planificador/`.
- No rehacer SQL de repositorios ni de controladores sin pruebas.
- No mover todavía el motor recomendador fuera de `planificador_service.php` sin inventario de llamadas.
- No intervenir CSS/UI de `planificador_menu.php` antes de separar su capa de datos.
- No borrar `ajax/` todavía si no se confirma que nadie la espera por convención del módulo.

## Clasificación de archivos del módulo

### Core real

- [app/Modules/Planificador/views/planificador_menu.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/planificador_menu.php)
- [app/Modules/Planificador/views/zonas.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas.php)
- [app/Modules/Planificador/views/zonas_clientes.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas_clientes.php)
- [app/Modules/Planificador/views/zonas_rutas.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas_rutas.php)
- [app/Modules/Planificador/views/completar_dia.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/completar_dia.php)
- [app/Modules/Planificador/views/reiniciar_ciclos.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/reiniciar_ciclos.php)
- [app/Modules/Planificador/services/planificador_service.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/planificador_service.php)
- [app/Modules/Planificador/services/PlanificadorZonasRepository.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/PlanificadorZonasRepository.php)
- [app/Modules/Planificador/services/PlanificadorAsignacionesRepository.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/PlanificadorAsignacionesRepository.php)
- [app/Modules/Planificador/services/PlanificadorViewDataService.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/PlanificadorViewDataService.php)
- todos los controladores de `controllers/`

### Wrapper / compatibilidad

- todos los PHP de raíz de `app/Modules/Planificador/` que hacen `require_once` a `views/` o `controllers/`
- todos los PHP públicos del módulo en `public/`
- [app/Modules/Planificador/services/funciones_planificador.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/funciones_planificador.php)

### Candidato a consolidación

- [app/Modules/Planificador/views/planificador_menu.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/planificador_menu.php)
- [app/Modules/Planificador/views/zonas_clientes.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas_clientes.php)
- [app/Modules/Planificador/views/zonas_rutas.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/zonas_rutas.php)
- [app/Modules/Planificador/services/planificador_service.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/planificador_service.php)

### Candidato a extracción

- funciones locales en `views/planificador_menu.php`
- CSS inline de:
  - `views/planificador_menu.php`
  - `views/zonas.php`
  - `views/zonas_clientes.php`
  - `views/zonas_rutas.php`
  - `views/completar_dia.php`
  - `views/reiniciar_ciclos.php`
- render HTML de éxito/error en:
  - `controllers/procesar_crear_zona.php`
  - `controllers/procesar_asignar_ruta_zona.php`

### Dudoso / revisar manualmente

- [app/Modules/Planificador/ajax](/c:/MAMP/htdocs/app/Modules/Planificador/ajax)
- [app/Modules/Planificador/services/funciones_planificador.php](/c:/MAMP/htdocs/app/Modules/Planificador/services/funciones_planificador.php)
- [app/Modules/Planificador/views/registrar_dia_no_laborable.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/registrar_dia_no_laborable.php)
- [app/Modules/Planificador/views/editar_no_laborable.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/editar_no_laborable.php)

## Señales específicas pedidas

### Archivos en raíz del módulo que deberían vivir en controllers/, views/ o services/

Sí, pero hoy ya viven realmente allí; la raíz mantiene puentes de compatibilidad. No deben eliminarse todavía.

### Lógica de negocio incrustada en vistas

Sí.

Casos claros:

- [app/Modules/Planificador/views/planificador_menu.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/planificador_menu.php)
- [app/Modules/Planificador/views/completar_dia.php](/c:/MAMP/htdocs/app/Modules/Planificador/views/completar_dia.php)

### Acciones POST mezcladas con render

Sí.

Casos más claros:

- [app/Modules/Planificador/controllers/procesar_crear_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/controllers/procesar_crear_zona.php)
- [app/Modules/Planificador/controllers/procesar_asignar_ruta_zona.php](/c:/MAMP/htdocs/app/Modules/Planificador/controllers/procesar_asignar_ruta_zona.php)

### Duplicidad entre rutas públicas y archivos internos

Sí, pero hoy es duplicidad intencional de compatibilidad, no un error inmediato:

- `public/*.php`
- `app/Modules/Planificador/*.php`
- `app/Modules/Planificador/views/*.php` o `controllers/*.php`

### Naming inconsistente

Sí.

Ejemplos:

- `planificador_service.php` frente a `PlanificadorViewDataService.php`
- `funciones_planificador.php` frente a `PlanificadorZonasRepository.php`
- `procesar_*` y `eliminar_*` conviven con nombres de estilo más orientado a servicio

## Assets JS/CSS específicos del planificador

No se detectan assets públicos propios claramente dedicados al módulo `Planificador`.

Sí hay dependencias visuales compartidas relacionadas con planificación/calendario:

- [public/assets/css/planner-calendar-shared.css](/c:/MAMP/htdocs/public/assets/css/planner-calendar-shared.css)
- [public/assets/js/shared-fullcalendar.js](/c:/MAMP/htdocs/public/assets/js/shared-fullcalendar.js)
- [public/assets/js/visita-editor-modal.js](/c:/MAMP/htdocs/public/assets/js/visita-editor-modal.js)
- [public/assets/js/app-ui.js](/c:/MAMP/htdocs/public/assets/js/app-ui.js)
- [public/assets/js/app-utils.js](/c:/MAMP/htdocs/public/assets/js/app-utils.js)

Conclusión:

- El módulo Planificador depende más de CSS inline que de assets modulares propios.
- La deuda visual está principalmente dentro de las vistas.

## Cambios seguros aplicados

- Se crea este documento de auditoría.
- No se ha aplicado ningún cambio funcional ni estructural en el módulo.
