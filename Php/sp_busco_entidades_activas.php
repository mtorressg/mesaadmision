<?php
/**
 * sp_busco_entidades_activas.php
 *
 * Servicio refactorizado de prg/sp_busco_entidades_activas.prg
 * Devuelve las entidades activas (alimenta el combo cboEntidad).
 *
 * Origen VFP:
 *   select ENT_codent, ENT_descrient, ENT_nroprestadorexterno, ENT_capita
 *     from entidades
 *    where ENT_fecpas is null
 *      and (ENT_turnoshabilit is null or ENT_turnoshabilit <> 'S')
 *    order by ENT_descrient
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTA: el .prg llamaba a sp_desconexion ante error (funcion externa no
 * reconocida); aqui se reemplaza por una excepcion.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db Conexion a SQL Server (si es null se crea una).
 * @return array Filas con: ENT_codent, ENT_descrient, ENT_nroprestadorexterno, ENT_capita.
 * @throws RuntimeException si la consulta falla.
 */
function sp_busco_entidades_activas($db = null): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        $sql = "select ENT_codent, ENT_descrient, ENT_nroprestadorexterno, ENT_capita
                  from sqluser.entidades
                 where ENT_fecpas is null
                   and (ENT_turnoshabilit is null or ENT_turnoshabilit <> 'S')
                 order by ENT_descrient";
        $res = $db->query($sql);
        if ($res === false) {
            throw new RuntimeException('ERROR EN LA GENERACION DEL CURSOR (entidades), AVISAR A SISTEMAS.');
        }

        $rows = [];
        while ($row = $db->fetch_assoc($res)) {
            $rows[] = $row;
        }
        return $rows;
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
