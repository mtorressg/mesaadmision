<?php

/**
 * Mesa1Controller
 *
 * Controlador del formulario frmmesa1 (Recepcion de Pacientes).
 * Conecta con los servicios sp_* (carpeta /Php) contra SQL Server.
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../../dbconexion/db.php';
require_once __DIR__ . '/../Php/sp_busco_motivos_me.php';
require_once __DIR__ . '/../Php/sp_busco_entidades_activas.php';
require_once __DIR__ . '/../Php/sp_actualizo_socio.php';
require_once __DIR__ . '/../Php/sp_busco_fecha_serv.php';

class Mesa1Controller extends BaseController
{
    /** Carga inicial de combos (motivos + entidades). VFP: PROCEDURE Init */
    public function index(): void
    {
        try {
            $db = new dbsqlserver();
            $resp = [
                'ok'        => true,
                'motivos'   => $this->mapMotivos(sp_busco_motivos_me($db)),
                'entidades' => $this->mapEntidades(sp_busco_entidades_activas($db)),
            ];
            $db->close();
            $this->json($resp);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Guarda la recepcion del paciente.
     * VFP: CmdSave.Click -> sp_actualizo_socio(..., 'frmMesa1', 1, ...)
     */
    public function guardar(): void
    {
        $apellidoNombre = trim((string) $this->param('apenom', ''));
        $idMotivo       = (int) $this->param('idmotivo', 0);
        $codEntidad     = $this->param('codentidad', '');
        $observacion    = trim((string) $this->param('observacion', ''));
        $prioridad      = (int) $this->param('prioridad', 0);

        // ---------------------------- Solo para prueba
        $flog = fopen("grabarsql.txt", "a");

        fwrite($flog, "---------------- entramos a la funcion guardar" . PHP_EOL);
        fwrite($flog, "nombre : " . $apellidoNombre . PHP_EOL);
        fwrite($flog, "motivo :" . $idMotivo . PHP_EOL);
        fwrite($flog, "entidad :" . $codEntidad . PHP_EOL);
        fwrite($flog, "observacion :" . $observacion . PHP_EOL);
        fwrite($flog, "-----------------" . PHP_EOL);


        // -----------------------------------------------

        if ($codEntidad === '' || $codEntidad === null) {
            $this->json(['ok' => false, 'msg' => 'Debe seleccionar una entidad.'], 422);
        }
        if ($apellidoNombre === '') {
            $this->json(['ok' => false, 'msg' => 'Debe ingresar Apellido y Nombre.'], 422);
        }
        // VFP: si no hay motivo, por defecto 7.
        if ($idMotivo <= 0) {
            $idMotivo = 7;
        }

        try {
            $db    = new dbsqlserver();
            $fecha = sp_busco_fecha_serv($db, 'DT');   // HoraLLegada  

            fwrite($flog, "vamos a sp_actualizar_socio" . PHP_EOL);

            $ok    = sp_actualizo_socio(
                $db,
                $apellidoNombre,
                $idMotivo,
                $observacion,
                $fecha,
                'frmMesa1',
                1,
                '',
                0,
                $prioridad,
                null,
                $codEntidad,
                $this->usuario()
            );
            $db->close();
            $this->json([
                'ok'  => $ok,
                'msg' => $ok ? 'Se guardaron los datos exitosamente.' : 'No se pudo guardar.',
            ]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }

        fclose($flog);
    }

    /** VFP: sp_busco_motivos_me (alimenta CboMotivos). */
    public function listarMotivos(): void
    {
        try {
            $db = new dbsqlserver();
            $data = $this->mapMotivos(sp_busco_motivos_me($db));
            $db->close();
            $this->json(['ok' => true, 'data' => $data]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /** VFP: sp_busco_entidades_activas (alimenta CboEntidad). */
    public function listarEntidades(): void
    {
        try {
            $db = new dbsqlserver();
            $data = $this->mapEntidades(sp_busco_entidades_activas($db));
            $db->close();
            $this->json(['ok' => true, 'data' => $data]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    private function mapMotivos(array $rows): array
    {
        return array_map(fn($r) => ['id' => $r['idmotivo'], 'texto' => trim((string) $r['motivotext'])], $rows);
    }

    private function mapEntidades(array $rows): array
    {
        return array_map(fn($r) => ['id' => $r['ENT_codent'], 'texto' => trim((string) $r['ENT_descrient'])], $rows);
    }
}

// Punto de entrada para peticiones AJAX directas.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    (new Mesa1Controller())->handle();
}
