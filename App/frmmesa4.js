/**
 * frmmesa4.js - Listados / Reportes
 * Conecta el formulario Php/frmmesa4.php con Ajax/Mesa4Controller.php.
 */
$(function () {
    // VFP: optespe.Click -> habilita cboUno cuando se elige "Uno Solo".
    $('input[name="alcance"]').on('change', function () {
        var uno = $('#alcUno').is(':checked');
        $('#cboUno').prop('disabled', !uno);
        if (uno) { $('#cboUno').focus(); }
    });

    // VFP: validacion de rango de fechas (OLEFECHA2.LostFocus).
    function fechasValidas() {
        var d = $('#fecDesde').val(), h = $('#fecHasta').val();
        if (d && h && d > h) {
            alert('La fecha Hasta ingresada no es valida');
            return false;
        }
        return true;
    }

    function enviar(action) {
        if (!fechasValidas()) { return; }
        var url = '../Ajax/Mesa4Controller.php?action=' + action;
        $.post(url, $('#frmListados').serialize(), null, 'json')
            .done(function (res) {
                if (!res.ok) { alert(res.msg || 'No se pudo procesar.'); }
            });
    }

    $('#cmdImprimir').on('click', function () { enviar('imprimir'); });

    // cmdExcel: el controlador devuelve el listado en HTML; lo abrimos en una
    // pestana nueva para mostrarlo en el navegador (sin abandonar el formulario).
    $('#cmdExcel').on('click', function () {
        if (!fechasValidas()) { return; }
        if (!$('#fecDesde').val() || !$('#fecHasta').val()) {
            alert('Ingrese el rango de fechas (Desde / Hasta).');
            return;
        }
        var url = '../Ajax/Mesa4Controller.php?action=exportarExcel&' + $('#frmListados').serialize();
        window.open(url, '_blank');
    });

    // Carga inicial: fecha del servidor (sp_busco_fecha_serv) en Desde/Hasta y
    // operadores (VFP: sp_busco_usuarios 'MESAINGRESOS').
    $.getJSON('../Ajax/Mesa4Controller.php?action=index', function (res) {
        if (!res || !res.ok) { return; }
        if (res.fecha) {
            $('#fecDesde').val(res.fecha);
            $('#fecHasta').val(res.fecha);
        }
        $.each(res.operadores || [], function (i, op) {
            $('#cboUno').append($('<option>').val(op.id).text(op.texto));
        });
    });
});
