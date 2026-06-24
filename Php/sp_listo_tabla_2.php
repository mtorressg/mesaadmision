<?php

/**
 * sp_listo_tabla_2.php
 *
 * Servicio refactorizado de prg/sp_listo_tabla_2.prg
 * Ejecutor generico: arma "SELECT <campos> FROM <tablalist> <condicion>" y
 * devuelve las filas.
 *
 * Origen VFP:
 *   SELECT vr_campos FROM vr_tablalist vr_Condicion
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTA: $tablalist (las tablas/joins) lo arma el llamador y DEBE venir
 * calificado con el esquema sqluser. (ej.: "sqluser.Socio join sqluser.motivos ...").
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param string $campos    Lista de campos del SELECT.
 * @param string $condicion Clausula WHERE/ORDER (fragmento).
 * @param string $tablalist Tabla(s)/join(s), calificadas con sqluser.
 * @return array Filas resultantes.
 * @throws RuntimeException si falla la consulta.
 */
function sp_listo_tabla_2($db, string $campos, string $condicion, string $tablalist): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        $sql = "SELECT " . $campos . " FROM " . $tablalist . " " . $condicion;

        // --------------------------------
        // $flog = fopen("sp_listo_tabla_2.log", "a");
        // fwrite($flog, "----------------------------------------" . PHP_EOL);
        // fwrite($flog, "campos: $campos" . PHP_EOL);
        // fwrite($flog, "condicion: $condicion" . PHP_EOL);
        // fwrite($flog, "tablalist: $tablalist" . PHP_EOL);
        // fwrite($flog, "sql: $sql" . PHP_EOL);
        // fclose($flog);
        // --------------------------------

        $res = $db->query($sql);
        if ($res === false) {
            throw new RuntimeException('ERROR DE CURSOR, REINTENTE.');
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
