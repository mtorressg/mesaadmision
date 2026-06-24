<?php
/**
 * sp_busco_motivos_me.php
 *
 * Servicio refactorizado de prg/sp_busco_motivos_me.prg
 * Devuelve la lista de motivos (alimenta el combo cboMotivos).
 *
 * Origen VFP: SELECT motivotext, idmotivo FROM motivos ORDER BY motivotext
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db Conexion a SQL Server (si es null se crea una).
 * @return array Filas con claves: motivotext, idmotivo.
 * @throws RuntimeException si la consulta falla.
 */
function sp_busco_motivos_me($db = null): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        $res = $db->query("select motivotext, idmotivo from sqluser.motivos order by motivotext");
        if ($res === false) {
            // VFP: messagebox("Los Motivos no estan disponibles - Informar a Sistemas")
            throw new RuntimeException('Los Motivos no estan disponibles - Informar a Sistemas.');
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
