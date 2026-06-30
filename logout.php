<?php

/**
 * logout.php - Cierre de sesion
 *
 * Destruye la sesion creada en ../usuario/login.php y vuelve al login.
 */
// Inicia la sesion con el nombre dinamico $lses (val_usuario.php), para destruir
// la sesion correcta y no una vacia con el nombre por defecto.
require_once __DIR__ . '/Php/partials/session.php';
mesa_session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    // Cookie de la sesion (nombre = $lses) + cookie auxiliar mi_lses.
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    setcookie('mi_lses', '', time() - 42000, '/');
}

session_destroy();

//header('Location: /usuario/login.php');
//$indexUrl = rtrim($base, '/') . '/index.php';      // ej.: /mesaadmision/index.php
//header('Location: /usuario/login.php?redirect=' . urlencode($indexUrl));

require_once __DIR__ . '/Php/partials/auth.php';

exit;
