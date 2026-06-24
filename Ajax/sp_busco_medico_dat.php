<?php
/**
 * sp_busco_medico_dat.php  (carpeta /Ajax)
 *
 * Refactor de prg/sp_busco_medico_dat.prg
 * Devuelve los datos del profesional (nombre, matricula, especialidad) para
 * completar la Solicitud de Internacion (repadm01).
 *
 * Conexion: Cache via ODBC (clase db de dbconexion/db.php).
 *   - La clase db NO soporta sentencias preparadas: los valores se interpolan
 *     ya saneados (igual criterio que el resto del refactor sobre Cache).
 *
 * Logica VFP original:
 *   - mimed > 9999  -> es un medico externo (TabMedExterno): se obtiene su
 *     matricula y se busca en Prestadores; si no esta, se cae a TabPreRegMed.
 *   - mimed <= 9999 -> es un prestador interno (Prestadores) por ID.
 *
 * Se omiten los Messagebox / prg_cancelo del original (UI VFP): ante datos
 * faltantes se devuelve un array vacio y el llamador decide.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param db|null $db   Conexion Cache (si es null se crea una).
 * @param int     $mimed Codigo del medico (Prof: de la observacion).
 * @return array  Filas de MwkDatMed (la primera trae nombre / matriculas / codesp).
 */
function sp_busco_medico_dat($db, int $mimed): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new db();
        $cerrar = true;
    }

    // Ejecuta un SELECT y devuelve todas las filas como array asociativo.
    $run = function (string $sql) use ($db): array {
        $res = $db->query($sql);
        if ($res === false) {
            return [];
        }
        $out = [];
        while ($row = $db->fetch_assoc($res)) {
            $out[] = $row;
        }
        return $out;
    };

    try {
        if ($mimed > 9999) {
            // Medico externo: primero su matricula desde TabMedExterno.
            $ext = $run("SELECT ID, nombre, matricula, gerenciadora, codesp, CAST(1 as integer) as codprof
                           FROM TabMedExterno WHERE id = {$mimed}");
            if (!$ext) {
                return [];
            }
            $mmatb       = (string) ($ext[0]['matricula'] ?? '');
            $gerenciadora = (int) ($ext[0]['gerenciadora'] ?? 0);
            $mmatbSafe   = str_replace("'", "''", $mmatb);

            // Busqueda en Prestadores por matricula.
            $datMed = $run("SELECT Prestadores.*, Tabproffiltro.TPF_filtro
                              FROM prestadores
                              LEFT OUTER JOIN Tabproffiltro ON Tabproffiltro.TPF_codmed = Prestadores.ID
                             WHERE Prestadores.matriculas = '{$mmatbSafe}'
                             ORDER BY fecpasivap");

            // Si no esta en Prestadores, se busca en TabPreRegMed (preregistrados).
            if (!$datMed) {
                $tpf    = $gerenciadora === 222 ? 0 : 6;   // 222 = medicos OSDE
                $datMed = $run("SELECT tabpreregmed.*, CAST({$tpf} AS INTEGER) as TPF_filtro
                                  FROM tabpreregmed WHERE matriculas = '{$mmatbSafe}'");
            }
            return $datMed;
        }

        // Prestador interno por ID.
        return $run("SELECT Prestadores.*, Tabproffiltro.TPF_filtro
                       FROM prestadores
                       LEFT OUTER JOIN Tabproffiltro ON Tabproffiltro.TPF_codmed = Prestadores.ID
                      WHERE Prestadores.id = {$mimed}
                      ORDER BY fecpasivap");
    } finally {
        if ($cerrar && $db instanceof db) {
            $db->close();
        }
    }
}
