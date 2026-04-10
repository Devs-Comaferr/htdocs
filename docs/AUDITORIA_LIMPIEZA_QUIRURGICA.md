# Auditoria de limpieza quirurgica

Fecha: 2026-04-11
Alcance: analisis estatico y limpieza de riesgo muy bajo sobre el estado real del repositorio, sin rehacer arquitectura ni tocar logica de negocio.

## Resumen ejecutivo

- El proyecto ya mezcla una capa modular en `app/Modules` con una capa de compatibilidad publica muy amplia en `public/`.
- La mayor parte de los archivos raiz de `public/` son wrappers finos correctos: mantienen rutas publicas estables y delegan en modulos internos.
- Hay senales de deuda estructural por dispersion de rutas, duplicidad de puertas de entrada y capas scaffolding vacias o no activadas (`app/Http`, `resources/modals`).
- `config/runtime_secrets.local.php` ya estaba correctamente protegido frente a versionado en [`.gitignore`](/c:/MAMP/htdocs/.gitignore).
- La duplicidad entre [`imagenes`](/c:/MAMP/htdocs/imagenes) y [`public/imagenes`](/c:/MAMP/htdocs/public/imagenes) no es una copia real: `public/imagenes` es una junction al directorio raiz `imagenes`.
- Se confirma que `public/test_types_json.php` era un script manual de inspeccion de dataset sin referencias entrantes. Se ha eliminado por ser limpieza segura.
- Se ha eliminado un BOM molesto en [`public/planificador_menu.php`](/c:/MAMP/htdocs/public/planificador_menu.php) sin cambiar comportamiento.

## Archivos de prueba/debug detectados

- Eliminado por limpieza segura:
  `public/test_types_json.php`
  Script manual para listar tipos dentro de `dataset-work-calendar.json`; sin referencias detectadas en el repo.
- Detectado y documentado, no eliminado por prudencia:
  [`storage/debug_import_encoding.php`](/c:/MAMP/htdocs/storage/debug_import_encoding.php)
  Script puntual de comprobacion de encoding para importacion de festivos. No tiene referencias entrantes, pero vive en `storage/` y podria seguir usandose en soporte manual.

## Wrappers publicos finos correctos

- La raiz de [`public`](/c:/MAMP/htdocs/public) contiene decenas de wrappers de 2-4 lineas que hacen `bootstrap/init.php` y delegan al modulo canonico. Esto es correcto mientras se necesite compatibilidad de URL.
- Ejemplos claros:
  [`public/index.php`](/c:/MAMP/htdocs/public/index.php)
  [`public/clientes.php`](/c:/MAMP/htdocs/public/clientes.php)
  [`public/pedido.php`](/c:/MAMP/htdocs/public/pedido.php)
  [`public/estadisticas.php`](/c:/MAMP/htdocs/public/estadisticas.php)
  [`public/visita_pedido.php`](/c:/MAMP/htdocs/public/visita_pedido.php)
  [`public/api/pedidos_todos.php`](/c:/MAMP/htdocs/public/api/pedidos_todos.php)
- [`modales/modal_documento.php`](/c:/MAMP/htdocs/modales/modal_documento.php) sigue siendo util como wrapper de compatibilidad hacia [`app/Modules/Pedidos/Views/modal_documento.php`](/c:/MAMP/htdocs/app/Modules/Pedidos/Views/modal_documento.php). Se mantiene.

## Wrappers redundantes o mejorables

- [`public/alta_cliente.php`](/c:/MAMP/htdocs/public/alta_cliente.php) y [`public/altaClientes/alta_cliente.php`](/c:/MAMP/htdocs/public/altaClientes/alta_cliente.php) apuntan al mismo modulo [`app/Modules/Clientes/alta_cliente.php`](/c:/MAMP/htdocs/app/Modules/Clientes/alta_cliente.php). Parece compatibilidad deliberada, asi que no se toca, pero es redundancia documentada.
- [`app/Modules/Pedidos/api/pedidos_todos_controller.php`](/c:/MAMP/htdocs/app/Modules/Pedidos/api/pedidos_todos_controller.php) solo reexpone [`app/Modules/Pedidos/controllers/pedidos_todos_controller.php`](/c:/MAMP/htdocs/app/Modules/Pedidos/controllers/pedidos_todos_controller.php). Es una capa fina adicional, candidata a simplificacion futura si se consolida la API.
- [`public/visitas.php`](/c:/MAMP/htdocs/public/visitas.php) no es un wrapper fino sino un dispatcher legacy por `action`. Sigue teniendo sentido por compatibilidad, pero concentra deuda de enrutado y merece fase propia.

