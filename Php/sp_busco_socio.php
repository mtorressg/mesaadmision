<?php

/**
 * sp_busco_socio.php
 *
 * Servicio refactorizado de prg/sp_busco_socio.prg
 * Busca socios/pacientes segun la opcion (sin atender, atendidos, por criterio).
 *
 * Conexion a SQL Server via la clase dbsqlserver (db.php).
 *
 * NOTAS:
 *  - Se elimina Prg_EjecutoSql -> consulta directa.
 *  - Se elimina prg_dtoc -> date('Y-m-d').
 *  - $tcWhere es un fragmento SQL armado por el llamador (se concatena tal cual).
 *  - $mTIPOPAC es un unico tipo de paciente (default 'INT').
 *  - Todas las tablas calificadas con sqluser.
 */
require_once __DIR__ . '/../../dbconexion/db.php';
require_once __DIR__ . '/sp_busco_fecha_serv.php';

/**
 * @param dbsqlserver|null $db
 * @param int    $tnOpcion 0/1 = sin atender ; 2 = atendidos ; 3 = por criterio (cama) ; 4 = por criterio.
 * @param string $tcWhere  Fragmento SQL adicional.
 * @param string|null $fecha1 'Y-m-d' (default: fecha de servidor).
 * @param string|null $fecha2 'Y-m-d' (default: = fecha1).
 * @param string $mTIPOPAC Tipo de paciente (default 'INT').
 * @return array Filas resultantes.
 * @throws RuntimeException si falla la consulta.
 */
