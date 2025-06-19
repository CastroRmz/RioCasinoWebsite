<?php
session_start();
require_once "../../conexion.php";

// Verifica que el usuario esté autenticado y sea SuperAdmin
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "SuperAdmin") {
    header("Location: Login.php");
    exit;
}

// Actualizar rol de usuario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["usuario_id"], $_POST["nuevo_rol"])) {
    $usuario_id = $_POST["usuario_id"];
    $nuevo_rol = $_POST["nuevo_rol"];

    $stmt = $conn->prepare("UPDATE usuarios SET rol = ? WHERE Usuario_id = ?");
    $stmt->bind_param("si", $nuevo_rol, $usuario_id);
    $stmt->execute();
}

// Obtener todos los usuarios excepto el SuperAdmin actual
$stmt = $conn->prepare("SELECT Usuario_id, usuario, correo, rol FROM usuarios WHERE Usuario_id != ?");
$stmt->bind_param("i", $_SESSION["usuario_id"]);
$stmt->execute();
$resultado = $stmt->get_result();
$usuarios = $resultado->fetch_all(MYSQLI_ASSOC);
?>

<?php
if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) {
    header("Location: ../../login.php");
    exit();
}
$rol = $_SESSION['rol'];
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
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark px-3">
    <a class="navbar-brand" href="#">Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></a>
    <div class="ms-auto">
        <a href="../../logout.php" class="btn btn-outline-light">Cerrar sesión</a>
    </div>
</nav>

<!-- Main layout -->
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
            <?php if ($rol === 'SuperAdmin'): ?>
                <a href="#" class="active" onclick="cargarContenido('general.php', event)">
                    <i class="bi bi-house-door-fill me-2"></i>General
                </a>
            <?php endif; ?>

            <div class="dropdown">
                <a class="dropdown-toggle" href="#" id="dropdownInventario" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-box-seam me-2"></i>Inventario
                </a>
                <ul class="dropdown-menu" aria-labelledby="dropdownInventario">
                    <li><a class="dropdown-item" href="#" onclick="cargarContenido('Inventario/Inventario.php', event)">Ver Inventario</a></li>
                    <li><a class="dropdown-item" href="#" onclick="cargarContenido('Inventario/Categorias.php', event)">Categorías</a></li>
                    <li><a class="dropdown-item" href="#" onclick="cargarContenido('Inventario/Ubicacion.php', event)">Ubicación</a></li>
                    <li><a class="dropdown-item" href="#" onclick="cargarContenido('Inventario/Maquinas.php', event)">Maquinas</a></li>
                </ul>
            </div>

            <div class="dropdown">
                <a class="dropdown-toggle" href="#" id="dropdownTickets" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-ticket-perforated me-2"></i>Sistema de Tickets
                </a>
                <ul class="dropdown-menu" aria-labelledby="dropdownTickets">
                    <li><a class="dropdown-item" href="#" onclick="cargarContenido('SistemaTickets/SistemaTickets.php', event)">Ver Tickets</a></li>
                    <?php if ($rol === 'SuperAdmin'): ?>
                        <li><a class="dropdown-item" href="#" onclick="cargarContenido('SistemaTickets/Cargos.php', event)">Cargos</a></li>
                        <li><a class="dropdown-item" href="#" onclick="cargarContenido('SistemaTickets/Empleados.php', event)">Empleados</a></li>
                        <li><a class="dropdown-item" href="#" onclick="cargarContenido('SistemaTickets/Departamentos.php', event)">Departamentos</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <?php if ($rol === 'SuperAdmin'): ?>
                <a href="#" onclick="cargarContenido('Usuarios/Usuarios.php', event)">
                    <i class="bi bi-people-fill me-2"></i>Usuarios
                </a>
            <?php endif; ?>
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
            } else if (target.tagName === 'A') {
                target.classList.add('active');
            }
        }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
