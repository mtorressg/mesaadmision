<?php
/**
 * sp_busco_pac_int_impre.php
 *
 * Servicio refactorizado de prg/sp_busco_pac_int_impre.prg
 * Datos para el informe de hospitalizacion de un paciente internado.
 *
 * Origen VFP: gran join pacientes + coberturas + entidades + contratos +
 * registracio + tabcie10 + afiliacion + tabdocumentos + sectores
 * (+ left joins tabsectorint/entidexclu/pajareras/TabPacVip) y group by.
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTA: se elimina sp_busco_estados -> se consulta sqluser.tabestados directo
 * (tipo=53) para decidir el filtro por centro medico. log_errores -> excepcion.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param string|int $mcodadm        PAC_codadmision.
 * @param string     $eqorden        ORDER BY adicional (fragmento).
 * @param int|null   $mxcentromedico Centro medico (si tabestados tipo=53 estado=1).
 * @return array Filas del informe (agrupadas por PAC_codadmision).
 * @throws RuntimeException si falla la consulta.
 */
function sp_busco_pac_int_impre($db, $mcodadm, string $eqorden = '', $mxcentromedico = null): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        // sp_busco_estados 57, tipo = 53 (ahora directo a SQL Server)
        $mwcm   = '';
        $estado = 0;
        $qe = $db->query("select Estado from sqluser.tabestados
                           where propietario = 57 and tipo = 53 order by subestado");
        if ($qe !== false && ($re = $db->fetch_assoc($qe))) {
            $estado = (int) $re['Estado'];
        }
        if ($estado === 1) {
            $cm = (int) $mxcentromedico;
            $mwcm = " and sec_codsector in (select sec_codsector from sqluser.sectores where sec_centromedico = {$cm}) ";
        }

        $sql = "select PAC_nombrepaciente, PAC_codhce, PAC_codadmision,
                       paj_nropajarera, COB_codentidad, ENT_descrient, ENT_nroprestadorexterno, COB_codcontrato, COB_CondicImpositiva,
                       CON_descricont, AFI_nroafiliado, REG_tipodocumento, REG_numdocumento, PAC_denuncia,
                       PAC_fecnacimiento, PAC_edad, PAC_sexo, PAC_estadocivil, PAC_ocupacion,
                       reg_domicilio, REG_localidad, REG_provincia, REG_telefonos, tabcie10.descrip as diagnostico,
                       PAC_nombrerespons, PAC_domicresponsab, PAC_telefresponsab, PAC_fechaadmision,
                       PAC_horaadmision, tabsectorint.descrip as areaint, PAC_habitacion, PAC_cama, PAC_motivoalta, PAC_motivoadmision, PAC_codcie10diagegr,
                       PAC_operadm, PAC_operalta, PAC_fechaalta, PAC_horaalta, TPV_Estado, PAC_codhci, PAC_codcie10diagn, tabcie10.codcie10,
                       tabdocumentos.abrevio, PAC_descripdiagn, entidexclu.fecpasiva, pac_tipopac, PAC_sectorinternac, sec_descripsec
                  from sqluser.pacientes
                  Inner Join sqluser.coberturas on COB_pacientes = PAC_codadmision
                  Inner Join sqluser.entidades on ENT_codent = COB_codentidad
                  Inner Join sqluser.contratos on CON_codcont = COB_codcontrato
                  Inner Join sqluser.registracio on PAC_codhci = REG_nroregistrac
                  Inner Join sqluser.tabcie10 on sqluser.tabcie10.Id = PAC_codcie10diagn
                  Inner Join sqluser.afiliacion on sqluser.afiliacion.registracio = REG_nroregistrac and AFI_codentidad = COB_codentidad
                  Inner Join sqluser.tabdocumentos on codigovax = cast(REG_tipodocumento as integer)
                  Inner Join sqluser.sectores on sqluser.pacientes.PAC_sectorinternac = sec_codsector
                  left join sqluser.tabsectorint on sqluser.pacientes.PAC_areainternac = sqluser.tabsectorint.id
                  left join sqluser.entidexclu on sqluser.coberturas.COB_codentidad = sqluser.entidexclu.codent and sqluser.entidexclu.tpopac='INT'
                  left join sqluser.pajareras on sqluser.pacientes.PAC_codadmision = sqluser.pajareras.paj_codadmision
                  left outer join sqluser.TabPacVip on sqluser.pacientes.PAC_codhci = sqluser.TabPacVip.TPV_NroReg
                 where PAC_codadmision = ? " . $mwcm .
                " order by COB_fechacomcob " . $eqorden;

        $stmt = $db->prepare($sql);
        if (!$stmt || $db->execute($stmt, [$mcodadm]) === false) {
            throw new RuntimeException('ERROR DE LECTURA (informe de hospitalizacion).');
        }

        // VFP: group by PAC_codadmision -> un registro por admision.
        $vistos = [];
        $rows = [];
        while ($row = $db->fetch_assoc($stmt)) {
            $k = $row['PAC_codadmision'] ?? count($rows);
            if (!isset($vistos[$k])) {
                $vistos[$k] = true;
                $rows[] = $row;
            }
        }
        return $rows;
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
