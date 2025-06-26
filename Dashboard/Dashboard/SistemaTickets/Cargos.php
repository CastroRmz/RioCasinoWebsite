<?php
session_start();
include("../../../conexion.php");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

$mysqli = new mysqli("localhost", "root", "", "rcdb");
if ($mysqli->connect_error) {
    die(json_encode(['success' => false, 'message' => "Error de conexión: " . $mysqli->connect_error]));
}

// Configuración de paginación
$por_pagina = 10;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina - 1) * $por_pagina;

// Procesar AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_POST['accion'] === 'editar') {
        $id = intval($_POST['Cargo_id']);
        $nombre = trim($_POST['nombre']);
        
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'message' => "El nombre del cargo no puede estar vacío."]);
            exit();
        }
        
        $stmt = $mysqli->prepare("UPDATE cargos SET nombre = ? WHERE Cargo_id = ?");
        $stmt->bind_param("si", $nombre, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "Cargo actualizado correctamente."]);
        } else {
            echo json_encode(['success' => false, 'message' => "Error al actualizar el cargo: " . $stmt->error]);
        }
        $stmt->close();
        exit();
    }
    
    if ($_POST['accion'] === 'agregar') {
        $nombre = trim($_POST['nombre']);
        
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'message' => "El nombre del cargo no puede estar vacío."]);
            exit();
        }
        
        $stmt = $mysqli->prepare("INSERT INTO cargos (nombre) VALUES (?)");
        $stmt->bind_param("s", $nombre);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "Cargo agregado correctamente.", 'refresh' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => "Error al agregar cargo: " . $stmt->error]);
        }
        $stmt->close();
        exit();
    }
}

