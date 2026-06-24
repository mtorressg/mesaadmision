<?php

/**
 * Mesa2Controller
 *
 * Controlador del formulario frmmesa2 (Atencion de Pacientes).
 * Conecta con los servicios sp_* (carpeta /Php) contra SQL Server.
 *
 * PageFrame de 4 pestanas: Sala de Espera | Cambios de Cama | Recien Nacidos | Datos
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../../dbconexion/db.php';
require_once __DIR__ . '/../Php/sp_sectorint.php';
require_once __DIR__ . '/../Php/sp_busco_socio.php';
require_once __DIR__ . '/../Php/sp_busco_motivos_me.php';
require_once __DIR__ . '/../Php/sp_actualizo_socio.php';
require_once __DIR__ . '/../Php/sp_busco_fecha_serv.php';

class Mesa2Controller extends BaseController
{
    /** Datos iniciales del formulario (combos de sectores y motivos). */
    public function index(): void
    {
        try {
            $db = new dbsqlserver();
            $sectores = $this->mapSectores(sp_sectorint($db));
            $motivos  = $this->mapMotivos(sp_busco_motivos_me($db));
            $fecha    = sp_busco_fecha_serv($db, 'DD');   // fecha del servidor (Y-m-d) para los filtros
            $db->close();
            $this->json(['ok' => true, 'sectores' => $sectores, 'motivos' => $motivos, 'fecha' => $fecha]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /** Alimenta el combo de sectores (PGCambios). */
    public function listarSectores(): void
    {
        try {
            $db = new dbsqlserver();
            $data = $this->mapSectores(sp_sectorint($db));
            $db->close();
            $this->json(['ok' => true, 'data' => $data]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Carga la grilla de una pestana.
     *   page = sala | cambios | rn ; atendidos = 0 | 1
     * Solo 'sala' tiene servicio mapeado (sp_busco_socio). 'cambios'/'rn' usaban
     * cursores propios en VFP (cargogrid_cambios/_rn) que no se refactorizaron.
     */
    public function cargarGrid(): void
    {
        $page      = (string) $this->param('page', 'sala');
        $desde     = $this->param('desde', '');
        $hasta     = $this->param('hasta', '');
        $atendidos = (int) $this->param('atendidos', 0);

        try {
            $db = new dbsqlserver();

            // "Traer atendidos" solo aplica a la solapa Sala de Espera.
            if ($page === 'sala' && $atendidos === 1) {
                $f1 = $desde !== '' ? $desde : null;
                $f2 = $hasta !== '' ? $hasta : null;
                $rows  = sp_busco_socio($db, 2, '', $f1, $f2);
                $cells = $this->mapAtendidos($rows);
                $db->close();
                $this->json(['ok' => true, 'page' => $page, 'rows' => $cells, 'cantidad' => count($cells)]);
            }

            // Sin atender: una sola consulta cruda, particionada por solapa.
            // VFP: V_Cursor (mwkLLegadas) -> cargagrid(1/2/3) -> mwkLLegadas/hotel/rn.
            $rows = sp_busco_socio($db, 0);
            $db->close();

            if ($page === 'sala') {
                $part = $this->cargogrid_sala($rows);   // cargagrid(1)
            } elseif ($page === 'cambios') {
                $part = $this->cargogrid_hotel($rows);  // cargagrid(2)
            } elseif ($page === 'rn') {
                $part = $this->cargogrid_rn($rows);     // cargagrid(3)
            } else {
                $this->json(['ok' => false, 'msg' => "Pestana desconocida: {$page}"], 400);
            }

            $cells = $this->mapLlegadas($part);
            // Metadatos por fila (no se muestran): permiten al modal preseleccionar
            // el motivo (cboMotivos) e identificar el registro (idsocio).
            $meta = array_map(fn($r) => [
                'idmotivo' => (int) ($r['IdMotivo'] ?? 0),
                'idsocio'  => $r['IdSocio'] ?? null,
            ], $part);
            $this->json(['ok' => true, 'page' => $page, 'rows' => $cells, 'meta' => $meta, 'cantidad' => count($cells)]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Devuelve el detalle de un registro para la pestana Datos.
     * VFP: cargodatos -> sp_busco_socio (busqueda por criterio).
     */
    public function cargarDatos(): void
    {
        $idSocio = (int) $this->param('idsocio', 0);
        try {
            $db = new dbsqlserver();
            $rows = sp_busco_socio($db, 3, " where SOCIO.IdSocio = " . $idSocio . " ");
            $db->close();
            $this->json(['ok' => true, 'data' => $rows[0] ?? null]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }


    // ------------------------------------------
    function cargogrid_sala(array $rows): array
    {

        // Tomar el array y filtrarlo por criterio copiado del vfp

        $fmtHora = function ($v) {
            if (empty($v)) {
                return '';
            }
            try {
                return (new DateTime($v))->format('H:i:s');
            } catch (Throwable $e) {
                return (string) $v;
            }
        };


        $rows_Sala = [];
        foreach ($rows as $r) {
            $idMotivo = (int) ($r['IdMotivo'] ?? 0);
            $paciente = trim((string) ($r['paciente'] ?? ''));

            //WHERE !(Inlist(IdMotivo,29,66,68,69,58,71) Or (IdMotivo=27 And !Empty(paciente)))
            $excluir = in_array($idMotivo, [29, 66, 68, 69, 58, 71], true)
                || ($idMotivo === 27 && $paciente !== '');
            if ($excluir) {
                continue;
            }


            // HoraLLegada se descompone en fecha (ttod) y hora (ttoc(...,2)).
            $ll = $r['HoraLLegada'] ?? null;
            try {
                $dt = !empty($ll) ? new DateTime($ll) : null;
            } catch (Throwable $e) {
                $dt = null;
            }

            $rows_Sala[] = [
                // Iif(Nvl(prioridadat,.f.)=.t.,'!',' ')
                'prioridadat'    => !empty($r['PrioridadAt']) ? '!' : ' ',
                'dia'            => $dt ? $dt->format('d/m/Y') : '',   // ttod(HoraLLegada)
                'HoraLLegada'    => $dt ? $dt->format('H:i:s') : '',   // Ttoc(HoraLLegada,2)
                'ApellidoNombre' => $r['ApellidoNombre'] ?? '',
                'MotivoText'     => $r['MotivoText'] ?? '',   // MOTIVOS.MotivoText (motivo ingreso)
                'Observacion'    => $r['Observacion'] ?? '',
                'MotivoText1'    => $r['MotivoText1'] ?? '',  // A.MotivoText (motivo atendido)
                'ENT_descrient'  => $r['ENT_DESCRIENT'] ?? '',
                'ObservaA'       => $r['ObservaA'] ?? '',
                'paciente'       => $paciente,
                'IdSocio'        => $r['IdSocio'] ?? null,
                'IdMotivo'       => $idMotivo,
                // iif(Isnull(fecpasiva_Excl),'  ','PE')
                'ESPE'           => (($r['fecpasiva_Excl'] ?? null) === null) ? '  ' : 'PE',
                'codentidad'     => $r['codentidad'] ?? null,
                // Campos de atencion: no figuran en mwkLLega pero la grilla de atendidos los muestra.
                'operadora'        => trim((string) ($r['operadora'] ?? '')),
                'OperadoraA'       => trim((string) ($r['OperadoraA'] ?? '')),
                'HoraAtencion'     => $fmtHora($r['HoraAtencion'] ?? null),
                'Horafinalizacion' => $fmtHora($r['Horafinalizacion'] ?? null),
            ];
        }

        return $rows_Sala;
    }


    // --------------------------------------------------------
    function cargogrid_hotel(array $rows): array
    {
        // Replica del SELECT ... INTO CURSOR mwkLLegahotel (FoxPro):
        // toma SOLO IdMotivo = 71 y agrega 'sectorori' extraido de la Observacion ("De: XXX").
        $rows_hotel = [];


        foreach ($rows as $r) {
            //$idMotivo = (int) ($r['IdMotivo'] ?? 0);

            // WHERE (Inlist(IdMotivo,29,66,68,58,69))
            //if ($idMotivo !== 71) {
            //if (intval($r['IdMotivo']) !== 71) {
            //    continue;
            //}

            $idMotivo = (int) ($r['IdMotivo'] ?? 0);
            $excluir = in_array($idMotivo, [29, 66, 68, 69, 58, 71], true);

            if (!$excluir) {
                continue;
            }

            // HoraLLegada se descompone en fecha (ttod) y hora (ttoc(...,2)).
            $ll = $r['HoraLLegada'] ?? null;
            try {
                $dt = !empty($ll) ? new DateTime($ll) : null;
            } catch (Throwable $e) {
                $dt = null;
            }

            // sectorori = Padr(Substr(Observacion, At("De:",Observacion)+4, 3), 3)
            // At() es 1-based y Substr 1-based; en PHP (0-based) el desplazamiento equivale a strpos()+4.
            $obs = (string) ($r['Observacion'] ?? '');
            $pos = strpos($obs, 'De:');
            $sectorori = $pos === false ? '' : substr($obs, $pos + 4, 3);
            $sectorori = str_pad($sectorori, 3, ' ', STR_PAD_RIGHT);

            $rows_hotel[] = [
                // Iif(Nvl(prioridadat,.f.)=.t.,'!',' ')
                'prioridadat'    => !empty($r['PrioridadAt']) ? '!' : ' ',
                'dia'            => $dt ? $dt->format('d/m/Y') : '',   // ttod(HoraLLegada)
                'HoraLLegada'    => $dt ? $dt->format('H:i:s') : '',   // Ttoc(HoraLLegada,2)
                'ApellidoNombre' => $r['ApellidoNombre'] ?? '',
                'MotivoText'     => $r['MotivoText'] ?? '',
                'Observacion'    => $obs,
                'MotivoText1'    => $r['MotivoText1'] ?? '',
                'ENT_descrient'  => $r['ENT_DESCRIENT'] ?? '',
                'ObservaA'       => $r['ObservaA'] ?? '',
                'paciente'       => trim((string) ($r['paciente'] ?? '')),
                'IdSocio'        => $r['IdSocio'] ?? null,
                'IdMotivo'       => $idMotivo,
                // iif(Isnull(fecpasiva_Excl),'  ','PE')
                'ESPE'           => (($r['fecpasiva_Excl'] ?? null) === null) ? '  ' : 'PE',
                'sectorori'      => $sectorori,
                'codentidad'     => $r['codentidad'] ?? null,
            ];
        }

        return $rows_hotel;
    }


    // --------------------------------------------------
    function cargogrid_rn(array $rows): array
    {
        // Replica del SELECT ... INTO CURSOR mwkLLegarn (FoxPro):
        // toma SOLO IdMotivo 18 o 38 y agrega 'sectorori' extraido de la Observacion ("De: XXX").
        $rows_rn = [];
        foreach ($rows as $r) {
            $idMotivo = (int) ($r['IdMotivo'] ?? 0);

            // WHERE (Inlist(IdMotivo,18,38))
            if (!in_array($idMotivo, [18, 38], true)) {
                continue;
            }

            // HoraLLegada se descompone en fecha (ttod) y hora (ttoc(...,2)).
            $ll = $r['HoraLLegada'] ?? null;
            try {
                $dt = !empty($ll) ? new DateTime($ll) : null;
            } catch (Throwable $e) {
                $dt = null;
            }

            // sectorori = Padr(Substr(Observacion, At("De:",Observacion)+4, 3), 3)
            // At() es 1-based y Substr 1-based; en PHP (0-based) el desplazamiento equivale a strpos()+4.
            $obs = (string) ($r['Observacion'] ?? '');
            $pos = strpos($obs, 'De:');
            $sectorori = $pos === false ? '' : substr($obs, $pos + 4, 3);
            $sectorori = str_pad($sectorori, 3, ' ', STR_PAD_RIGHT);

            $rows_rn[] = [
                // Iif(Nvl(prioridadat,.f.)=.t.,'!',' ')
                'prioridadat'    => !empty($r['PrioridadAt']) ? '!' : ' ',
                'dia'            => $dt ? $dt->format('d/m/Y') : '',   // ttod(HoraLLegada)
                'HoraLLegada'    => $dt ? $dt->format('H:i:s') : '',   // Ttoc(HoraLLegada,2)
                'ApellidoNombre' => $r['ApellidoNombre'] ?? '',
                'MotivoText'     => $r['MotivoText'] ?? '',
                'Observacion'    => $obs,
                'MotivoText1'    => $r['MotivoText1'] ?? '',
                'ENT_descrient'  => $r['ENT_DESCRIENT'] ?? '',
                'ObservaA'       => $r['ObservaA'] ?? '',
                'paciente'       => trim((string) ($r['paciente'] ?? '')),
                'IdSocio'        => $r['IdSocio'] ?? null,
                'IdMotivo'       => $idMotivo,
                // iif(Isnull(fecpasiva_Excl),'  ','PE')
                'ESPE'           => (($r['fecpasiva_Excl'] ?? null) === null) ? '  ' : 'PE',
                'sectorori'      => $sectorori,
                'codentidad'     => $r['codentidad'] ?? null,
            ];
        }

        return $rows_rn;
    }



    /**
     * Guarda la atencion del paciente.
     * VFP: cmdsave.Click -> sp_actualizo_socio(..., 'frmMesa2', 1, ...)
     */
    public function guardar(): void
    {
        $idSocio    = (int) $this->param('idsocio', 0);
        $idMotivoA  = (int) $this->param('idmotivoa', 0);
        $observaA   = trim((string) $this->param('observaa', ''));
        $paciente   = trim((string) $this->param('paciente', ''));
        $prioridad  = (int) $this->param('prioridad', 0);

        try {
            $db    = new dbsqlserver();
            $fecha = sp_busco_fecha_serv($db, 'DT');
            $ok    = sp_actualizo_socio(
                $db,
                '',
                $idMotivoA,
                $observaA,
                $fecha,
                'frmMesa2',
                1,
                $paciente,
                0,
                $prioridad,
                $idSocio,
                null,
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
    }

    /**
     * Libera el paciente (vuelve a sala de espera sin atender).
     * VFP: cmdblank.Click / cmdbusco.Click -> sp_actualizo_socio(..., meven=4/0)
     */
    public function liberar(): void
    {
        $idSocio = (int) $this->param('idsocio', 0);
        try {
            $db    = new dbsqlserver();
            $fecha = sp_busco_fecha_serv($db, 'DT');
            // mape vacio + meven<>1 -> UPDATE que resetea HoraAtencion/atendido/OperadoraA.
            $ok = sp_actualizo_socio($db, '', 0, '', $fecha, 'frmMesa2', 0, '', 0, 0, $idSocio, null, $this->usuario());
            $db->close();
            $this->json(['ok' => $ok, 'msg' => $ok ? 'Paciente liberado.' : 'No se pudo liberar el paciente.']);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /** VFP: cmdcheckin.Click (aviso al hotel via OLE). Sin servicio refactorizado. */
    public function checkin(): void
    {
        $habitacion = $this->param('habitacion', '');
        if ($habitacion === '' || (int) $habitacion === 0) {
            $this->json(['ok' => false, 'msg' => 'Ingrese el numero de habitacion.'], 422);
        }
        $this->stub('checkin');
    }

    /** VFP: cmdprintsol.Click (reporte). Sin servicio refactorizado. */
    public function imprimirSolicitud(): void
    {
        $this->stub('imprimirSolicitud');
    }

    /** VFP: cmdvercamas.Click (listado de camas). Sin servicio refactorizado. */
    public function verCamasVacias(): void
    {
        $this->stub('verCamasVacias');
    }

    /** VFP: cmdmodify.Click. Sin servicio refactorizado. */
    public function modificar(): void
    {
        $this->stub('modificar');
    }

    /** VFP: cmdexcel.Click / exportaexcel. Sin servicio refactorizado. */
    public function exportarExcel(): void
    {
        $this->stub('exportarExcel');
    }


    // ----------------------------------------------------------------- mapeos
    private function mapSectores(array $rows): array
    {
        return array_map(fn($r) => [
            'id'    => $r['sec_codsector'],
            'texto' => trim((string) $r['sec_descripsec']),
        ], $rows);
    }

    /** Combo cboMotivos: sp_busco_motivos_me -> {id: idmotivo, texto: motivotext}. */
    private function mapMotivos(array $rows): array
    {
        return array_map(fn($r) => [
            'id'    => (int) ($r['idmotivo'] ?? 0),
            'texto' => trim((string) ($r['motivotext'] ?? '')),
        ], $rows);
    }

    /**
     * Mapea la salida de los cargogrid_* (sala/hotel/rn) a celdas COLS_SIN.
     * Esas filas ya vienen display-ready (prioridadat '!'/' ', dia d/m/Y, hora H:i:s).
     * La columna vacia de COLS_SIN (indice 6) se usa para 'sectorori' (hotel/rn);
     * en sala no existe esa clave y queda en blanco.
     * COLS_SIN: ['!','Fecha','Hs Ll','Apellido, Nombre','Motivos','Observacion','','Entidad']
     */
    private function mapLlegadas(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                trim((string) ($r['prioridadat'] ?? '')),    // !
                (string) ($r['dia'] ?? ''),                  // Fecha
                (string) ($r['HoraLLegada'] ?? ''),          // Hs Ll
                trim((string) ($r['ApellidoNombre'] ?? '')), // Apellido, Nombre
                trim((string) ($r['MotivoText'] ?? '')),     // Motivos
                trim((string) ($r['Observacion'] ?? '')),    // Observacion
                trim((string) ($r['sectorori'] ?? '')),      // (sector de origen: hotel/rn)
                trim((string) ($r['ENT_descrient'] ?? '')),  // Entidad
            ];
        }
        return $out;
    }

    /** Columnas COLS_SIN: ['!','Fecha','Hs Ll','Apellido, Nombre','Motivos','Observacion','','Entidad'] */
    private function mapSinAtender(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $ll = $r['HoraLLegada'] ?? null;
            $out[] = [
                !empty($r['PrioridadAt']) ? '!' : '',
                $this->fecha($ll),
                $this->hora($ll),
                trim((string) ($r['ApellidoNombre'] ?? '')),
                trim((string) ($r['MotivoText'] ?? '')),
                trim((string) ($r['Observacion'] ?? '')),
                '',
                trim((string) ($r['ENT_DESCRIENT'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * Columnas COLS_ATE (11): incluye motivo atendido, operadores, horas, etc.
     * sp_busco_socio (opcion 2) ya entrega los valores listos para mostrar
     * (prioridadat '!'/' ', dia d/m/Y, horas H:i:s), por eso aqui solo se mapean.
     */
    private function mapAtendidos(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                trim((string) ($r['prioridadat'] ?? '')),        // !
                (string) ($r['dia'] ?? ''),                      // Fecha
                (string) ($r['HoraLLegada'] ?? ''),              // Hs Ll
                trim((string) ($r['ApellidoNombre'] ?? '')),     // Apellido, Nombre
                trim((string) ($r['MotivoText1'] ?? '')),        // Motivo Atendido (A.MotivoText)
                trim((string) ($r['operadora'] ?? '')),          // Operador Ingreso
                trim((string) ($r['OperadoraA'] ?? '')),         // Operador Atendio
                (string) ($r['HoraAtencion'] ?? ''),             // Hora Atendido
                (string) ($r['Horafinalizacion'] ?? ''),         // Hora finalizacion
                trim((string) ($r['MotivoText'] ?? '')),         // Motivo Ingreso (MOTIVOS.MotivoText)
                trim((string) ($r['ENT_descrient'] ?? '')),      // Entidad
            ];
        }
        return $out;
    }

    // -----------------------------------------------------
    private function fecha($v): string
    {
        if (empty($v)) {
            return '';
        }
        try {
            return (new DateTime($v))->format('d/m/Y');
        } catch (Throwable $e) {
            return (string) $v;
        }
    }

    // -----------------------------------------------------
    private function hora($v): string
    {
        if (empty($v)) {
            return '';
        }
        try {
            return (new DateTime($v))->format('H:i');
        } catch (Throwable $e) {
            return (string) $v;
        }
    }
}

// Punto de entrada para peticiones AJAX directas.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    (new Mesa2Controller())->handle();
}
