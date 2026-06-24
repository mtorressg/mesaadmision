<?php

/**
 * sp_depuro_socios.php
 *
 * Refactor del proceso VFP prg/prg_depuro_socios.prg
 *
 * Proceso de depuracion de la tabla de socios (mesa de admision): archiva en
 * sqluser.Sociohis y elimina de sqluser.Socio los registros cuya fecha de
 * llegada (horallegada) es anterior a una fecha de corte (mfecproc).
 *
 * La fecha de corte se calcula como (hoy - retraso) pero se "retrocede" hasta
 * la llegada mas antigua que todavia esta sin atender, de modo de NO archivar
 * pacientes pendientes ni los registros posteriores a ellos.
 *
 * El proceso solo se ejecuta en la ventana horaria 6-10 hs, salvo que se
 * fuerce con retraso > 0 (equivalente al IF del .prg original).
 *
 * La conexion a SQL Server se obtiene de la clase dbsqlserver (db.php), que
 * arma el connection string desde la tabla de Cache TabEstados.
 *
 * Uso:
 *   require 'sp_depuro_socios.php';
 *   $r = sp_depuro_socios();          // crea su propia conexion a SQL Server
 *   // $r = ['ok' => bool, 'procesados' => int, 'mfecproc' => 'Y-m-d', 'msg' => string]
 */

require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * Ejecuta la depuracion de socios.
 *
 * @param dbsqlserver|null $db Conexion a SQL Server. Si es null, se crea una
 *                             nueva con la clase dbsqlserver.
 * @param int $retraso         Dias de retraso para la fecha de corte (default 2).
 * @return array               Resultado del proceso.
 *
 * @throws RuntimeException ante errores de SQL (equivale a los Messagebox + Cancel del .prg).
 */
