<div class="modal fade" id="editarVisitaModal" tabindex="-1" aria-labelledby="editarVisitaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form id="editarVisitaForm">
        <?= csrfInput() ?>
        <input type="hidden" name="id_visita" id="editarVisitaId">
        <div class="modal-header">
          <h5 class="modal-title" id="editarVisitaModalLabel">Editar visita</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div id="editarVisitaFeedback" class="alert d-none" role="alert"></div>
          <div class="mb-4">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span id="editarVisitaBloqueadaIcon" class="text-success d-none" title="Visita realizada con pedidos asociados">
                <i class="fa-solid fa-circle-check"></i>
              </span>
              <div id="editarVisitaResumen" class="fw-bold fs-4 text-dark"></div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Fecha de visita</label>
              <input type="date" class="form-control" name="fecha_visita" id="editarVisitaFecha" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Hora de inicio</label>
              <input type="time" class="form-control" name="hora_inicio_visita" id="editarVisitaHoraInicio" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Hora de fin</label>
              <input type="time" class="form-control" name="hora_fin_visita" id="editarVisitaHoraFin" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Estado de visita</label>
              <select class="form-select" name="estado_visita" id="editarVisitaEstado">
                <option value="Pendiente">Pendiente</option>
                <option value="Planificada">Planificada</option>
                <option value="Realizada">Realizada</option>
                <option value="No atendida">No atendida</option>
                <option value="Descartada">Descartada</option>
              </select>
              <div id="editarVisitaEstadoHint" class="form-text d-none text-muted"></div>
              <div id="editarVisitaEstadoSoloLectura" class="d-none">
                <span class="badge text-bg-success">Realizada</span>
                <div class="form-text text-muted">Esta visita tiene pedidos asociados y su estado no puede cambiarse.</div>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <textarea class="form-control" name="observaciones" id="editarVisitaObservaciones" rows="4"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer justify-content-between">
          <div>
            <a href="#" class="btn btn-outline-warning d-none" id="editarVisitaFichaCliente" target="_blank" rel="noopener">Ficha cliente</a>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-danger" id="editarVisitaEliminar">Eliminar visita</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-primary" id="editarVisitaGuardar">Guardar cambios</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
