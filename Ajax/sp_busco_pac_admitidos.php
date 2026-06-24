<?php
/**
 * sp_busco_pac_admitidos.php  (carpeta /Ajax)
 *
 * Refactor ACOTADO de prg/sp_busco_pac_admitidos.prg
 * Trae el detalle de un paciente internado/admitido. En la Solicitud de
 * Internacion de Neonatologia (IdMotivo = 18) se usa para obtener los datos
 * de la admision de la madre (a partir de la relacion de pulseras).
 *
 * Conexion: Cache via ODBC (clase db).
 *
 * ALCANCE: se omiten del original el control de pacientes VIP (frmpass_sec),
 * el logging (sp_insert_tabCtrlErr) y los joins a usuarios para resolver el
 * nombre de operador de admision/alta (no los usa el reporte). Se conserva la
 * consulta principal y el filtro opcional por centro medico.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param db|null  $db             Conexion Cache (si es null se crea una).
 * @param string   $mbusco         Fragmento WHERE adicional (ej. " and pac_codadmision = '123' ").
 * @param int|null $mxcentromedico Centro medico (si TabEstados tipo=53 estado=1).
 * @return array Filas de pacientes admitidos (una por PAC_codadmision).
 */
function sp_busco_pac_admitidos($db, string $mbusco = '', $mxcentromedico = null): array
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

        $sql = "select PAC_nombrepaciente, PAC_edad, sec_codsector, sec_descripsec, PAC_habitacion, PAC_cama,
                       ENT_descrient, ENT_nroprestadorexterno, PAC_sexo, PAC_codadmision, PAC_fechaadmision,
                       PAC_horaadmision, pac_horaalta, PAC_codhci, PAC_operadm, PAC_operalta, PAC_codhce,
                       AFI_nroafiliado, PAC_descripdiagn, pac_domicilio, PAC_fechaalta, PAC_categoria,
                       COB_codcontrato, ENT_codent, PAC_denuncia, TPV_Estado, PAC_motivoalta,
                       mte_descripcion, PAC_nombrerespons, REG_numdocumento
                  from pacientes
                  Inner Join registracio on PAC_codhci = REG_nroregistrac
                  left join sectores on pacientes.PAC_sectorinternac = sectores.sec_codsector
                  left join coberturas on pacientes.PAC_codadmision = coberturas.COB_pacientes
                  left join pacinternad on pacinternad.pin_codadmision = pacientes.PAC_codadmision
                  left join entidades on coberturas.COB_codentidad = entidades.ENT_codent
                  left outer join TabPacVip on pacientes.PAC_codhci = TabPacVip.TPV_NroReg
                  left join motivoegreso on pacientes.PAC_motivoalta = motivoegreso.mte_codmotivo
                  left join afiliacion on pacientes.PAC_codhci = afiliacion.registracio
                       and coberturas.COB_codentidad = afiliacion.AFI_codentidad
                 where PAC_tipopac < 2 " . $mwcm . $mbusco;

        $res = $db->query($sql);
        if ($res === false) {
            throw new RuntimeException('ERROR DE LECTURA (pacientes admitidos).');
        }

        // group by PAC_codadmision -> una fila por admision.
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
