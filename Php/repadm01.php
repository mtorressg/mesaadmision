<?php

/**
 * repadm01.php - Solicitud de Internacion
 *
 * Refactor del reporte VFP rep/repadm01.frx + del evento cmdprintsol.Click de
 * scx/frmmesa2, como vista HTML imprimible (boton Imprimir / Ctrl+P).
 *
 * Se invoca con ?idsocio=N (y opcional &idmotivo=N) desde las grillas de frmmesa2.
 *
 * Orquestacion (equivalente a cmdprintsol):
 *   1. IdSocio -> registro de SOCIO (codigo de admision = SOCIO.paciente).   [SQL Server]
 *   2. Solicitud: SOCIO.paciente + IdMotivo in (1,18,27) -> observacion.      [SQL Server]
 *   3. Datos del paciente:                                                    [Cache]
 *        - "De: GUARDIA"  -> sp_busco_nombre_paciente_1 (por Historia Clinica)
 *        - internado      -> sp_busco_pac_int_impre
 *   4. Medico/matricula   -> sp_busco_medico_dat ; Sector -> sp_secagrup.     [Cache]
 *   5. Neonatologia (motivo 18) -> sp_busco_pulseras_mmbb_adm + sp_busco_pac_admitidos.
 *   6. La observacion se parsea para Dx, Profesional, sector, Urgente/Programada.
 *
 * Conexiones (ver dbconexion/db.php): dbsqlserver = SOCIO (mesa de entrada);
 * db = Cache (datos clinicos/medicos), segun lo solicitado.
 */
require_once __DIR__ . '/../../dbconexion/db.php';
require_once __DIR__ . '/sp_busco_socio.php';          // SQL Server (SOCIO)
require_once __DIR__ . '/sp_busco_fecha_serv.php';     // fecha del servidor
require_once __DIR__ . '/../Ajax/sp_busco_pac_int_impre.php';      // Cache
require_once __DIR__ . '/../Ajax/sp_busco_nombre_paciente_1.php';  // Cache
require_once __DIR__ . '/../Ajax/sp_busco_medico_dat.php';         // Cache
require_once __DIR__ . '/../Ajax/sp_secagrup.php';                 // Cache
require_once __DIR__ . '/../Ajax/sp_busco_pulseras_mmbb_adm.php';  // Cache
require_once __DIR__ . '/../Ajax/sp_busco_pac_admitidos.php';      // Cache

// ----------------------------------------------------------------- helpers
/** Texto SQL Server/Cache (Windows-1252) -> UTF-8 + escape HTML. */
function h($v): string
{
    $s = (string) $v;
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    }
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function vget($arr, string $key, string $default = ''): string
{
    if (!is_array($arr) || !isset($arr[$key]) || $arr[$key] === null) {
        return $default;
    }
    return trim((string) $arr[$key]);
}
function ffecha($v): string
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
function sexoTxt($s): string
{
    $s = strtoupper(trim((string) $s));
    return $s === 'M' ? 'Masculino' : ($s === 'I' ? 'Indeterminado' : ($s === 'F' ? 'Femenino' : ''));
}
/** Edad en [anios, meses] entre nacimiento y fecha de referencia (VFP prg_edad 'AMD'). */
function edadAM($fnac, $fref): array
{
    if (empty($fnac) || empty($fref)) {
        return [0, 0];
    }
    try {
        $d = (new DateTime($fnac))->diff(new DateTime($fref));
        return [(int) $d->y, (int) $d->m];
    } catch (Throwable $e) {
        return [0, 0];
    }
}

// ----------------------------------------------------------------- datos
$idSocio  = (int) ($_GET['idsocio'] ?? 0);
$idMotivo = (int) ($_GET['idmotivo'] ?? 0);

$R = [
    'nombre' => '',
    'entidad' => '',
    'afiliado' => '',
    'sexo' => '',
    'edad' => '',
    'sector' => '',
    'diagnostico' => '',
    'medico' => '',
    'matricula' => '',
    'especialidad' => '',
    'urgente' => false,
    'programada' => false,
    'fechaSol' => '',
];
$error = '';
$aviso = '';

