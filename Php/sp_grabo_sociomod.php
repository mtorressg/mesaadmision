<?php
/**
 * sp_grabo_sociomod.php
 *
 * Servicio refactorizado de prg/sp_grabo_sociomod.prg
 * Inserta un registro en la tabla SocioMod (historico de modificaciones).
 *
 * Origen VFP:
 *   INSERT INTO SocioMod(ApellidoNombre, Atendido, HoraAtencion, HoraFinalizacion,
 *     HoraLLegada, IdMotivo, IdSocio, ObservaA, Observacion, OperadoraA, Operadora,
 *     puestoAtencion, IdMotivoA, Paciente, PrioridadAt)
 *   VALUES (?mape,1,?mdtA,?mdtF,?mdtll,?midI,?midsocio,?mobA,?mobI,?mopA,?mopI,
 *           ?maten,?midA,?mpac,?mprio)
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTAS:
 *  - El .prg usaba la variable global ?midsocio (IdSocio) que NO esta entre sus
 *    parametros; aqui se agrega como parametro explicito ($midsocio).
 *  - El .prg llamaba a log_errores ante error (funcion externa no reconocida).
 *  - El .prg retorna .T. si hubo ERROR y .F. si fue OK (semantica invertida);
 *    aqui se devuelve true cuando la operacion fue EXITOSA.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param string      $mape     ApellidoNombre
 * @param int         $midA     IdMotivoA (motivo de atencion)
 * @param string      $mobA     ObservaA
 * @param string      $mdtF     HoraFinalizacion (datetime)
 * @param string      $mpac     Paciente
 * @param int         $midI     IdMotivo (motivo de ingreso)
 * @param string      $mobI     Observacion
 * @param string      $mdtll    HoraLLegada (datetime)
 * @param string      $mdtA     HoraAtencion (datetime)
 * @param string      $mopA     OperadoraA
 * @param string      $maten    puestoAtencion
 * @param string      $mopI     Operadora
 * @param int         $mprio    PrioridadAt
 * @param int         $midsocio IdSocio (era variable global en el .prg)
 * @return bool true si se inserto correctamente.
 */
function sp_grabo_sociomod(
    $db, $mape, $midA, $mobA, $mdtF, $mpac, $midI, $mobI,
    $mdtll, $mdtA, $mopA, $maten, $mopI, $mprio, $midsocio
): bool {
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        $sql = "INSERT INTO sqluser.SocioMod
                   (ApellidoNombre, Atendido, HoraAtencion, HoraFinalizacion, HoraLLegada,
                    IdMotivo, IdSocio, ObservaA, Observacion, OperadoraA, Operadora,
                    puestoAtencion, IdMotivoA, Paciente, PrioridadAt)
                VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return false;
        }

        // Orden de ? segun el INSERT (Atendido es constante = 1):
        $ok = $db->execute($stmt, [
            $mape,      // ApellidoNombre
            $mdtA,      // HoraAtencion
            $mdtF,      // HoraFinalizacion
            $mdtll,     // HoraLLegada
            $midI,      // IdMotivo
            $midsocio,  // IdSocio
            $mobA,      // ObservaA
            $mobI,      // Observacion
            $mopA,      // OperadoraA
            $mopI,      // Operadora
            $maten,     // puestoAtencion
            $midA,      // IdMotivoA
            $mpac,      // Paciente
            $mprio,     // PrioridadAt
        ]);

        // VFP: do log_errores (...) ante error -> funcion externa no reconocida.
        return $ok !== false;
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
