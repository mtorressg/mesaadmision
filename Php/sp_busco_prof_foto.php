<?php
/**
 * sp_busco_prof_foto.php
 *
 * Servicio refactorizado de prg/sp_busco_prof_foto.prg
 * Trae los datos anexos del profesional y, si tipo=1, extrae la foto a archivo.
 *
 * Origen VFP:
 *   select ID, FechaBaja, FechaToma, IdMedico, Imagen, Tipo
 *     FROM TabMedFoto where idmedico = ?mlid and Tipo = ?mtipo
 *   (luego prg_saveBin grababa el campo Imagen a C:\temp\imagenes\<id>.JPG)
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTA: se reemplaza prg_saveBin por escritura directa del BLOB con PHP
 * (file_put_contents). Se devuelve la ruta del archivo generado.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param int    $mlid     IdMedico.
 * @param int    $mtipo    Tipo (default 1 = foto).
 * @param string $mDirImg  Carpeta destino (default C:\temp\imagenes\).
 * @return array [
 *     'datos'   => filas (ID, FechaBaja, FechaToma, IdMedico, Tipo),
 *     'archivo' => ruta del .JPG generado (o '' si no aplica)
 * ]
 * @throws RuntimeException si falla la consulta.
 */
function sp_busco_prof_foto($db, int $mlid, int $mtipo = 1, string $mDirImg = 'C:\\temp\\imagenes\\'): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        $sql = "select ID, FechaBaja, FechaToma, IdMedico, Imagen, Tipo
                  FROM sqluser.TabMedFoto where idmedico = ? and Tipo = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt || $db->execute($stmt, [$mlid, $mtipo]) === false) {
            throw new RuntimeException('ERROR al leer TabMedFoto.');
        }

        $datos   = [];
        $imagen  = null;
        while ($row = $db->fetch_assoc($stmt)) {
            if ($imagen === null && isset($row['Imagen'])) {
                $imagen = $row['Imagen'];
            }
            unset($row['Imagen']); // no devolvemos el binario en la lista
            $datos[] = $row;
        }

        $archivo = '';
        if ($mtipo === 1 && $imagen !== null && $imagen !== '') {
            if (!is_dir($mDirImg)) {
                @mkdir($mDirImg, 0777, true);
            }
            $archivo = rtrim($mDirImg, '\\/') . DIRECTORY_SEPARATOR . $mlid . '.JPG';
            file_put_contents($archivo, $imagen);
        }

        return ['datos' => $datos, 'archivo' => $archivo];
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