## Carpetas vacias o sin uso claro

- [`app/Http/Controllers`](/c:/MAMP/htdocs/app/Http/Controllers) vacia.
- [`app/Http/Middleware`](/c:/MAMP/htdocs/app/Http/Middleware) vacia.
- [`resources/modals`](/c:/MAMP/htdocs/resources/modals) vacia.
- Conclusion:
  [`app/Http`](/c:/MAMP/htdocs/app/Http) y [`resources/modals`](/c:/MAMP/htdocs/resources/modals) parecen scaffolding o capas de transicion no activadas. No se eliminan por prudencia porque su retirada no aporta valor funcional inmediato y podria interferir con expectativas del equipo.

## Recursos duplicados o dispersos

- [`public/imagenes`](/c:/MAMP/htdocs/public/imagenes) es una junction hacia [`imagenes`](/c:/MAMP/htdocs/imagenes). Hoy no hay duplicidad fisica real, pero si dos rutas publicas posibles.
- Favicon y branding aparecen dispersos entre:
  [`favicon.ico`](/c:/MAMP/htdocs/favicon.ico)
  [`imagenes/favicon.ico`](/c:/MAMP/htdocs/imagenes/favicon.ico)
  [`public/assets/favicon-32.png`](/c:/MAMP/htdocs/public/assets/favicon-32.png)
  [`imagenes/favicon-32.png`](/c:/MAMP/htdocs/imagenes/favicon-32.png)
- No se migra nada ahora porque cualquier consolidacion puede romper referencias historicas en HTML, manifests o accesos directos.

## Endpoints sin referencias claras

- Confirmado sin referencias entrantes:
  `public/test_types_json.php` eliminado
- Candidatos sin referencias claras, pero no eliminados por prudencia:
  [`storage/debug_import_encoding.php`](/c:/MAMP/htdocs/storage/debug_import_encoding.php)
- Nota metodologica:
  el resto de endpoints publicos revisados en `public/`, `public/ajax/` y `public/api/` si muestran referencias internas o encajan como wrappers publicos canonicos; eso no garantiza uso en runtime, pero si justifica conservarlos en esta fase.

## Dependencias Composer usadas / dudosas / no confirmadas

### Usadas claramente

- `phpmailer/phpmailer`
  Referencias en [`app/Modules/AltaClientes/mail_config.php`](/c:/MAMP/htdocs/app/Modules/AltaClientes/mail_config.php) y [`app/Modules/Clientes/services/alta_cliente_service.php`](/c:/MAMP/htdocs/app/Modules/Clientes/services/alta_cliente_service.php)
- `mailchimp/marketing`
  Referencias en [`app/Modules/AltaClientes/mailchimp_sdk_subscribe.php`](/c:/MAMP/htdocs/app/Modules/AltaClientes/mailchimp_sdk_subscribe.php)
- `npm-asset/bootstrap`
  Referencias en [`resources/views/layouts/header.php`](/c:/MAMP/htdocs/resources/views/layouts/header.php) y varios modulos con `bootstrap.Modal`
- `npm-asset/jquery`
  Referencias en [`resources/views/layouts/header.php`](/c:/MAMP/htdocs/resources/views/layouts/header.php), [`app/Modules/Home/index.php`](/c:/MAMP/htdocs/app/Modules/Home/index.php), [`app/Modules/Clientes/seccion_detalles.php`](/c:/MAMP/htdocs/app/Modules/Clientes/seccion_detalles.php)
- `npm-asset/nouislider`
  Referencias en [`app/Modules/Clientes/historico.php`](/c:/MAMP/htdocs/app/Modules/Clientes/historico.php) y [`app/Modules/Estadisticas/estadisticas_ventas_clasicas.php`](/c:/MAMP/htdocs/app/Modules/Estadisticas/estadisticas_ventas_clasicas.php)
