SELECT 
    ventCabe.cod_venta, 
    ventCabe.fecha_hora_alta, 
    ventLinea.cod_articulo, 
    artd.descripcion, 
    ventLinea.cantidad AS cant_pedida, 
    ISNULL(ventLineaServida.cant_servida, 0) AS cant_servida, 
    ventCabe.historico
FROM 
    hist_ventas_linea ventLinea
INNER JOIN 
    hist_ventas_cabecera ventCabe 
    ON ventCabe.cod_venta = ventLinea.cod_venta 
    AND ventCabe.tipo_venta = ventLinea.tipo_venta
LEFT JOIN 
    articulo_descripcion artd 
    ON artd.cod_articulo = ventLinea.cod_articulo 
    AND artd.cod_idioma = 'ES'
LEFT JOIN 
    (
        SELECT 
            ventLinea.cod_articulo, 
            ventLinea.cantidad AS cant_servida, 
            ventCabe.cod_documento_generador
        FROM 
            hist_ventas_linea ventLinea
        INNER JOIN 
            hist_ventas_cabecera ventCabe 
            ON ventCabe.cod_venta = ventLinea.cod_venta 
            AND ventCabe.tipo_venta = ventLinea.tipo_venta
        WHERE 
            ventLinea.tipo_venta = 2 
            AND cod_cliente = 55196
    ) ventLineaServida
    ON ventLinea.cod_articulo = ventLineaServida.cod_articulo
    AND ventCabe.cod_venta = ventLineaServida.cod_documento_generador
WHERE 
    ventLinea.tipo_venta = 1 
    AND cod_cliente = 55196 
    -- AND ventLineaServida.cant_servida <> ventLinea.cantidad 
    -- AND historico = 'N'
    -- AND cod_seccion = 1 
    -- AND ventCabe.cod_venta = 2401629;