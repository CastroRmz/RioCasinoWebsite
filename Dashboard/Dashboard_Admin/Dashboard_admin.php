<?php
session_start();
require_once "../../conexion.php";

// Verificar autenticación y rol Admin
if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Admin') {
    header("Location: ../../login.php");
    exit();
}

$rol = $_SESSION['rol'];
$usuario_id = $_SESSION['usuario_id'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Dashboard Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
    <style>
        :root {
            --color-verde: #198754;
            --color-hover: #157347;
            --text-light: #f8f9fa;
        }

        body {
            background-color: #f8f9fa;
            color: #212529;
        }

        nav.navbar {
            background-color: #000;
        }

        nav.navbar .navbar-brand,
        nav.navbar .btn {
            color: white;
        }

        nav.navbar .btn:hover {
            background-color: #343a40;
            color: white;
        }

        .sidebar {
            height: 100vh;
            background-color: #212529;
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            padding-top: 1rem;
        }

        .sidebar a,
        .sidebar .dropdown-toggle {
            padding: 0.75rem 1.25rem;
            color: var(--text-light);
            font-weight: 500;
            text-decoration: none;
            display: block;
            transition: all 0.3s ease;
            border-radius: 0.375rem;
            margin: 0.25rem 1rem;
        }

        .sidebar a:hover,
        .sidebar .active,
        .dropdown-item:hover {
            background-color: var(--color-verde);
            color: white;
        }

        .dropdown-menu {
            background-color: #343a40;
            border: none;
            border-radius: 0.375rem;
        }

        .dropdown-item {
            color: var(--text-light);
        }

        .dropdown-item:hover {
            background-color: var(--color-hover);
        }

        .content-frame {
            width: 100%;
            height: calc(100vh - 56px);
            border: none;
        }
        
        .badge-asignado {
            background-color: #0d6efd;
        }
        
        .badge-sin-asignar {
            background-color: #6c757d;
        }
        
        .badge-notificacion {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.6rem;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark px-3">
    <a class="navbar-brand" href="#">Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></a>
    <div class="ms-auto d-flex align-items-center">
        <span class="badge me-3 bg-primary">
            <?= htmlspecialchars($rol) ?>
        </span>
        <a href="../../logout.php" class="btn btn-outline-light">Cerrar sesión</a>
    </div>
</nav>

<!-- Main layout -->
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
            <a href="#" class="active" onclick="cargarContenido('general.php', event)">
                <i class="bi bi-house-door-fill me-2"></i>General
            </a>

            <div class="dropdown">
                <a class="dropdown-toggle" href="#" id="dropdownInventario" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-box-seam me-2"></i>Inventario
                </a>
                <ul class="dropdown-menu" aria-labelledby="dropdownInventario">
                    <li><a class="dropdown-item" href="#" onclick="cargarContenido('Inventario/Inventario2.php', event)">
                        <i class="bi bi-list-ul me-2"></i>Ver Inventario
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="cargarContenido('Inventario/Maquinas2.php', event)">
                        <i class="bi bi-joystick me-2"></i>Máquinas
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="cargarContenido('Inventario/Ubicacion2.php', event)">
                        <i class="bi bi-geo-alt me-2"></i>Ubicación
                    </a></li>
                </ul>
            </div>

            <div class="dropdown">
                <a class="dropdown-toggle position-relative" href="#" id="dropdownTickets" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-ticket-perforated me-2"></i>Sistema de Tickets
                    <span class="badge bg-danger badge-notificacion" id="contador-total-tickets" style="display: none;"></span>
                </a>
                <ul class="dropdown-menu" aria-labelledby="dropdownTickets">
                    <li><a class="dropdown-item" href="#" onclick="cargarContenido('SistemaTickets/SistemaTickets2.php?filtro=asignados', event)">
                        <i class="bi bi-person-check me-2"></i>Mis Tickets
                        <span class="badge badge-asignado float-end" id="contador-asignados">0</span>
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="cargarContenido('SistemaTickets/SistemaTickets2.php?filtro=sin-asignar', event)">
                        <i class="bi bi-person-x me-2"></i>Sin Asignar
                        <span class="badge badge-sin-asignar float-end" id="contador-sin-asignar">0</span>
                    </a></li>
                </ul>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="col-md-10 p-0">
            <iframe src="general.php" name="contenido" class="content-frame" id="iframeContenido"></iframe>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
    function cargarContenido(url, event) {
        if (event) event.preventDefault();
        document.getElementById('iframeContenido').src = url;

        // Limpiar clase activa
        const links = document.querySelectorAll('.sidebar a, .dropdown-item');
        links.forEach(link => link.classList.remove('active'));

        // Marcar como activo
        if (event) {
            let target = event.target;
            if (target.classList.contains('dropdown-item')) {
                target.classList.add('active');
                // Marcar también el dropdown toggle como activo
                const dropdownToggle = document.getElementById('dropdownTickets');
                dropdownToggle.classList.add('active');
            } else if (target.tagName === 'A') {
                target.classList.add('active');
            }
        }
    }

    // Función para cargar contadores de tickets
    function cargarContadoresTickets() {
        fetch('../../api/contador_tickets.php?usuario_id=<?= $usuario_id ?>')
            .then(response => response.json())
            .then(data => {
                document.getElementById('contador-asignados').textContent = data.asignados;
                document.getElementById('contador-sin-asignar').textContent = data.sin_asignar;
                
                // Mostrar notificación si hay tickets sin asignar
                const totalSinAsignar = parseInt(data.sin_asignar);
                const contadorTotal = document.getElementById('contador-total-tickets');
                
                if (totalSinAsignar > 0) {
                    contadorTotal.textContent = totalSinAsignar;
                    contadorTotal.style.display = 'block';
                } else {
                    contadorTotal.style.display = 'none';
                }
            })
            .catch(error => console.error('Error:', error));
    }

    // Cargar contadores al iniciar
    document.addEventListener('DOMContentLoaded', function() {
        cargarContadoresTickets();
        // Actualizar cada 5 minutos
        setInterval(cargarContadoresTickets, 300000);
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>