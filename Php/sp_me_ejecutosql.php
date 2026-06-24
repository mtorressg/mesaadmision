<?php
/**
 * sp_me_ejecutosql.php
 *
 * Servicio refactorizado de prg/sp_me_ejecutosql.prg
 * Segun el formulario, devuelve motivos y/o la lista de personas (sin atender o
 * atendidas).
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTAS:
 *  - Se elimina log_errores (error -> excepcion).
 *  - Se elimina prg_dtoc -> date('Y-m-d').
 *  - $mTIPOPAC fijo en 'INT' como en el .prg. Tablas calificadas con sqluser.
 *
 * @return array [
 *     'motivos'   => filas (solo si $formulario == 0),
 *     'llegadas'  => filas sin atender (si $formulario 0 o 1),
 *     'atendidos' => filas atendidas (si $formulario distinto de 0 y 1)
 * ]
 */
require_once __DIR__ . '/../../dbconexion/db.php';
require_once __DIR__ . '/sp_busco_fecha_serv.php';

function sp_me_ejecutosql($db, int $formulario = 0, $fecha1 = null, $fecha2 = null): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        if (empty($fecha1)) {
            $fecha1 = sp_busco_fecha_serv($db, 'DD');
            $fecha2 = $fecha1;
        }
        if (empty($fecha2)) {
            $fecha2 = $fecha1;
        }
        $mf1 = (new DateTime($fecha1))->format('Y-m-d');
        $mf2 = (new DateTime($fecha2))->modify('+1 day')->format('Y-m-d');

        $mTIPOPAC = "'INT'";

        $run = function (string $sql) use ($db): array {
            $res = $db->query($sql);
            if ($res === false) {
                throw new RuntimeException('ERROR en la consulta (sp_me_ejecutosql).');
            }
            $out = [];
            while ($row = $db->fetch_assoc($res)) {
                $out[] = $row;
            }
            return $out;
        };

        $resultado = [];

        // Motivos
        if ($formulario === 0) {
            $resultado['motivos'] = $run("Select motivotext, idmotivo from sqluser.motivos order by motivotext");
        }

        if ($formulario === 1 || $formulario === 0) {
            // Sin atender
            $resultado['llegadas'] = $run(
                "SELECT SOCIO.HoraLLegada, MOTIVOS.MotivoText,
                        SOCIO.ApellidoNombre, SOCIO.Observacion,
                        SOCIO.HoraAtencion, ObservaA, Horafinalizacion,
                        paciente, MOTIVOS.MotivoText, operadora, OperadoraA,
                        puestoatencion, SOCIO.IdSocio, MOTIVOS.IdMotivo, SOCIO.PrioridadAt,
                        ENT_DESCRIENT, entidexclu.fecpasiva as fecpasiva_Excl
                   FROM sqluser.SOCIO
                   inner JOIN sqluser.MOTIVOS ON SOCIO.IdMotivo = MOTIVOS.IdMotivo
                   LEFT JOIN sqluser.ENTIDADES ON SOCIO.codentidad = ENTIDADES.ENT_codent
                   LEFT JOIN sqluser.entidexclu On SOCIO.codentidad = entidexclu.codent And tpopac={$mTIPOPAC}
                  WHERE SOCIO.HoraAtencion is Null AND SOCIO.Atendido=0
                  ORDER BY SOCIO.HoraLLegada"
            );
        } else {
            // Atendidos
            $limiteDia = null;
            $res = $db->query("SELECT MIN(HoraLLegada) as minll FROM sqluser.SOCIO where Atendido = 1");
            if ($res !== false && ($r = $db->fetch_assoc($res)) && !empty($r['minll'])) {
                $limiteDia = (new DateTime($r['minll']))->format('Y-m-d');
            } else {
                $limiteDia = sp_busco_fecha_serv($db, 'DD');
            }

            if ($fecha1 < $fecha2) {
                $vbusco = " AND SOCIO.HoraAtencion between '{$mf1}' and '{$mf2}' ";
            } else {
                $f1 = (new DateTime($fecha1))->format('Y-m-d');
                $f2 = (new DateTime($fecha1))->modify('+1 day')->format('Y-m-d');
                $vbusco = " AND SOCIO.HoraAtencion >= '{$f1}' and SOCIO.HoraAtencion < '{$f2}' ";
            }

            $cols = "SOCIO.HoraLLegada, MOTIVOS.MotivoText,
                     SOCIO.ApellidoNombre, SOCIO.Observacion,
                     SOCIO.HoraAtencion, ObservaA, Horafinalizacion,
                     A.MotivoText, operadora, OperadoraA,
                     puestoatencion, SOCIO.IdSocio,
                     MOTIVOS.IdMotivo, SOCIO.IdMotivoA, paciente, SOCIO.PrioridadAt,
                     ENT_DESCRIENT, entidexclu.fecpasiva as fecpasiva_Excl";

            $atend = $run(
                "SELECT {$cols}
                   FROM sqluser.SOCIO
                   inner JOIN sqluser.MOTIVOS ON SOCIO.IdMotivo = MOTIVOS.IdMotivo
                   inner JOIN sqluser.MOTIVOS as A ON SOCIO.IdMotivoA = A.IdMotivo
                   LEFT JOIN sqluser.ENTIDADES ON SOCIO.codentidad = ENTIDADES.ENT_codent
                   LEFT JOIN sqluser.entidexclu On SOCIO.codentidad = entidexclu.codent And tpopac={$mTIPOPAC}
                  WHERE SOCIO.Atendido = 1 " . $vbusco . " ORDER BY SOCIO.HoraLLegada"
            );

            if ($fecha1 < $limiteDia) {
                $atend = array_merge($atend, $run(
                    "SELECT {$cols}
                       FROM sqluser.SOCIOHIS as SOCIO
                       inner JOIN sqluser.MOTIVOS ON SOCIO.IdMotivo = MOTIVOS.IdMotivo
                       inner JOIN sqluser.MOTIVOS as A ON SOCIO.IdMotivoA = A.IdMotivo
                       LEFT JOIN sqluser.ENTIDADES ON SOCIO.codentidad = ENTIDADES.ENT_codent
                       LEFT JOIN sqluser.entidexclu On SOCIO.codentidad = entidexclu.codent And tpopac={$mTIPOPAC}
                      WHERE SOCIO.Atendido = 1 " . $vbusco . " ORDER BY SOCIO.HoraLLegada"
                ));
            }
            $resultado['atendidos'] = $atend;
        }

        return $resultado;
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
