# Arquitectura de datasets — módulo estadísticas

## Principio arquitectónico

Todos los KPIs del módulo de estadísticas deben derivarse de un dataset base común.

El dataset base actual es:

`obtenerDatasetServicioLineas()`

Granularidad declarada:

- 1 fila = 1 línea de pedido.

Este dataset debería soportar de forma consistente el cálculo de:

- servicio %
- líneas pendientes
- pedidos pendientes
- velocidad de servicio
- backlog
- riesgo comercial
- métricas de cumplimiento

---

## Dataset base

Función:

`obtenerDatasetServicioLineas()`

Ubicación:

`includes (legacy)/funciones_estadisticas.php`

### Columnas generadas (salida actual)

- `fecha_pedido`
- `cantidad_pedida`
- `cantidad_servida`
- `pendiente` (flag 0/1)
- `dias_desde_pedido`

### Joins utilizados

- Base: `integral.dbo.hist_ventas_cabecera vc`
- Join líneas: `integral.dbo.hist_ventas_linea vl`
- Left join a CTE `entregas_agrupadas` basada en `integral.dbo.entrega_lineas_venta elv` + `hist_ventas_cabecera vc2` destino tipo albarán (`tipo_venta = 2`).

### Filtros aplicados

- Pedido (`vc.tipo_venta = 1`, `vl.tipo_venta = 1`)
- Excluye comisionista 0 (`ISNULL(vc.cod_comisionista,0) <> 0`)
- Excluye anuladas (`ISNULL(vc.anulada,'N') = 'N'`)
- Rango de fechas (`construirRangoFechasSql('vc.fecha_venta')`)
- Filtros de artículo/marca/familia/subfamilia (`construirFiltroArticulosSql($contexto, $params)`).

### Posibles duplicaciones

- No se observa duplicación directa por CTE, porque `entregas_agrupadas` agrega antes de unir.
- Riesgo potencial: la unión de entregas no usa explícitamente `tipo_venta_origen` en la clave del join final; depende de que la combinación `cod_empresa/cod_caja/cod_venta/linea` sea unívoca para pedidos.

### Posibles inconsistencias detectadas

- El dataset base devuelve `pendiente` como flag, pero varios KPIs esperan `cantidad_pendiente` numérica.
- El dataset base no incluye campos que consumen KPIs actuales:
  - `cod_venta`
  - `cod_cliente`
  - `importe_linea`
  - `cantidad_pendiente`
  - `tiene_entrega`
  - `dias_primera_entrega`
- Esto rompe la premisa de “fuente única” para ciertos KPIs del panel.

---

## Datasets derivados

### `obtenerDatasetServicioPedidos()`

- Granularidad: 1 fila = 1 pedido (agrega líneas).
- Ubicación: `includes (legacy)/funciones_estadisticas.php`.
- Estado respecto al dataset base:
  - **No deriva del dataset base en memoria**.
  - Recalcula con SQL propio (CTE propia, joins y agregaciones propios).

### `obtenerDatasetLineasPendientes()`

- Granularidad: 1 fila = 1 línea pendiente.
- Ubicación: `includes (legacy)/funciones_estadisticas.php`.
- Estado respecto al dataset base:
  - **No deriva del dataset base**.
  - Repite consulta independiente similar a la base, con filtro adicional de pendiente (`vl.cantidad > servida`).

### `obtenerDetalleServicioPedidos()` y flujo de detalle operativo

- Funciones relevantes:
  - `obtenerDetalleServicioPedidos()`
  - `obtenerKpiServicioPedidosAjustado()`
  - `agruparDetalleServicioPedidosDesdeMapa()`
- Estado:
  - **No derivan del dataset base**.
  - Ejecutan SQL independientes y lógica adicional (incluye asignación operativa de albaranes huérfanos).

### Datasets esperados no encontrados con esos nombres

- No se detectan funciones explícitas llamadas:
  - `obtenerDatasetBacklog()`
  - `obtenerDatasetRiesgoCliente()`

