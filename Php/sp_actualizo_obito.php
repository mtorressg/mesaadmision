<?php
/**
 * sp_actualizo_obito.php
 *
 * Servicio refactorizado de prg/sp_actualizo_obito.prg
 * Actualiza el seguimiento de un obito en sqluser.Tabpacobito.
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTAS:
 *  - Se elimina Prg_EjecutoSql -> UPDATE directo.
 *  - Las variables que en el .prg venian del cursor mwkLLegadas1 (IdSocio,
 *    ApellidoNombre, paciente, Observacion, IdMotivo) y mwkobito (id) se reciben
 *    como parametros: $midSocio, $mnroadm, $mestado, $midObito.
 *  - El estado se calcula como en el .prg: IdMotivo=15 -> 2, si no -> 11.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param string $mnrocert    PO_NroCertif.
 * @param string $mentrega    PO_entregadoa.
 * @param string $mfecentrega PO_FHEntrega (datetime).
 * @param int    $midSocio    PO_NroSocio (mwkLLegadas1.IdSocio).
 * @param string $mnroadm     Admision para localizar el obito (PO_admision) si no se pasa $midObito.
 * @param int    $mIdMotivo   IdMotivo del registro (para calcular PO_Estado).
 * @param int|null $midObito  id de Tabpacobito (mwkobito.id) si ya se conoce.
 * @return bool true si se actualizo (o si no correspondia seguimiento).
 * @throws RuntimeException ante error de acceso a datos.
 */
function sp_actualizo_obito(
    $db, $mnrocert, $mentrega, $mfecentrega, int $midSocio,
    string $mnroadm = '', int $mIdMotivo = 0, $midObito = null
): bool {
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        $mestado = ($mIdMotivo === 15) ? 2 : 11;

        // Si no viene el id del obito, se busca por admision (VFP: mwkrreg).
        $mid = (int) ($midObito ?? 0);
        $encontrado = $mid > 0;

        if ($mid === 0) {
            $stmt = $db->prepare("SELECT id from sqluser.Tabpacobito where PO_admision = ?");
            if (!$stmt || $db->execute($stmt, [$mnroadm]) === false) {
                throw new RuntimeException('No se puede acceder a algunos Datos (obito).');
            }
            $row = $db->fetch_assoc($stmt);
            if ($row && isset($row['id'])) {
                $mid = (int) $row['id'];
                $encontrado = true;
            }
        }

        if (!$encontrado || $mid === 0) {
            // VFP: "ESTE OBITO NO FUE INGRESADO DESDE PISOS. NO SE EFECTUARA SEGUIMIENTO"
            return true;
        }

        $sql = "UPDATE sqluser.Tabpacobito set
                    PO_Estado = ?, PO_NroCertif = ?, PO_entregadoa = ?,
                    PO_FHEntrega = ?, PO_NroSocio = ?
                 where id = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt || $db->execute($stmt, [$mestado, $mnrocert, $mentrega, $mfecentrega, $midSocio, $mid]) === false) {
            return false;
        }

        return true;
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
