<?php
/**
 * sp_busco_denuncia_ob.php
 *
 * Servicio refactorizado de prg/sp_busco_denuncia_ob.prg
 * Devuelve las denuncias de obito segun criterio.
 *
 * Origen VFP: join Tabpacobito + pacientes + coberturas + entidades +
 * prestadores/TabMedExterno + Tabcie10 (+ pacinternad si lomitefin=1).
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTA: $mbusco es un fragmento SQL armado por el llamador (se concatena tal
 * cual, como en el .prg).
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param int    $mtipo     1 = abiertas (PO_Estado<50) ; otro = con mbusco.
 * @param string $mbusco    Fragmento SQL adicional.
 * @param int    $lomitefin 1 = exige PO_FechaCierre nula (join pacinternad).
 * @return array Filas de obito.
 * @throws RuntimeException si falla la consulta.
 */
function sp_busco_denuncia_ob($db = null, int $mtipo = 1, string $mbusco = '', int $lomitefin = 0): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        $mjoin = '';
        if ($lomitefin == 1) {
            $mjoin = " inner join sqluser.pacinternad on pin_codadmision = sqluser.pacientes.pac_codadmision ";
        }

        $mfecnul = "'1900-01-01'";
        if ($mtipo == 1) {
            $cierre = ($lomitefin == 0) ? '' : " and PO_FechaCierre = {$mfecnul} ";
            $mbusco = " where PO_Estado<50 " . $cierre . (trim($mbusco) !== '' ? " and " . $mbusco : '');
        } else {
            $mbusco = (trim($mbusco) !== '' ? " where " . $mbusco : '') . " and PO_Estado<50 ";
        }

        $sql = "SELECT Tabpacobito.*, prestadores.nombre, prestadores.matriculas,
                       TabMedExterno.nombre as nombreE, TabMedExterno.matricula as matriculasE,
                       pacientes.*, ent_codent, ent_descrient,
                       Tabcie10.codcie10, Tabcie10.descrip as desdiag, prestadores.codesp
                  from sqluser.Tabpacobito
                  inner join sqluser.pacientes on sqluser.pacientes.pac_codadmision = Tabpacobito.PO_admision
                  " . $mjoin . "
                  inner join sqluser.coberturas on sqluser.pacientes.pac_codadmision = sqluser.coberturas.COB_pacientes
                  inner join sqluser.entidades on sqluser.entidades.ent_codent = sqluser.coberturas.COB_codentidad
                  LEFT OUTER join sqluser.prestadores on sqluser.Tabpacobito.po_codmed = sqluser.prestadores.id
                  LEFT OUTER join sqluser.TabMedExterno on sqluser.Tabpacobito.po_codmed = sqluser.TabMedExterno.id
                  LEFT OUTER join sqluser.Tabcie10 on sqluser.Tabcie10.id = PO_Codcie10 "
                . $mbusco .
                " order by PO_FechaIngreso";

        // VFP: "group by Tabpacobito.id" (una fila por obito). SQL Server no
        // admite columnas fuera del GROUP BY/agregado, asi que la deduplicacion
        // por id se hace en PHP.
        $res = $db->query($sql);
        if ($res === false) {
            throw new RuntimeException('No se puede acceder a algunos Datos (denuncia de obito).');
        }

        $rows = [];
        $vistos = [];
        while ($row = $db->fetch_assoc($res)) {
            $k = $row['id'] ?? count($rows);
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
