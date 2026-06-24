/**
 * index.js - Menu principal de Mesa de Admision (index.php)
 */
$(function () {
    // Boton Salir -> logout.php (cierra la sesion). El enlace navega solo.

    // Card Depuracion -> ejecuta sp_depuro_socios (con confirmacion).
    $('#cardDepuracion').on('click', function (e) {
        e.preventDefault();
        var $card = $(this);
        if ($card.hasClass('disabled')) { return; }

        if (!confirm('Se archivaran y eliminaran los socios anteriores a la fecha de corte.\n\nDesea ejecutar la depuracion?')) {
            return;
        }

        $card.addClass('disabled');
        $.post('Ajax/DepuracionController.php?action=ejecutar', {}, null, 'json')
            .done(function (res) {
                alert(res && res.msg ? res.msg : (res && res.ok ? 'Proceso terminado.' : 'No se pudo ejecutar la depuracion.'));
            })
            .fail(function (xhr) {
                var msg = 'No se pudo ejecutar la depuracion.';
                if (xhr.responseJSON && xhr.responseJSON.msg) { msg = xhr.responseJSON.msg; }
                alert(msg);
            })
            .always(function () {
                $card.removeClass('disabled');
            });
    });
});
