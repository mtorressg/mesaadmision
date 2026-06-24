<?php

/**
 * auth.php - Guard de sesion
 *
 * Valida que exista un usuario logueado usando la sesion creada por
 * ../usuario/login.php (valid_login.php). Si no hay sesion, redirige al login
 * conservando la URL actual como destino de retorno (?redirect=).
 *
 * Debe incluirse ANTES de cualquier salida HTML.
 *
 * Expone:
 *   $usuarioNombre  string  Nombre del usuario logueado (nomape) para mostrar.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['idusuario'])) {
    // login.php no sabe a que aplicacion volver: le pasamos la ubicacion del
    // index.php de ESTA app (no la pagina actual). auth.php esta en
    // <app>/Php/partials/, asi que la raiz de la app son dos niveles arriba.
    $appRoot = str_replace('\\', '/', dirname(__DIR__, 2));
    $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));

    if ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) {
        $base = substr($appRoot, strlen($docRoot));   // ej.: /mesaadmision
    } else {
        $base = '/mesaadmision';
    }

    $indexUrl = rtrim($base, '/') . '/index.php';      // ej.: /mesaadmision/index.php
    header('Location: /usuario/login.php?redirect=' . urlencode($indexUrl));
    exit;
}

// Nombre a mostrar en la barra de los formularios.
$usuarioNombre = $_SESSION['nomape'] ?? ($_SESSION['user_name'] ?? '');
