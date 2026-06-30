<?php

/**
 * auth.php - Guard de sesion
 *
 * Valida que exista un usuario logueado. La sesion la crea el lanzador clasico
 * (../usuarios/val_usuario.php), que usa un nombre de sesion DINAMICO
 * ($lses = md5(...)), guarda los datos del usuario ANIDADOS bajo
 * $_SESSION[$lses][...] y lanza la app con index.php?lses=<md5>.
 *
 * Por eso aca:
 *   1) Resolvemos $lses: llega en ?lses= en la primera carga; para la
 *      navegacion interna posterior (enlaces sin ?lses=) lo recordamos en una
 *      cookie auxiliar (mi_lses).
 *   2) Iniciamos la sesion con ESE nombre (val_usuario.php hizo
 *      session_name($lses)) y validamos $_SESSION[$lses]['idusuario'].
 *
 * Debe incluirse ANTES de cualquier salida HTML.
 *
 * Expone:
 *   $lses           string  Nombre de sesion dinamico (propagar en enlaces/AJAX).
 *   $usuarioNombre  string  Nombre del usuario logueado (nomape) para mostrar.
 */

// Resuelve $lses (de ?lses= o cookie mi_lses) e inicia la sesion con ese nombre.
require_once __DIR__ . '/session.php';
$lses = mesa_session_start();

if ($lses === '' || empty($_SESSION[$lses]['idusuario'])) {
    // No hay sesion: volver al formulario de login del lanzador clasico, que
    // esta en la RAIZ de la intranet (/intranet/index.php) y valida via
    // usuarios/val_usuario.php (el mismo flujo que crea la sesion $lses).
    // Le pasamos ?prog=<app> para que el reingreso vuelva directo a esta app.
    // auth.php esta en <app>/Php/partials/, asi que la raiz de la app son dos
    // niveles arriba; usamos ruta absoluta (root-relativa) para que funcione
    // sin importar la profundidad de la pagina que incluyo este guard.
    $appRoot = str_replace('\\', '/', dirname(__DIR__, 2));
    $prog    = basename($appRoot);                                 // ej.: mesaingresos
    $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));

    if ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) {
        $appBase = substr($appRoot, strlen($docRoot));             // ej.: /intranet/lanzador/prog/mesaingresos
    } else {
        $appBase = '/intranet/lanzador/prog/' . $prog;
    }

    // Raiz de la intranet = todo lo anterior a /lanzador (ej.: /intranet).
    $pos        = stripos($appBase, '/lanzador');
    $portalBase = $pos !== false ? substr($appBase, 0, $pos) : '';

    $loginUrl = $portalBase . '/index.php?prog=' . urlencode($prog);
    header('Location: ' . $loginUrl);
    exit;
}

// Nombre a mostrar en la barra de los formularios (provisto por val_usuario.php).
$usuarioNombre = $_SESSION[$lses]['nomape'] ?? '';
