<?php
/**
 * frmmesa3.php  - ABM de Motivos
 * Refactor del formulario VFP scx/frmmesa3.scx
 * Servicio: Ajax/sp_me_GuardoMot.php
 */
$tituloForm = 'Motivos';
$anchoForm  = 'container';
require __DIR__ . '/partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-light fw-semibold">Nuevo Motivo</div>
            <div class="card-body">
                <form id="frmMotivo" autocomplete="off">
                    <div class="mb-3">
                        <label for="txtdescri" class="form-label req">Motivo</label>
                        <input type="text" class="form-control" id="txtdescri" name="descri" maxlength="100">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <!-- cmdsave -->
                        <button type="submit" class="btn btn-primary" id="cmdsave" disabled>
                            <i class="bi bi-check-lg"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../App/frmmesa3.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
