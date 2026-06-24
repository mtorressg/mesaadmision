<?php
/**
 * sp_busco_datos.php
 *
 * Servicio refactorizado de prg/sp_busco_datos.prg
 * Lee los valores de configuracion (sqluser.TabDatos).
 *
 * Origen VFP: SELECT * FROM TabDatos
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 * NOTA: el .prg llamaba a Log_errores ante error: se reemplaza por excepcion.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @return array Filas de sqluser.TabDatos.
 * @throws RuntimeException si falla la consulta.
 */
function sp_busco_datos($db = null): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        $res = $db->query("SELECT * FROM sqluser.TabDatos");
        if ($res === false) {
            throw new RuntimeException('ERROR DE LECTURA (TabDatos).');
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
