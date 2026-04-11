# 1. Resumen ejecutivo

El proyecto ha mejorado de verdad en estructura de entrada, organización por módulos y centralización básica de bootstrap/layout/assets. No está "sano" en sentido arquitectónico fuerte, pero ya tampoco está en el punto caótico inicial.

Lo mejor del saneamiento reciente es esto:
- `public/` y `public/ajax/` se han convertido en su mayoría en wrappers finos hacia módulos.
- `bootstrap/` ya concentra arranque, sesión, auth y utilidades base.
- `resources/views/layouts/header.php` y `public/assets/` ya actúan como punto común de UI real.
- `Visitas` y `Planificador` tienen separación parcial entre entrypoint, servicio y vista.

Lo que sigue pesando:
- gran dependencia de funciones globales y `require_once`;
- servicios muy grandes con demasiada responsabilidad;
- SQL ODBC incrustada todavía en servicios y algunos controladores;
- varias vistas pesadas con CSS/JS inline;
- compatibilidad legacy viva en wrappers, aliases y fachadas, especialmente en `Visitas` y `Planificador`;
- problemas de encoding todavía visibles en textos/mensajes.

Conclusión honesta: el proyecto ya está suficientemente ordenado como para dejar de hacer refactor amplio. Sí compensa seguir, pero solo en cambios muy dirigidos y con retorno claro.

# 2. Qué ha quedado realmente saneado

- `bootstrap/app.php`, `bootstrap/init.php` y `bootstrap/auth.php` ya dan un arranque común razonable. Para este proyecto, eso ya es una base válida.
- `public/*.php` y `public/ajax/*.php` están casi todos reducidos a dispatchers de 3-5 líneas hacia módulos. Esto sí es un saneamiento real y útil.
- `app/Support/db.php` ya centraliza la apertura ODBC, configuración y logging de conexión. No es bonito, pero sí útil y estable.
- `resources/views/layouts/header.php` ya concentra header global, Bootstrap local, Font Awesome local y parte de la UI común.
- `public/assets/modules/visitas/*` y `public/assets/modules/planificador/*` muestran externalización real de JS/CSS que antes previsiblemente estaba más dispersa.
- `app/Modules/Planificador/views/planificador_menu.php` está bastante mejor resuelto que otras vistas: usa builder, asset propio y markup ya más limpio.
- `app/Modules/Pedidos/api/pedidos_todos.php` + `app/Modules/Pedidos/api/pedidos_todos_controller.php` sí representan un paso útil hacia endpoint más limpio.
- `app/Support/statistics.php` parece ya un wrapper de compatibilidad sobre servicios reales de `Estadisticas`, no un cajón nuevo. Eso está suficientemente bien por ahora.

# 3. Qué deuda estructural importante sigue viva

- La arquitectura sigue siendo funcional/procedural, no modular fuerte. Hay módulos por carpetas, pero no hay frontera dura de dominio.
- Se sigue abusando de funciones globales compartidas y de `extract(...)`, lo que mantiene acoplamiento implícito.
- `app/Support/functions.php` sigue siendo un macro-archivo enorme y de muy alta entropía. Es soporte central, pero también vertedero histórico.
- Persisten servicios gigantes:
  - `app/Modules/Visitas/services/VisitasService.php` (~675 líneas)
  - `app/Modules/Visitas/services/VisitasQueryService.php` (~502)
  - `app/Modules/Planificador/services/planificador_service.php` (~470)
  - `app/Modules/Planificador/services/PlanificadorRecomendacionService.php` (~497)
  - `app/Modules/Planificador/services/PlanificadorAsignacionesRepository.php` (~458)
  - `app/Modules/Pedidos/services/pedidos_todos_service.php` (~447)
  - `app/Modules/Pedidos/services/faltas_todos_service.php` (~446)
