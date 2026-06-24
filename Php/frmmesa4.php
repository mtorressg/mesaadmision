<?php

/**
 * frmmesa4.php  - Listados / Reportes
 * Refactor del formulario VFP scx/frmmesa4.scx
 * Controlador: Ajax/Mesa4Controller.php
 */
$tituloForm = 'Listados';
$anchoForm  = 'container';
require __DIR__ . '/partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <form id="frmListados" autocomplete="off">

                    <!-- Listar (optlista) -->
                    <fieldset class="border rounded p-3 mb-3">
                        <legend class="float-none w-auto px-2 fs-6 fw-bold">Listar</legend>
                        <div class="d-flex flex-wrap gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipoLista" id="tipoMotivo" value="1" checked>
                                <label class="form-check-label" for="tipoMotivo">Motivo</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipoLista" id="tipoPaciente" value="2">
                                <label class="form-check-label" for="tipoPaciente">Por Paciente</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipoLista" id="tipoResp" value="3">
                                <label class="form-check-label" for="tipoResp">Por responsable del Tramite</label>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Periodo (OLEFECHA1 / OLEFECHA2) -->
                    <fieldset class="border rounded p-3 mb-3">
                        <legend class="float-none w-auto px-2 fs-6 fw-bold">Periodo</legend>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="fecDesde" class="form-label">Desde</label>
                                <input type="date" class="form-control" id="fecDesde" name="desde">
                            </div>
                            <div class="col-sm-6">
                                <label for="fecHasta" class="form-label">Hasta</label>
                                <input type="date" class="form-control" id="fecHasta" name="hasta">
                            </div>
                        </div>
                    </fieldset>

                    <!-- Operador (optespe + cboUno) -->
                    <fieldset class="border rounded p-3 mb-3">
                        <legend class="float-none w-auto px-2 fs-6 fw-bold">Operador</legend>
                        <div class="d-flex flex-wrap gap-4 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="alcance" id="alcTodos" value="1" checked>
                                <label class="form-check-label" for="alcTodos">Todos</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="alcance" id="alcUno" value="2">
                                <label class="form-check-label" for="alcUno">Uno Solo</label>
                            </div>
                        </div>
                        <select class="form-select" id="cboUno" name="operador" disabled>
                            <option value="">-- Seleccione un operador --</option>
                        </select>
                    </fieldset>

                    <div class="d-flex justify-content-end gap-2">
                        <!-- cmdprinter -->
                        <!-- <button type="button" class="btn btn-primary" id="cmdImprimir" title="Imprimir">
                            <i class="bi bi-printer"></i> Imprimir
                        </button> -->
                        <!-- cmdexcel -->
                        <button type="button" class="btn btn-success" id="cmdExcel" title="Pasar a Excel">
                            <i class="bi bi-file-earmark-excel"></i> Exportar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../App/frmmesa4.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>