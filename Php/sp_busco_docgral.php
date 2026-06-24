<?php
/**
 * sp_busco_docgral.php
 *
 * Servicio refactorizado de prg/sp_busco_docgral.prg
 * Recupera un documento de TabDocGral y lo escribe a un archivo en disco.
 *
 * Origen VFP:
 *   select documento, tipo, Formulario from TabDocGral
 *    where propietario = ?mpropietario and Formulario = ?mform <where>
 *   (luego prg_saveBinnb grababa el campo "documento" a un archivo)
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTA: se reemplaza prg_saveBinnb por escritura directa del BLOB con PHP
 * (file_put_contents). Devuelve la ruta del archivo generado (miresp).
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param int         $mPropietario propietario.
 * @param string      $mform        Formulario.
 * @param string|null $mtema        Filtro tema (opcional).
 * @param int|null    $manio        Filtro anio (opcional).
 * @param int|null    $mnivel       Filtro Subnivel (opcional).
 * @param string|null $marchivo     Ruta/base del archivo destino (opcional).
 * @return string Ruta del archivo generado, o '' si no hubo documento.
 * @throws RuntimeException si falla la consulta.
 */
function sp_busco_docgral($db, int $mPropietario, string $mform, $mtema = null, $manio = null, $mnivel = null, $marchivo = null): string
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        $params = [$mPropietario, $mform];
        $mwhere = '';
        if (is_string($mtema)) { $mwhere .= " and tema = ? ";     $params[] = $mtema; }
        if (is_numeric($manio)) { $mwhere .= " and anio = ? ";     $params[] = (int) $manio; }
        if (is_numeric($mnivel)) { $mwhere .= " and Subnivel = ? "; $params[] = (int) $mnivel; }

        $sql = "select documento, tipo, Formulario
                  from sqluser.TabDocGral
                 where propietario = ? and Formulario = ? " . $mwhere;
        $stmt = $db->prepare($sql);
        if (!$stmt || $db->execute($stmt, $params) === false) {
            throw new RuntimeException('ERROR al leer TabDocGral.');
        }

        $row = $db->fetch_assoc($stmt);
        if (!$row || !isset($row['documento']) || $row['documento'] === '') {
            return '';
        }

        // Destino del archivo (reemplaza prg_saveBinnb).
        $mtipo = isset($row['tipo']) ? trim((string) $row['tipo']) : '';
        if (is_string($marchivo)) {
            $midocu = trim($marchivo) . $mtipo;
        } else {
            $midocu = 'C:\\tempdoc\\' . trim($mform);
        }

        $dir = dirname($midocu);
        if ($dir && !is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        file_put_contents($midocu, $row['documento']);

        return $midocu;
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