if ($idSocio <= 0) {
    $error = 'Falta el parametro idsocio.';
} else {
    try {
        $sql = new dbsqlserver();

        // 1. Registro seleccionado -> codigo de admision (protocolo) + entidad.
        $selRows = sp_busco_socio($sql, 3, ' where SOCIO.IdSocio = ' . $idSocio . ' ');
        $sel     = $selRows[0] ?? null;

        if ($sel === null) {
            $error = 'No se encontro el registro (IdSocio ' . $idSocio . ').';
        } else {
            $mprotocolo = trim((string) ($sel['paciente'] ?? ''));
            $mcodent    = $sel['codentidad'] ?? null;
            // Fallback con datos basicos de SOCIO.
            $R['nombre']  = vget($sel, 'ApellidoNombre');
            $R['entidad'] = vget($sel, 'entidad');

            if ($mprotocolo === '') {
                $aviso = 'El registro no tiene codigo de admision: el paciente aun no esta internado.';
            } else {
                // 2. Solicitud de internacion (observacion estructurada).
                $prot    = str_replace("'", "''", $mprotocolo);
                $solRows = sp_busco_socio($sql, 3, " where SOCIO.paciente = '{$prot}' and IdMotivo in (1,18,27) ");

                if (!$solRows) {
                    $aviso = 'EL PROFESIONAL NO INGRESO LA SOLICITUD DE INTERNACION.';
                } else {
                    $solSel = end($solRows);                 // VFP: Go Bottom
                    $mobs   = (string) ($solSel['Observacion'] ?? '');
                    $R['fechaSol'] = ffecha($solSel['HoraLLegada'] ?? null);
                    $dgua = stripos($mobs, 'De: GUARDIA') !== false;

                    // 3. Datos del paciente (Cache).
                    $cache = new db();
                    $anos  = 0;
                    $meses = 0;
                    $sectorInternac = '';

                    if ($dgua) {
                        // Paciente de guardia: por Historia Clinica.
                        $pac   = sp_busco_nombre_paciente_1($cache, $mprotocolo);
                        $lista = array_values(array_filter($pac, function ($r) use ($mcodent) {
                            return (string) ($r['ENT_codent'] ?? '') === (string) $mcodent;
                        }));
                        $p = ($lista[0] ?? ($pac[0] ?? null));
                        if ($p) {
                            $R['nombre']   = vget($p, 'REG_nombrepac', $R['nombre']);
                            $R['sexo']     = sexoTxt($p['REG_sexo'] ?? '');
                            $R['entidad']  = vget($p, 'ENT_descrient', $R['entidad']);
                            $R['afiliado'] = vget($p, 'AFI_nroafiliado');
                            [$anos, $meses] = edadAM($p['REG_fecnacimiento'] ?? null, sp_busco_fecha_serv($sql, 'DD'));
                        }
                    } else {
                        // Paciente internado.
                        $det = sp_busco_pac_int_impre($cache, $mprotocolo);
                        $p   = $det[0] ?? null;
                        if ($p) {
                            $R['nombre']      = vget($p, 'PAC_nombrepaciente', $R['nombre']);
                            $R['sexo']        = sexoTxt($p['PAC_sexo'] ?? '');
                            $R['entidad']     = vget($p, 'ENT_descrient', $R['entidad']);
                            $R['afiliado']    = vget($p, 'AFI_nroafiliado');
                            $R['sector']      = vget($p, 'areaint', vget($p, 'sec_descripsec'));
                            $R['diagnostico'] = vget($p, 'PAC_descripdiagn');
                            $sectorInternac   = vget($p, 'PAC_sectorinternac');
                            [$anos, $meses]   = edadAM($p['PAC_fecnacimiento'] ?? null, $p['PAC_fechaadmision'] ?? null);
                        }
                    }
                    $R['edad'] = trim($anos . 'A ' . $meses . 'M');

                    // 4. Parseo de la observacion (Dx / Prof / sector / urgente-programada).
                    if (preg_match('/Dx:\s*(.*?)(?:Obs:|Prof:|$)/is', $mobs, $m)) {
                        $dx = trim($m[1]);
                        if ($dx !== '') {
                            $R['diagnostico'] = $dx;
                        }
                    }
                    $R['urgente']    = stripos($mobs, 'Urgente') !== false;
                    $R['programada'] = stripos($mobs, 'Programada') !== false;

                    // El sector va acotado entre "Se interna en:" y la siguiente
                    // etiqueta (Prof:/Dx:/Obs:/Urgente/Programada), como en el VFP.
                    $careaint = '';
                    if (preg_match('/Se interna en:\s*(.*?)\s*(?:Prof:|Dx:|Obs:|Urgente|Programada|$)/is', $mobs, $m)) {
                        $careaint = trim($m[1]);
                    } elseif (preg_match('/\bA:\s*([^\s,;]{1,10})/i', $mobs, $m)) {
                        $careaint = trim($m[1]);
                    }

                    // Especialidad por edad (VFP: NNT/PED/CM).
                    $espEdad = ($anos === 0 && $meses < 1) ? 'NNT' : ($anos < 16 ? 'PED' : 'CM');
                    $mespec  = '';

                    // 4b. Profesional solicitante.
                    if (preg_match('/Prof:\s*(\d+)/i', $mobs, $m)) {
                        $med = sp_busco_medico_dat($cache, (int) $m[1]);
                        if ($med) {
                            $R['medico']    = vget($med[0], 'nombre');
                            $R['matricula'] = vget($med[0], 'matriculas', vget($med[0], 'matricula'));
                        }
                        $mespec = $espEdad;
                    } else {
                        if (preg_match('/MEDICO:\s*(.*)$/im', $mobs, $m)) {
                            $R['medico'] = trim($m[1]);
                        }
                        if ($sectorInternac !== '') {
                            $careaint = $sectorInternac;   // VFP: careaint = mwklista.PAC_sectorinternac
                        }
                    }

                    // 4c. Sector de internacion via agrupacion (sp_secagrup tipo 4).
                    if ($careaint !== '') {
                        foreach (sp_secagrup($cache, 4) as $s) {
                            if (trim((string) ($s['SectorAgrup'] ?? '')) === $careaint) {
                                $R['sector'] = trim((string) ($s['descripcion'] ?? ''));
                                break;
                            }
                        }
                        if ($R['sector'] === '' && $dgua) {
                            $R['sector'] = $careaint;
                        }
                    }
                    if ($R['sector'] === '') {
                        $R['sector'] = $espEdad;
                    }

                    // 5. Neonatologia (motivo 18).
                    if ($idMotivo === 18) {
                        $mespec = 'NEONATOLOGIA';
                        $rel = sp_busco_pulseras_mmbb_adm($cache, 2, $mprotocolo);
                        if ($rel) {
                            $admOrig = trim((string) ($rel[0]['TRR_ADMORIG'] ?? ''));
                            if ($admOrig !== '') {
                                // Datos de la admision de la madre (disponibles para el reporte NEO).
                                $admMadre = sp_busco_pac_admitidos(
                                    $cache,
                                    " and pac_codadmision = '" . str_replace("'", "''", $admOrig) . "' "
                                );
                                // NOTA: el layout repadm01NEO (madre/bebe) queda pendiente; aqui se
                                // reutiliza repadm01 y la especialidad NEONATOLOGIA.
                            }
                        }
                    }
                    $R['especialidad'] = $mespec;

                    $cache->close();
                }
            }
        }
        $sql->close();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitud de Internacion</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, "Helvetica Neue", sans-serif;
            color: #111;
            margin: 0;
            background: #f3f3f3;
        }

        .hoja {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 12px auto;
            padding: 18mm 16mm;
            box-shadow: 0 0 6px rgba(0, 0, 0, .2);
        }

        .cab {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .cab .logo img {
            max-height: 75px;
            max-width: 320px;
        }

        .cab .titulo {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .cab .sub {
            font-size: 12px;
            color: #555;
        }

        table.campos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        table.campos td {
            padding: 8px 6px;
            vertical-align: bottom;
        }

        .lbl {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: .03em;
            display: block;
        }

        .dato {
            font-size: 14px;
            min-height: 20px;
            border-bottom: 1px solid #999;
            padding-bottom: 2px;
        }

        .seccion {
            margin-top: 22px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            color: #333;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
        }

        .marco {
            margin-top: 16px;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 8px 10px;
        }

        .marco .lbl {
            margin-bottom: 4px;
        }

        .marco .cont {
            font-size: 14px;
            min-height: 38px;
        }

        .tipo {
            margin-top: 10px;
            font-size: 13px;
        }

        .tipo .op {
            display: inline-block;
            margin-right: 28px;
        }

        .box {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1px solid #333;
            vertical-align: -2px;
            margin-right: 6px;
            text-align: center;
            line-height: 13px;
            font-weight: bold;
        }

        .firma {
            margin-top: 70px;
            text-align: center;
        }

        .firma .linea {
            border-top: 1px solid #333;
            width: 60%;
            margin: 0 auto;
            padding-top: 4px;
            font-size: 12px;
            color: #555;
        }

        .aviso {
            background: #fff3cd;
            border: 1px solid #ffe69c;
            color: #664d03;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 14px;
            font-size: 13px;
        }

        .errbox {
            background: #f8d7da;
            border: 1px solid #f1aeb5;
            color: #842029;
            padding: 14px 18px;
            border-radius: 6px;
        }

        .toolbar {
            width: 210mm;
            margin: 12px auto 0;
            text-align: right;
        }

        .toolbar button {
            font-size: 14px;
            padding: 8px 16px;
            cursor: pointer;
            border: 1px solid #0d6efd;
            background: #0d6efd;
            color: #fff;
            border-radius: 6px;
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .hoja {
                box-shadow: none;
                margin: 0;
                width: auto;
                min-height: auto;
                padding: 0;
            }
        }
    </style>
</head>

<body>

    <?php if ($error !== ''): ?>
        <div class="toolbar"><button type="button" onclick="window.close()">Cerrar</button></div>
        <div class="hoja">
            <div class="errbox"><strong>No se pudo generar la solicitud.</strong><br><?= h($error) ?></div>
        </div>
    <?php else: ?>

        <div class="toolbar"><button type="button" onclick="window.print()">Imprimir</button></div>

        <div class="hoja">
            <div class="cab">
                <div class="logo">
                    <img src="../Images/LOGO_HEAD.JPG" alt="Logo">
                </div>
                <div style="text-align:right">
                    <div class="titulo">Solicitud de Internaci&oacute;n</div>
                    <div class="sub">Servicio de emergencias m&eacute;dicas</div>
                    <div style="margin-top:6px">
                        <span class="lbl">Fecha</span>
                        <div class="dato" style="min-width:120px; display:inline-block"><?= h($R['fechaSol']) ?></div>
                    </div>
                </div>
            </div>

            <?php if ($aviso !== ''): ?>
                <div class="aviso" style="margin-top:14px"><?= h($aviso) ?></div>
            <?php endif; ?>

            <table class="campos">
                <tr>
                    <td colspan="2"><span class="lbl">Apellido y Nombre</span>
                        <div class="dato"><?= h($R['nombre']) ?></div>
                    </td>
                </tr>
                <tr>
                    <td style="width:60%"><span class="lbl">Entidad</span>
                        <div class="dato"><?= h($R['entidad']) ?></div>
                    </td>
                    <td><span class="lbl">Nro. beneficiario</span>
                        <div class="dato"><?= h($R['afiliado']) ?></div>
                    </td>
                </tr>
                <tr>
                    <td><span class="lbl">Sexo</span>
                        <div class="dato"><?= h($R['sexo']) ?></div>
                    </td>
                    <td><span class="lbl">Edad</span>
                        <div class="dato"><?= h($R['edad']) ?></div>
                    </td>
                </tr>
            </table>

            <div class="marco">
                <span class="lbl">Diagn&oacute;stico presuntivo</span>
                <div class="cont"><?= h($R['diagnostico']) ?></div>
            </div>

            <table class="campos">
                <tr>
                    <td colspan="2"><span class="lbl">Sector de internaci&oacute;n</span>
                        <div class="dato"><?= h($R['sector']) ?></div>
                    </td>
                </tr>
            </table>

            <div class="seccion">Profesional Solicitante</div>
            <table class="campos">
                <tr>
                    <td style="width:60%"><span class="lbl">M&eacute;dico solicitante</span>
                        <div class="dato"><?= h($R['medico']) ?></div>
                    </td>
                    <td><span class="lbl">Matr&iacute;cula</span>
                        <div class="dato"><?= h($R['matricula']) ?></div>
                    </td>
                </tr>
                <tr>
                    <td><span class="lbl">Especialidad</span>
                        <div class="dato"><?= h($R['especialidad']) ?></div>
                    </td>
                    <td></td>
                </tr>
            </table>

            <!-- <div class="seccion">Diagn&oacute;stico presuntivo</div>
        <table class="campos">
            <tr><td colspan="2"><div class="dato" style="min-height:40px"><?= h($R['diagnostico']) ?></div></td></tr>
        </table> -->

            <div class="tipo">
                <strong>Internaci&oacute;n:</strong>
                <span class="op"><span class="box"><?= $R['urgente'] ? 'X' : '' ?></span>Urgente</span>
                <span class="op"><span class="box"><?= $R['programada'] ? 'X' : '' ?></span>Programada</span>
            </div>

            <div class="firma">
                <div class="linea">Profesional Solicitante</div>
                <div class="dato" style="border-bottom:none"><?= h($R['medico']) ?></div>
                <div class="dato" style="border-bottom:none">Matricula : <?= h($R['matricula']) ?></div>
            </div>

        </div>

    <?php endif; ?>

</body>

</html>