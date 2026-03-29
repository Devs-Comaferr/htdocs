<style>
  #modalDocumento .modal-dialog {
    width: min(1140px, 96%);
    margin: 2rem auto;
    max-width: 95vw;
    width: 95vw;
  }

  #modalDocumento .modal-content {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 0;
    border-radius: 12px;
    overflow: hidden;
    height: 90vh;
    display: flex;
    flex-direction: column;
  }

  #modalDocumento .modal-header,
  #modalDocumento .modal-footer {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e5e7eb;
  }

  #modalDocumento .modal-footer {
    border-top: 1px solid #e5e7eb;
    border-bottom: 0;
    justify-content: flex-end;
  }

  #modalDocumento .modal-body {
    padding: 1.25rem;
    overflow: hidden;
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
  }

  #contenidoDocumento {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
  }

  #modalDocumento .btn-close-documento {
    border: 0;
    background: transparent;
    font-size: 1.75rem;
    line-height: 1;
    cursor: pointer;
    color: #6b7280;
  }

  #modalDocumento .btn-secondary {
    border: 1px solid #6c757d;
    background: #6c757d;
    color: #fff;
    border-radius: 6px;
    padding: 0.5rem 1rem;
    cursor: pointer;
  }

  #contenidoDocumento .documento-cargando,
  #contenidoDocumento .documento-vacio,
  #contenidoDocumento .documento-error {
    margin: 0;
    color: #6b7280;
  }

  #contenidoDocumento .documento-error {
    color: #b91c1c;
  }

  #contenidoDocumento .documento-resumen {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
    position: sticky;
    top: 0;
    z-index: 3;
    background: #fff;
    padding-bottom: 10px;
    flex: 0 0 auto;
  }

  #contenidoDocumento .documento-card {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 0.75rem;
  }

  #contenidoDocumento .documento-card-label {
    display: block;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b7280;
    margin-bottom: 0.25rem;
  }

  #contenidoDocumento .documento-card-value {
    color: #111827;
    font-weight: 600;
  }

  #contenidoDocumento .documento-tabla-wrap {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    flex: 1;
    margin-top: 10px;
    min-height: 0;
    overflow-y: auto;
  }

  @media (max-width: 1200px) {
    #modalDocumento .modal-dialog {
        max-width: 98vw;
        width: 98vw;
    }
  }

  @media (max-width: 768px) {
    #modalDocumento .modal-dialog {
        max-width: 100vw;
        width: 100vw;
        margin: 0;
    }

    #modalDocumento .modal-content {
        height: 100vh;
        border-radius: 0;
    }
  }

  #contenidoDocumento .documento-tabla {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
  }

  #contenidoDocumento .documento-tabla thead {
    background: #1f2937;
  }

  #contenidoDocumento .documento-tabla thead th {
    position: sticky;
    top: 0;
    z-index: 30;
    background: #1f2937;
    color: #f9fafb;
    font-weight: 600;
    font-size: 13px;
    letter-spacing: 0.02em;
    padding: 10px 14px;
    border-bottom: 1px solid #374151;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
  }

  #contenidoDocumento .documento-tabla th,
  #contenidoDocumento .documento-tabla td {
    padding: 10px 14px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: top;
  }

  #contenidoDocumento .documento-tabla tbody td {
    font-size: 13px;
  }

  #contenidoDocumento .documento-tabla tbody tr {
    transition: background 0.15s ease;
  }

  #contenidoDocumento .documento-tabla tbody tr:hover {
    background: #f9fafb;
  }

  #contenidoDocumento .documento-tabla th.text-end,
  #contenidoDocumento .documento-tabla td.text-end {
    text-align: right;
  }

  #contenidoDocumento .documento-tabla tbody tr.documento-fila-of > td {
    background-color: #d1e7dd;
  }

  #contenidoDocumento .documento-tabla tbody tr.documento-fila-op > td {
    background-color: #fff3cd;
  }

  #contenidoDocumento .documento-tabla tbody tr.documento-fila-pendiente > td {
    background-color: #f8d7da;
  }

  #contenidoDocumento .documento-tabla tbody tr.documento-fila-of > td,
  #contenidoDocumento .documento-tabla tbody tr.documento-fila-op > td,
  #contenidoDocumento .documento-tabla tbody tr.documento-fila-pendiente > td {
    color: #212529;
  }

  .documento-pendiente-rojo {
    background: #f8d7da;
  }

  .documento-pendiente-amarillo {
    background: #fff3cd;
  }

  .barra-progreso {
    width: 100px;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
  }

  .barra-progreso-fill {
    height: 100%;
    background: #0d6efd;
    border-radius: 4px;
  }

  .barra-servicio,
  .servicio-bar {
    width: 110px;
    height: 6px;
    background: #e5e7eb;
    border-radius: 6px;
    display: inline-block;
    margin-right: 6px;
    vertical-align: middle;
    overflow: hidden;
  }

  .barra-servicio-fill,
  .servicio-bar-inner {
    height: 100%;
    border-radius: 6px;
  }

  .barra-verde,
  .servicio-ok {
    background: #16a34a;
  }

  .barra-amarilla,
  .servicio-warning {
    background: #f59e0b;
  }

  .barra-roja,
  .servicio-danger {
    background: #dc2626;
  }

  .barra-servicio-texto {
    font-size: 11px;
    color: #6c757d;
  }

  .cantidad-estado {
    display: flex;
    justify-content: flex-end;
    align-items: center;
  }

  .cantidad {
    text-align: right;
  }

  .icono-ci-estado,
  .estado-icono-ci {
    width: 16px;
    height: 16px;
    margin-left: 6px;
    flex-shrink: 0;
    vertical-align: middle;
  }

  .alb-extra {
    color: #2563eb;
    font-weight: 600;
    margin-left: 4px;
  }

  .pedido-servicio {
    font-size: 13px;
    color: #444;
    margin-top: 4px;
  }

  .servicio-pedido {
    margin-top: 4px;
  }

  .servicio-pedido-texto {
    font-size: 13px;
    color: #444;
  }

  .riesgo-pedido {
    font-size: 12px;
    font-weight: bold;
    margin-left: 8px;
  }

  .riesgo-alto {
    color: #e53935;
  }

  .riesgo-medio {
    color: #fb8c00;
  }

  .riesgo-bajo {
    color: #fdd835;
  }

  .riesgo-ok {
    color: #43a047;
  }

  .resumen-servicio {
    font-size: 13px;
    margin-top: 6px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
  }

  .importe-pendiente {
    color: #e53935;
    font-weight: 600;
  }

  .importe-servido {
    color: #43a047;
    font-weight: 600;
  }

  .barra-servicio-pedido {
    width: 100%;
    height: 10px;
    background: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 6px;
  }

  .barra-servicio-pedido-fill {
    height: 100%;
    background: #4caf50;
  }

  #contenidoDocumento .documento-tabla tbody tr.fila-grupo {
    background: #f3f4f6;
    font-weight: 600;
    font-size: 13px;
    color: #374151;
    cursor: pointer;
  }

  #contenidoDocumento .documento-tabla tbody tr.fila-grupo td {
    padding: 8px 12px;
    border-top: 1px solid #e5e7eb;
    border-bottom: 0;
  }

  .grupo-oculto {
    display: none;
  }

  .toggle-grupo {
    margin-right: 6px;
  }

  /* ===============================
     MODAL GENERAL
  ================================ */

  #modalDocumento .modal-content {
    border-radius: 10px;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
  }

  /* ===============================
     TARJETAS RESUMEN
  ================================ */

  #contenidoDocumento .documento-resumen {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 18px;
  }

  #contenidoDocumento .documento-resumen .campo,
  #contenidoDocumento .documento-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 16px;
    transition: all .15s ease;
  }

  #contenidoDocumento .documento-resumen .campo:hover,
  #contenidoDocumento .documento-card:hover {
    box-shadow: 0 4px 10px rgba(0,0,0,0.06);
  }

  #contenidoDocumento .documento-resumen .label,
  #contenidoDocumento .documento-card-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #6b7280;
  }

  #contenidoDocumento .documento-resumen .valor,
  #contenidoDocumento .documento-card-value {
    font-size: 15px;
    font-weight: 600;
    color: #111827;
  }

  /* ===============================
     TABLA
  ================================ */

  #contenidoDocumento .documento-tabla {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
  }

  /* CABECERA */

  #contenidoDocumento .documento-tabla thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #1f2937;
    color: #f9fafb;
    font-weight: 600;
    letter-spacing: .02em;
    padding: 10px 14px;
    border-bottom: 1px solid #374151;
  }

  /* FILAS */

  #contenidoDocumento .documento-tabla tbody td {
    padding: 11px 14px;
    border-bottom: 1px solid #f1f5f9;
  }

  /* HOVER SUAVE */

  #contenidoDocumento .documento-tabla tbody tr:hover {
    background: #f9fafb;
  }

  /* ===============================
     BARRAS DE SERVICIO
  ================================ */

  .servicio-bar,
  .barra-servicio {
    height: 6px;
    background: #e5e7eb;
    border-radius: 6px;
    overflow: hidden;
  }

  .servicio-bar-inner,
  .barra-servicio-fill {
    height: 100%;
    border-radius: 6px;
  }

  .servicio-ok,
  .barra-verde {
    background: #22c55e;
  }

  .servicio-warning,
  .barra-amarilla {
    background: #f59e0b;
  }

  .servicio-danger,
  .barra-roja {
    background: #ef4444;
  }

  /* ===============================
     FILAS ESTADO
  ================================ */

  .fila-pendiente,
  #contenidoDocumento .documento-tabla tbody tr.documento-fila-pendiente > td {
    background: #fee2e2;
  }

  .fila-parcial,
  #contenidoDocumento .documento-tabla tbody tr.documento-fila-op > td {
    background: #fef3c7;
  }

  /* ===============================
     BOTON CERRAR
  ================================ */

  #modalDocumento .modal-footer {
    border-top: 1px solid #e5e7eb;
  }
</style>

<div class="modal fade" id="modalDocumento" tabindex="-1" aria-labelledby="modalDocumentoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDocumentoLabel">Documento</h5>
        <button type="button" class="btn-close-documento" aria-label="Cerrar" onclick="cerrarDocumentoModal()">&times;</button>
      </div>
      <div class="modal-body">
        <div id="contenidoDocumento"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="cerrarDocumentoModal()">Cerrar</button>
      </div>
    </div>
  </div>
</div>
