<?php
/**
 * sp_busco_hab_me.php
 *
 * Servicio refactorizado de prg/sp_busco_hab_me.prg
 * Devuelve las camas de las habitaciones de un sector, armadas de a dos camas
 * por habitacion (presentacion para la grilla de camas).
 *
 * Origen VFP: 1 consulta a SQL Server (habitacions + pacientes + pacinternad +
 * TabHabcolor) y luego varias transformaciones locales (iif/group by/join entre
 * cursores) que aqui se reimplementan en PHP.
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTA: por la cantidad de transformaciones de presentacion del .prg, esta
 * traduccion deberia validarse contra datos reales. La rama 'H' (habitsala)
 * arma cama '01' y '02'; el resto arma la primera cama y la de codigo vacio.
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param string $mcodsec    Sector (hab_sectores).
 * @param string $mhabitsala 'H' = habitacion/sala (cama 01 y 02).
 * @return array Filas de presentacion (hab1, cam1, est1, ent1, nom1, sex1, eda1,
 *               cam2, est2, ent2, nom2, sex2, eda2, dia1, dia2, cama_esp_1, cama_esp_2).
 * @throws RuntimeException si falla la consulta.
 */
function sp_busco_hab_me($db, $mcodsec, string $mhabitsala = 'H'): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        // mwkcama1: habitacions + pacientes + pacinternad + TabHabcolor (THC_Cor)
        $sql = "select h.hab_codhabitacion, h.hab_codcama, h.hab_codpaciente, h.hab_codbloqueo,
                       h.hab_habilitada, p.pac_nombrepaciente, p.pac_sexo, p.pac_edad,
                       p.pac_descripdiagn, p.pac_categoria, pin.pin_codentidad, c.THC_Cor
                  from sqluser.habitacions h
                  left outer join sqluser.pacientes p on h.hab_codpaciente = p.pac_codadmision
                  left outer join sqluser.pacinternad pin on pin.pin_codadmision = p.pac_codadmision
                  left join sqluser.TabHabcolor c on h.hab_codhabitacion = c.THC_Hab and h.hab_codcama = c.THC_Cama
                 where h.hab_sectores = ?
                 order by h.hab_codhabitacion, h.hab_codcama";
        $stmt = $db->prepare($sql);
        if (!$stmt || $db->execute($stmt, [$mcodsec]) === false) {
            throw new RuntimeException('ERROR al leer habitacions.');
        }

        // Agrupar camas por habitacion.
        $habs = [];
        $orden = [];
        while ($row = $db->fetch_assoc($stmt)) {
            $h = trim((string) ($row['hab_codhabitacion'] ?? ''));
            if (!isset($habs[$h])) {
                $habs[$h] = [];
                $orden[] = $h;
            }
            $habs[$h][] = $row;
        }

        $esH = (strtoupper(trim($mhabitsala)) === 'H');
        $out = [];

        foreach ($orden as $h) {
            $camas = $habs[$h];

            if ($esH) {
                $slot1 = sp_hab_buscar_cama($camas, '01');
                $slot2 = sp_hab_buscar_cama($camas, '02');
            } else {
                $slot1 = $camas[0] ?? null;
                $slot2 = sp_hab_buscar_cama($camas, '');
            }

            $b1 = sp_hab_presenta($slot1, true);
            $b2 = sp_hab_presenta($slot2, false);

            $out[] = [
                'hab1'       => $h,
                'cam1'       => $b1['cam'],
                'est1'       => $b1['est'],
                'ent1'       => $b1['ent'],
                'nom1'       => $b1['nom'],
                'sex1'       => $b1['sex'],
                'eda1'       => $b1['eda'],
                'dia1'       => $b1['dia'],
                'cama_esp_1' => $b1['cama_esp'],
                'cam2'       => $b2['cam'],
                'est2'       => $b2['est'],
                'ent2'       => $b2['ent'],
                'nom2'       => $b2['nom'],
                'sex2'       => $b2['sex'],
                'eda2'       => $b2['eda'],
                'dia2'       => $b2['dia'],
                'cama_esp_2' => $b2['cama_esp'],
            ];
        }

        return $out;
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}

/** Busca dentro de la habitacion la cama con el codigo dado ('' = vacia). */
function sp_hab_buscar_cama(array $camas, string $cod)
{
    foreach ($camas as $c) {
        if (trim((string) ($c['hab_codcama'] ?? '')) === $cod) {
            return $c;
        }
    }
    return null;
}

/**
 * Calcula los campos de presentacion de una cama (equivale a los iif del .prg).
 * @param array|null $c       Fila de la cama (o null si no hay).
 * @param bool       $conAisl true para la cama 1 (incluye AISL/IND).
 */
function sp_hab_presenta($c, bool $conAisl): array
{
    if ($c === null) {
        return [
            'cam' => '  ', 'est' => '    ', 'ent' => '     ',
            'nom' => str_repeat(' ', 40), 'sex' => ' ', 'eda' => '   ',
            'dia' => null, 'cama_esp' => 0,
        ];
    }

    $codp = trim((string) ($c['hab_codpaciente'] ?? ''));
    $pac  = trim((string) ($c['pac_categoria'] ?? ''));
    $ent  = $c['pin_codentidad'] ?? null;
    $nom  = $c['pac_nombrepaciente'] ?? null;
    $sex  = $c['pac_sexo'] ?? null;
    $eda  = $c['pac_edad'] ?? null;

    // est
    if ($conAisl && $pac === 'A') {
        $est = 'AISL';
    } elseif ($conAisl && $pac === 'I') {
        $est = 'IND ';
    } elseif ($codp === 'BLOQUEO') {
        $est = 'BLQ ';
    } elseif ($codp === 'RESERV') {
        $est = 'RES ';
    } else {
        $est = '    ';
    }

    // ent: '@z 99999' -> en blanco si 0/nulo
    if ($ent === null || (int) $ent === 0) {
        $entOut = '     ';
    } else {
        $entOut = str_pad((string) (int) $ent, 5, ' ', STR_PAD_LEFT);
    }

    // eda: si codp no es numerico (val=0) o edad nula -> en blanco
    $codpNum = is_numeric($codp) ? (int) $codp : 0;
    if ($codpNum === 0 || $eda === null || $eda === '') {
        $edaOut = '   ';
    } else {
        $edaOut = str_pad((string) (int) $eda, 3, ' ', STR_PAD_LEFT);
    }

    return [
        'cam'      => trim((string) ($c['hab_codcama'] ?? '')) !== '' ? $c['hab_codcama'] : '  ',
        'est'      => $est,
        'ent'      => $entOut,
        'nom'      => ($nom === null || $nom === '') ? str_repeat(' ', 40) : $nom,
        'sex'      => ($sex === null || $sex === '') ? ' ' : $sex,
        'eda'      => $edaOut,
        'dia'      => $c['pac_descripdiagn'] ?? null,
        'cama_esp' => isset($c['THC_Cor']) && $c['THC_Cor'] !== null ? (int) $c['THC_Cor'] : 0,
    ];
}