function sp_depuro_socios($db = null, int $retraso = 2): array
{
    if ($retraso < 0) {
        $retraso = 2;
    }

    // Conexion a SQL Server via la nueva clase (valida app 68 + conecta).
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        // -- 1. Fecha/hora del servidor (VFP: sp_busco_fecha_serv('DT')) ----
        $mfecproc1 = sp_depuro_fecha_servidor($db);       // DateTime
        $hora      = (int) $mfecproc1->format('G');        // 0-23

        // mfecproc  = (hoy - retraso)   ;  mfecingre = (hoy - 2)
        $mfecproc  = (clone $mfecproc1)->setTime(0, 0, 0)->modify("-{$retraso} days");
        $mfecingre = (clone $mfecproc1)->setTime(0, 0, 0)->modify('-2 days');

        // -- Ventana de ejecucion (VFP: hour>6 and hour<10) or retraso>0 ----
        if (!((($hora > 6) && ($hora < 10)) || ($retraso > 0))) {
            return [
                'ok'         => true,
                'procesados' => 0,
                'mfecproc'   => $mfecproc->format('Y-m-d'),
                'msg'        => 'Fuera de la ventana horaria de depuracion; no se ejecuta.',
            ];
        }

        $fCorte = $mfecproc->format('Y-m-d');
        $fIngre = $mfecingre->format('Y-m-d');

        // -- 2. Ajuste de la fecha de corte --------------------------------
        // VFP: mwksinatender (sin atender, idmotivo<>27) + mwktendidos
        // (atendidos, idmotivo=57); mwksinatender2 descarta los motivos 27 ya
        // contemplados y, si quedan registros, mfecproc pasa a ser la llegada
        // mas antigua de ese conjunto (Ttod del primer horallegada ordenado).
        // NOTA: el parametro dentro del IN (subconsulta) va como literal y no como
        // marcador '?'. El driver "Microsoft ODBC SQL Server Driver" (legacy) no
        // soporta marcadores de parametro dentro de subconsultas IN y falla el
        // odbc_prepare ("Error de sintaxis o infraccion de acceso"). $fCorte es
        // una fecha 'Y-m-d' generada por el servidor, por lo que es segura inlinear.
        $sqlMin = "SELECT MIN(s.horallegada) AS minll
                     FROM sqluser.socio s
                    WHERE CAST(s.horallegada AS date) < ?
                      AND s.Atendido = 0
                      AND s.idmotivo <> 27
                      AND s.idmotivo > 0
                      AND NOT (
                            (s.idmotivo = 27
                             AND COALESCE(s.paciente, '*') IN (
                                    SELECT t.paciente
                                      FROM sqluser.socio t
                                     WHERE t.Atendido = 1
                                       AND t.idmotivo = 57
                                       AND CAST(t.horallegada AS date) < '{$fCorte}'))
                            OR (s.idmotivo = 27 AND s.horallegada < ?)
                          )";


        // -----------------------------------grabamos log, solo prueba
        $flog = fopen("depurasocio.log", "a");
        fwrite($flog, "--------------------\n");
        fwrite($flog, "Fecha: " . date("Y-m-d H:i:s") . "\n" . PHP_EOL);
        fwrite($flog, "Fecha de corte: $fCorte\n" . PHP_EOL);
        fwrite($flog, "Fecha de ingreso: $fIngre\n" . PHP_EOL);
        fwrite($flog, "sql: " . $sqlMin . "\n" . PHP_EOL);
        fclose($flog);
        // -----------------------------------grabamos log, solo prueba

        $stmt = $db->prepare($sqlMin);
        if (!$stmt) {
            throw new RuntimeException('Error al consultar la tabla Socios - 1');
        }
        sp_depuro_check(
            // Solo quedan 2 marcadores: el del IN (subconsulta) se inlineo arriba.
            $db->execute($stmt, [$fCorte, $fIngre]),
            'Error al consultar la tabla Socios - 1'
        );

        $rowMin = $db->fetch_assoc($stmt);
        $minll  = ($rowMin && !empty($rowMin['minll'])) ? $rowMin['minll'] : '';
        if ($minll !== '') {
            // mfecproc = Ttod(horallegada): fecha (sin hora) de la llegada mas antigua.
            $fCorte = (new DateTime($minll))->format('Y-m-d');
        }

        // -- 3. Registros a depurar (VFP: cursor mwksaco) ------------------
        $stmt = $db->prepare("SELECT * FROM sqluser.socio WHERE CAST(horallegada AS date) < ?");
        if (!$stmt) {
            throw new RuntimeException('Error al consultar la tabla Socios - 3');
        }
        sp_depuro_check($db->execute($stmt, [$fCorte]), 'Error al consultar la tabla Socios - 3');

        $registros = [];
        while ($row = $db->fetch_assoc($stmt)) {
            $registros[] = $row;
        }

        if (empty($registros)) {
            return [
                'ok'         => true,
                'procesados' => 0,
                'mfecproc'   => $fCorte,
                'msg'        => 'No hay registros para depurar.',
            ];
        }

        // INSERT en Sociohis. Se respeta el mapeo del .prg original, que asigna
        // m.Observacion a la columna ObservaA y m.ObservaA a la columna
        // Observacion (los nombres aparecen cruzados en el INSERT VFP; se
        // conserva tal cual).
        $sqlIns = "INSERT INTO sqluser.Sociohis
                      (ApellidoNombre, Atendido, HoraAtencion, HoraFinalizacion,
                       HoraLLegada, IdMotivo, IdSocio, ObservaA, Observacion,
                       Operadora, OperadoraA, puestoAtencion, IdMotivoA, paciente)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $sqlDel = "DELETE FROM sqluser.Socio WHERE id = ?";

        $procesados = 0;

        foreach ($registros as $r) {
            // VFP: SCATTER MEMVAR + normalizaciones.
            $atendido = !empty($r['Atendido']) ? 1 : 0;
            $idreg    = $r['Id'] ?? ($r['id'] ?? null);

            // Cada par insert+delete en su propia transaccion: si falla el
            // delete no queda el registro duplicado en el historial (Cancel).
            $db->autocommit(false);
            try {
                $insStmt = $db->prepare($sqlIns);
                if (!$insStmt) {
                    throw new RuntimeException('Error al insertar registro en la tabla SociosHis.');
                }
                sp_depuro_check($db->execute($insStmt, [
                    $r['ApellidoNombre']   ?? null,
                    $atendido,
                    $r['HoraAtencion']     ?? null,
                    $r['HoraFinalizacion'] ?? null,
                    $r['HoraLLegada']      ?? ($r['horallegada'] ?? null),
                    $r['IdMotivo']         ?? ($r['idmotivo'] ?? null),
                    $r['IdSocio']          ?? null,
                    // Mapeo cruzado conservado del .prg:
                    $r['Observacion']      ?? null,
                    $r['ObservaA']         ?? null,
                    $r['Operadora']        ?? null,
                    $r['OperadoraA']       ?? null,
                    $r['puestoAtencion']   ?? null,
                    $r['IdMotivoA']        ?? null,
                    $r['paciente']         ?? null,
                ]), 'Error al insertar registro en la tabla SociosHis.');

                $delStmt = $db->prepare($sqlDel);
                if (!$delStmt) {
                    throw new RuntimeException('Error al intentar actualizar la tabla Socios.');
                }
                sp_depuro_check(
                    $db->execute($delStmt, [$idreg]),
                    'Error al intentar actualizar la tabla Socios.'
                );

                $db->commit();
                $db->autocommit(true);
                $procesados++;
            } catch (Throwable $e) {
                $db->rollback();
                $db->autocommit(true);
                // VFP: ante error mostraba el mensaje y hacia Cancel (corta el proceso).
                throw new RuntimeException(
                    'NO SE REALIZA CORRECTAMENTE LA OPERACION. VERIFIQUE LA INFORMACION. ' .
                        $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return [
            'ok'         => true,
            'procesados' => $procesados,
            'mfecproc'   => $fCorte,
            'msg'        => "Proceso terminado. Registros procesados: {$procesados}.",
        ];
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}

/**
 * Devuelve la fecha/hora del servidor SQL (VFP: sp_busco_fecha_serv).
 */
function sp_depuro_fecha_servidor($db): DateTime
{
    $res = $db->query('SELECT GETDATE() AS fechaHora');
    if (!$res) {
        throw new RuntimeException('ERROR DE GENERACION DE CURSOR (FECHA-HORA).');
    }
    $row = $db->fetch_assoc($res);
    if (!$row || empty($row['fechaHora'])) {
        throw new RuntimeException('ERROR DE GENERACION DE CURSOR (FECHA-HORA).');
    }
    return new DateTime($row['fechaHora']);
}

/**
 * Valida el resultado de un execute(); lanza excepcion ante error
 * (equivale a los Messagebox de error del .prg).
 */
function sp_depuro_check($ok, string $mensaje): void
{
    if ($ok === false) {
        throw new RuntimeException($mensaje);
    }
}

// ---------------------------------------------------------------------------
// Punto de entrada por CLI (ej.: tarea programada):  php sp_depuro_socios.php [retraso]
// La conexion la arma sp_depuro_socios() con la clase dbsqlserver.
// ---------------------------------------------------------------------------
if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    $retraso = isset($argv[1]) ? (int) $argv[1] : 2;
    try {
        $res = sp_depuro_socios(null, $retraso);
        fwrite(STDOUT, $res['msg'] . PHP_EOL);
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
