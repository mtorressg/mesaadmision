<?php

/**
 * sp_actualizo_socio.php
 *
 * Servicio refactorizado de prg/sp_actualizo_socio.prg
 * Rutina del boton de guardado: alta (frmMesa1) o actualizacion de la atencion
 * del paciente en sqluser.Socio.
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php) -> ODBC.
 * Usa sp_busco_fecha_serv para la fecha/hora del servidor.
 *
 * NOTAS:
 *  - Se elimina log_errores: el mensaje de error SQL se devuelve en el parametro
 *    por referencia $mensajeError (ademas del bool de retorno).
 *  - num_rows() solo es confiable en SELECT (sobre todo con el linked server a
 *    Cache); por eso el exito de INSERT/UPDATE se basa en que execute() no falle,
 *    NO en la cantidad de filas. Se separan los helpers DML y SELECT.
 *  - Variables externas convertidas en parametros: $operadora (mwkusuario.idusuario),
 *    $maten (sys(0) = puesto), $mCodEnt (alta frmMesa1), $mObsR (UI, caso dq=8),
 *    $mpidsocio (IdSocio por defecto).
 */
require_once __DIR__ . '/../../dbconexion/db.php';
require_once __DIR__ . '/sp_busco_fecha_serv.php';
require_once __DIR__ . '/partials/session.php';

/**
 * @param string|null $mensajeError (por referencia) mensaje de error SQL, '' si no hubo.
 * @return bool true si se guardo/actualizo correctamente (GuardoDatosSQL).
 */
