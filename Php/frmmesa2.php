<?php

/**
 * frmmesa2.php  - Atencion de Pacientes
 * Refactor del formulario VFP scx/frmmesa2.scx
 * Controlador: Ajax/Mesa2Controller.php
 *
 * Formulario principal con un PageFrame de 4 pestanas:
 *   Sala de Espera | Cambios de Cama | Recien Nacidos | Datos
 */
$tituloForm = 'Atencion de Pacientes';
$anchoForm  = 'container-fluid';
require __DIR__ . '/partials/header.php';
?>

<!-- Barra de herramientas (botones del encabezado VFP) -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center gap-2 toolbar">
            <!-- CmdRecep -> frmmesa1 -->
            <a href="frmmesa1.php" class="btn btn-outline-primary" id="cmdRecep" title="Recepcion de Pacientes">
                <i class="bi bi-person-plus"></i> Recepcion
            </a>

            <div class="vr mx-1"></div>

            <!-- cmdmodify -->
            <!-- <button type="button" class="btn btn-outline-secondary" id="cmdModify" title="Modificar">
                <i class="bi bi-pencil-square"></i>
            </button> -->
            <!-- cmdsave -->
            <!-- <button type="button" class="btn btn-outline-primary" id="cmdSave" title="Guardar" disabled>
                <i class="bi bi-save"></i>
            </button> -->
            <!-- cmdundo -->
            <button type="button" class="btn btn-outline-secondary" id="cmdUndo" title="Volver a Pacientes sin atender">
                <i class="bi bi-arrow-counterclockwise"></i>
            </button>
            <!-- cmdfind -->
            <button type="button" class="btn btn-outline-secondary" id="cmdFind" title="Traer atendidos">
                <i class="bi bi-search"></i>
            </button>

            <div class="vr mx-1"></div>

            <!-- cmdbusco -->
            <!-- <button type="button" class="btn btn-outline-warning" id="cmdBusco" title="Liberar Paciente">
                <i class="bi bi-box-arrow-right"></i>
            </button> -->
            <!-- cmdprintsol -->
            <!-- <button type="button" class="btn btn-outline-secondary" id="cmdPrintSol" title="Imprime Solicitud de Internacion">
                <i class="bi bi-file-earmark-medical"></i>
            </button> -->
            <!-- cmdvercamas -->
            <button type="button" class="btn btn-outline-secondary" id="cmdVerCamas" title="Listado de camas vacias">
                <i class="bi bi-hospital"></i>
            </button>
            <!-- cmdexcel -->
            <button type="button" class="btn btn-outline-success" id="cmdExcel" title="Excel">
                <i class="bi bi-file-earmark-excel"></i>
            </button>

            <!-- Filtro de fechas (txtfechad / txtfechah) -->
            <div class="ms-auto d-flex align-items-end gap-2">
                <div>
                    <label for="txtFechaD" class="form-label mb-0 small">Fecha Desde</label>
                    <input type="date" class="form-control form-control-sm" id="txtFechaD">
                </div>
                <div>
                    <label for="txtFechaH" class="form-label mb-0 small">Fecha Hasta</label>
                    <input type="date" class="form-control form-control-sm" id="txtFechaH">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PageFrame "Pg" -->