- `npm-asset/wnumb`
  Referencias en los mismos flujos de slider temporal
- `npm-asset/fortawesome--fontawesome-free`
  Referencias en [`resources/views/layouts/header.php`](/c:/MAMP/htdocs/resources/views/layouts/header.php) y pantallas de configuracion/clientes
- `npm-asset/chart.js`
  Referencias en [`app/Modules/Clientes/cliente_detalles.php`](/c:/MAMP/htdocs/app/Modules/Clientes/cliente_detalles.php) y [`app/Modules/Clientes/seccion_detalles.php`](/c:/MAMP/htdocs/app/Modules/Clientes/seccion_detalles.php)
- `npm-asset/chartjs-plugin-datalabels`
  Referencias junto a Chart.js en los mismos modulos

### Uso localizado

- `mailchimp/marketing`
  Uso concentrado en alta de clientes; no es transversal.
- `phpmailer/phpmailer`
  Uso concentrado en envio de correo de alta cliente.

### Uso dudoso / no confirmado

- `vlucas/phpdotenv`
  No aparecen referencias fiables a `Dotenv\\`, `createImmutable()` o `load()` propias del paquete dentro de `app/`, `bootstrap/`, `config/`, `public/` o `resources/`.
  No se elimina porque podria estar previsto para bootstrap futuro o usarse fuera del arbol analizado, pero hoy su uso no queda confirmado.

## Riesgos de limpieza

- El mayor riesgo no esta en archivos muertos aislados sino en quitar wrappers publicos que preservan rutas historicas.
- La coexistencia de multiples puertas de entrada hacia el mismo modulo sugiere compatibilidad legacy activa; por eso no se han eliminado wrappers salvo certeza absoluta.
- La junction [`public/imagenes`](/c:/MAMP/htdocs/public/imagenes) puede inducir a falsa sensacion de duplicidad. Una consolidacion apresurada podria romper URLs publicas.
- [`public/visitas.php`](/c:/MAMP/htdocs/public/visitas.php) actua como dispatcher legacy y no debe tocarse en una fase de limpieza cosmetica.

## Propuesta de siguientes fases

- Fase 1: inventario de rutas publicas reales
  Registrar accesos, formularios, redirects y `fetch/ajax` para saber que wrappers siguen recibiendo trafico.
- Fase 2: consolidacion de wrappers duplicados
  Empezar por pares equivalentes como `alta_cliente.php` y `altaClientes/alta_cliente.php`, dejando redireccion o wrapper unico segun telemetria.
- Fase 3: saneado de scaffolding no activado
  Retirar `app/Http` y `resources/modals` solo cuando exista acuerdo de equipo y ausencia de uso en roadmap.
- Fase 4: racionalizacion de recursos estaticos
  Definir ruta canonica de branding/favicons y migrar referencias de forma controlada.
- Fase 5: dependencias Composer
  Verificar si `vlucas/phpdotenv` sigue aportando valor real antes de quitarla.
- Fase 6: dispatcher legacy
  Extraer y estabilizar el contrato de [`public/visitas.php`](/c:/MAMP/htdocs/public/visitas.php) antes de simplificarlo.

## Plan recomendado de limpieza por fases

### Fase A: limpieza segura inmediata

- Eliminar scripts manuales sin referencias y claramente no productivos.
- Retirar BOMs o ruido de encoding en wrappers finos.
- Etiquetar wrappers legacy relevantes para evitar borrados accidentales.

### Fase B: inventario de compatibilidad publica

- Listar rutas duplicadas que apuntan al mismo modulo.
- Identificar cuales son canonicas y cuales son solo historicas.
- Medir impacto antes de consolidar.

### Fase C: consolidacion estructural de bajo impacto

- Reducir wrappers intermedios redundantes.
- Unificar capas puente de controladores/API donde no aporten contrato independiente.

### Fase D: ordenacion de recursos y dependencias

- Definir arbol canonico de assets.
- Validar dependencias Composer dudosas.
- Cerrar scaffolding vacio no activado.
