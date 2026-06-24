<?php

/**
 * logout.php - Cierre de sesion
 *
 * Destruye la sesion creada en ../usuario/login.php y vuelve al login.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

//header('Location: /usuario/login.php');
//$indexUrl = rtrim($base, '/') . '/index.php';      // ej.: /mesaadmision/index.php
//header('Location: /usuario/login.php?redirect=' . urlencode($indexUrl));

require_once __DIR__ . '/Php/partials/auth.php';

exit;
