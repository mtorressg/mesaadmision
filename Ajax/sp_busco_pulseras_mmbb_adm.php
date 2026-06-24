<?php
/**
 * sp_busco_pulseras_mmbb_adm.php  (carpeta /Ajax)
 *
 * Refactor de prg/sp_busco_pulseras_mmbb_adm.prg
 * Relacion madre/bebe (TabRelReg) usada en la Solicitud de Internacion de
 * Neonatologia (IdMotivo = 18) para vincular la admision del recien nacido
 * con la de la madre.
 *
 * Conexion: Cache via ODBC (clase db).
 *
 * Opciones (tnOpcion):
 *   1 = busca por admision de la MADRE (TRR_ADMORIG).
 *   2 = busca por admision del BEBE  (TRR_ADMDEST). -> usada por cmdprintsol.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param db|null    $db        Conexion Cache (si es null se crea una).
 * @param int        $tnOpcion  1 (por madre) o 2 (por bebe).
 * @param string|int $tcAdmOrig Codigo de admision de referencia.
 * @return array Filas de la relacion madre/bebe.
 */
function sp_busco_pulseras_mmbb_adm($db, int $tnOpcion, $tcAdmOrig): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new db();
        $cerrar = true;
    }

    try {
        $adm = str_replace("'", "''", (string) $tcAdmOrig);

        if ($tnOpcion === 1) {
            // Por admision de la madre.
            $sql = "Select TabRelReg.id, TRR_ADMDEST, B.PAC_NombrePaciente,
                           R2.Reg_NombrePac, TRR_FecHorAdd, u.NomApe, R2.Reg_NrohClinica
                      From TabRelReg
                      left JOIN PACIENTES B ON B.PAC_CODADMISION = TabRelReg.TRR_ADMDEST
                      left JOIN REGISTRACIO R2 ON R2.REG_NROREGISTRAC = TabRelReg.TRR_RegDest
                      left JOIN TabUsuario u ON u.CodigoVax = TabRelReg.TRR_CodVaxAdd
                     where TabRelReg.TRR_ADMORIG = '{$adm}'";
        } elseif ($tnOpcion === 2) {
            // Por admision del bebe.
            $sql = "Select TabRelReg.id, TRR_ADMDEST, TRR_ADMORIG, TRR_RegOrig, B.PAC_NombrePaciente,
                           R2.Reg_NombrePac, r2.REG_numdocumento, TRR_FecHorAdd, u.NomApe,
                           R2.Reg_NrohClinica, TRR_FechorNac, TRR_InternaEn
                      From TabRelReg
                      left JOIN PACIENTES B ON B.PAC_CODADMISION = TabRelReg.TRR_ADMORIG
                      left JOIN REGISTRACIO R2 ON R2.REG_NROREGISTRAC = TabRelReg.TRR_RegOrig
                      left JOIN TabUsuario u ON u.CodigoVax = TabRelReg.TRR_CodVaxAdd
                     where TabRelReg.TRR_ADMDEST = '{$adm}'";
        } else {
            return [];
        }

        $res = $db->query($sql);
        if ($res === false) {
            return [];
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
