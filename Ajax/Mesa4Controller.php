<?php

/**
 * Mesa4Controller
 *
 * Controlador del formulario frmmesa4 (Listados / Reportes).
 * Origen VFP: scx/frmmesa4.scx
 *
 * Acciones derivadas de los eventos del formulario VFP:
 *   - listarOperadores  <- Init / sp_busco_usuarios 'MESAINGRESOS' (combo cboUno)
 *   - imprimir          <- cmdprinter.Click  (report form repmesa1)
 *   - exportarExcel     <- cmdexcel.Click     (listado exportado)
 *
 * Parametros del reporte:
 *   tipoLista : 1=Motivo, 2=Por Paciente, 3=Por responsable del Tramite (optlista)
 *   alcance   : 1=Todos, 2=Uno solo (optespe)
 *   operador  : id de usuario cuando alcance = 2 (cboUno)
 *   desde, hasta : rango de fechas (OLEFECHA1 / OLEFECHA2)
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../../dbconexion/db.php';
require_once __DIR__ . '/../Php/sp_listo_tabla_2.php';
require_once __DIR__ . '/../Php/sp_busco_fecha_serv.php';

class Mesa4Controller extends BaseController
{
    /**
     * Estructura de columnas del listado (VFP: cursor MwkEstruc).
     *   [ campo en el cursor de datos , titulo de la columna ]
     * El orden define las columnas del listado exportado.
     */
    private const COLUMNAS = [
        ['ApellidoNombre',   'Apellido y Nombre'],
        ['HoraAtencion',     'Hora de Atencion'],
        ['HoraFinalizacion', 'Hora de Finalizacion'],
        ['Horallegada',      'Hora de llegada'],
        ['motivoText',       'Motivo Solicitado'],
        ['Observacion',      'Observacion de Recepcion'],
        ['ObservaA',         'Observacion en Atencion'],
        ['motivoText1',      'Motivo Atendido'],
        ['Operadora',        'Operadora Ingreso'],
        ['OperadoraA',       'Operadora Atendio'],
        ['paciente',         'Paciente'],
        ['Ent_Descrient',    'Entidad'],
    ];

    /** Datos iniciales (operadores + fecha del servidor para los filtros). */
    public function index(): void
    {
        try {
            $db    = new dbsqlserver();
            $fecha = sp_busco_fecha_serv($db, 'DD');   // fecha del servidor (Y-m-d)
            $db->close();
            $this->json([
                'ok'         => true,
                'fecha'      => $fecha,
                'operadores' => [],   // sp_busco_usuarios 'MESAINGRESOS'
            ]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /** VFP: sp_busco_usuarios 'MESAINGRESOS' (alimenta cboUno). */
    public function listarOperadores(): void
    {
        $this->json(['ok' => true, 'data' => []]);
    }

    /**
     * Genera el reporte para impresion.
     * VFP: cmdprinter.Click -> report form repmesa1 HEADING titulo preview
     */
    public function imprimir(): void
    {
        $filtros = $this->filtros();
        // TODO: armar la consulta (sp_listo_tabla_2) y generar el reporte.
        $this->stub('imprimir');
    }

    /**
     * Exporta el listado (VFP: cmdexcel.Click).
     *
     * Refactor de la rama "X" (Microsoft Excel) del Do Case original. Se excluye
     * deliberadamente la libreria LIB_OPENOFFICE, toda la rama 'Case mOpcion = "O"'
     * y el control de instalacion de Excel (prg_valido_inst_excel). En lugar de
     * generar una planilla, el listado se entrega como un archivo .html que el
     * navegador descarga.
     */
    public function exportarExcel(): void
    {
        $filtros = $this->filtros();

        if ($filtros['desde'] === '' || $filtros['hasta'] === '') {
            $this->json(['ok' => false, 'msg' => 'Debe indicar el rango de fechas (Desde / Hasta).'], 422);
        }

        try {
            $rows   = $this->datosListado($filtros);
            $titulo = $this->tituloListado($filtros);

            // El reporte se genera siempre, aunque no haya registros (en ese caso
            // sale solo con los encabezados). Difiere del VFP original, que
            // mostraba un Messagebox "NO HAY INFORMACION DISPONIBLE".
            $this->generarHtml($rows, $titulo);   // hace el streaming + exit
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /** Normaliza los filtros del reporte recibidos del formulario. */
    private function filtros(): array
    {
        return [
            'tipoLista' => (int) $this->param('tipoLista', 1),
            'alcance'   => (int) $this->param('alcance', 1),
            'operador'  => $this->param('operador', ''),
            'desde'     => (string) $this->param('desde', ''),
            'hasta'     => (string) $this->param('hasta', ''),
        ];
    }

    // ----------------------------------------------- Datos del listado -------

    /**
     * Reproduce la consulta del cmdexcel.Click: corre sp_listo_tabla_2 sobre
     * Socio / SocioMod / Sociohis, unifica (UNION) las filas y las ordena.
     *
     * @return array Filas del listado (claves segun los campos del SELECT).
     */
    private function datosListado(array $filtros): array
    {
        // Rango de fechas. VFP: mfechas = Olefecha2 + 82800 (incluye el dia hasta).
        $mfecdes = $filtros['desde'] . ' 00:00:00';
        $mfechas = (new DateTime($filtros['hasta']))
            ->modify('+82800 seconds')
            ->format('Y-m-d H:i:s');

        // Alcance (optespe). 2 = un operador puntual (cboUno).
        $mbusco1 = '';
        if ($filtros['alcance'] === 2) {
            $mcusu   = str_replace("'", "''", trim((string) $filtros['operador']));
            $mbusco1 = " and (operadora = '{$mcusu}' or OperadoraA = '{$mcusu}') ";
        }

        // Orden (optlista). Se conserva del original aunque el resultado final
        // se reordena por motivoText (ver mas abajo).
        switch ($filtros['tipoLista']) {
            case 2:
                $orden = ' Order BY paciente ';
                break;
            case 3:
                $orden = ' Order BY ApellidoNombre ';
                break;
            case 1:
            default:
                $orden = ' Order BY motivos.motivoText ';
                break;
        }

        // Los dos motivos comparten nombre de campo (motivos / mot): se alias an
        // para que el resultset tenga claves distintas (motivoText / motivotext1).
        $campos = 'ApellidoNombre, HoraAtencion, HoraFinalizacion, Horallegada,'
            . ' motivos.motivoText as motivoText, Observacion, ObservaA, mot.motivotext as motivotext1,'
            . ' Operadora, OperadoraA, paciente, Ent_Descrient';

        $condicion = "Where Horallegada between '{$mfecdes}' and '{$mfechas}' " . $mbusco1 . $orden;

        // Una entrada por tabla origen (Socio actual / modificados / historico).
        $tablas = [
            'sqluser.Socio join sqluser.motivos on motivos.idMotivo = socio.idmotivo '
                . 'join sqluser.motivos as mot on mot.idMotivo = socio.idmotivoA '
                . 'Left Join sqluser.Entidades on codentidad = ENT_codent ',
            'sqluser.SocioMod join sqluser.motivos on motivos.idMotivo = socioMod.idmotivo '
                . 'join sqluser.motivos as mot on mot.idMotivo = socioMod.idmotivoA '
                . 'Left Join sqluser.Entidades on codentidad = ENT_codent ',
            'sqluser.Sociohis join sqluser.motivos on motivos.idMotivo = sociohis.idmotivo '
                . 'join sqluser.motivos as mot on mot.idMotivo = sociohis.idmotivoA '
                . 'Left Join sqluser.Entidades on codentidad = ENT_codent ',
        ];


        // --------------------------------
        // $flog = fopen("generarHtml.log", "a");
        // fwrite($flog, "campos: $campos" . PHP_EOL);
        // fwrite($flog, "condicion: $condicion\n" . PHP_EOL);
        // fwrite($flog, "tablas: " . implode(" | ", $tablas) . "\n" . PHP_EOL);
        // fclose($flog);
        // --------------------------------

        $db = new dbsqlserver();
        try {
            $merge = [];
            foreach ($tablas as $tablalist) {
                foreach (sp_listo_tabla_2($db, $campos, $condicion, $tablalist) as $row) {
                    // UNION del original: descarta filas duplicadas exactas.
                    $merge[md5(serialize($row))] = $row;
                }
            }
        } finally {
            $db->close();
        }

        $rows = array_values($merge);

        // VFP: Select * From MwkLista4 order By motivoText into Cursor MwkLista
        usort($rows, function ($a, $b) {
            return strcasecmp(
                trim((string) $this->valor($a, 'motivoText')),
                trim((string) $this->valor($b, 'motivoText'))
            );
        });

        return $rows;
    }

    /** VFP: titulo = 'Atenciones del ' + Dtoc(mfecdes) + ' Al ' + Dtoc(mfechas) */
    private function tituloListado(array $filtros): string
    {
        $desde = (new DateTime($filtros['desde']))->format('d/m/Y');
        $hasta = (new DateTime($filtros['hasta']))->format('d/m/Y');
        return 'Atenciones del ' . $desde . ' Al ' . $hasta;
    }

    /** Lee un campo del row sin importar la capitalizacion devuelta por ODBC. */
    private function valor(array $row, string $campo)
    {
        foreach ($row as $k => $v) {
            if (strcasecmp($k, $campo) === 0) {
                return $v;
            }
        }
        return null;
    }

    // ----------------------------------------------- Generacion del HTML -----

    /**
     * Arma el listado como documento HTML y lo muestra en el navegador.
     * Disposicion equivalente a la planilla original: titulo arriba, encabezados
     * de columna y luego las filas de datos.
     */
    private function generarHtml(array $rows, string $titulo): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: max-age=0');

            // header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            // header('Content-Disposition: attachment; filename="reporte.xls"');
            // header('Cache-Control: max-age=0');
        }

        echo $this->htmlListado($rows, $titulo);
        exit;
    }

    /** Construye el documento HTML completo del listado. */
    private function htmlListado(array $rows, string $titulo): string
    {
        // El texto de SQL Server (ODBC) viene en Windows-1252; lo pasamos a UTF-8
        // antes de escaparlo para HTML.
        $esc = static function ($valor): string {
            $valor = (string) $valor;
            if (!mb_check_encoding($valor, 'UTF-8')) {
                $valor = mb_convert_encoding($valor, 'UTF-8', 'Windows-1252');
            }
            return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
        };

        $encabezados = '';
        foreach (self::COLUMNAS as [$campo, $tituloCol]) {
            $encabezados .= '<th>' . $esc($tituloCol) . '</th>';
        }

        $filas = '';
        foreach ($rows as $row) {
            $filas .= '<tr>';
            foreach (self::COLUMNAS as [$campo, $tituloCol]) {
                $filas .= '<td>' . $esc(trim((string) $this->valor($row, $campo))) . '</td>';
            }
            $filas .= "</tr>\n";
        }

        // Sin registros: una fila informativa que ocupa todas las columnas.
        if ($filas === '') {
            $cols   = count(self::COLUMNAS);
            $filas  = '<tr><td colspan="' . $cols . '" class="vacio">'
                . 'NO HAY INFORMACION DISPONIBLE</td></tr>' . "\n";
        }

        $tituloEsc = $esc($titulo);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{$tituloEsc}</title>
<style>
    body  { font-family: Arial, Helvetica, sans-serif; margin: 16px; color: #222; }
    h1    { font-size: 16px; margin: 0 0 12px; }
    table { border-collapse: collapse; width: 100%; font-size: 12px; }
    th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; vertical-align: top; }
    thead th { background: #e9e9e9; }
    tbody tr:nth-child(even) { background: #f6f6f6; }
    td.vacio { text-align: center; font-style: italic; color: #666; }
</style>
</head>
<body>
<h1>{$tituloEsc}</h1>
<table>
<thead><tr>{$encabezados}</tr></thead>
<tbody>
{$filas}</tbody>
</table>
</body>
</html>
HTML;
    }
}

// Punto de entrada para peticiones AJAX directas.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    (new Mesa4Controller())->handle();
}
