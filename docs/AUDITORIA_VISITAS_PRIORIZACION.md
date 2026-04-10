# Auditoria Quirurgica de Priorizacion del Modulo Visitas

## 1. Resumen ejecutivo corto

El siguiente frente con mejor retorno tecnico/funcional en `app/Modules/Visitas` es el flujo **`visita_manual`**.

No es el unico punto con deuda, pero si el que mejor combina:
- uso probable alto
- vista muy pesada
- JS/CSS inline todavia embebidos
- acoplamiento fuerte con `VisitasService.php`
- impacto funcional directo sobre el alta manual de visitas

La capa `public/*` de Visitas, en general, esta razonablemente sana: los entry points revisados (`public/visita_manual.php`, `public/visita_pedido.php`, varios `public/ajax/*`) actuan como wrappers finos y no son hoy la deuda principal.

## 2. Top 5 archivos/flujos mas prioritarios

### 1. Flujo `visita_manual`
- Archivos principales:
  - `app/Modules/Visitas/views/visita_manual.php`
  - `app/Modules/Visitas/controllers/visita_manual.php`
  - `app/Modules/Visitas/controllers/registrar_visita.php`
  - `app/Modules/Visitas/services/VisitasService.php`
- Por que es prioritario:
  - La vista pesa mucho (`~42 KB`) y mezcla render, estilos inline, JS inline, bootstrap de flujo y dependencias de varias llamadas AJAX.
  - Es una pantalla de captura principal y con alta probabilidad de uso operativo.
  - El POST real termina delegando en un servicio muy cargado, asi que cualquier futura mejora de seguridad o mantenibilidad pasa por este flujo.
- Tipo de deuda:
  - vista pesada
  - assets inline
  - mezcla de capas
  - flujo GET/POST muy acoplado al servicio
- Riesgo:
  - medio
- Impacto:
  - muy alto
- Dificultad estimada:
  - media

### 2. `app/Modules/Visitas/services/VisitasService.php`
- Por que es prioritario:
  - Es el cuello de botella tecnico del modulo (`~34 KB`).
  - Mezcla validacion operativa, transacciones, inserciones, actualizaciones, asociaciones visita-pedido y logica de negocio de varios subflujos.
  - Ya existe una base de separacion con `VisitasQueryService.php`, `VisitasValidationService.php`, `VisitasCalendarioService.php` y `VisitasAjaxService.php`, pero el servicio principal sigue absorbiendo demasiadas responsabilidades.
- Tipo de deuda:
  - mezcla de capas
  - servicio god-object
  - logica transaccional concentrada
- Riesgo:
  - alto
- Impacto:
  - muy alto
- Dificultad estimada:
  - media-alta

### 3. Cluster de edicion de visita
- Archivos principales:
  - `app/Modules/Visitas/views/editar_visita_handler.php`
  - `app/Modules/Visitas/controllers/editar_visita.php`
  - `app/Modules/Visitas/ajax/obtener_visita_edicion.php`
  - `app/Modules/Visitas/ajax/guardar_visita_edicion.php`
  - `app/Modules/Visitas/ajax/eliminar_visita_edicion.php`
  - `public/assets/js/visita-editor-modal.js`
- Por que es prioritario:
  - Es un flujo repartido entre vista legacy, AJAX y asset compartido.
  - Funcionalmente es sensible porque toca edicion y borrado.
  - Tiene sintomas de evolucion incremental: handler de vista, endpoints AJAX y modal reutilizable conviven sin una frontera del todo clara.
- Tipo de deuda:
  - flujo fragmentado
  - mezcla de capas
  - AJAX disperso
- Riesgo:
  - medio-alto
- Impacto:
  - alto
- Dificultad estimada:
  - media

### 4. Duplicidad casi total entre `programar_visita` y `visita_sin_venta`
- Archivos principales:
  - `app/Modules/Visitas/views/programar_visita.php`
  - `app/Modules/Visitas/views/visita_sin_venta.php`
  - controladores equivalentes