function sp_busco_socio($db, int $tnOpcion = 0, string $tcWhere = '', $fecha1 = null, $fecha2 = null, string $mTIPOPAC = 'INT'): array
{
    $cerrar = false;
    if ($db === null) {
        $db = new dbsqlserver();
        $cerrar = true;
    }

    try {
        // Fechas por defecto = fecha del servidor.
        if (empty($fecha1)) {
            $fecha1 = sp_busco_fecha_serv($db, 'DD');
            $fecha2 = $fecha1;
        }
        if (empty($fecha2)) {
            $fecha2 = $fecha1;
        }
        $mf1 = (new DateTime($fecha1))->format('Y-m-d');
        $mf2 = (new DateTime($fecha2))->modify('+1 day')->format('Y-m-d');

        $tipo = "'" . str_replace("'", '', $mTIPOPAC) . "'"; // 'INT'

        $run = function (string $sql) use ($db): array {
            $res = $db->query($sql);
            if ($res === false) {
                throw new RuntimeException('ERROR en la consulta de socios.');
            }
            $out = [];
            while ($row = $db->fetch_assoc($res)) {
                $out[] = $row;
            }
            return $out;
        };

        if ($tnOpcion < 2) {
            // Sin atender
            $sql = "SELECT SOCIO.HoraLLegada, MOTIVOS.MotivoText,
                           SOCIO.ApellidoNombre, SOCIO.Observacion,
                           SOCIO.HoraAtencion, ObservaA, Horafinalizacion,
                           paciente, MOTIVOS.MotivoText as MotivoText1 , operadora, OperadoraA,
                           puestoatencion, SOCIO.IdSocio, MOTIVOS.IdMotivo, SOCIO.PrioridadAt,
                           ENT_DESCRIENT, entidexclu.fecpasiva as fecpasiva_Excl, codentidad
                      FROM sqluser.SOCIO
                      inner JOIN sqluser.MOTIVOS ON SOCIO.IdMotivo = MOTIVOS.IdMotivo
                      LEFT JOIN sqluser.ENTIDADES ON SOCIO.codentidad = ENTIDADES.ENT_codent
                      LEFT JOIN sqluser.entidexclu On SOCIO.codentidad = entidexclu.codent And tpopac in ({$tipo})
                     WHERE SOCIO.HoraAtencion is Null AND SOCIO.Atendido=0
                     ORDER BY SOCIO.HoraLLegada";

            // Devuelve las filas crudas (equivale al cursor V_Cursor/mwkLLegadas de FoxPro).
            // El particionado por solapa (sala/hotel/rn) lo hace el controlador con cargogrid_*.
            return $run($sql);
        }


        if ($tnOpcion == 2) {
            // Atendidos: limiteDia = min(HoraLLegada) de los atendidos.
            $limiteDia = null;
            $res = $db->query("SELECT MIN(HoraLLegada) as minll FROM sqluser.SOCIO where Atendido = 1");
            if ($res !== false && ($r = $db->fetch_assoc($res)) && !empty($r['minll'])) {
                $limiteDia = (new DateTime($r['minll']))->format('Y-m-d');
            } else {
                $limiteDia = sp_busco_fecha_serv($db, 'DD');
            }

            if ($fecha1 < $fecha2) {
                $tcWhere .= " AND SOCIO.HoraAtencion between '{$mf1}' and '{$mf2}' ";
            } else {
                $f1 = (new DateTime($fecha1))->format('Y-m-d');
                $f2 = (new DateTime($fecha1))->modify('+1 day')->format('Y-m-d');
                $tcWhere .= " AND SOCIO.HoraAtencion >= '{$f1}' and SOCIO.HoraAtencion < '{$f2}' ";
            }

            $colsA = "SOCIO.HoraLLegada, MOTIVOS.MotivoText,
                      SOCIO.ApellidoNombre, SOCIO.Observacion,
                      SOCIO.HoraAtencion, ObservaA, Horafinalizacion,
                      A.MotivoText as MotivoText1, operadora, OperadoraA,
                      puestoatencion, SOCIO.IdSocio,
                      MOTIVOS.IdMotivo, SOCIO.IdMotivoA, paciente, SOCIO.PrioridadAt,
                      ENT_DESCRIENT, entidexclu.fecpasiva as fecpasiva_Excl, codentidad";

            $sqlA = "SELECT {$colsA}
                       FROM sqluser.SOCIO
                       inner JOIN sqluser.MOTIVOS ON SOCIO.IdMotivo = MOTIVOS.IdMotivo
                       inner JOIN sqluser.MOTIVOS as A ON SOCIO.IdMotivoA = A.IdMotivo
                       LEFT JOIN sqluser.ENTIDADES ON SOCIO.codentidad = ENTIDADES.ENT_codent
                       LEFT JOIN sqluser.entidexclu On SOCIO.codentidad = entidexclu.codent And tpopac={$tipo}
                      WHERE SOCIO.Atendido = 1 " . $tcWhere .
                " ORDER BY SOCIO.HoraLLegada";
            $rows = $run($sqlA);

            if ($fecha1 < $limiteDia) {
                $sqlH = "SELECT {$colsA}
                           FROM sqluser.SOCIOHIS as SOCIO
                           inner JOIN sqluser.MOTIVOS ON SOCIO.IdMotivo = MOTIVOS.IdMotivo
                           inner JOIN sqluser.MOTIVOS as A ON SOCIO.IdMotivoA = A.IdMotivo
                           LEFT JOIN sqluser.ENTIDADES ON SOCIO.codentidad = ENTIDADES.ENT_codent
                           LEFT JOIN sqluser.entidexclu On SOCIO.codentidad = entidexclu.codent And tpopac={$tipo}
                          WHERE SOCIO.Atendido = 1 " . $tcWhere .
                    " ORDER BY SOCIO.HoraLLegada";
                $rowsH = $run($sqlH);

                $rows = array_merge($rows, $rowsH);
            }



            return $rows;
        }

        if ($tnOpcion == 3) {
            // Busqueda de protocolo en solic. cambio de cama
            $sql = "SELECT HoraLLegada, ApellidoNombre, Observacion,
                           HoraAtencion, Horafinalizacion,
                           paciente, operadora, OperadoraA,
                           puestoatencion, Atendido, ENT_DESCRIENT as entidad,
                           IdSocio, PrioridadAt, codentidad, ObservaA
                      FROM sqluser.SOCIO
                      LEFT JOIN sqluser.ENTIDADES ON SOCIO.codentidad = ENTIDADES.ENT_codent " . $tcWhere .
                " ORDER BY SOCIO.HoraLLegada";
            return $run($sql);
        }

        if ($tnOpcion == 4) {
            // Busqueda por criterio
            $sql = "SELECT SOCIO.HoraLLegada, MOTIVOS.MotivoText,
                           SOCIO.ApellidoNombre, SOCIO.Observacion,
                           SOCIO.HoraAtencion, ObservaA, Horafinalizacion,
                           paciente, MOTIVOS.MotivoText, operadora, OperadoraA,
                           puestoatencion, SOCIO.IdSocio, MOTIVOS.IdMotivo, SOCIO.PrioridadAt,
                           ENT_DESCRIENT, entidexclu.fecpasiva as fecpasiva_Excl, codentidad,
                           CAST(HoraLLegada as date) as dia
                      FROM sqluser.SOCIO
                      inner JOIN sqluser.MOTIVOS ON SOCIO.IdMotivo = MOTIVOS.IdMotivo
                      LEFT JOIN sqluser.ENTIDADES ON SOCIO.codentidad = ENTIDADES.ENT_codent
                      LEFT JOIN sqluser.entidexclu On SOCIO.codentidad = entidexclu.codent And tpopac in ({$tipo}) "
                . $tcWhere .
                " ORDER BY SOCIO.HoraLLegada DESC";
            return $run($sql);
        }

        return [];
    } finally {
        if ($cerrar && $db instanceof dbsqlserver) {
            $db->close();
        }
    }
}