- Sigue habiendo SQL interpolada a string en varios puntos relevantes. No es un detalle estilístico: es deuda operativa real.
- La compatibilidad legacy sigue incrustada en servicios activos, no aislada en una capa claramente desechable.
- Hay mezcla inconsistente de assets locales con dependencias por CDN. En `app/Modules/Visitas/views/visita_pedido/index.php` todavía se carga FullCalendar desde CDN.
- El problema de encoding no está erradicado. Se ven cadenas rotas en varios ficheros, lo que indica deuda transversal todavía viva.

# 4. Estado por módulos

## Visitas

Estado general: mejorado, pero todavía híbrido.

Lo que está bien:
- Tiene organización clara de `controllers/`, `services/`, `views/`, `ajax/`.
- `public/visita_manual.php`, `public/visita_pedido.php`, `public/get_visitas.php` y similares son wrappers finos.
- Hay externalización real de front en `public/assets/modules/visitas/visita_manual.*` y `visita_pedido.*`.
- `VisitaManualViewBuilder.php` sí aporta valor y reduce ruido de la vista.

Lo que sigue mal:
- `VisitasService.php` sigue siendo un contenedor mixto de compatibilidad, lógica de negocio, edición, asociación de pedidos y operaciones directas con BD.
- `VisitasQueryService.php` mezcla helpers válidos con SQL interpolada y consultas crudas.
- `controllers/visita_pedido.php` sigue llevando SQL directa y armado manual de datos. Es el controlador más claramente fuera del patrón saneado.
- La compatibilidad está demasiado viva:
  - funciones alias legacy;
  - `procesarRegistroVisitaLegacy`;
  - fachadas "kept for compatibility";
  - dispatch legacy en `public/visitas.php`.
- `public/assets/modules/visitas/visita_pedido.js` es muy grande (~70 KB, lógica abundante) y además arrastra síntomas de encoding roto en mensajes.

Juicio:
- `Visitas` está suficientemente bien para operar y evolucionar funcionalidad.
- No está suficientemente bien para justificar una refactorización amplia adicional.
- Solo merece cirugía muy dirigida en los puntos con mayor mezcla de flujo/SQL/compatibilidad.

## Planificador

Estado general: es el módulo con mejor avance estructural relativo.

Lo que está bien:
- Tiene repositorios diferenciados (`PlanificadorZonasRepository.php`, `PlanificadorAsignacionesRepository.php`).
- Tiene capa de view-data/builders (`PlanificadorViewsDataService.php`, `PlanificadorMenuViewBuilder.php`, `PlanificadorZonasClientesViewBuilder.php`).
- `views/planificador_menu.php` es el mejor ejemplo actual de vista ya razonablemente saneada.
- Assets propios en `public/assets/modules/planificador/*` ya sacan bastante CSS/JS fuera de la vista.

Lo que sigue mal:
- `planificador_service.php` sigue funcionando como índice + fachada + compatibilidad + aliases de servicio.
- `PlanificadorRecomendacionService.php` sigue siendo un bloque denso y crítico; si falla, el saneamiento externo no protege mucho.
- Los repositorios siguen usando bastante SQL interpolada.
- `controllers/actualizar_asignacion.php` mantiene SQL directa en controlador.
- `views/zonas_clientes.php` y `views/zonas_rutas.php` siguen siendo vistas bastante pesadas y todavía muy ligadas al flujo legacy del módulo.

Juicio:
- `Planificador` ya está en un punto suficientemente válido.
- Aquí el retorno de seguir refactorizando estructura completa es bajo.
- Solo compensa tocar fallos concretos, flujo crítico o puntos de seguridad/mantenibilidad muy claros.

## Pedidos

Estado general: mejora parcial, pero sigue siendo el módulo más pesado en UI y consultas.

Lo que está bien:
- Existe una separación útil entre página y API en `pedidos_todos`.
- `public/pedido.php`, `public/pedidos_todos.php`, `public/api/pedidos_todos.php` ya actúan como entrypoints finos.
- Parte del dominio se ha extraído a `pedido_service.php`, `pedidos_todos_service.php` y `faltas_todos_service.php`.

