<?php
/**
 * sp_busco_pac_int_impre.php  (carpeta /Ajax)
 *
 * Refactor de prg/sp_busco_pac_int_impre.prg para la Solicitud de Internacion.
 * Trae el detalle del paciente internado (paciente + cobertura + entidad +
 * contrato + registracion + diagnostico CIE10 + afiliacion + documento + sector).
 *
 * Conexion: Cache via ODBC (clase db). Tablas sin prefijo de esquema.
 *   (La version de /Php usa SQL Server/sqluser.*; esta usa Cache segun lo pedido.)
 *
 * VFP: group by PAC_codadmision -> una fila por admision.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param db|null    $db             Conexion Cache (si es null se crea una).
 * @param string|int $mcodadm        PAC_codadmision.
 * @param int|null   $mxcentromedico Centro medico (si TabEstados tipo=53 estado=1).
 * @return array Filas del informe (agrupadas por PAC_codadmision).
 */
function sp_busco_pac_int_impre($db, $mcodadm, $mxcentromedico = null): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new db();
        $cerrar = true;
    }

    try {
        // Filtro opcional por centro medico (TabEstados tipo = 53, estado = 1).
        $mwcm = '';
        $qe = $db->query("select Estado from tabestados
                           where propietario = 57 and tipo = 53 order by subestado");
        if ($qe !== false && ($re = $db->fetch_assoc($qe)) && (int) ($re['Estado'] ?? 0) === 1) {
            $cm   = (int) $mxcentromedico;
            $mwcm = " and sec_codsector in (select sec_codsector from sectores where sec_centromedico = {$cm}) ";
        }

        $cod = str_replace("'", "''", (string) $mcodadm);

        $sql = "select PAC_nombrepaciente, PAC_codhce, PAC_codadmision,
                       paj_nropajarera, COB_codentidad, ENT_descrient, ENT_nroprestadorexterno, COB_codcontrato, COB_CondicImpositiva,
                       CON_descricont, AFI_nroafiliado, REG_tipodocumento, REG_numdocumento, PAC_denuncia,
                       PAC_fecnacimiento, PAC_edad, PAC_sexo, PAC_estadocivil, PAC_ocupacion,
                       reg_domicilio, REG_localidad, REG_provincia, REG_telefonos, tabcie10.descrip as diagnostico,
                       PAC_nombrerespons, PAC_domicresponsab, PAC_telefresponsab, PAC_fechaadmision,
                       PAC_horaadmision, tabsectorint.descrip as areaint, PAC_habitacion, PAC_cama, PAC_motivoalta, PAC_motivoadmision, PAC_codcie10diagegr,
                       PAC_operadm, PAC_operalta, PAC_fechaalta, PAC_horaalta, TPV_Estado, PAC_codhci, PAC_codcie10diagn, tabcie10.codcie10,
                       tabdocumentos.abrevio, PAC_descripdiagn, entidexclu.fecpasiva, pac_tipopac, PAC_sectorinternac, sec_descripsec
                  from pacientes
                  Inner Join coberturas on COB_pacientes = PAC_codadmision
                  Inner Join entidades on ENT_codent = COB_codentidad
                  Inner Join contratos on CON_codcont = COB_codcontrato
                  Inner Join registracio on PAC_codhci = REG_nroregistrac
                  Inner Join tabcie10 on tabcie10.Id = PAC_codcie10diagn
                  Inner Join afiliacion on afiliacion.registracio = REG_nroregistrac and AFI_codentidad = COB_codentidad
                  Inner Join tabdocumentos on codigovax = cast(REG_tipodocumento as integer)
                  Inner Join sectores on pacientes.PAC_sectorinternac = sec_codsector
                  left join tabsectorint on pacientes.PAC_areainternac = tabsectorint.id
                  left join entidexclu on coberturas.COB_codentidad = entidexclu.codent and entidexclu.tpopac='INT'
                  left join pajareras on pacientes.PAC_codadmision = pajareras.paj_codadmision
                  left outer join TabPacVip on pacientes.PAC_codhci = TabPacVip.TPV_NroReg
                 where PAC_codadmision = '{$cod}' " . $mwcm . "
                 order by COB_fechacomcob";

        $res = $db->query($sql);
        if ($res === false) {
            throw new RuntimeException('ERROR DE LECTURA (informe de hospitalizacion).');
        }

        // VFP: group by PAC_codadmision -> un registro por admision.
        $vistos = [];
        $rows   = [];
        while ($row = $db->fetch_assoc($res)) {
            $k = $row['PAC_codadmision'] ?? count($rows);
            if (!isset($vistos[$k])) {
                $vistos[$k] = true;
                $rows[]     = $row;
            }
        }
        return $rows;
    } finally {
        if ($cerrar && $db instanceof db) {
            $db->close();
        }
    }
}
