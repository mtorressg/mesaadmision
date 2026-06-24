<?php
/**
 * sp_busco_nombre_paciente_1.php  (carpeta /Ajax)
 *
 * Refactor ACOTADO de prg/sp_busco_nombre_paciente_1.prg para la Solicitud de
 * Internacion (rama "De: GUARDIA"): busca al paciente por numero de Historia
 * Clinica (REG_nrohclinica) en registracio + afiliacion + entidades.
 *
 * Conexion: Cache via ODBC (clase db).
 *
 * ALCANCE: el .prg original es un buscador general muy grande (paginas mpg=1/4,
 * busqueda por documento, alta de preregistrados y un form modal de validacion
 * de pacientes VIP -frmpass_sec-). Aqui solo se refactoriza la consulta por
 * Historia Clinica que usa el evento cmdprintsol; se omiten:
 *   - el control de pacientes VIP (frmpass_sec / TPV_Estado) y su logging;
 *   - las ramas de preregistrados (preregistra) y busqueda por documento.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param db|null    $db   Conexion Cache (si es null se crea una).
 * @param string|int $mhc  Numero de Historia Clinica (REG_nrohclinica).
 * @return array Filas con los datos del paciente (REG_*, AFI_*, ENT_*).
 */
function sp_busco_nombre_paciente_1($db, $mhc): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new db();
        $cerrar = true;
    }

    try {
        $hc = str_replace("'", "''", (string) $mhc);

        $sql = "select REG_nrohclinica, REG_nombrepac, REG_domicilio, entidades.ENT_descrient,
                       REG_numdocumento, REG_fecregistra, REG_fecaltapadron, AFI_fechabaja,
                       AFI_nroafiliado, REG_fecnacimiento, REG_telefonos, REG_fecbajapadron, ENT_fecpas, ENT_turnoshabilit,
                       entidades.ENT_codent, REG_nroregistrac, REG_cpostal, REG_provincia, ENT_capita, ENT_tipo, ENT_nroprestadorexterno,
                       REG_tipodocumento, REG_localidad, REG_sexo, TPV_Estado
                  from afiliacion, entidades, registracio
                       left outer join bloqregist on registracio.REG_bloqueo = bloqregist.blr_codigobloqueo
                       left outer join TabPacVip on registracio.REG_nroregistrac = TabPacVip.TPV_NroReg
                 where registracio.REG_nrohclinica = '{$hc}'
                   and registracio.REG_nroregistrac = afiliacion.registracio
                   and afiliacion.AFI_codentidad = entidades.ENT_codent
                 order by REG_nombrepac, AFI_fechabaja, ENT_turnoshabilit";

        $res = $db->query($sql);
        if ($res === false) {
            throw new RuntimeException('ERROR EN LA GENERACION DEL CURSOR (busqueda de paciente).');
        }
        $out = [];
        while ($row = $db->fetch_assoc($res)) {
            $out[] = $row;
        }
        return $out;
    } finally {
        if ($cerrar && $db instanceof db) {
            $db->close();
        }
    }
}
