<?php
/**
 * Layout compartido - Cabecera
 *
 * Equivalente al encabezado comun de los formularios VFP (Image1 + Container1
 * con los botones de cerrar / documentacion). Reune la carga de Bootstrap 5,
 * Bootstrap Icons y jQuery via CDN.
 *
 * Variables esperadas antes del include:
 *   $tituloForm  string  Titulo que se muestra en la barra superior.
 *   $anchoForm   string  (opcional) clase de ancho del contenedor. Por defecto 'container'.
 */

// Valida la sesion y expone $usuarioNombre (redirige al login si no hay usuario).
require_once __DIR__ . '/auth.php';

$tituloForm = isset($tituloForm) ? $tituloForm : 'Mesa de Admision';
$anchoForm  = isset($anchoForm)  ? $anchoForm  : 'container';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloForm) ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (reemplazan los .bmp/.ico del proyecto VFP) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- jQuery + Bootstrap + comportamiento comun.
         Se cargan en el <head> para que el <script src="../App/frmmesaX.js">
         que cada formulario incluye al final del body ya tenga jQuery disponible. -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../App/common.js"></script>

    <style>
        body { background-color: #f4f6f9; }
        .form-titulo { font-weight: 600; letter-spacing: .3px; }
        .brand-logo {
            height: 56px;
            display: flex; align-items: center; justify-content: center;
            background: #ffffff; border-radius: .375rem;
            padding: 4px;
        }
        .brand-logo img { max-height: 100%; max-width: 100%; object-fit: contain; }
        .toolbar .btn { min-width: 42px; }
        .req::after { content: " *"; color: #dc3545; }
    </style>
</head>
<body>

<!-- Barra superior comun (equivalente a Image1 + Container1 + CmdClose) -->
<nav class="navbar navbar-dark bg-primary mb-3">
    <div class="<?= htmlspecialchars($anchoForm) ?> d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div class="brand-logo"><img src="../Images/LOGO_HEAD.JPG" alt="Logo"></div>
            <span class="navbar-brand mb-0 h4 form-titulo"><?= htmlspecialchars($tituloForm) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- Nombre del usuario logueado -->
            <span class="text-white d-flex align-items-center me-1">
                <i class="bi bi-person-circle me-1"></i>
                <span id="usuarioNombre"><?= htmlspecialchars($usuarioNombre) ?></span>
            </span>
            <!-- cmdDocumentacion -->
            <button type="button" class="btn btn-outline-light" id="cmdDocumentacion"
                    title="Documentacion">
                <i class="bi bi-file-earmark-text"></i>
            </button>
            <!-- CmdClose -->
            <button type="button" class="btn btn-light" id="cmdClose" title="Cerrar (Esc)">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
</nav>

<main class="<?= htmlspecialchars($anchoForm) ?> pb-5">
