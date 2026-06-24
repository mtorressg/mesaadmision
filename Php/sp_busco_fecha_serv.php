<?php
/**
 * sp_busco_fecha_serv.php
 *
 * Servicio refactorizado de prg/sp_busco_fecha_serv.prg
 * Devuelve la fecha/hora del servidor SQL Server.
 *
 * Origen VFP: SELECT GETDATE() as fechaHora
 *   - 'DT' => datetime ; 'DD' => date (solo fecha)
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param string $tipo 'DT' (datetime) o 'DD' (date).
 * @return string Fecha en formato 'Y-m-d H:i:s' (DT) o 'Y-m-d' (DD).
 * @throws RuntimeException si falla la consulta.
 */
function sp_busco_fecha_serv($db = null, string $tipo = 'DT'): string
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        $res = $db->query("select GETDATE() as fechaHora");
        if ($res === false) {
            throw new RuntimeException('ERROR DE GENERACION DE CURSOR (FECHA-HORA).');
        }
        $row = $db->fetch_assoc($res);
        if (!$row || empty($row['fechaHora'])) {
            throw new RuntimeException('ERROR DE GENERACION DE CURSOR (FECHA-HORA).');
        }

        $dt = new DateTime($row['fechaHora']);
        return strtoupper($tipo) === 'DD' ? $dt->format('Y-m-d') : $dt->format('Y-m-d H:i:s');
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
