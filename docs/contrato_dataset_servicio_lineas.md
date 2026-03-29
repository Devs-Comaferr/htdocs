# Contrato base del dataset de servicio

## 1. Dataset base actual

Función:
`obtenerDatasetServicioLineas()`

Ubicación:
`includes/funciones_estadisticas.php`

### Tablas utilizadas

- `integral.dbo.hist_ventas_cabecera` (`vc`)
- `integral.dbo.hist_ventas_linea` (`vl`)
- `integral.dbo.entrega_lineas_venta` (`elv`)
- `integral.dbo.hist_ventas_cabecera` (`vc2`, cabecera destino de entrega para validar albaranes `tipo_venta = 2`)

### Joins principales

- `vc` INNER JOIN `vl` por clave documental completa:
  - `cod_venta`, `tipo_venta`, `cod_empresa`, `cod_caja`
- LEFT JOIN a CTE de entregas agregadas (`entregas_agrupadas`) por:
  - `cod_venta_origen`, `cod_empresa_origen`, `cod_caja_origen`, `linea_origen`

### CTEs utilizadas

- `entregas_agrupadas`:
  - agrega `elv` por línea origen y calcula `SUM(cantidad)` servida
  - valida destino contra `vc2` de albarán (`tipo_venta = 2`)

### Filtros aplicados

- Solo pedidos:
  - `vc.tipo_venta = 1`
  - `vl.tipo_venta = 1`
- Excluye comisionista cero:
  - `ISNULL(vc.cod_comisionista,0) <> 0`
- Excluye anuladas:
  - `ISNULL(vc.anulada,'N') = 'N'`
- Rango de fechas de `vc.fecha_venta`:
  - `construirRangoFechasSql('vc.fecha_venta')`
- Filtros de catálogo (si aplican en contexto):
  - `construirFiltroArticulosSql($contexto, $params)`

### Granularidad real de salida

- Intención arquitectónica: 1 fila = 1 línea de pedido.
- Implementación actual: 1 fila por línea de pedido según join documental + entrega agregada por línea origen.

---

## 2. Campos que devuelve actualmente

Campos realmente construidos en el `while` final de `obtenerDatasetServicioLineas()`:

1. `fecha_pedido`
- Significado: fecha del pedido de la línea.
- Tipo aproximado: `string` (fecha).
- Origen: `vc.fecha_venta`.

2. `cantidad_pedida`
- Significado: cantidad pedida en la línea.
- Tipo aproximado: `float`.
- Origen: `vl.cantidad`.

3. `cantidad_servida`
- Significado: cantidad servida acumulada para la línea de pedido.
- Tipo aproximado: `float`.
- Origen: `SUM(elv.cantidad)` agregada en CTE `entregas_agrupadas`.

4. `pendiente`
- Significado: flag de pendiente (1 si `cantidad_pedida > cantidad_servida`, si no 0).
- Tipo aproximado: `int` (0/1).
- Origen: `CASE` calculado en SQL.

5. `dias_desde_pedido`
- Significado: antigüedad en días desde fecha de pedido hasta hoy.
- Tipo aproximado: `int`.
- Origen: `DATEDIFF(day, vc.fecha_venta, GETDATE())`.

---

## 3. Campos utilizados por los KPIs actuales

### KPI: servicio %
- Función: `obtenerKpiServicioPedidosUnified()`
- Subfunciones: `obtenerKpiServicioPedidos()` / `obtenerKpiServicioPedidosAjustado()`
- Campos usados: no consume el dataset base en memoria; recalcula con SQL propio (CTEs propias y agregados documentales/operativos).
- ¿Existen en dataset base?: no aplica (usa consulta independiente).
- Mismatch: **sí**, por diseño actual (rompe fuente única).

### KPI: pedidos pendientes
- Función: `obtenerKpiPedidosPendientes(array $dataset)`
- Campos que usa:
  - `cantidad_pendiente`
  - `cod_venta`
- ¿Existen en dataset base?: **no**.
- Mismatch: **sí**.

### KPI: líneas pendientes
- Función: `obtenerKpiLineasPendientes(array $dataset)`
- Campos que usa:
  - `cantidad_pendiente`
  - `dias_desde_pedido`
- ¿Existen en dataset base?:
  - `cantidad_pendiente`: no
  - `dias_desde_pedido`: sí
- Mismatch: **sí** (parcial).

### KPI: velocidad servicio
- Función: `obtenerKpiVelocidadServicio(array $dataset)`
- Campos que usa:
  - `tiene_entrega`
  - `dias_primera_entrega`
