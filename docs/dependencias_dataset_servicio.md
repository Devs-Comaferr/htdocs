# Dependencias del dataset servicio

Función base analizada:
`obtenerDatasetServicioLineas()`

Ubicación:
`includes/funciones_estadisticas.php`

## 1) Funciones que llaman a obtenerDatasetServicioLineas()

Llamadas directas detectadas:

- `ajax/estadisticas_servicio.php`
  - `$datasetServicio = obtenerDatasetServicioLineas($conn, $contexto);`
- `ajax/estadisticas_kpis.php`
  - `$datasetServicio = obtenerDatasetServicioLineas($conn, $contexto);`

No se detectan otras llamadas directas en páginas o helpers.

## 2) Endpoints AJAX que consumen el dataset

Endpoints con consumo directo:

- `ajax/estadisticas_servicio.php`
- `ajax/estadisticas_kpis.php`

Observación:

- `ajax/estadisticas_servicio.php` mezcla cálculo con dataset base y dataset alternativo (`obtenerDatasetServicioPedidos`) para algunos KPIs.
- `ajax/estadisticas_kpis.php` usa dataset base para KPIs de pendientes/velocidad/backlog/clientes/lineas críticas y usa cálculo independiente para KPI de servicio (`obtenerKpiServicioPedidosUnified`).

## 3) Páginas que dependen de esos endpoints

Dependencia confirmada:

- `estadisticas_ventas_comerciales.php`
  - consume `fetch('/ajax/estadisticas_servicio.php?...')`
  - renderiza KPIs con `renderKpisServicio(...)`.

Dependencia no confirmada por referencia estática:

- No se detecta referencia textual activa a `ajax/estadisticas_kpis.php` en páginas del flujo principal (puede existir consumo manual o legado).

## 4) KPIs que dependen del dataset

### En `ajax/estadisticas_kpis.php`

KPIs calculados pasando `$datasetServicio` (base):

- `obtenerKpiPedidosPendientes($datasetServicio)`
- `obtenerKpiVelocidadServicio($datasetServicio)`
- `obtenerKpiLineasPendientes($datasetServicio)`
- `obtenerKpiBacklogImporte($datasetServicio)`
- `obtenerKpiClientesConBacklog($datasetServicio)`
- `obtenerKpiLineasCriticas($datasetServicio)`

KPI relacionado de servicio, pero no derivado del dataset base:

- `obtenerKpiServicioPedidosUnified($conn, $contexto, ['modo' => 'operativo'])`
  - recalcula por SQL propio.

### En `ajax/estadisticas_servicio.php`

KPIs apoyados en dataset base o dataset alternativo:

- Usa `obtenerDatasetServicioLineas(...)` para servicio, pendientes y velocidad (implementación legacy en ese endpoint).
- Para backlog/clientes/lineas críticas usa `obtenerDatasetServicioPedidos(...)` en vez del dataset base.

## 5) Posibles impactos si el dataset cambia su estructura

Si cambia la estructura de `obtenerDatasetServicioLineas()` (nombres o tipos de campos), impactos probables:

- Rotura directa de KPIs en `ajax/estadisticas_kpis.php`:
  - pedidos pendientes
  - velocidad servicio
  - líneas pendientes
  - backlog importe
  - clientes con backlog
  - líneas críticas
- Rotura parcial en `ajax/estadisticas_servicio.php` (depende de los campos usados en su bucle local).
- Inconsistencias de panel en `estadisticas_ventas_comerciales.php`:
  - tarjetas de KPI mostrando valores erróneos o vacíos.
- Divergencia adicional entre KPI servicio (SQL independiente) y KPIs derivados de dataset (si no se sincronizan cambios de reglas/filtros).

Riesgo arquitectónico principal:

- El dataset base tiene dependencias activas en varios KPIs, pero convive con rutas de cálculo paralelas; cambiar contrato sin unificar capas puede amplificar incoherencias entre tarjetas y detalle.

---

Nota metodológica:

- Análisis estático de referencias y llamadas en código PHP/JS del repositorio.
