<?php
/**
 * repcamvac.php - Listado de Camas Vacias
 *
 * Refactor del reporte VFP rep/repcamvac.frx + del evento cmdvercamas.Click de
 * scx/frmmesa2, como vista HTML imprimible (boton Imprimir / Ctrl+P).
 *
 * Logica (equivalente a cmdvercamas):
 *   - Recorre los sectores de internacion (sp_sectorint).
 *   - Por cada sector trae sus habitaciones/camas (sp_busco_hab_me).
 *   - Arma el listado de camas VACIAS: una habitacion tiene 2 camas (1/2); se
 *     toma la cama cuyo "Ent" esta vacio, salvo que este Indistinta/Aislamiento
 *     (Est = IND/AISL) o Bloqueada/Reservada (Est = BLQ/RES).
 *     El sexo permitido de la cama vacia se toma del ocupante de la otra cama
 *     (cama 1 -> Sex2 ; cama 2 -> Sex1), tal como el VFP.
 *
 * Conexion: SQL Server (dbsqlserver), igual que sp_sectorint / sp_busco_hab_me.
 */
require_once __DIR__ . '/../../dbconexion/db.php';
require_once __DIR__ . '/sp_sectorint.php';
require_once __DIR__ . '/sp_busco_hab_me.php';

/** Texto SQL Server (Windows-1252) -> UTF-8 + escape HTML. */
function h($v): string
{
    $s = (string) $v;
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    }
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$listado = [];
$error   = '';

try {
    $db = new dbsqlserver();

    // Sectores de internacion (sp_sectorint sin parametro -> sec_internacion = 1).
    $sectores = sp_sectorint($db);

    foreach ($sectores as $sec) {
        $codsec    = trim((string) ($sec['sec_codsector'] ?? ''));
        $habitsala = trim((string) ($sec['sec_habitsala'] ?? ''));
        $descripse = trim((string) ($sec['sec_descripsec'] ?? ''));
        if ($codsec === '') {
            continue;
        }

        $camas = sp_busco_hab_me($db, $codsec, $habitsala);

        foreach ($camas as $c) {
            $est1 = rtrim((string) ($c['est1'] ?? ''));
            $est2 = rtrim((string) ($c['est2'] ?? ''));

            // Scan filter VFP: Est1 <> 'IND' and Est1 <> 'AISL' and (Empty(Ent1) or Empty(Ent2))
            if ($est1 === 'IND' || $est1 === 'AISL') {
                continue;
            }
            $ent1 = trim((string) ($c['ent1'] ?? ''));
            $ent2 = trim((string) ($c['ent2'] ?? ''));
            if ($ent1 !== '' && $ent2 !== '') {
                continue;
            }

            $hab   = trim((string) ($c['hab1'] ?? ''));
            $mEst1 = !($est1 === 'BLQ' || $est1 === 'RES');
            $mEst2 = !($est2 === 'BLQ' || $est2 === 'RES');

            // Cama 1 vacia.
            if ($ent1 === '') {
                $cam1 = trim((string) ($c['cam1'] ?? ''));
                if ($cam1 !== '' && $mEst1) {
                    $listado[] = ['sect' => $descripse, 'hab' => $hab, 'cam' => $cam1, 'sex' => trim((string) ($c['sex2'] ?? ''))];
                }
            }
            // Cama 2 vacia.
            if ($ent2 === '') {
                $cam2 = trim((string) ($c['cam2'] ?? ''));
                if ($cam2 !== '' && $mEst2) {
                    $listado[] = ['sect' => $descripse, 'hab' => $hab, 'cam' => $cam2, 'sex' => trim((string) ($c['sex1'] ?? ''))];
                }
            }
        }
    }

    $db->close();
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Listado de Camas Vacias</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, "Helvetica Neue", sans-serif; color: #111; margin: 0; background: #f3f3f3; }
        .hoja { background: #fff; width: 210mm; min-height: 297mm; margin: 12px auto; padding: 18mm 16mm; box-shadow: 0 0 6px rgba(0,0,0,.2); }
        .cab { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .cab .logo img { max-height: 75px; max-width: 320px; }
        .cab .titulo { font-size: 20px; font-weight: bold; text-transform: uppercase; }
        .cab .sub { font-size: 12px; color: #555; }
        table.lista { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 13px; }
        table.lista th, table.lista td { border: 1px solid #999; padding: 5px 8px; text-align: left; }
        table.lista th { background: #eee; text-transform: uppercase; font-size: 11px; }
        table.lista td.c { text-align: center; }
        table.lista tr.sector td { background: #d9e6f2; font-weight: bold; text-transform: uppercase; font-size: 12px; }
        .total { margin-top: 12px; font-size: 12px; color: #555; }
        .aviso { background: #fff3cd; border: 1px solid #ffe69c; color: #664d03; padding: 12px 16px; border-radius: 6px; font-size: 14px; margin-top: 16px; }
        .errbox { background: #f8d7da; border: 1px solid #f1aeb5; color: #842029; padding: 14px 18px; border-radius: 6px; }
        .toolbar { width: 210mm; margin: 12px auto 0; text-align: right; }
        .toolbar button { font-size: 14px; padding: 8px 16px; cursor: pointer; border: 1px solid #0d6efd; background: #0d6efd; color: #fff; border-radius: 6px; }
        @media print { body { background: #fff; } .toolbar { display: none; } .hoja { box-shadow: none; margin: 0; width: auto; min-height: auto; padding: 0; } }
    </style>
</head>
<body>

<?php if ($error !== ''): ?>
    <div class="toolbar"><button type="button" onclick="window.close()">Cerrar</button></div>
    <div class="hoja"><div class="errbox"><strong>No se pudo generar el listado.</strong><br><?= h($error) ?></div></div>
<?php else: ?>

    <div class="toolbar"><button type="button" onclick="window.print()">Imprimir</button></div>

    <div class="hoja">
        <div class="cab">
            <div class="logo"><img src="../Images/LOGO_HEAD.JPG" alt="Logo"></div>
            <div style="text-align:right">
                <div class="titulo">Listado de Camas Vac&iacute;as</div>
                <div class="sub">Camas disponibles por sector</div>
            </div>
        </div>

        <?php if (!$listado): ?>
            <div class="aviso">NO HAY CAMAS DISPONIBLES.</div>
        <?php else: ?>
            <?php
            // Agrupar las camas vacias por sector (conservando el orden de aparicion).
            $porSector = [];
            foreach ($listado as $r) {
                $porSector[$r['sect']][] = $r;
            }
            ?>
            <table class="lista">
                <thead>
                    <tr>
                        <th style="width:25%">Habitaci&oacute;n</th>
                        <th style="width:25%">Cama</th>
                        <th style="width:50%">Sexo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($porSector as $sector => $camas): ?>
                        <tr class="sector"><td colspan="3"><?= h($sector) ?> (<?= count($camas) ?>)</td></tr>
                        <?php foreach ($camas as $r): ?>
                            <tr>
                                <td class="c"><?= h($r['hab']) ?></td>
                                <td class="c"><?= h($r['cam']) ?></td>
                                <td class="c"><?= h($r['sex']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="total">Total de camas vac&iacute;as: <?= count($listado) ?></div>
        <?php endif; ?>
    </div>

<?php endif; ?>

</body>
</html>
