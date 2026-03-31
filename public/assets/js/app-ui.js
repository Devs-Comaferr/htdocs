function abrirModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.style.display = "block";
}

function cerrarModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.style.display = "none";
}

function escapeHtml(value) {
    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function formatearNumeroDocumento(value) {
    var numero = Number(value || 0);
    return numero.toLocaleString("es-ES", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatearPorcentajeDocumento(value) {
    return Number(value || 0).toFixed(1) + "%";
}

var contextoDocumentoActual = {
    cod_empresa: "",
    cod_caja: ""
};

function appBaseUrl() {
    if (typeof window !== "undefined" && typeof window.APP_BASE_URL === "string" && window.APP_BASE_URL !== "") {
        return window.APP_BASE_URL;
    }

    return "/public";
}

function renderizarEnlacesDocumentoRelacionados(valor, tipoDestino) {
    var albaranes = String(valor || "").trim();

    if (!albaranes) {
        return "";
    }

    return albaranes.split(",").map(function (item) {
        var texto = item.trim();
        var match = texto.match(/^(\d+)(.*)$/);

        if (!match) {
            return escapeHtml(texto);
        }

        return '<a href="#" class="abrir-documento" data-tipo="' + escapeHtml(tipoDestino) + '" data-codventa="' + escapeHtml(match[1]) + '">' +
            escapeHtml(match[1]) +
            "</a>" +
            escapeHtml(match[2] || "");
    }).join(", ");
}

function obtenerGrupoServicioDocumento(linea) {
    if (linea.grupo_servicio) {
        return linea.grupo_servicio;
    }

    var porcentaje = Number(linea.porcentaje_servicio || 0);

    if (porcentaje === 0) {
        return "pendientes";
    }
    if (porcentaje === 100) {
        return "servidas";
    }
    return "parciales";
}

function obtenerTituloGrupoServicioDocumento(grupo) {
    if (grupo === "pendientes") {
        return "Pendientes";
    }
    if (grupo === "parciales") {
        return "Parciales";
    }
    if (grupo === "sin_pedido_relacionado") {
        return "Sin pedido relacionado";
    }
    return "Servidas";
}

function formatearTextoGrupoDocumento(titulo, totalLineas, totalImporte, sufijo) {
    var textoLineas = totalLineas === 1 ? "1 linea" : totalLineas + " lineas";
    return titulo + " (" + textoLineas + " - " + formatearNumeroDocumento(totalImporte) + " â‚¬ " + sufijo + ")";
}

function obtenerRiesgoPedidoDocumento(porcentaje) {
    if (porcentaje < 40) {
        return { clase: "alto", texto: "Riesgo alto" };
    }
    if (porcentaje < 80) {
        return { clase: "medio", texto: "Riesgo medio" };
    }
    if (porcentaje < 100) {
        return { clase: "bajo", texto: "Casi servido" };
    }
    return { clase: "ok", texto: "Servido" };
}

function obtenerClaseFilaDocumento(linea) {
    if (linea.tiene_pedido_relacionado === false) {
        return "";
    }

    var servido = Number(linea.cantidad_servida || 0);
    var pendiente = Number(linea.cantidad_pendiente || 0);

    if (linea.estado_servicio === "OP") {
        return "documento-fila-op";
    }

    if (servido > 0 && pendiente > 0) {
        return "documento-fila-op";
    }

    if (servido === 0 && pendiente > 0) {
        return "documento-fila-pendiente";
    }

    return "";
}

function formatearServidoDocumento(linea) {
    var servido = formatearNumeroDocumento(linea.cantidad_servida);
    return linea.estado_servicio === "OP" ? servido + "*" : servido;
}

function renderizarIconoEstadoCI(linea) {
    if (!linea.estado_linea_icono) {
        return "";
    }

    return '<img class="icono-ci-estado" src="' + escapeHtml(linea.estado_linea_icono) + '" title="' +
        escapeHtml(linea.estado_linea_texto || "") + '" alt="">';
}

function obtenerInstanciaDocumentoModal() {
    var modalElement = document.getElementById("modalDocumento");
    if (!modalElement) {
        return null;
    }

    if (window.bootstrap && window.bootstrap.Modal) {
        return window.bootstrap.Modal.getOrCreateInstance(modalElement);
    }

    return {
        show: function () {
            modalElement.classList.add("show");
            modalElement.style.display = "block";
        },
        hide: function () {
            modalElement.classList.remove("show");
            modalElement.style.display = "none";
        }
    };
}

function cerrarDocumentoModal() {
    var modal = obtenerInstanciaDocumentoModal();
    if (modal) {
        modal.hide();
    }
}

function abrirModalDocumento(codVenta, tipoVenta) {
    if (!contextoDocumentoActual.cod_empresa || !contextoDocumentoActual.cod_caja) {
        return;
    }

    abrirDocumento(tipoVenta, contextoDocumentoActual.cod_empresa, contextoDocumentoActual.cod_caja, codVenta);
}

function inicializarEnlacesDocumento() {
    var contenedorDocumento;

    if (window.__documentoEnlacesInicializados) {
        return;
    }

    contenedorDocumento = document.getElementById("contenidoDocumento");
    if (!contenedorDocumento) {
        return;
    }

    window.__documentoEnlacesInicializados = true;

    contenedorDocumento.addEventListener("click", function (e) {
        var enlace = e.target.closest(".abrir-documento");
        var codVenta;
        var tipoVenta;

        if (!enlace || !contenedorDocumento.contains(enlace)) {
            return;
        }

        e.preventDefault();
        codVenta = enlace.getAttribute("data-codventa");
        tipoVenta = enlace.getAttribute("data-tipo");
        abrirModalDocumento(codVenta, tipoVenta);
    });
}

function inicializarGruposDocumento() {
    document.querySelectorAll("#contenidoDocumento .fila-grupo").forEach(function (row) {
        row.addEventListener("click", function () {
            var grupo = this.getAttribute("data-grupo");
            var icon = this.querySelector(".toggle-grupo");

            document.querySelectorAll("#contenidoDocumento .linea-grupo." + grupo).forEach(function (linea) {
                linea.classList.toggle("grupo-oculto");
            });

            if (icon) {
                icon.textContent = icon.textContent === "â–¼" ? "â–¶" : "â–¼";
            }
        });
    });
}

function renderizarDocumento(data) {
    var contenedor = document.getElementById("contenidoDocumento");
    var cabecera = data && data.cabecera ? data.cabecera : null;
    var lineas = data && Array.isArray(data.lineas) ? data.lineas : [];
    var porcentajeServicioTotal = Number(cabecera && cabecera.porcentaje_servicio_total || 0);
    var esAlbaran = Number(cabecera && cabecera.tipo_venta || 0) === 2;
    var etiquetaColumnaCentral = esAlbaran ? "Pedido" : "Servido";
    var etiquetaColumnaFinal = esAlbaran ? "Pedido" : "Albaran";

    if (!contenedor) {
        return;
    }

    if (!cabecera) {
        contenedor.innerHTML = '<p class="documento-vacio">No se encontraron datos del documento.</p>';
        return;
    }

    var filas = lineas.length
        ? lineas.map(function (linea, index) {
            var tienePedidoRelacionado = linea.tiene_pedido_relacionado !== false;
            var porcentaje = Number(linea.porcentaje_servicio || 0);
            var colorBarra = "barra-roja";
            var barraServicio;
            var grupoActual = esAlbaran ? "" : obtenerGrupoServicioDocumento(linea);
            var grupoAnterior = (!esAlbaran && index > 0) ? obtenerGrupoServicioDocumento(lineas[index - 1]) : "";
            var separador = "";

            if (porcentaje >= 90) {
                colorBarra = "barra-verde";
            } else if (porcentaje >= 50) {
                colorBarra = "barra-amarilla";
            }

            if (!esAlbaran && (index === 0 || grupoActual !== grupoAnterior)) {
                if (grupoActual === "pendientes" && Number(cabecera.total_lineas_pendientes || 0) > 0) {
                    separador = '<tr class="fila-grupo" data-grupo="pendientes"><td colspan="9"><span class="toggle-grupo">?</span>' + formatearTextoGrupoDocumento("Pendientes", Number(cabecera.total_lineas_pendientes || 0), Number(cabecera.total_importe_pendiente || 0), "pendientes") + '</td></tr>';
                } else if (grupoActual === "parciales" && Number(cabecera.total_lineas_parciales || 0) > 0) {
                    separador = '<tr class="fila-grupo" data-grupo="parciales"><td colspan="9"><span class="toggle-grupo">?</span>' + formatearTextoGrupoDocumento("Parciales", Number(cabecera.total_lineas_parciales || 0), Number(cabecera.total_importe_pendiente_parcial || 0), "pendientes") + '</td></tr>';
                } else if (grupoActual === "servidas" && Number(cabecera.total_lineas_servidas || 0) > 0) {
                    separador = '<tr class="fila-grupo" data-grupo="servidas"><td colspan="9"><span class="toggle-grupo">?</span>' + formatearTextoGrupoDocumento("Servidas", Number(cabecera.total_lineas_servidas || 0), Number(cabecera.total_importe_servido_grupo || 0), "servidos") + '</td></tr>';
                }
            }

            barraServicio =
                '<div class="barra-servicio">' +
                    '<div class="barra-servicio-fill ' + colorBarra + '" style="width:' + porcentaje + '%"></div>' +
                '</div>' +
                '<span class="barra-servicio-texto">' + porcentaje.toFixed(1) + '%</span>';

            return separador
                + '<tr class="' + (esAlbaran ? "" : ("linea-grupo " + grupoActual + " ")) + obtenerClaseFilaDocumento(linea) + '">'
                + '<td>' + escapeHtml(linea.cod_articulo) + '</td>'
                + '<td>' + escapeHtml(linea.descripcion) + '</td>'
                + '<td class="text-end"><div class="cantidad-estado"><span class="cantidad">' + formatearNumeroDocumento(linea.cantidad) + '</span>' + renderizarIconoEstadoCI(linea) + '</div></td>'
                + '<td class="text-end">' + (esAlbaran ? (tienePedidoRelacionado ? formatearNumeroDocumento(linea.cantidad_pedida) : '—') : formatearServidoDocumento(linea)) + '</td>'
                + '<td class="text-end">' + ((esAlbaran && !tienePedidoRelacionado) ? '—' : formatearNumeroDocumento(linea.cantidad_pendiente)) + '</td>'
                + '<td class="text-end">' + ((esAlbaran && !tienePedidoRelacionado) ? '—' : barraServicio) + '</td>'
                + '<td class="text-end">' + formatearNumeroDocumento(linea.precio) + ' &euro;</td>'
                + '<td class="text-end">' + formatearNumeroDocumento(linea.importe) + ' &euro;</td>'
                + '<td class="col-albaran">' + ((esAlbaran && !tienePedidoRelacionado) ? '' : renderizarEnlacesDocumentoRelacionados(esAlbaran ? linea.pedidos : linea.albaranes, esAlbaran ? 1 : 2)) + '</td>'
                + '</tr>';
        }).join('')
        : '<tr><td colspan="9" class="documento-vacio">No hay lineas en el documento.</td></tr>';

    var riesgoPedido = obtenerRiesgoPedidoDocumento(porcentajeServicioTotal);

    contenedor.innerHTML = ''
        + '<div class="documento-resumen">'
        + '  <div class="documento-card"><span class="documento-card-label">Documento</span><span class="documento-card-value">' + escapeHtml(cabecera.cod_venta) + '</span></div>'
        + '  <div class="documento-card"><span class="documento-card-label">Fecha</span><span class="documento-card-value">' + escapeHtml(cabecera.fecha) + '</span></div>'
        + '  <div class="documento-card"><span class="documento-card-label">Cliente</span><span class="documento-card-value">' + escapeHtml(cabecera.cliente) + '</span></div>'
        + '  <div class="documento-card"><span class="documento-card-label">Importe</span><span class="documento-card-value">' + formatearNumeroDocumento(cabecera.importe) + ' &euro;</span><div class="servicio-pedido"><div class="servicio-pedido-texto">Servicio pedido: <strong>' + formatearPorcentajeDocumento(porcentajeServicioTotal) + '</strong><span class="riesgo-pedido riesgo-' + riesgoPedido.clase + '">' + riesgoPedido.texto + '</span></div><div class="barra-servicio-pedido"><div class="barra-servicio-pedido-fill" style="width:' + porcentajeServicioTotal + '%"></div></div><div class="resumen-servicio"><span class="importe-pendiente">Pendiente: ' + formatearNumeroDocumento(cabecera.importe_pendiente_total || 0) + ' &euro;</span><span class="importe-servido">Servido: ' + formatearNumeroDocumento(cabecera.importe_servido_total || 0) + ' &euro;</span></div></div></div>'
        + '</div>'
        + '<div class="documento-tabla-wrap">'
        + '  <table class="documento-tabla table table-sm">'
        + '    <thead>'
        + '      <tr>'
        + '        <th>Cod. art&iacute;culo</th>'
        + '        <th>Descripci&oacute;n</th>'
        + '        <th class="text-end">Cantidad</th>'
        + '        <th class="text-end">' + etiquetaColumnaCentral + '</th>'
        + '        <th class="text-end">Pendiente</th>'
        + '        <th class="text-end">Servicio</th>'
        + '        <th class="text-end">Precio</th>'
        + '        <th class="text-end">Importe</th>'
        + '        <th>' + etiquetaColumnaFinal + '</th>'
        + '      </tr>'
        + '    </thead>'
        + '    <tbody>' + filas + '</tbody>'
        + '  </table>'
        + '</div>';

    if (!esAlbaran) {
        inicializarGruposDocumento();
    }
}
function abrirDocumento(tipo_venta, cod_empresa, cod_caja, cod_venta) {
    var modal = obtenerInstanciaDocumentoModal();
    var contenedor = document.getElementById("contenidoDocumento");
    var titulo = document.getElementById("modalDocumentoLabel");
    var tipoNumero = Number(tipo_venta);
    const url = appBaseUrl() + "/ajax/detalle_documento.php?" + new URLSearchParams({
        tipo_venta,
        cod_empresa,
        cod_caja,
        cod_venta
    });

    console.log("abrirDocumento START", { tipo_venta, cod_empresa, cod_caja, cod_venta });
    if (!modal || !contenedor || !titulo) {
        return;
    }

    titulo.textContent = tipoNumero === 1 ? "Pedido" : "Albarán";
    contextoDocumentoActual.cod_empresa = cod_empresa;
    contextoDocumentoActual.cod_caja = cod_caja;
    inicializarEnlacesDocumento();

    contenedor.innerHTML = '<p class="documento-cargando">Cargando documento...</p>';

    const modalEl = document.getElementById("modalDocumento");

    if (!modalEl) {
        console.error("modalDocumento NO EXISTE");
        return;
    }

    console.log("FETCH URL:", url);

    fetch(url, { credentials: "same-origin" })
    .then(function (response) {
        return response.json();
    })
    .then(function (data) {
        renderizarDocumento(data);

        let modalInstance = bootstrap.Modal.getInstance(modalEl);

        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(modalEl);
        }

        modalInstance.show();
    })
    .catch(function (error) {
        console.error("FETCH ERROR:", error);
    });
}

