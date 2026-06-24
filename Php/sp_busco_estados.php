<?php
/**
 * sp_busco_estados.php
 *
 * Servicio refactorizado de prg/sp_busco_estados.prg
 * Lee la tabla de estados/configuracion (sqluser.tabestados) en SQL Server.
 *
 * Origen VFP:
 *   select * from sqluser.tabestados where propietario = ?mpropietario <mbusco>
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTA: $mbusco es un fragmento SQL adicional armado por el llamador (se
 * concatena tal cual, igual que en el .prg). El .prg llamaba a log_errores
 * ante error: se reemplaza por excepcion.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param int    $mpropietario Propietario a filtrar.
 * @param string $mbusco       Fragmento SQL adicional (ej.: " and tipo = 53 order by subestado ").
 * @return array Filas de sqluser.tabestados.
 * @throws RuntimeException si falla la consulta.
 */
function sp_busco_estados($db = null, int $mpropietario = 0, string $mbusco = ''): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        $sql = "select * from sqluser.tabestados where propietario = " . (int) $mpropietario . " " . $mbusco;
        $res = $db->query($sql);
        if ($res === false) {
            throw new RuntimeException('ERROR EN LA LECTURA DE TABESTADOS.');
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
