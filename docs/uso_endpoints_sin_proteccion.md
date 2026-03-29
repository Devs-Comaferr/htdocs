# Uso de endpoints sin protección

Analisis estatico de referencias en el proyecto (`php/js/html`) para los endpoints indicados.
Criterios revisados: `href`, `form action`, llamadas AJAX (`fetch`, `xhr`, `$.ajax`, `source/url` en JS), `include/require`.

## Usados activamente

- `actualizar_asignacion.php`
  - llamado desde: `editar_asignacion.php` (form `action="actualizar_asignacion.php"`)
- `buscar_cliente.php`
  - llamado desde: `programar_visita.php`, `posponer_visita.php`, `visita_sin_venta.php` (autocomplete `source`)
- `calcular_promedio_visita.php`
  - llamado desde: `cliente_detalles.php`, `seccion_detalles.php` (URL AJAX construida en JS)
- `get_eventos.php`
  - llamado desde: `calendario.php` (`events: 'get_eventos.php'`)
- `get_marcas.php`
  - llamado desde: `productos.php` (URL AJAX en JS)
- `obtener_secciones.php`
  - llamado desde: `asignacion_clientes_zonas.php` (`xhr.open('GET', 'obtener_secciones.php...')`)
- `obtener_secciones_pedidos_visitas.php`
  - llamado desde: `programar_visita.php`, `posponer_visita.php`, `visita_sin_venta.php` (campo `url` en llamadas AJAX)
- `procesar_asignar_cliente_zona.php`
  - llamado desde: `asignacion_clientes_zonas.php` (form `action`)
- `procesar_asignar_ruta_zona.php`
  - llamado desde: `zonas_rutas.php` (form `action`)
- `procesar_crear_zona.php`
  - llamado desde: `zonas.php` (form `action`)

## Usados indirectamente

- `ajax/detalle_albaran.php`
  - llamado desde: `ajax/detalle_pedido.php` (abre URL `/ajax/detalle_albaran.php?...`)
  - Nota: su consumo depende del flujo del endpoint `ajax/detalle_pedido.php`.

## Sin uso detectado

- `get_lineas_pedido.php`
- `obtener_eventos.php`
- `w.php`
- `db_query.php`

## Notas

- Este analisis es estatico y puede tener falsos negativos si existe invocacion dinamica no textual.
- No se han aplicado cambios de codigo en esta fase.