function sp_actualizo_socio(
    $db,
    $mape,
    $mid,
    $mob,
    $mdt,
    $mForm,
    $meven,
    $mpac,
    $dq,
    $mprio = 0,
    $mids = null,
    $mCodEnt = null,
    $operadora = null,
    $maten = null,
    $mObsR = null,
    $mpidsocio = null,
    &$mensajeError = null
): bool {
    $mensajeError = '';
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    // Mensaje del ultimo error ODBC. Guarda el acoplamiento a getConn() (solo lo
    // expone dbsqlserver); si la conexion no lo tiene, devuelve ''.
    $errorOdbc = function () use ($db): string {
        if (method_exists($db, 'getConn')) {
            return trim((string) @odbc_errormsg($db->getConn()));
        }
        return '';
    };

    // --- DML (INSERT / UPDATE) ---
    // Devuelve true si la sentencia se ejecuto sin error. NO usa num_rows porque
    // en este entorno (linked server a Cache) no informa filas afectadas en DML.
    $ejecutarDML = function (string $sql, array $params) use ($db, $errorOdbc, &$mensajeError): bool {
        $stmt = $db->prepare($sql);
        if (!$stmt || $db->execute($stmt, $params) === false) {
            $err = $errorOdbc();
            $mensajeError = $err !== '' ? $err : 'Error al ejecutar la sentencia SQL.';
            return false;
        }
        return true;
    };

    // --- SELECT ---
    // Ejecuta la consulta y devuelve el statement listo para fetch_assoc(),
    // o false ante error (con $mensajeError seteado).
    $ejecutarSelect = function (string $sql, array $params) use ($db, $errorOdbc, &$mensajeError) {
        $stmt = $db->prepare($sql);
        if (!$stmt || $db->execute($stmt, $params) === false) {
            $err = $errorOdbc();
            $mensajeError = $err !== '' ? $err : 'Error al ejecutar la consulta SQL.';
            return false;
        }
        return $stmt;
    };

    try {
        if ($mprio === null || $mprio === '') {
            $mprio = 0;
        }
        if (!is_numeric($mids)) {
            $mids = $mpidsocio;
        }
        $midsocio = $mids;

        $mnombre = $operadora !== null ? $operadora : mesa_session_user();
        if ($maten === null) {
            $maten = $mnombre; // sys(0) (puesto de trabajo) no tiene equivalente web directo
        }
        $mdtF = sp_busco_fecha_serv($db, 'DT');

        // MAX(IdSocio) para el autonumerico (solo se usa en frmMesa1).
        $res = $db->query("SELECT MAX(IdSocio) as IdSocio FROM sqluser.SOCIO");
        if ($res === false) {
            $err = $errorOdbc();
            $mensajeError = $err !== '' ? $err : 'No se puede acceder a algunos Datos.';
            return false;
        }
        $rowMax = $db->fetch_assoc($res);
        $maxId  = ($rowMax && $rowMax['IdSocio'] !== null) ? (int) $rowMax['IdSocio'] : 0;

        // --- Ejecucion del INSERT/UPDATE correspondiente ---
        $ok = false;

        if ($mForm === 'frmMesa1') {
            $midpers = $maxId + 1;
            $sql = "INSERT INTO sqluser.Socio
                       (ApellidoNombre, Atendido, HoraLLegada, IdMotivo, IdSocio,
                        Observacion, Operadora, puestoAtencion, PrioridadAt, CodEntidad)
                    VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?)";
            $ok = $ejecutarDML($sql, [$mape, $mdt, $mid, $midpers, $mob, $mnombre, $maten, $mprio, $mCodEnt]);
        } else {
            if ((int) $meven === 1) {
                if ((int) $dq === 0) {
                    $sql = "UPDATE sqluser.Socio SET
                                HoraFinalizacion=?, atendido=1, IdMotivoA=?, ObservaA=?,
                                PuestoAtencion=?, OperadoraA=?, Paciente=?, prioridadat=?
                             WHERE IdSocio=?";
                    $ok = $ejecutarDML($sql, [$mdtF, $mid, $mob, $maten, $mnombre, $mpac, $mprio, $mids]);
                } elseif ((int) $dq === 8) {
                    // mObsR provenia de frmMesa2.pg.pgDatos.edtobservacion.Value
                    $sql = "UPDATE sqluser.Socio SET
                                HoraFinalizacion=?, IdMotivoA=?, ObservaA=?, PuestoAtencion=?,
                                OperadoraA=?, Paciente=?, prioridadat=?, Observacion=?
                             WHERE IdSocio=?";
                    $ok = $ejecutarDML($sql, [$mdtF, $mid, $mob, $maten, $mnombre, $mpac, $mprio, $mObsR, $mids]);
                } else {
                    $sql = "UPDATE sqluser.Socio SET
                                HoraFinalizacion=?, IdMotivoA=?, ObservaA=?, PuestoAtencion=?,
                                OperadoraA=?, Paciente=?, prioridadat=?
                             WHERE IdSocio=?";
                    $ok = $ejecutarDML($sql, [$mdtF, $mid, $mob, $maten, $mnombre, $mpac, $mprio, $mids]);
                }
            } else {
                if (!empty($mape)) {
                    $sql = "UPDATE sqluser.Socio SET HoraAtencion=?, atendido=1, OperadoraA=?
                             WHERE IdSocio=? and HoraAtencion is null";
                    $ok = $ejecutarDML($sql, [$mdt, $mnombre, $midsocio]);
                } else {
                    $sql = "UPDATE sqluser.Socio SET HoraAtencion=null, atendido=0, OperadoraA=null
                             WHERE IdSocio=?";
                    $ok = $ejecutarDML($sql, [$mids]);
                }
            }
        }

        // ---- Evaluacion del resultado (GuardoDatosSQL) ----
        if (!$ok) {
            // $mensajeError ya fue seteado por $ejecutarDML.
            return false;
        }

        // frmMesa1 (alta): el exito depende solo de que la insercion no falle.
        if ($mForm === 'frmMesa1') {
            return true;
        }

        // frmMesa2, guardar (meven = 1): se considera exitoso si el UPDATE no fallo.
        if ((int) $meven === 1) {
            return true; // "Se Guardaron los Datos Exitosamente"
        }

        // meven <> 1: se verifica con un SELECT quien grabo (num_rows aqui SI sirve,
        // pero usamos fetch_assoc por portabilidad).
        if (empty($mape)) {
            $stmt = $ejecutarSelect(
                "select OperadorA, horaAtencion from sqluser.Socio
                  where IdSocio = ? and horaAtencion is null",
                [$midsocio]
            );
            if ($stmt === false) {
                return false; // mensaje ya seteado
            }
            if ($db->fetch_assoc($stmt)) {
                return true; // "Se Descartaron los Datos Exitosamente"
            }
            $mensajeError = 'No se pudo confirmar el descarte de los datos.';
            return false;
        }

        $stmt = $ejecutarSelect(
            "select OperadorA, horaAtencion from sqluser.Socio
              where OperadoraA like ? and IdSocio = ? and horaAtencion is not null",
            [$mnombre, $midsocio]
        );
        if ($stmt === false) {
            return false; // mensaje ya seteado
        }
        // Si no hay fila -> fue llamado/modificado por otro operador.
        if ($db->fetch_assoc($stmt)) {
            return true;
        }
        $mensajeError = 'Los datos fueron modificados por otro operador.';
        return false;
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