<ul class="nav nav-tabs" id="pgMesa" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-sala" data-bs-toggle="tab" data-bs-target="#pgCatalogo"
            type="button" role="tab" data-page="sala">Sala de Espera</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-cambios" data-bs-toggle="tab" data-bs-target="#pgCambios"
            type="button" role="tab" data-page="cambios">Cambios de Cama</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-rn" data-bs-toggle="tab" data-bs-target="#pgRN"
            type="button" role="tab" data-page="rn">Recien Nacidos</button>
    </li>
    <!-- La solapa "Datos" se reemplazo por una ventana modal (#modalDatos), abierta desde el boton Editar de Sala de Espera. -->
</ul>

<div class="tab-content border border-top-0 rounded-bottom bg-white p-3">

    <!-- ===================== PgCatalogo: Sala de Espera ===================== -->
    <div class="tab-pane fade show active" id="pgCatalogo" role="tabpanel">
        <div class="d-flex justify-content-between mb-2">
            <span class="fw-bold">Cantidad de Personas en Espera:
                <span class="badge bg-primary" id="cantSala">0</span>
            </span>
        </div>
        <div class="table-responsive" style="max-height: 60vh; overflow:auto;">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light position-sticky top-0">
                    <tr id="thSala"></tr>
                </thead>
                <tbody id="tbSala"></tbody>
            </table>
        </div>
    </div>

    <!-- ===================== PGCambios: Cambios de Cama ===================== -->
    <div class="tab-pane fade" id="pgCambios" role="tabpanel">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-2 gap-2">
            <span class="fw-bold">Cantidad de Pacientes en Espera:
                <span class="badge bg-primary" id="cantCambios">0</span>
            </span>
            <div class="d-flex align-items-end gap-2">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" id="chkSect">
                    <label class="form-check-label" for="chkSect">x Sector</label>
                </div>
                <select class="form-select form-select-sm" id="cboSectores" style="min-width: 220px;" disabled></select>
            </div>
        </div>
        <div class="table-responsive" style="max-height: 60vh; overflow:auto;">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light position-sticky top-0">
                    <tr id="thCambios"></tr>
                </thead>
                <tbody id="tbCambios"></tbody>
            </table>
        </div>
    </div>

    <!-- ===================== pgRN: Recien Nacidos ===================== -->
    <div class="tab-pane fade" id="pgRN" role="tabpanel">
        <div class="d-flex justify-content-between mb-2">
            <span class="fw-bold">Recien Nacidos:
                <span class="badge bg-primary" id="cantRN">0</span>
            </span>
        </div>
        <div class="table-responsive" style="max-height: 60vh; overflow:auto;">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light position-sticky top-0">
                    <tr id="thRN"></tr>
                </thead>
                <tbody id="tbRN"></tbody>
            </table>
        </div>
    </div>

</div>

<!-- ===================== Modal Datos del paciente (antes solapa "Datos") ===================== -->
<div class="modal fade" id="modalDatos" tabindex="-1" aria-labelledby="modalDatosLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDatosLabel">Datos del Paciente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="frmDatos" autocomplete="off">
                    <input type="hidden" id="txtIdSocio" name="idsocio">
                    <input type="hidden" id="txtCodEnt" name="codentidad">

                    <div class="row g-3">
                        <!-- Apellido y Nombre (TxtApeNom, readonly) -->
                        <div class="col-md-8">
                            <label for="dTxtApeNom" class="form-label fw-bold">Apellido y Nombre</label>
                            <input type="text" class="form-control text-uppercase" id="dTxtApeNom" name="apenom" readonly>
                        </div>

                        <!-- Motivos del Ingreso (txtMotivoI, readonly) -->
                        <div class="col-md-8">
                            <label for="txtMotivoI" class="form-label fw-bold">Motivos del Ingreso</label>
                            <input type="text" class="form-control text-uppercase" id="txtMotivoI" name="motivoi" readonly>
                        </div>

                        <!-- Primera Observacion (EdtObservacion, readonly) -->
                        <div class="col-md-10">
                            <label for="edtObservacion" class="form-label fw-bold">Primera Observacion</label>
                            <textarea class="form-control text-uppercase" id="edtObservacion" name="observacion" rows="3" readonly></textarea>
                        </div>

                        <!-- Entidad (txtentidad, readonly) -->
                        <div class="col-md-10">
                            <label for="txtEntidad" class="form-label fw-bold">Entidad</label>
                            <input type="text" class="form-control" id="txtEntidad" name="entidad" readonly>
                        </div>

                        <!-- Motivos de Atencion (cboMotivos) + nuevo (cmdnuevo) -->
                        <div class="col-md-8">
                            <label for="cboMotivos" class="form-label fw-bold">Motivos de Atencion</label>
                            <div class="input-group">
                                <select class="form-select" id="cboMotivos" name="idmotivoa"></select>
                                <button class="btn btn-outline-secondary" type="button" id="cmdNuevo" title="Agregar un nuevo motivo">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Observacion Final (EdtObservaA) -->
                        <div class="col-md-10">
                            <label for="edtObservaA" class="form-label fw-bold">Observacion Final</label>
                            <textarea class="form-control text-uppercase" id="edtObservaA" name="observaa" rows="3" maxlength="250"></textarea>
                        </div>

                        <!-- Paciente (txtpaciente) -->
                        <div class="col-md-6">
                            <label for="txtPaciente" class="form-label fw-bold">Paciente</label>
                            <input type="text" class="form-control text-uppercase" id="txtPaciente" name="paciente"
                                title="Ingrese la admision si esta internado o la H.Clinica si esta en Guardia">
                        </div>

                        <!-- Nro.Habitacion (txthab) -->
                        <div class="col-md-3">
                            <label for="txtHab" class="form-label fw-bold">Nro. Habitacion</label>
                            <input type="text" class="form-control" id="txtHab" name="habitacion">
                        </div>

                        <!-- Prioridad de Atencion (ChkPrior) -->
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="dChkPrior" name="prioridad" value="1">
                                <label class="form-check-label" for="dChkPrior">Prioridad de Atencion</label>
                            </div>
                        </div>

                        <!-- Nro.Certificado (Txtnrocert) -->
                        <div class="col-md-3">
                            <label for="txtNroCert" class="form-label fw-bold">Nro. Certificado</label>
                            <input type="text" class="form-control text-uppercase" id="txtNroCert" name="nrocert" disabled>
                        </div>
                        <!-- Entregado a (txtSeg) -->
                        <div class="col-md-5">
                            <label for="txtSeg" class="form-label fw-bold">Entregado a</label>
                            <input type="text" class="form-control text-uppercase" id="txtSeg" name="entregadoa" disabled>
                        </div>
                        <!-- Hora (txtFechaentrega) -->
                        <div class="col-md-4">
                            <label for="txtFechaEntrega" class="form-label fw-bold">Hora</label>
                            <input type="text" class="form-control" id="txtFechaEntrega" name="fechaentrega" disabled>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <!-- cmdcheckin -->
                <button type="button" class="btn btn-info" id="cmdCheckin" title="Confirmar el Check-in">
                    <i class="bi bi-box-arrow-in-right"></i> Check in
                </button>
                <!-- cmdblank -->
                <button type="button" class="btn btn-outline-secondary" id="cmdBlank" title="Volver a la sala de espera" disabled>
                    <i class="bi bi-check2-circle"></i>
                </button>
                <!-- Guardar dentro del modal (el cmdSave del toolbar queda detras del backdrop). -->
                <button type="button" class="btn btn-primary" id="cmdSaveModal" title="Guardar" disabled>
                    <i class="bi bi-save"></i> Guardar
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para alta rapida de Motivo (cmdnuevo -> frmmesa3) -->
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

<script src="../App/frmmesa2.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>