Lo que sigue mal:
- `app/Modules/Pedidos/pedidos_todos.php` (~521 líneas), `Views/faltas.php` (~435), `Views/faltas_todos.php` (~433), `ajax/detalle_documento.php` (~626) y `Views/modal_documento.php` (~543) siguen siendo piezas muy pesadas.
- `pedido.php` sigue metiendo bastante CSS inline.
- Los servicios siguen cargando SQL compleja y extensa.
- `controllers/faltas.php` sigue teniendo SQL directa y es uno de los controladores más dudosos del repo.
- En este módulo el saneamiento ha sido más de encapsulación superficial que de simplificación real del corazón.

Juicio:
- `Pedidos` está usable y algo mejor ordenado.
- No merece una re-arquitectura grande ahora.
- Sí merece mejoras puntuales en vistas/endpoint pesados con impacto directo en mantenimiento.

## Otros módulos relevantes

- `Home/index.php` sigue siendo un bloque muy grande y muy procedural. No parece prioridad para más saneamiento ahora salvo incidencia funcional.
- `Estadisticas` parece haber mejorado por debajo mediante `app/Support/statistics.php` como wrapper hacia servicios, pero el módulo visible sigue siendo pesado.
- `Clientes` y `Configuracion` siguen con vistas grandes e inline, pero no son hoy el cuello de botella más claro para la decisión estratégica.

# 5. Estado de servicios legacy

Servicios legacy todavía relevantes:
- `app/Support/functions.php`: sigue siendo el gran soporte transversal. Es útil, pero estructuralmente es deuda viva.
- `app/Support/statistics.php`: wrapper legacy válido; no urge tocarlo más si sigue estable.
- `app/Support/pedidos_badges.php`: soporte pequeño y razonable; no merece atención ahora.
- `app/Modules/Planificador/services/planificador_service.php`: probablemente el mayor concentrador de compatibilidad activa del proyecto.
- `app/Modules/Visitas/services/VisitasService.php`: mezcla fachada legacy con lógica actual, y eso lo convierte en punto de fragilidad.

Valoración:
- Hay servicios legacy "aceptables" y otros peligrosos.
- Los aceptables son los que ya actúan como capa de compatibilidad fina.
- Los peligrosos son los que además siguen concentrando dominio real.

# 6. Estado de vistas pesadas

Vistas claramente pesadas o con carga alta:
- `app/Modules/Pedidos/ajax/detalle_documento.php`
- `app/Modules/Pedidos/Views/modal_documento.php`
- `app/Modules/Pedidos/pedidos_todos.php`
- `app/Modules/Pedidos/Views/faltas.php`
- `app/Modules/Pedidos/Views/faltas_todos.php`
- `app/Modules/Planificador/views/zonas_rutas.php`
- `app/Modules/Planificador/views/zonas_clientes.php`
- `app/Modules/Visitas/views/programar_visita.php`
- `app/Modules/Visitas/views/visita_sin_venta.php`

Situación real:
- En `Planificador` y parte de `Visitas` ya hay externalización apreciable.
- En `Pedidos` y otros módulos todavía hay demasiada vista haciendo de plantilla + layout + estilos + comportamiento.
- No todas estas vistas justifican extracción adicional inmediata. Algunas ya están "feas pero válidas".

# 7. Estado de controladores con flujo dudoso o SQL directa

Controladores claramente a vigilar:
- `app/Modules/Visitas/controllers/visita_pedido.php`
  - sigue con SQL directa, armado de datos manual y dependencia fuerte de estructura histórica.
- `app/Modules/Planificador/controllers/actualizar_asignacion.php`
  - mantiene update SQL directo en controlador.
- `app/Modules/Pedidos/controllers/faltas.php`
  - carga SQL extensa y es uno de los puntos más densos del flujo.

