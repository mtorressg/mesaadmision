/**
 * frmmesa1.js - Recepcion de Pacientes
 * Conecta el formulario Php/frmmesa1.php con Ajax/Mesa1Controller.php (y Mesa3 para motivos).
 */
$(function () {
  var $apenom = $("#txtApeNom");
  var $save = $("#cmdSave");

  $apenom = $apenom.toUpperCase();

  // ---- Carga inicial de combos (VFP: PROCEDURE Init) ----
  function cargarCombos() {
    $.getJSON("../Ajax/Mesa1Controller.php?action=index", function (res) {
      if (!res || !res.ok) {
        return;
      }
      llenarCombo(
        $("#cboEntidad"),
        res.entidades,
        "-- Seleccione una entidad --",
      );
      llenarCombo($("#cboMotivos"), res.motivos, null);
    });
  }

  // -----------------------------------------------------
  function llenarCombo($sel, items, placeholder) {
    $sel
      .find("option")
      .not(placeholder ? ":first" : "")
      .remove();
    $.each(items || [], function (i, it) {
      $sel.append($("<option>").val(it.id).text(it.texto));
    });
  }

  // ------------------------------------------------------VFP: .grabo = (len(apenom) > 0). Habilita guardar.
  function refrescarGuardar() {
    $save.prop("disabled", $.trim($apenom.val()) === "");
  }
  $apenom.on("input", refrescarGuardar);

  // ------------------------------------------------------VFP: CmdSave.Click -> validacion de entidad + guardar.
  $("#frmRecepcion").on("submit", function (e) {
    e.preventDefault();
    if ($("#cboEntidad").val() === "") {
      alert("Debe seleccionar una entidad");
      $("#cboEntidad").focus();
      return;
    }
    $.post(
      "../Ajax/Mesa1Controller.php?action=guardar",
      $(this).serialize(),
      null,
      "json",
    ).done(function (res) {
      if (res.ok) {
        $("#frmRecepcion")[0].reset();
        refrescarGuardar();
        $apenom.focus();
      } else {
        alert(res.msg || "No se pudo guardar.");
      }
    });
  });

  // VFP: cmdmodcto.Click -> abre el ABM de motivos (frmmesa3).
  var modalMotivo = new bootstrap.Modal(document.getElementById("modalMotivo"));
  $("#cmdmodcto").on("click", function () {
    $("#txtNuevoMotivo").val("");
    modalMotivo.show();
  });
  $("#btnGuardarMotivo").on("click", function () {
    var descri = $.trim($("#txtNuevoMotivo").val());
    if (descri === "") {
      return;
    }
    $.post(
      "../Ajax/sp_me_GuardoMot.php",
      { descri: descri },
      null,
      "json",
    ).done(function (res) {
      if (res && res.estado === "OK") {
        modalMotivo.hide();
        cargarCombos();
      } else {
        alert((res && res.mensaje) || "No se pudo guardar.");
      }
    });
  });

  cargarCombos();
  $apenom.focus();
});