- ¿Existen en dataset base?: **no**.
- Mismatch: **sí**.

### KPI: backlog
- Función: `obtenerKpiBacklogImporte(array $dataset)`
- Campos que usa:
  - `cantidad_pendiente`
  - `cantidad_pedida`
  - `importe_linea`
- ¿Existen en dataset base?:
  - `cantidad_pedida`: sí
  - `cantidad_pendiente`: no
  - `importe_linea`: no
- Mismatch: **sí** (parcial).

### KPI: clientes con backlog
- Función: `obtenerKpiClientesConBacklog(array $dataset)`
- Campos que usa:
  - `cantidad_pendiente`
  - `cod_cliente`
- ¿Existen en dataset base?: **no**.
- Mismatch: **sí**.

### KPI: líneas críticas
- Función: `obtenerKpiLineasCriticas(array $dataset)`
- Campos que usa:
  - `cantidad_pendiente`
  - `dias_desde_pedido`
- ¿Existen en dataset base?:
  - `cantidad_pendiente`: no
  - `dias_desde_pedido`: sí
- Mismatch: **sí** (parcial).

---

## 4. Contrato BASE recomendado del dataset

Contrato mínimo estable recomendado para `obtenerDatasetServicioLineas()` (ampliable en el futuro):

1. `cod_venta`
- Identificador de pedido. Necesario para agrupar por pedido y contar pendientes.

2. `linea`
- Identificador de línea dentro del pedido. Mantiene granularidad línea.

3. `cod_empresa`
- Parte de clave documental; evita colisiones entre empresas.

4. `cod_caja`
- Parte de clave documental; evita colisiones de numeración.

5. `cod_cliente`
- Necesario para KPIs de backlog por cliente.

6. `cod_articulo`
- Necesario para segmentación y análisis de riesgo/cumplimiento por artículo.

7. `fecha_pedido`
- Base temporal para antigüedad, ventanas y tendencia.

8. `cantidad_pedida`
- Magnitud base de demanda por línea.

9. `cantidad_servida`
- Magnitud base servida documentalmente por línea.

10. `cantidad_pendiente`
- Diferencia normalizada (`max(cantidad_pedida - cantidad_servida, 0)`).

11. `importe_linea`
- Base monetaria para backlog y servicio monetario.

12. `tiene_entrega`
- Indicador de primera entrega (0/1).

13. `dias_desde_pedido`
- Antigüedad de la línea para criticidad y cumplimiento.

14. `dias_primera_entrega`
- Métrica de velocidad (si no hay entrega, `null`).

### Nota de contrato

- Este contrato es un **mínimo estable** para coherencia de KPIs.
- El dataset podrá ampliarse con campos adicionales sin romper el contrato base.

---

## 5. Inconsistencias detectadas

- KPIs que esperan `cantidad_pendiente`, pero dataset base actual solo expone `pendiente` (flag).
- KPIs que requieren `cod_venta`, `cod_cliente`, `importe_linea`, `tiene_entrega`, `dias_primera_entrega` y no están en dataset base.
- KPI de servicio principal (`obtenerKpiServicioPedidosUnified`) usa SQL paralelo, no dataset base.
- Existen rutas de cálculo en paralelo (`obtenerDatasetServicioPedidos`, `obtenerDetalleServicioPedidos`, SQLs de servicio ajustado) con lógica parcialmente duplicada.
- Coexisten granularidades línea/pedido/detalle operativo sin contrato común explícito de transformación.

---

## 6. Impacto actual en el panel de KPIs

Estas inconsistencias pueden provocar:

- KPIs incoherentes entre sí al no compartir exactamente la misma base de cálculo.
- Diferencias entre tarjetas del panel y vistas detalle/drawer de servicio.
- Cálculos repetidos con SQL distinto para métricas conceptualmente relacionadas.
- Mayor riesgo de drift funcional cuando se ajusta una ruta y no las demás.

---

## 7. Recomendaciones

Sin implementar cambios en esta fase:

- Definir y publicar el contrato base mínimo como referencia oficial del módulo.
- Hacer que los KPIs de pendientes, backlog, velocidad y criticidad consuman únicamente ese contrato.
- Mantener el KPI de servicio documental/operativo alineado al mismo contrato (o a un derivado explícito trazable desde el base).
- Reducir SQL duplicado centralizando CTEs y reglas en funciones de dataset/derivación, no en cada KPI.
- Añadir validaciones de contrato (campos requeridos por KPI) para detectar mismatches de forma temprana.
- Documentar formalmente transformaciones permitidas de granularidad (línea -> pedido -> cliente) para preservar coherencia métrica.

