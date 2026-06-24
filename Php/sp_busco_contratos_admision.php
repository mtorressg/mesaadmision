<?php
/**
 * sp_busco_contratos_admision.php
 *
 * Servicio refactorizado de prg/sp_busco_contratos_admision.prg
 * Devuelve los contratos de una admision.
 *
 * Origen VFP: join coberturas/contratos/entidades/registracio/afiliacion.
 * El .prg dejaba ademas dos cursores derivados (msql_con, msql_ent); aqui se
 * devuelven los tres conjuntos en un array.
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param string|int $mcodadm Codigo de admision (COB_pacientes).
 * @param string|int $mcodhce Nro de historia clinica (REG_nrohclinica).
 * @return array [
 *     'contratos' => filas completas,
 *     'con'       => [COB_fechacomcob, CON_descricont],
 *     'ent'       => [AFI_nroafiliado, ENT_descrient] (distinct)
 * ]
 * @throws RuntimeException si falla la consulta.
 */
function sp_busco_contratos_admision($db, $mcodadm, $mcodhce): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        $sql = "select COB_fechacomcob, CON_descricont, AFI_nroafiliado,
                       ENT_descrient, COB_codentidad, COB_CondicImpositiva
                  from sqluser.coberturas, sqluser.contratos, sqluser.entidades,
                       sqluser.registracio, sqluser.afiliacion
                 where COB_codcontrato = CON_codcont
                   and COB_codentidad = ENT_codent
                   and REG_nrohclinica = ?
                   and REG_nroregistrac = sqluser.afiliacion.registracio
                   and COB_codentidad = AFI_codentidad
                   and COB_pacientes = ?
                 order by COB_fechacomcob";
        $stmt = $db->prepare($sql);
        if (!$stmt || $db->execute($stmt, [$mcodhce, $mcodadm]) === false) {
            throw new RuntimeException('ERROR al consultar contratos de la admision.');
        }

        $contratos = [];
        while ($row = $db->fetch_assoc($stmt)) {
            $contratos[] = $row;
        }

        // Derivados (VFP: msql_con / msql_ent) calculados en PHP.
        $con = [];
        $ent = [];
        $vistos = [];
        foreach ($contratos as $r) {
            $con[] = [
                'COB_fechacomcob' => $r['COB_fechacomcob'] ?? null,
                'CON_descricont'  => $r['CON_descricont'] ?? null,
            ];
            $clave = ($r['AFI_nroafiliado'] ?? '') . '|' . ($r['ENT_descrient'] ?? '');
            if (!isset($vistos[$clave])) {
                $vistos[$clave] = true;
                $ent[] = [
                    'AFI_nroafiliado' => $r['AFI_nroafiliado'] ?? null,
                    'ENT_descrient'   => $r['ENT_descrient'] ?? null,
                ];
            }
        }

        return ['contratos' => $contratos, 'con' => $con, 'ent' => $ent];
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
