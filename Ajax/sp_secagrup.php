<?php
/**
 * sp_secagrup.php  (carpeta /Ajax)
 *
 * Refactor de prg/sp_secagrup.prg
 * Devuelve las agrupaciones de sectores (Tabagrup + Tabsecagrup) que el reporte
 * usa para resolver el "Sector de internacion" (mareaint) a partir del codigo
 * de agrupacion (SectorAgrup) parseado de la observacion.
 *
 * Conexion: Cache via ODBC (clase db).
 *
 * El .prg original arma varios cursores (TSA_Tipo 1/3/4/10/11). Para la
 * Solicitud de Internacion solo se necesita el de TSA_Tipo = 4 (mwkSecagrpan).
 * Se expone $tipo para poder reutilizar la funcion con cualquier agrupacion.
 *
 * NOTA: el filtro por centro medico (sp_busco_estados tipo=53 + mxcentromedico)
 * del original se omite; si se necesita, se puede pasar $centroMedico.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param db|null  $db           Conexion Cache (si es null se crea una).
 * @param int      $tipo         TSA_Tipo (4 = mwkSecagrpan, usado por el reporte).
 * @param int|null $centroMedico Si se indica, filtra sectores por sec_centromedico.
 * @return array Filas: sector, descripcion, SectorAgrup, TSA_Agrupa.
 */
function sp_secagrup($db, int $tipo = 4, $centroMedico = null): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new db();
        $cerrar = true;
    }

    try {
        $mwcm = '';
        if ($centroMedico !== null) {
            $cm   = (int) $centroMedico;
            $mwcm = " AND TSA_Sector in (select sec_codsector from sectores where sec_centromedico = {$cm}) ";
        }

        $sql = "SELECT TSA_Sector as sector, AGS_descripcion as descripcion,
                       AGS_secagrup as SectorAgrup, TSA_Agrupa
                  FROM Tabagrup, Tabsecagrup
                 WHERE TSA_Agrupa = Tabagrup.ID" . $mwcm . "
                   AND TSA_Tipo = " . (int) $tipo;

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
