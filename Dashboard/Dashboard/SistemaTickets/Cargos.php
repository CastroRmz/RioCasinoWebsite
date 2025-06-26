<?php
$mysqli = new mysqli("localhost", "root", "", "rcdb");
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

$msg = "";

// Configuración de paginación
$registrosPorPagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$inicio = ($pagina - 1) * $registrosPorPagina;

// Procesar formularios
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"];

    if ($accion === "agregar") {
        $nombre = trim($_POST["nombre"]);
        if (!empty($nombre)) {
            $stmt = $mysqli->prepare("INSERT INTO cargos (nombre) VALUES (?)");
            $stmt->bind_param("s", $nombre);
            $msg = $stmt->execute() ? "Cargo agregado correctamente." : "Error al agregar cargo.";
            $stmt->close();
        } else {
            $msg = "El nombre del cargo no puede estar vacío.";
        }
    }

    if ($accion === "editar") {
        $Cargo_id = intval($_POST["Cargo_id"]);
        $nombre = trim($_POST["nombre"]);
        if (!empty($nombre)) {
            $stmt = $mysqli->prepare("UPDATE cargos SET nombre = ? WHERE Cargo_id = ?");
            $stmt->bind_param("si", $nombre, $Cargo_id);
            $msg = $stmt->execute() ? "Cargo actualizado correctamente." : "Error al actualizar el cargo.";
            $stmt->close();
        } else {
            $msg = "El nombre del cargo no puede estar vacío.";
        }
    }
}

// Eliminar
if (isset($_GET["accion"]) && $_GET["accion"] === "eliminar" && isset($_GET["Cargo_id"])) {
    $Cargo_id = intval($_GET["Cargo_id"]);
    $stmt = $mysqli->prepare("DELETE FROM cargos WHERE Cargo_id = ?");
    $stmt->bind_param("i", $Cargo_id);
    $msg = $stmt->execute() ? "Cargo eliminado correctamente." : "Error al eliminar el cargo.";
    $stmt->close();
}

// Obtener el total de cargos para paginación
$totalRegistros = $mysqli->query("SELECT COUNT(*) as total FROM cargos")->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Obtener cargos con paginación y orden
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'Cargo_id';
$direccion = isset($_GET['dir']) ? $_GET['dir'] : 'DESC';
$ordenValido = in_array($orden, ['Cargo_id', 'nombre']) ? $orden : 'Cargo_id';
$direccionValida = $direccion === 'ASC' ? 'ASC' : 'DESC';

$query = "SELECT * FROM cargos ORDER BY $ordenValido $direccionValida LIMIT $inicio, $registrosPorPagina";
$cargos = $mysqli->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Cargos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sortable:hover {
            cursor: pointer;
            background-color: #f8f9fa;
        }
        .sort-icon {
            margin-left: 5px;
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4">Gestión de Cargos</h2>

    <?php if ($msg): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Botón para agregar -->
    <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#modalAgregar">
        <i class="bi bi-plus-circle"></i> Agregar Cargo
    </button>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
            <tr>
                <th class="sortable" onclick="ordenarTabla('Cargo_id')">
                    ID 
                    <?php if ($ordenValido === 'Cargo_id'): ?>
                        <i class="bi bi-arrow-<?= $direccionValida === 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                    <?php endif; ?>
                </th>
                <th class="sortable" onclick="ordenarTabla('nombre')">
                    Nombre 
                    <?php if ($ordenValido === 'nombre'): ?>
                        <i class="bi bi-arrow-<?= $direccionValida === 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                    <?php endif; ?>
                </th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($cargo = $cargos->fetch_assoc()): ?>
                <tr>
                    <td><?= $cargo['Cargo_id'] ?></td>
                    <td><?= htmlspecialchars($cargo['nombre']) ?></td>
                    <td>
                        <div class="btn-group" role="group">
                            <!-- Ver -->
                            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalVer<?= $cargo['Cargo_id'] ?>">
                                <i class="bi bi-eye"></i> Ver
                            </button>
                            <!-- Editar -->
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $cargo['Cargo_id'] ?>">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                            <!-- Eliminar -->
                            <a href="?accion=eliminar&Cargo_id=<?= $cargo['Cargo_id'] ?>&pagina=<?= $pagina ?>&orden=<?= $ordenValido ?>&dir=<?= $direccionValida ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('¿Está seguro de eliminar este cargo?')">
                                <i class="bi bi-trash"></i> Eliminar
                            </a>
                        </div>
                    </td>
                </tr>

                <!-- Modal Ver -->
                <div class="modal fade" id="modalVer<?= $cargo['Cargo_id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title">Detalles del Cargo</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">ID:</label>
                                    <p><?= $cargo['Cargo_id'] ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Nombre:</label>
                                    <p><?= htmlspecialchars($cargo['nombre']) ?></p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Editar -->
                <div class="modal fade" id="modalEditar<?= $cargo['Cargo_id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form method="post" class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">Editar Cargo</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="accion" value="editar">
                                <input type="hidden" name="Cargo_id" value="<?= $cargo['Cargo_id'] ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Nombre del cargo:</label>
                                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($cargo['nombre']) ?>" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($pagina > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&orden=<?= $ordenValido ?>&dir=<?= $direccionValida ?>" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                    <a class="page-link" href="?pagina=<?= $i ?>&orden=<?= $ordenValido ?>&dir=<?= $direccionValida ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($pagina < $totalPaginas): ?>
                <li class="page-item">
                    <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&orden=<?= $ordenValido ?>&dir=<?= $direccionValida ?>" aria-label="Siguiente">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>

<!-- Modal Agregar -->
<div class="modal fade" id="modalAgregar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Agregar Nuevo Cargo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="accion" value="agregar">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nombre del cargo:</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Agregar</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.min.js"></script>
<script>
    function ordenarTabla(columna) {
        const urlParams = new URLSearchParams(window.location.search);
        let dir = 'ASC';
        
        if (urlParams.get('orden') === columna) {
            dir = urlParams.get('dir') === 'ASC' ? 'DESC' : 'ASC';
        }
        
        window.location.href = `?pagina=1&orden=${columna}&dir=${dir}`;
    }
</script>
</body>
</html>