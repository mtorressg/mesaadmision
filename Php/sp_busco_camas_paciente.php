<?php
/**
 * sp_busco_camas_paciente.php
 *
 * Servicio refactorizado de prg/sp_busco_camas_paciente.prg
 * Devuelve todas las camas por las que paso el paciente (lugarintern) cruzadas
 * con el usuario que realizo el cambio de cama (tabverC, prg=20).
 *
 * Origen VFP: 2 consultas (lugarintern y tabverC) + un join entre cursores
 * locales por fecha/habitacion/cama/hora. Ese join se reimplementa en PHP.
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 */
require_once __DIR__ . '/../../dbconexion/db.php';

/**
 * @param dbsqlserver|null $db
 * @param string|int $mcodadm Codigo de admision (lug_pacientes / codadmision).
 * @return array Filas: lug_fechaingreso, hora, lug_categoria, lug_codsector,
 *               lug_habitacion, lug_cama, usuario.
 * @throws RuntimeException si falla la consulta.
 */
function sp_busco_camas_paciente($db, $mcodadm): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        // mwkcamas: camas del paciente (lugarintern)
        $stmt = $db->prepare("select *, DATEPART(hour, lug_horaingreso) as horaingreso
                                from sqluser.lugarintern
                               where lug_pacientes = ?
                               order by lug_fechaingreso");
        if (!$stmt || $db->execute($stmt, [$mcodadm]) === false) {
            throw new RuntimeException('ERROR al leer lugarintern.');
        }
        $camas = [];
        while ($row = $db->fetch_assoc($stmt)) {
            $camas[] = $row;
        }

        // mwkverc: cambios de cama registrados (tabverC, prg = 20)
        $stmt = $db->prepare("select * from sqluser.tabverC where codadmision = ? and prg = 20");
        if (!$stmt || $db->execute($stmt, [$mcodadm]) === false) {
            throw new RuntimeException('ERROR al leer tabverC.');
        }
        $verc = [];
        while ($row = $db->fetch_assoc($stmt)) {
            $hc = trim((string) ($row['habcama'] ?? ''));
            $fecha = !empty($row['fecha']) ? new DateTime($row['fecha']) : null;
            $verc[] = [
                'usuario'   => $row['usuario'] ?? null,
                'fechacbio' => $fecha ? $fecha->format('Y-m-d') : null,
                'horacbio'  => $fecha ? (int) $fecha->format('G') : null,
                'hab'       => trim(substr($hc, 0, max(0, strlen($hc) - 2))),
                'cama'      => (int) substr($hc, -2),
            ];
        }

        // left join mwkcamas con mwkvercama (en PHP).
        $out = [];
        foreach ($camas as $c) {
            $usuario   = null;
            $fIngreso  = !empty($c['lug_fechaingreso']) ? (new DateTime($c['lug_fechaingreso']))->format('Y-m-d') : null;
            $habIng    = trim((string) ($c['lug_habitacion'] ?? ''));
            $camaIng   = (int) ($c['lug_cama'] ?? 0);
            $horaIng   = isset($c['horaingreso']) ? (int) $c['horaingreso'] : null;

            foreach ($verc as $v) {
                if ($v['fechacbio'] === $fIngreso
                    && $v['hab'] === $habIng
                    && $v['cama'] === $camaIng
                    && $horaIng !== null && $v['horacbio'] !== null
                    && $horaIng >= ($v['horacbio'] - 1) && $horaIng <= ($v['horacbio'] + 1)) {
                    $usuario = $v['usuario'];
                    break;
                }
            }

            // hora = HH:MM de lug_horaingreso
            $hora = '';
            if (!empty($c['lug_horaingreso'])) {
                $hora = (new DateTime($c['lug_horaingreso']))->format('H:i');
            }

            $out[] = [
                'lug_fechaingreso' => $c['lug_fechaingreso'] ?? null,
                'hora'             => $hora,
                'lug_categoria'    => $c['lug_categoria'] ?? null,
                'lug_codsector'    => $c['lug_codsector'] ?? null,
                'lug_habitacion'   => $c['lug_habitacion'] ?? null,
                'lug_cama'         => $c['lug_cama'] ?? null,
                'usuario'          => $usuario !== null ? $usuario : str_repeat(' ', 20),
            ];
        }

        return $out;
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
