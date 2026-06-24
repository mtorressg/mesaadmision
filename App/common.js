/**
 * common.js - Comportamiento comun a todos los formularios de Mesa de Admision.
 * Cargado por Php/partials/header.php (despues de jQuery y Bootstrap).
 */
$(function () {
    // CmdClose: en VFP cerraba el formulario (Release). Aqui volvemos atras.
    $('#cmdClose').on('click', function () {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.close();
        }
    });

    // Tecla Escape = cerrar (CmdClose.Cancel = .T. en VFP).
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') { $('#cmdClose').trigger('click'); }
    });

    // cmdDocumentacion (sin logica de negocio).
    $('#cmdDocumentacion').on('click', function () {
        // TODO: abrir el modulo de documentacion.
        console.log('cmdDocumentacion: pendiente de implementar.');
    });

    // Activa los tooltips de Bootstrap (equivalentes a ToolTipText de VFP).
    $('[title]').each(function () {
        new bootstrap.Tooltip(this);
    });
});