- Por que es prioritario:
  - Las dos vistas son practicamente la misma pantalla; cambian sobre todo titulo, action y CTA.
  - Mantener dos copias casi identicas aumenta el coste de cada correccion y eleva el riesgo de divergencia.
- Tipo de deuda:
  - duplicidad de vistas
  - assets inline
  - mantenimiento paralelo innecesario
- Riesgo:
  - medio
- Impacto:
  - medio-alto
- Dificultad estimada:
  - baja-media

### 5. `app/Modules/Visitas/views/get_visitas.php` y consulta asociada
- Archivos principales:
  - `app/Modules/Visitas/views/get_visitas.php`
  - `app/Modules/Visitas/controllers/get_visitas.php`
  - `app/Modules/Visitas/services/VisitasQueryService.php`
- Por que es prioritario:
  - La pantalla sigue siendo una vista pesada con CSS inline y render denso.
  - La query de soporte en `VisitasQueryService.php` parece tener bastante responsabilidad de armado.
  - Es una pantalla de consulta operativa, con valor funcional alto si se quiere seguir saneando el modulo de forma visible.
- Tipo de deuda:
  - vista pesada
  - assets inline
  - preparacion de datos y render demasiado juntos
- Riesgo:
  - medio
- Impacto:
  - medio-alto
- Dificultad estimada:
  - media

## 3. Para cada uno

### 1. Flujo `visita_manual`
- Por que es prioritario:
  - concentra deuda visual, de flujo y de acoplamiento con negocio
- Tipo de deuda:
  - vista pesada, assets inline, mezcla de capas
- Riesgo:
  - medio
- Impacto:
  - muy alto
- Dificultad estimada:
  - media

### 2. `VisitasService.php`
- Por que es prioritario:
  - concentra demasiadas responsabilidades del modulo
- Tipo de deuda:
  - servicio central sobredimensionado
- Riesgo:
  - alto
- Impacto:
  - muy alto
- Dificultad estimada:
  - media-alta

### 3. Cluster de edicion de visita
- Por que es prioritario:
  - es un flujo delicado y repartido entre varias capas
- Tipo de deuda:
  - AJAX disperso, mezcla de responsabilidades
- Riesgo:
  - medio-alto
- Impacto:
  - alto
- Dificultad estimada:
  - media

### 4. `programar_visita` / `visita_sin_venta`
- Por que es prioritario:
  - la duplicidad esta muy clara y casi textual
- Tipo de deuda:
  - duplicidad estructural
- Riesgo:
  - medio
- Impacto:
  - medio-alto
- Dificultad estimada:
  - baja-media

### 5. `get_visitas`
- Por que es prioritario:
  - sigue siendo una pantalla con bastante peso visual y de armado
- Tipo de deuda:
  - vista pesada, preparacion mezclada con render
- Riesgo:
  - medio
- Impacto:
  - medio-alto
- Dificultad estimada:
  - media

## 4. Recomendacion clara de siguiente paso unico

El **siguiente paso unico recomendado** es:

**Atacar el flujo `visita_manual` end-to-end, pero sin rehacer negocio:**
- separar preparacion de datos y render en `views/visita_manual.php`
- mover CSS/JS inline a assets publicos
- dejar el controlador fino y estable
- mantener `registrar_visita.php` y `VisitasService.php` compatibles, tocando solo lo imprescindible

Es el mejor siguiente frente porque da retorno visible en UX/mantenibilidad, reduce riesgo en un flujo central y prepara el terreno para adelgazar `VisitasService.php` con menos incertidumbre.

## 5. Que NO tocar todavia

- No atacar todavia `visita_pedido`: ya fue descompuesta y su retorno marginal inmediato es menor.
- No rehacer todavia todo `VisitasService.php` de golpe: conviene entrar desde un flujo concreto (`visita_manual`) y no desde el servicio entero.
- No tocar todavia la capa `public/*` de Visitas salvo wrappers incorrectos: los revisados son finos y razonables.
- No consolidar todavia `programar_visita` y `visita_sin_venta` antes de estabilizar `visita_manual`: es una buena fase siguiente, pero no la de mayor impacto inmediato.
