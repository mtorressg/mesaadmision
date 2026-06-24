<?php
/**
 * frmmesa1.php  - Recepcion de Pacientes
 * Refactor del formulario VFP scx/frmmesa1.scx
 * Controlador: Ajax/Mesa1Controller.php
 */
$tituloForm = 'Recepcion de Pacientes';
$anchoForm  = 'container';
require __DIR__ . '/partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">
        <div class="card shadow-sm">
            <div class="card-body">
                <form id="frmRecepcion" autocomplete="off">

                    <!-- Apellido y Nombre (txtApeNom) -->
                    <div class="mb-3">
                        <label for="txtApeNom" class="form-label fw-bold req">Apellido y Nombre</label>
                        <input type="text" class="form-control text-uppercase" id="txtApeNom"
                               name="apenom" maxlength="50">
                    </div>

                    <!-- Entidad (CboEntidad) -->
                    <div class="mb-3">
                        <label for="cboEntidad" class="form-label fw-bold req">Entidad</label>
                        <select class="form-select" id="cboEntidad" name="codentidad">
                            <option value="">-- Seleccione una entidad --</option>
                        </select>
                    </div>

                    <!-- Motivos (CboMotivos) + alta de motivo (cmdmodcto) -->
                    <div class="mb-3">
                        <label for="cboMotivos" class="form-label fw-bold">Motivos</label>
                        <div class="input-group">
                            <select class="form-select" id="cboMotivos" name="idmotivo"></select>
                            <button class="btn btn-outline-secondary" type="button" id="cmdmodcto"
                                    title="Agregar un nuevo motivo">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Observaciones (EdtObservacion) -->
                    <div class="mb-3">
                        <label for="edtObservacion" class="form-label fw-bold">Observaciones</label>
                        <textarea class="form-control text-uppercase" id="edtObservacion"
                                  name="observacion" rows="3"></textarea>
                    </div>

                    <!-- Prioridad en Atencion (ChkPrior) -->
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="chkPrior" name="prioridad" value="1">
                        <label class="form-check-label" for="chkPrior">Prioridad en Atencion</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <!-- CmdSave -->
                        <button type="submit" class="btn btn-primary" id="cmdSave" disabled>
                            <i class="bi bi-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para alta rapida de Motivo (equivalente a "do form frmmesa3") -->
<div class="modal fade" id="modalMotivo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Motivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label for="txtNuevoMotivo" class="form-label req">Motivo</label>
                <input type="text" class="form-control" id="txtNuevoMotivo" maxlength="100">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarMotivo">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="../App/frmmesa1.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