Controladores aceptables aunque no ideales:
- `app/Modules/Visitas/controllers/registrar_visita.php`
  - no está "limpio limpio", pero ya delega el núcleo y es suficiente por ahora.
- `app/Modules/Pedidos/api/pedidos_todos_controller.php`
  - correcto para el tamaño actual del proyecto.

Conclusión:
- No hay una plaga de controladores enormes en el estado actual.
- El problema ya se ha desplazado más a servicios gigantes y algunos controladores concretos.

# 8. Estado de assets públicos vs inline

Mejoras reales:
- `public/assets/vendor/*` centraliza librerías frontend relevantes.
- `public/assets/modules/visitas/*` y `public/assets/modules/planificador/*` ya alojan parte importante del comportamiento y estilos.
- `resources/views/layouts/header.php` unifica Bootstrap, iconos y header global.

Deuda viva:
- Sigue habiendo mucho `<style>` y `<script>` inline en módulos relevantes, especialmente `Pedidos`, `Clientes`, `Configuracion`, `Estadisticas` y parte de `Visitas`.
- `app/Modules/Visitas/views/visita_pedido/index.php` usa FullCalendar desde CDN en vez de asset local.
- El header global también contiene bastante CSS/JS inline; es aceptable como punto común, pero no está realmente "limpio".

Conclusión:
- La dirección es buena.
- El frente ya no es un caos.
- Pero todavía no se puede decir que el proyecto tenga política consistente de assets.

# 9. Estado de wrappers y compatibilidad

No existe un directorio `wrappers/` como tal. La compatibilidad activa vive en tres sitios:

- `public/*.php` y `public/ajax/*.php`
  - en general bien: wrappers finos, útiles y con bajo coste de mantenimiento.
- servicios-fachada
  - `app/Modules/Planificador/services/planificador_service.php`
  - `app/Modules/Planificador/services/PlanificadorViewDataService.php`
  - `app/Modules/Visitas/services/VisitasService.php`
  - `app/Support/statistics.php`
- dispatchers legacy explícitos
  - `public/visitas.php` sigue siendo dispatcher heredado por compatibilidad.

Juicio:
- Los wrappers de `public/` sí compensa mantenerlos.
- La compatibilidad dentro de servicios gigantes no compensa seguir ampliándola.
- El objetivo no debería ser eliminar compatibilidad ahora, sino dejar de engordarla.

# 10. Riesgos técnicos actuales

- Riesgo de regresión por acoplamiento implícito entre funciones globales, `extract()` y variables de vista.
- Riesgo de mantenimiento por tamaño y mezcla de responsabilidades en servicios clave.
- Riesgo de seguridad/mantenibilidad por SQL interpolada todavía activa en varios puntos.
- Riesgo operativo por encoding roto en textos, respuestas y JS.
- Riesgo de deuda invisible: parece más modular de lo que realmente es.
- Riesgo de seguir gastando Codex en refactor estructural de bajo retorno y generar desgaste sin impacto de negocio.

# 11. Quick wins reales que aún compensan

- Eliminar SQL directa en los 3 controladores más dudosos.
- Sustituir CDN de FullCalendar por asset local o dejarlo explícitamente consolidado si no se quiere tocar más.
- Corregir encoding roto en mensajes/strings de los flujos más usados.
- Recortar compatibilidad muerta o duplicada dentro de `VisitasService.php` y `planificador_service.php`, sin re-arquitectura grande.
- Extraer CSS/JS inline solo de las pantallas más tocadas, no de todo el proyecto.
- Documentar oficialmente qué entrypoints públicos son canónicos y cuáles solo son compatibilidad.

# 12. Qué ya NO merece la pena tocar ahora

- No merece la pena rehacer `public/` solo por pureza: ya cumple bien su función de wrapper.
- No merece la pena redividir masivamente `app/Support/functions.php` en esta fase. Sería caro y arriesgado para el retorno esperado.
- No merece la pena una re-arquitectura completa de `Pedidos`.
- No merece la pena intentar llevar todo a clases/DDD/PSR "bonito" en este punto.
- No merece la pena limpiar todos los inline del proyecto.
- No merece la pena perseguir perfección en módulos secundarios si no hay dolor funcional o de mantenimiento directo.

