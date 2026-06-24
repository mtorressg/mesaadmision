<?php
/**
 * index.php - Menu principal de la Mesa de Admision
 *
 * Punto de entrada de la aplicacion. Muestra el menu de acceso a los
 * formularios refactorizados desde Visual FoxPro (carpeta /Php).
 */

// Valida la sesion y expone $usuarioNombre (redirige al login si no hay usuario).
require_once __DIR__ . '/Php/partials/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesa de Admision</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { background-color: #f4f6f9; }
        .menu-card {
            cursor: pointer;
            transition: transform .12s ease, box-shadow .12s ease;
            text-decoration: none;
        }
        .menu-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15) !important;
        }
        .menu-card .icono {
            font-size: 2.5rem;
            line-height: 1;
        }
        .menu-card.disabled {
            cursor: not-allowed;
            opacity: .55;
        }
        .menu-card.disabled:hover { transform: none; box-shadow: var(--bs-box-shadow-sm) !important; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-dark bg-primary">
    <div class="container-fluid">
        <!-- Izquierda: titulo del programa -->
        <span class="navbar-brand mb-0 h4 fw-semibold">Mesa de Admision</span>

        <!-- Derecha: usuario + salir -->
        <div class="d-flex align-items-center gap-2">
            <input type="text" class="form-control form-control-sm" id="txtUsuario"
                   value="<?= htmlspecialchars($usuarioNombre) ?>" placeholder="Usuario" readonly style="min-width: 200px;">
            <a href="logout.php" class="btn btn-light" id="btnSalir" title="Salir">
                <i class="bi bi-box-arrow-right"></i> Salir
            </a>
        </div>
    </div>
</nav>

<!-- Menu principal con cards -->
<main class="container py-5">
    <div class="row g-4 justify-content-center">

        <!-- Recepcion de pacientes -> frmmesa1 -->
        <div class="col-sm-6 col-lg-3">
            <a href="Php/frmmesa1.php" class="card shadow-sm h-100 menu-card text-center text-dark">
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                    <span class="icono text-primary mb-3"><i class="bi bi-person-plus"></i></span>
                    <h5 class="card-title mb-0">Recepcion de pacientes</h5>
                </div>
            </a>
        </div>

        <!-- Operador de Box -> frmmesa2 -->
        <div class="col-sm-6 col-lg-3">
            <a href="Php/frmmesa2.php" class="card shadow-sm h-100 menu-card text-center text-dark">
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                    <span class="icono text-primary mb-3"><i class="bi bi-clipboard2-pulse"></i></span>
                    <h5 class="card-title mb-0">Operador de Box</h5>
                </div>
            </a>
        </div>

        <!-- Listados -> frmmesa04 -->
        <div class="col-sm-6 col-lg-3">
            <a href="Php/frmmesa4.php" class="card shadow-sm h-100 menu-card text-center text-dark">
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                    <span class="icono text-primary mb-3"><i class="bi bi-printer"></i></span>
                    <h5 class="card-title mb-0">Listados</h5>
                </div>
            </a>
        </div>

        <!-- Depuracion -> Ajax/DepuracionController.php (sp_depuro_socios) -->
        <div class="col-sm-6 col-lg-3">
            <a href="#" class="card shadow-sm h-100 menu-card text-center text-dark" id="cardDepuracion">
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                    <span class="icono text-primary mb-3"><i class="bi bi-eraser"></i></span>
                    <h5 class="card-title mb-0">Depuracion</h5>
                </div>
            </a>
        </div>

    </div>
</main>

<!-- jQuery + Bootstrap bundle -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="App/index.js"></script>
</body>
</html>
