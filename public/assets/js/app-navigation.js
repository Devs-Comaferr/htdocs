function navigateToPedido(codVenta) {
    if (!codVenta) return;
    var baseUrl = (typeof window !== "undefined" && window.APP_BASE_URL) ? window.APP_BASE_URL : "/public";
    window.location.href = baseUrl + "/detalle_pedido.php?cod_venta=" + encodeURIComponent(codVenta);
}

function navigateTopedido(codCliente, codSeccion, pedido) {
    var codVenta = (typeof pedido !== "undefined") ? pedido : codCliente;
    navigateToPedido(codVenta);
}