// Eliminar (vía GET)
if (isset($_GET["accion"]) && $_GET["accion"] === "eliminar" && isset($_GET["Cargo_id"])) {
    $id = intval($_GET["Cargo_id"]);
    
    // Verificar si el cargo está en uso
    $check = $mysqli->prepare("SELECT COUNT(*) FROM empleados WHERE cargoID = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $check->bind_result($en_uso);
    $check->fetch();
    $check->close();
    
    if ($en_uso > 0) {
        $msg = "No se puede eliminar el cargo porque está asignado a empleados.";
    } else {
        $stmt = $mysqli->prepare("DELETE FROM cargos WHERE Cargo_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $msg = "Cargo eliminado correctamente.";
        } else {
            $msg = "Error al eliminar el cargo: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: Cargos.php?pagina=$pagina&msg=" . urlencode($msg));
    exit();
}

// Obtener datos para la vista
$total_query = $mysqli->query("SELECT COUNT(*) AS total FROM cargos");
$total = $total_query->fetch_assoc()['total'];
$paginas = ceil($total / $por_pagina);

$cargos = $mysqli->query("SELECT * FROM cargos ORDER BY Cargo_id DESC LIMIT $inicio, $por_pagina");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Cargos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .table-responsive { overflow-x: auto; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        #toast-container { position: fixed; top: 20px; right: 20px; z-index: 1100; }
        .spinner-btn { display: none; }
        .is-loading .spinner-btn { display: inline-block; }
        .is-loading .btn-text { display: none; }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4">Gestión de Cargos</h2>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?= htmlspecialchars(urldecode($_GET['msg'])) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div id="toast-container"></div>

    <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#modalAgregar">
        <i class="bi bi-plus-circle"></i> Agregar Cargo
    </button>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($cargo = $cargos->fetch_assoc()): ?>
                <tr id="row-<?= $cargo['Cargo_id'] ?>">
                    <td><?= $cargo['Cargo_id'] ?></td>
                    <td class="nombre-cargo"><?= htmlspecialchars($cargo['nombre']) ?></td>
                    <td>
                        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalVer<?= $cargo['Cargo_id'] ?>">
                            <i class="bi bi-eye"></i> Ver
                        </button>
                        <button class="btn btn-primary btn-sm btn-editar" 
                                data-id="<?= $cargo['Cargo_id'] ?>" 
                                data-nombre="<?= htmlspecialchars($cargo['nombre']) ?>">
                            <i class="bi bi-pencil"></i> Editar
                        </button>
                        <a href="?accion=eliminar&Cargo_id=<?= $cargo['Cargo_id'] ?>&pagina=<?= $pagina ?>" 
                           class="btn btn-danger btn-sm" 
                           onclick="return confirm('¿Estás seguro de eliminar este cargo?')">
                            <i class="bi bi-trash"></i> Eliminar
                        </a>
                    </td>
                </tr>

                <!-- Modal Ver -->
                <div class="modal fade" id="modalVer<?= $cargo['Cargo_id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title">Ver Cargo</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label"><strong>ID:</strong></label>
                                    <p><?= $cargo['Cargo_id'] ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><strong>Nombre:</strong></label>
                                    <p><?= htmlspecialchars($cargo['nombre']) ?></p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center mt-3">
            <?php if ($pagina > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?pagina=<?= $pagina - 1 ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php
            $rango = 2;
            $inicio_rango = max(1, $pagina - $rango);
            $fin_rango = min($paginas, $pagina + $rango);
            
            if ($inicio_rango > 1) {
                echo '<li class="page-item"><a class="page-link" href="?pagina=1">1</a></li>';
                if ($inicio_rango > 2) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            
            for ($i = $inicio_rango; $i <= $fin_rango; $i++): ?>
                <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                    <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor;
            
            if ($fin_rango < $paginas) {
                if ($fin_rango < $paginas - 1) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                echo '<li class="page-item"><a class="page-link" href="?pagina='.$paginas.'">'.$paginas.'</a></li>';
            }
            ?>

            <?php if ($pagina < $paginas): ?>
                <li class="page-item">
                    <a class="page-link" href="?pagina=<?= $pagina + 1 ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>

<!-- Modal Editar (único para todos) -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formEditar" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Editar Cargo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" id="editarCargoId" name="Cargo_id" value="">
                <div class="mb-3">
                    <label class="form-label">Nombre del cargo:</label>
                    <input type="text" id="editarNombre" name="nombre" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <span class="spinner-border spinner-border-sm spinner-btn" role="status" aria-hidden="true"></span>
                    <span class="btn-text">Guardar Cambios</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Agregar -->
<div class="modal fade" id="modalAgregar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formAgregar" class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Agregar Cargo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="accion" value="agregar">
                <div class="mb-3">
                    <label class="form-label">Nombre del cargo:</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">
                    <span class="spinner-border spinner-border-sm spinner-btn" role="status" aria-hidden="true"></span>
                    <span class="btn-text">Agregar</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Función para mostrar notificaciones
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show`;
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    const container = document.getElementById('toast-container');
    container.appendChild(toast);
    
    // Eliminar después de 5 segundos
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// Manejar el modal de edición
document.querySelectorAll('.btn-editar').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const nombre = this.getAttribute('data-nombre');
        
        document.getElementById('editarCargoId').value = id;
        document.getElementById('editarNombre').value = nombre;
        
        const modal = new bootstrap.Modal(document.getElementById('modalEditar'));
        modal.show();
    });
});

// Envío del formulario de edición con AJAX
document.getElementById('formEditar').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Mostrar spinner y deshabilitar botón
    submitBtn.classList.add('is-loading');
    submitBtn.disabled = true;
    
    fetch('Cargos.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            // Actualizar la fila en la tabla sin recargar
            const row = document.querySelector(`#row-${form.Cargo_id.value}`);
            if (row) {
                row.querySelector('.nombre-cargo').textContent = form.nombre.value;
            }
            // Cerrar el modal
            bootstrap.Modal.getInstance(document.getElementById('modalEditar')).hide();
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error al procesar la solicitud', 'danger');
    })
    .finally(() => {
        submitBtn.classList.remove('is-loading');
        submitBtn.disabled = false;
    });
});

// Envío del formulario de agregar con AJAX
document.getElementById('formAgregar').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Mostrar spinner y deshabilitar botón
    submitBtn.classList.add('is-loading');
    submitBtn.disabled = true;
    
    fetch('Cargos.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            if (data.refresh) {
                // Recargar la página para mostrar el nuevo cargo
                setTimeout(() => window.location.reload(), 1000);
            }
            // Cerrar el modal
            bootstrap.Modal.getInstance(document.getElementById('modalAgregar')).hide();
            // Resetear el formulario
            form.reset();
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error al procesar la solicitud', 'danger');
    })
    .finally(() => {
        submitBtn.classList.remove('is-loading');
        submitBtn.disabled = false;
    });
});
</script>
</body>
</html>