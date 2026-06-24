<?php
/**
 * sp_sectorint.php
 *
 * Servicio refactorizado de prg/sp_sectorint.prg
 * Devuelve los sectores (de internacion o todos) para el combo de sectores.
 *
 * Origen VFP:
 *   - Si mtsec no es numerico -> solo internacion (sec_internacion = 1)
 *     caso contrario -> todos (1 = 1)
 *   - sp_busco_estados 57, tipo=53: si estado = 1, filtra por centro medico.
 *   - SELECT ... FROM sectores <where> ORDER BY sec_descripsec
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTAS:
 *  - Se elimina sp_busco_estados -> consulta directa a sqluser.tabestados
 *    (todo a SQL Server). Tablas calificadas con sqluser.
 *  - mxcentromedico era variable global; aqui se pasa como parametro.
 *  - mbusco es un fragmento SQL armado por el llamador (se concatena tal cual).
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param mixed    $mtsec          Si NO es numerico -> solo internacion.
 * @param string   $mbusco         Fragmento SQL adicional para el WHERE.
 * @param int|null $mxcentromedico Centro medico (si tabestados tipo=53 estado=1).
 * @return array Filas: sec_codsector, sec_descripsec, sec_habitsala, SEC_secquirur, SEC_internacion.
 * @throws RuntimeException si falla la consulta.
 */
function sp_sectorint($db = null, $mtsec = null, string $mbusco = '', $mxcentromedico = null): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        // si no le pasa nada toma internacion, sino TODOS
        if (!is_numeric($mtsec)) {
            $mwhere = " where sec_internacion = 1 " . $mbusco;
        } else {
            $mwhere = " where 1 = 1 " . $mbusco;
        }

        // sp_busco_estados 57, tipo = 53 (ahora directo a SQL Server)
        $estado = 0;
        $qe = $db->query("select Estado from sqluser.tabestados
                           where propietario = 57 and tipo = 53 order by subestado");
        if ($qe !== false && ($re = $db->fetch_assoc($qe))) {
            $estado = (int) $re['Estado'];
        }

        if ($estado === 1) {
            $cm = (int) $mxcentromedico;
            $mwhere .= " and sec_codsector in (select sec_codsector from sqluser.sectores where sec_centromedico = {$cm}) ";
        }

        $sql = "select sec_codsector, sec_descripsec, sec_habitsala, SEC_secquirur, SEC_internacion
                  from sqluser.sectores " . $mwhere . " order by sec_descripsec";
        $res = $db->query($sql);
        if ($res === false) {
            throw new RuntimeException('ERROR EN LA GENERACION DEL CURSOR (sectores), REINTENTE.');
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
