/**
 * frmmesa2.js - Atencion de Pacientes
 * Conecta el formulario Php/frmmesa2.php con Ajax/Mesa2Controller.php (y Mesa3 para motivos).
 */
$(function () {
  var API = "../Ajax/Mesa2Controller.php";

  // Definicion de columnas de la grilla segun se vean atendidos o no
  // (VFP: cargogrid_*; columnas dinamicas).
  var COLS_SIN = [
    "!",
    "Fecha",
    "Hs Ll",
    "Apellido, Nombre",
    "Motivos",
    "Observacion",
    "",
    "Entidad",
  ];
  var COLS_ATE = [
    "!",
    "Fecha",
    "Hs Ll",
    "Apellido, Nombre",
    "Motivo Atendido",
    "Operador Ingreso",
    "Operador Atendio",
    "Hora Atendido",
    "Hora finalizacion",
    "Motivo Ingreso",
    "Entidad",
  ];
  var verAtendidos = false;

  //------------------------
  // Solapas que muestran el boton Editar (abre el modal de Datos).
  function conEditar(page) {
    return (page === "sala" || page === "cambios") && !verAtendidos;
  }
  // Solapas que muestran el boton Imprimir (solicitud de internacion).
  function conImprimir(page) {
    return (
      !verAtendidos &&
      (page === "sala" || page === "cambios" || page === "rn")
    );
  }

  function pintarHead($tr, page) {
    var cols = verAtendidos ? COLS_ATE : COLS_SIN;
    $tr.empty();
    $.each(cols, function (i, c) {
      $tr.append($("<th>").text(c));
    });
    // Columnas extra para los botones de accion.
    if (conEditar(page)) {
      $tr.append($("<th>").text("Editar"));
    }
    if (conImprimir(page)) {
      $tr.append($("<th>").text("Imprimir"));
    }
  }

  // -----------------------
  function pintarFilas($tbody, rows, page, meta) {
    $tbody.empty();
    var cols = verAtendidos ? COLS_ATE : COLS_SIN;
    // Cada boton de accion agrega un td extra por fila.
    var extra = (conEditar(page) ? 1 : 0) + (conImprimir(page) ? 1 : 0);
    if (!rows || !rows.length) {
      $tbody.append(
        $("<tr>").append(
          $("<td>")
            .attr("colspan", cols.length + extra)
            .addClass("text-center text-muted py-3")
            .text("Sin registros"),
        ),
      );
      return;
    }
    // Cada fila es un array de celdas en el orden de las columnas.
    $.each(rows, function (i, cells) {
      var $tr = $("<tr>");
      $.each(cells, function (j, c) {
        $tr.append($("<td>").text(c == null ? "" : c));
      });
      var m = (meta && meta[i]) || {};
      var idsocio = m.idsocio == null ? "" : m.idsocio;
      // Boton Editar.
      if (conEditar(page)) {
        var $btnEd = $("<button>")
          .attr("type", "button")
          .addClass("btn btn-sm btn-outline-primary btn-editar")
          .attr("data-idmotivo", m.idmotivo == null ? "" : m.idmotivo)
          .attr("data-idsocio", idsocio)
          .text("Editar");
        $tr.append($("<td>").append($btnEd));
      }
      // Boton Imprimir (solicitud de internacion).
      if (conImprimir(page)) {
        var $btnPr = $("<button>")
          .attr("type", "button")
          .addClass("btn btn-sm btn-outline-secondary btn-imprimir")
          .attr("data-idsocio", idsocio)
          .attr("data-idmotivo", m.idmotivo == null ? "" : m.idmotivo)
          .text("Imprimir");
        $tr.append($("<td>").append($btnPr));
      }
      $tbody.append($tr);
    });
  }

  // VFP: cargagrid(npage, ...)
  function cargarGrid(page) {
    var $th, $tb, $cant;
    if (page === "sala") {
      $th = $("#thSala");
      $tb = $("#tbSala");
      $cant = $("#cantSala");
    } else if (page === "cambios") {
      $th = $("#thCambios");
      $tb = $("#tbCambios");
      $cant = $("#cantCambios");
    } else if (page === "rn") {
      $th = $("#thRN");
      $tb = $("#tbRN");
      $cant = $("#cantRN");
    } else {
      return;
    }

    pintarHead($th, page);
    $.post(
      API + "?action=cargarGrid",
      {
        page: page,
        atendidos: verAtendidos ? 1 : 0,
        desde: $("#txtFechaD").val(),
        hasta: $("#txtFechaH").val(),
        sector: $("#cboSectores").val() || "",
      },
      null,
      "json",
    ).done(function (res) {
      if (!res || !res.ok) {
        return;
      }
      $cant.text(res.cantidad || 0);
      pintarFilas($tb, res.rows, page, res.meta);
    });
  }

  // Modal de edicion (reemplaza la antigua solapa "Datos").
  var modalDatos = new bootstrap.Modal(document.getElementById("modalDatos"));

  // Controladora del boton Editar (grillas Sala de Espera y Cambios de Cama):
  // abre el modal con los campos cargados desde la fila de la grilla.
  function abrirEditar() {
    var $tds = $(this).closest("tr").children("td");
    // Orden de COLS_SIN: 0 '!', 1 Fecha, 2 Hs Ll, 3 Apellido/Nombre, 4 Motivos, 5 Observacion, 6 '', 7 Entidad.
    var txt = function (i) {
      return $.trim($tds.eq(i).text());
    };

    // Campos provenientes de la grilla (solo lectura en el form).
    $("#dTxtApeNom").val(txt(3));
    $("#txtMotivoI").val(txt(4));
    $("#edtObservacion").val(txt(5));
    $("#txtEntidad").val(txt(7));
    $("#dChkPrior").prop("checked", txt(0) === "!");

    // Identificador del registro y motivo seleccionado (vienen del meta de la fila).
    var $btn = $(this);
    $("#txtIdSocio").val($btn.attr("data-idsocio") || "");
    // cboMotivos: se posiciona en el IdMotivo de la fila seleccionada.
    $("#cboMotivos").val($btn.attr("data-idmotivo") || "");

    // Campos editables que no provienen de la grilla: se limpian.
    $("#edtObservaA, #txtPaciente, #txtHab, #txtNroCert, #txtSeg, #txtFechaEntrega").val("");

    // Guardar arranca deshabilitado hasta que el operador modifique algo.
    $("#cmdSave, #cmdSaveModal").prop("disabled", true);

    modalDatos.show();
  }
  $("#tbSala, #tbCambios").on("click", ".btn-editar", abrirEditar);

  // Controladora del boton Imprimir (todas las grillas): abre la Solicitud de
  // Internacion (repadm01.php) en una ventana nueva, lista para imprimir.
  function abrirImprimir() {
    var $b = $(this);
    var idsocio = $b.attr("data-idsocio") || "";
    if (!idsocio) {
      alert("El registro no tiene IdSocio; no se puede imprimir.");
      return;
    }
    var idmotivo = $b.attr("data-idmotivo") || "";
    window.open(
      "repadm01.php?idsocio=" +
        encodeURIComponent(idsocio) +
        "&idmotivo=" +
        encodeURIComponent(idmotivo),
      "_blank",
    );
  }
  $("#tbSala, #tbCambios, #tbRN").on("click", ".btn-imprimir", abrirImprimir);

  // Recarga la grilla de la pestana activa.
  function recargarActiva() {
    var page = $("#pgMesa .nav-link.active").data("page");
    if (page && page !== "datos") {
      cargarGrid(page);
    }
  }

  // Recarga las 3 grillas (sala / cambios / rn).
  function recargarTodas() {
    cargarGrid("sala");
    cargarGrid("cambios");
    cargarGrid("rn");
  }

  // Cambio de pestana (VFP: Pg.*.Activate).
  $('#pgMesa button[data-bs-toggle="tab"]').on("shown.bs.tab", function () {
    recargarActiva();
  });

  // Cambio de fechas -> recargar.
  $("#txtFechaD, #txtFechaH").on("change", recargarActiva);

  // ---- Toolbar ----
  // cmdUndo: vuelve a pacientes sin atender y recarga las 3 grillas.
  $("#cmdUndo").on("click", function () {
    if (verAtendidos) {
      verAtendidos = false;
      $("#cmdFind").removeClass("active");
    }
    recargarTodas();
  });
  $("#cmdFind").on("click", function () {
    // traer atendidos
    verAtendidos = !verAtendidos;
    $(this).toggleClass("active", verAtendidos);
    recargarActiva();
  });
  $("#cmdExcel").on("click", function () {
    var page = $("#pgMesa .nav-link.active").data("page") || "sala";
    $.post(API + "?action=exportarExcel", { page: page }, null, "json");
  });
  $("#cmdBusco").on("click", function () {
    // liberar paciente
    $.post(
      API + "?action=liberar",
      { idsocio: $("#txtIdSocio").val() },
      null,
      "json",
    ).done(recargarActiva);
  });
  $("#cmdPrintSol").on("click", function () {
    $.post(API + "?action=imprimirSolicitud");
  });
  $("#cmdVerCamas").on("click", function () {
    // Listado de camas vacias (reporte HTML imprimible).
    window.open("repcamvac.php", "_blank");
  });
  $("#cmdModify").on("click", function () {
    $.post(API + "?action=modificar");
  });

  // ---- Modal Datos ----
  function habilitarGuardar() {
    $("#cmdSave, #cmdSaveModal").prop("disabled", false);
  }
  $("#frmDatos").on(
    "input change",
    "input:not([readonly]):not([disabled]), textarea:not([readonly]), select",
    habilitarGuardar,
  );

  function guardarDatos() {
    $.post(
      API + "?action=guardar",
      $("#frmDatos").serialize(),
      null,
      "json",
    ).done(function (res) {
      if (res.ok) {
        $("#cmdSave, #cmdSaveModal").prop("disabled", true);
        modalDatos.hide();
        cargarGrid("sala");
      } else {
        alert(res.msg || "No se pudo guardar.");
      }
    });
  }
  $("#cmdSave, #cmdSaveModal").on("click", guardarDatos);

  $("#cmdCheckin").on("click", function () {
    if (!$("#txtHab").val() || parseInt($("#txtHab").val(), 10) === 0) {
      alert("Ingrese el numero de habitacion en lugar correcto");
      return;
    }
    $.post(
      API + "?action=checkin",
      {
        idsocio: $("#txtIdSocio").val(),
        habitacion: $("#txtHab").val(),
      },
      null,
      "json",
    ).done(function (res) {
      if (!res.ok) {
        alert(res.msg || "No se pudo confirmar el check-in.");
      }
    });
  });

  $("#cmdBlank").on("click", function () {
    if (!confirm("Desea continuar con la liberacion")) {
      return;
    }
    $.post(
      API + "?action=liberar",
      { idsocio: $("#txtIdSocio").val() },
      null,
      "json",
    ).done(recargarActiva);
  });

  // PGCambios: filtro por sector.
  $("#chkSect").on("change", function () {
    $("#cboSectores").prop("disabled", !this.checked);
    cargarGrid("cambios");
  });
  $("#cboSectores").on("change", function () {
    cargarGrid("cambios");
  });

  // Alta rapida de Motivo (cmdnuevo -> frmmesa3).
  var modalMotivo = new bootstrap.Modal(document.getElementById("modalMotivo"));
  $("#cmdNuevo").on("click", function () {
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

  // Carga inicial: combo de motivos de atencion + sectores.
  function cargarCombos() {
    $.getJSON(API + "?action=index", function (res) {
      if (!res || !res.ok) {
        return;
      }
      var $sec = $("#cboSectores").empty();
      $.each(res.sectores || [], function (i, s) {
        $sec.append($("<option>").val(s.id).text(s.texto));
      });
      // cboMotivos: contenido de sqluser.motivos (sp_busco_motivos_me).
      var $mot = $("#cboMotivos").empty();
      $.each(res.motivos || [], function (i, m) {
        $mot.append($("<option>").val(m.id).text(m.texto));
      });
      // Fechas por defecto = fecha del servidor (sp_busco_fecha_serv 'DD').
      if (res.fecha) {
        if (!$("#txtFechaD").val()) {
          $("#txtFechaD").val(res.fecha);
        }
        if (!$("#txtFechaH").val()) {
          $("#txtFechaH").val(res.fecha);
        }
      }
    });
  }

  cargarCombos();
  recargarActiva();
});