# 13. Recomendación de estrategia siguiente, eligiendo claramente UNA

## seguir, pero solo con cambios muy dirigidos

Motivo:
- El saneamiento reciente ya ha comprado casi todo el valor estructural grueso que realmente hacía falta.
- A partir de aquí, más refactor amplio con Codex tiene rendimiento decreciente.
- El mejor uso de Codex ahora no es "seguir limpiando por si acaso", sino atacar puntos concretos con impacto real:
  - controladores con SQL directa;
  - servicios gigantes donde haya duplicidad o compatibilidad claramente sobrante;
  - vistas/JS pesadas realmente activas en negocio;
  - encoding y estabilidad de flujos.

No recomiendo:
- `seguir con más refactor estructural usando Codex`: demasiado retorno decreciente.
- `parar refactor y pasar a funcionalidad` sin más: todavía hay 4-6 arreglos quirúrgicos que sí compensa hacer antes o en paralelo.

# 14. Top 10 siguientes acciones priorizadas

1. Reducir SQL directa en `app/Modules/Visitas/controllers/visita_pedido.php`
- impacto: alto
- riesgo: medio
- dificultad: media
- si merece o no gastar Codex en ello: sí

2. Mover la actualización SQL de `app/Modules/Planificador/controllers/actualizar_asignacion.php` a capa de servicio/repositorio ya existente
- impacto: medio
- riesgo: bajo
- dificultad: baja
- si merece o no gastar Codex en ello: sí

3. Revisar y adelgazar `app/Modules/Visitas/services/VisitasService.php` quitando compatibilidad duplicada o código claramente sobrante
- impacto: alto
- riesgo: medio
- dificultad: media
- si merece o no gastar Codex en ello: sí, pero con alcance muy acotado

4. Corregir encoding roto en mensajes y cadenas visibles de `Visitas` y `Planificador`
- impacto: medio
- riesgo: bajo
- dificultad: baja
- si merece o no gastar Codex en ello: sí

5. Consolidar `visita_pedido` para no depender de FullCalendar por CDN
- impacto: medio
- riesgo: bajo
- dificultad: baja-media
- si merece o no gastar Codex en ello: sí

6. Documentar cuáles endpoints de `public/` y `public/ajax/` son canónicos y cuáles son compatibilidad
- impacto: medio
- riesgo: bajo
- dificultad: baja
- si merece o no gastar Codex en ello: sí, si se quiere bajar incertidumbre antes de nuevas funcionalidades

7. Extraer inline CSS/JS solo de `app/Modules/Pedidos/pedido.php` y/o `app/Modules/Pedidos/pedidos_todos.php`
- impacto: medio
- riesgo: bajo
- dificultad: media
- si merece o no gastar Codex en ello: sí, pero solo si esas pantallas se van a tocar mucho

8. Revisar `app/Modules/Pedidos/controllers/faltas.php` y decidir si basta con encapsular consultas críticas sin rehacer la pantalla
- impacto: medio
- riesgo: medio
- dificultad: media-alta
- si merece o no gastar Codex en ello: sí, pero solo si `faltas` sigue siendo flujo importante

9. Congelar el crecimiento de wrappers de compatibilidad y dejar criterio explícito de no añadir más fachadas legacy salvo necesidad real
- impacto: medio
- riesgo: bajo
- dificultad: baja
- si merece o no gastar Codex en ello: no especialmente; más decisión de criterio que trabajo grande

10. Pasar a funcionalidad nueva sobre la base actual, usando refactor solo como soporte de cada cambio
- impacto: muy alto
- riesgo: medio
- dificultad: variable
- si merece o no gastar Codex en ello: sí, aquí es donde más retorno debería dar a partir de ahora
