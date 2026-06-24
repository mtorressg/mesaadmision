<?php

/**
 * sp_me_GuardoMot.php
 *
 * Servicio refactorizado de prg/sp_me_GuardoMot.prg
 * Da de alta un motivo en la tabla sqluser.motivos.
 *
 * Origen VFP:
 *   Parameters v_descriMot, midm
 *   insert into motivos values(?midm, ?v_descriMot)   -> (idmotivo, motivotext)
 *   El id (midm) lo calculaba el formulario como MAX(idmotivo)+1; aqui, si no
 *   se recibe, lo calcula el propio servicio.
 *
 * Entrada (JSON o POST):
 *   { "descri": "<texto del motivo>", "idm": <opcional, id a usar> }
 *   (tambien acepta la clave "v_descriMot" por compatibilidad)
 *
 * Salida (JSON):
 *   { "estado": "OK"|"ERROR", "mensaje": "...", "datos": [ { idmotivo, motivotext } ] }
 *
 * Conexion a SQL Server via la clase dbsqlserver (dbconexion/db.php).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../dbconexion/db.php';

/** Emite la respuesta JSON y termina. */
function gm_responder(string $estado, string $mensaje, array $datos = []): void
{
    echo json_encode(
        ['estado' => $estado, 'mensaje' => $mensaje, 'datos' => $datos],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

// ---- Entrada: JSON crudo o, en su defecto, POST ----
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$descri = '';
if (isset($input['descri'])) {
    $descri = trim((string) $input['descri']);
} elseif (isset($input['v_descriMot'])) {
    $descri = trim((string) $input['v_descriMot']);
}
$idm = isset($input['idm']) ? (int) $input['idm'] : 0;

if ($descri === '') {
    gm_responder('ERROR', 'Debe ingresar el motivo.');
}

try {
    //$db = new dbsqlserver();
    $db = new db();

    // Id nuevo si no se recibio (VFP: MAX(idmotivo)+1).
    if ($idm <= 0) {
        //$res = $db->query("select MAX(idmotivo) + 1 as nuevo from sqluser.motivos");
        //$row = $res ? $db->fetch_assoc($res) : null;
        //$idm = ($row && $row['nuevo'] !== null) ? (int) $row['nuevo'] : 1;

        $res = $db->query("select MAX(idmotivo) + 1 as nuevo from sqluser.motivos");

        if (!empty(odbc_errormsg())) {
            $merror = true;
            $mcomen = odbc_errormsg();
            gm_responder('ERROR', 'No se puede acceder a algunos Datos. ' . $mcomen);
        } else {
            while ($datos_registro = odbc_fetch_array($res)) {
                $idm = (int) $datos_registro['nuevo'];
            }
        }
    }

    // El texto llega en UTF-8 (JSON); las columnas de SQL Server son Windows-1252.
    $descriDb = mb_convert_encoding($descri, 'Windows-1252', 'UTF-8');

    // $stmt = $db->prepare("insert into sqluser.motivos (idmotivo, motivotext) values (?, ?)");
    // if (!$stmt || $db->execute($stmt, [$idm, $descriDb]) === false) {
    //     $db->close();
    //     // VFP: "No se puede acceder a algunos Datos"
    //     gm_responder('ERROR', 'No se puede acceder a algunos Datos.');
    // }

    $stmt = $db->query("insert into sqluser.motivos (idmotivo, motivotext) values ($idm, '$descriDb')");

    if (!empty(odbc_errormsg())) {
        $merror = true;
        $mcomen = odbc_errormsg();
        gm_responder('ERROR', 'No se puede acceder a algunos Datos. ' . $mcomen);
    }

    $db->close();
    // VFP: "Se Actualizo la tabla"
    gm_responder('OK', 'Se actualizo la tabla de motivos.', [
        ['idmotivo' => $idm, 'motivotext' => $descri],
    ]);
} catch (Throwable $e) {
    gm_responder('ERROR', $e->getMessage());
}
