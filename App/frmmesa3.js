/**
 * frmmesa3.js - ABM de Motivos
 * Conecta el formulario Php/frmmesa3.php con Ajax/sp_me_GuardoMot.php.
 */
$(function () {
    var $descri = $('#txtdescri');
    var $save   = $('#cmdsave');

    // VFP: txtdescri.InteractiveChange -> habilita cmdsave si hay texto.
    $descri.on('input', function () {
        $save.prop('disabled', $.trim($descri.val()) === '');
    });

    // VFP: cmdsave.Click
    $('#frmMotivo').on('submit', function (e) {
        e.preventDefault();
        $.post('../Ajax/sp_me_GuardoMot.php', { descri: $.trim($descri.val()) }, null, 'json')
            .done(function (res) {
                if (res && res.estado === 'OK') {
                    $descri.val('').focus();
                    $save.prop('disabled', true);
                } else {
                    alert((res && res.mensaje) || 'No se pudo guardar.');
                }
            });
    });

    $descri.focus();
});
