<?php

/**
 * DepuracionController
 *
 * Dispara el proceso de depuracion de socios (Php/sp_depuro_socios.php), que
 * archiva en sqluser.Sociohis y elimina de sqluser.Socio los registros
 * anteriores a la fecha de corte.
 *
 * Acciones:
 *   - ejecutar <- card "Depuracion" del menu (index.php)
 *
 * Endpoint protegido: requiere sesion iniciada (../usuario/login.php).
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/sp_depuro_socios.php';

class DepuracionController extends BaseController
{
    public function __construct()
    {
        // Sesion del lanzador clasico (nombre dinamico $lses).
        mesa_session_start();
        // Para un endpoint JSON no redirigimos: devolvemos 401.
        if (mesa_session_user() === '') {
            $this->json(['ok' => false, 'msg' => 'Sesion no iniciada.'], 401);
        }
    }

    /**
     * Ejecuta la depuracion.
     * VFP: prg_depuro_socios (retraso por defecto = 2).
     */
    public function ejecutar(): void
    {
        $retraso = (int) $this->param('retraso', 2);

        try {
            $res = sp_depuro_socios(null, $retraso);
            $this->json($res);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }
}

// Punto de entrada para peticiones AJAX directas.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    (new DepuracionController())->handle();
}