El backlog actual se calcula vía KPI (`obtenerKpiBacklogImporte`) sobre un dataset recibido por parámetro.

---

## KPIs actuales y su fuente de datos

Fuente observada en runtime (endpoint `ajax/estadisticas_servicio.php`):

- Dataset cargado: `obtenerDatasetServicioLineas($conn, $contexto)`
- KPI servicio: `obtenerKpiServicioPedidosUnified($conn, $contexto, ['modo' => 'operativo'])`
- KPIs adicionales se calculan sobre `$datasetServicio`.

### Servicio %

- Función: `obtenerKpiServicioPedidosUnified()`
  - deriva en `obtenerKpiServicioPedidosAjustado()` (modo operativo) o `obtenerKpiServicioPedidos()` (modo documental).
- Fuente real: **consulta SQL independiente (no dataset base)**.

### Pedidos pendientes

- Función: `obtenerKpiPedidosPendientes(array $dataset)`
- Espera: `cantidad_pendiente`, `cod_venta`.
- Dataset pasado: `obtenerDatasetServicioLineas()`.
- Uso del dataset base: **sí (por contrato)**, pero **con mismatch de columnas**.

### Líneas pendientes

- Función: `obtenerKpiLineasPendientes(array $dataset)`
- Espera: `cantidad_pendiente`, `dias_desde_pedido`.
- Dataset pasado: `obtenerDatasetServicioLineas()`.
- Uso del dataset base: **sí (por contrato)**, pero **con mismatch de columnas**.

### Velocidad servicio

- Función: `obtenerKpiVelocidadServicio(array $dataset)`
- Espera: `tiene_entrega`, `dias_primera_entrega`.
- Dataset pasado: `obtenerDatasetServicioLineas()`.
- Uso del dataset base: **sí (por contrato)**, pero **con mismatch de columnas**.

### Otros KPIs experimentales del panel

- Backlog importe: `obtenerKpiBacklogImporte(array $dataset)` (espera `importe_linea`, `cantidad_pendiente`, `cantidad_pedida`).
- Clientes con backlog: `obtenerKpiClientesConBacklog(array $dataset)` (espera `cod_cliente`).
- Líneas críticas: `obtenerKpiLineasCriticas(array $dataset)` (espera `cantidad_pendiente`, `dias_desde_pedido`).

Todos se alimentan de `obtenerDatasetServicioLineas()` en el endpoint, con posibles inconsistencias por columnas faltantes.

---

## Riesgos detectados

- KPIs de servicio principal y detalle se calculan con SQL independiente, no sobre el dataset base común.
- Coexisten granularidades distintas (línea, pedido, detalle operativo) sin contrato unificado explícito.
- Repetición de CTE y lógica de servicio en múltiples funciones (riesgo de drift funcional).
- Mismatch entre columnas que generan datasets y columnas esperadas por funciones KPI.
- Potencial divergencia de filtros entre rutas (documental vs operativo, filtros de marca, ventanas temporales, huérfanos).
- Riesgo de resultados inconsistentes entre tarjetas KPI y detalle/drawer.

---

## Recomendaciones

Sin implementar cambios en esta fase, arquitectura recomendada:

- Definir formalmente un **contrato único de dataset base** (campos mínimos obligatorios por línea).
- Hacer que todos los KPIs de servicio, backlog y pendientes consuman ese contrato común (o derivados explícitos desde él, en memoria).
- Separar claramente:
  - dataset base documental
  - capa de ajustes operativos (huérfanos/FIFO)
  - vistas agregadas por pedido/cliente
- Evitar recalcular la misma lógica SQL en varias funciones; centralizar CTEs base y transformaciones.
- Añadir validación de esquema de dataset (assert de claves requeridas por KPI) para detectar mismatches temprano.
- Mantener alineados filtros y ventanas temporales en todos los KPIs que se comparan en el mismo panel.

