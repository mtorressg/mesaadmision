<?php
/**
 * sp_secagrup.php
 *
 * Servicio refactorizado de prg/sp_secagrup.prg
 * Devuelve las tablas de agrupacion de sectores para Admision.
 *
 * Origen VFP: varias consultas sobre Tabagrup/Tabsecagrup/Sectores/entidexclu
 * segun TSA_Tipo. Devolvia multiples cursores; aqui se devuelven en un array.
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTAS:
 *  - Se elimina sp_busco_estados -> consulta directa a sqluser.tabestados (tipo=53).
 *  - Se elimina sp_desconexion (la conexion la maneja dbsqlserver).
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param int|null $mxcentromedico Centro medico (si tabestados tipo=53 estado=1).
 * @return array [ secagrup, secagrpan, secagrdc, tabagrupacion, secagruprel,
 *                 secagrupsol, secagruppanel, entexc_int ]
 * @throws RuntimeException si falla alguna consulta.
 */
function sp_secagrup($db = null, $mxcentromedico = null): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        // sp_busco_estados 57, tipo = 53 (directo a SQL Server)
        $mwcm   = '';
        $estado = 0;
        $qe = $db->query("select Estado from sqluser.tabestados
                           where propietario = 57 and tipo = 53 order by subestado");
        if ($qe !== false && ($re = $db->fetch_assoc($qe))) {
            $estado = (int) $re['Estado'];
        }
        if ($estado === 1) {
            $cm = (int) $mxcentromedico;
            $mwcm = " and TSA_Sector in (select sec_codsector from sqluser.sectores where sec_centromedico = {$cm}) ";
        }

        // Helper local para ejecutar y volcar a array.
        $run = function (string $sql) use ($db): array {
            $res = $db->query($sql);
            if ($res === false) {
                throw new RuntimeException('ERROR EN LA GENERACION DEL CURSOR DE TABLAS, AVISAR A SISTEMAS.');
            }
            $out = [];
            while ($row = $db->fetch_assoc($res)) {
                $out[] = $row;
            }
            return $out;
        };

        $baseCols = "select TSA_Sector as sector, AGS_descripcion as descripcion, AGS_secagrup as SectorAgrup, TSA_Agrupa ";
        $baseFrom = " FROM sqluser.Tabagrup, sqluser.Tabsecagrup WHERE TSA_Agrupa = sqluser.Tabagrup.ID" . $mwcm;

        $resultado = [];
        $resultado['secagrup']   = $run($baseCols . $baseFrom . " AND TSA_Tipo = 3 ");
        $resultado['secagrpan']  = $run($baseCols . $baseFrom . " AND TSA_Tipo = 4 ");
        $resultado['secagrdc']   = $run($baseCols . $baseFrom . " AND TSA_Tipo = 1 ");
        $resultado['tabagrupacion'] = $run("select * FROM sqluser.Tabagrup order by AGS_descripcion ");

        $resultado['secagruprel'] = $run(
            "select TSA_Sector as sector, AGS_descripcion as descripcion, AGS_secagrup as SectorAgrup, TSA_FechaDesde, TSA_FechaHasta " .
            $baseFrom . " AND TSA_Tipo = 10 ORDER BY sector, TSA_FechaHasta "
        );

        $resultado['secagrupsol'] = $run(
            "select TSA_Sector as sector, AGS_descripcion as descripcion, AGS_secagrup as SectorAgrup, SEC_codsector, tsa_fechahasta " .
            " FROM sqluser.Tabagrup, sqluser.Tabsecagrup, sqluser.Sectores " .
            " WHERE SEC_codsector = TSA_Sector and TSA_Agrupa = sqluser.Tabagrup.ID" . $mwcm .
            " AND TSA_Tipo = 11 AND SEC_internacion = 1 order by descripcion "
        );

        $resultado['secagruppanel'] = $run(
            "select TSA_Sector as sector, AGS_descripcion as descripcion, AGS_secagrup as SectorAgrup, SEC_codsector, tsa_fechahasta " .
            " FROM sqluser.Tabagrup, sqluser.Tabsecagrup, sqluser.Sectores " .
            " WHERE SEC_codsector = TSA_Sector and TSA_Agrupa = sqluser.Tabagrup.ID" . $mwcm .
            " AND TSA_Tipo = 4 AND SEC_internacion = 1 order by descripcion "
        );

        $resultado['entexc_int'] = $run(
            "select fecpasiva, codent from sqluser.entidexclu where tpopac='INT' and tipoturno = 0 "
        );

        return $resultado;
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
