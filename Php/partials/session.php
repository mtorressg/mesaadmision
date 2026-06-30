<?php

/**
 * session.php - Bootstrap de sesion del lanzador clasico.
 *
 * val_usuario.php crea la sesion con un nombre DINAMICO ($lses = md5(...)),
 * guarda los datos del usuario ANIDADOS bajo $_SESSION[$lses][...] y lanza la
 * app con index.php?lses=<md5>.
 *
 * Este helper centraliza, para que auth.php y los controladores AJAX compartan
 * la MISMA logica:
 *   - resolver $lses (de la URL/request ?lses=, o de la cookie mi_lses),
 *   - iniciar la sesion con ese nombre,
 *   - exponer el usuario logueado ($_SESSION[$lses]['idusuario']).
 *
 * Debe usarse ANTES de cualquier salida y antes de cualquier session_start().
 */

if (!function_exists('mesa_session_start')) {

    /** Resuelve el nombre de sesion dinamico $lses (cacheado por request). */
    function mesa_session_lses(): string
    {
        static $lses = null;
        if ($lses !== null) {
            return $lses;
        }

        $crudo = '';
        if (isset($_REQUEST['lses'])) {            // primera carga / AJAX que lo propaga
            $crudo = (string) $_REQUEST['lses'];
        } elseif (isset($_COOKIE['mi_lses'])) {    // navegacion interna posterior
            $crudo = (string) $_COOKIE['mi_lses'];
        }

        // Nombre de sesion: solo alfanumerico (es un md5).
        $lses = preg_replace('/[^A-Za-z0-9]/', '', $crudo);
        return $lses;
    }

    /**
     * Inicia la sesion con el nombre dinamico $lses (el mismo cookie que escribio
     * val_usuario.php) y lo recuerda en la cookie mi_lses para la navegacion
     * interna posterior (enlaces/AJAX sin ?lses=). Devuelve el $lses ('' si no hay).
     */
    function mesa_session_start(): string
    {
        $lses = mesa_session_lses();

        if (session_status() === PHP_SESSION_NONE) {
            if ($lses !== '') {
                session_name($lses);
            }
            session_start();
        }

        // Solo (re)escribimos la cookie cuando $lses llega por la URL/request
        // (primera carga). En la navegacion interna ya esta puesta; reescribirla
        // en cada request ademas "resucitaria" la cookie tras el logout.
        if ($lses !== '' && isset($_REQUEST['lses']) && !headers_sent()) {
            setcookie('mi_lses', $lses, 0, '/');
        }

        return $lses;
    }

    /** idusuario del usuario logueado, o '' si no hay sesion valida. */
    function mesa_session_user(): string
    {
        $lses = mesa_session_lses();
        if ($lses === '') {
            return '';
        }
        return (string) ($_SESSION[$lses]['idusuario'] ?? '');
    }
}
