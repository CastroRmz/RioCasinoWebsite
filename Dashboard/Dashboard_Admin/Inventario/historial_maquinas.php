<?php
// Mostrar errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("../../../conexion.php");

// Función para sanitizar entradas
function sanitizarInput($input) {
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

// Obtener y validar ID de máquina
$idmac = isset($_GET['idmac']) ? intval($_GET['idmac']) : 0;

if ($idmac <= 0) {
    die("ID de máquina inválido.");
}

try {
    // Obtener historial de la máquina
    $stmt = $conn->prepare("SELECT h.*, m.nombre as nombre_maquina 
                           FROM historial_maquinas h
                           LEFT JOIN maquinas m ON h.idmac = m.idmac
                           WHERE h.idmac = ? 
                           ORDER BY h.fecha_cambio DESC");
    $stmt->bind_param("i", $idmac);
    $stmt->execute();
    $result = $stmt->get_result();

    $historial = [];
    while ($row = $result->fetch_assoc()) {
        $historial[] = $row;
    }
    $stmt->close();

    // Verificar si se encontró la máquina
    if (empty($historial)) {
        $stmt = $conn->prepare("SELECT nombre FROM maquinas WHERE idmac = ? LIMIT 1");
        $stmt->bind_param("i", $idmac);
        $stmt->execute();
        $stmt->bind_result($nombreMaquina);
        $stmt->fetch();
        $stmt->close();
        
        if (empty($nombreMaquina)) {
            die("Máquina no encontrada.");
        }
    } else {
        $nombreMaquina = $historial[0]['nombre_maquina'];
    }

} catch (Exception $e) {
    die("Error al obtener el historial: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Cambios - <?= htmlspecialchars($nombreMaquina) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 100%;
            padding: 20px;
        }
        .table th {
            background-color: #343a40;
            color: white;
        }
        .badge-descripcion {
            background-color: #6c757d;
            color: white;
        }
        .badge-motivo {
            background-color: #17a2b8;
            color: white;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>
            <i class="bi bi-clock-history"></i> 
            Historial de Cambios - <?= htmlspecialchars($nombreMaquina) ?>
            <small class="text-muted">(ID: <?= $idmac ?>)</small>
        </h3>
        <a href="Maquinas.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver a listado
        </a>
    </div>

    <?php if (empty($historial)): ?>
        <div class="alert alert-info">
            No se encontraron registros de historial para esta máquina.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th width="150">Fecha</th>
                        <th width="120">Usuario</th>
                        <th>Descripción</th>
                        <th>Motivo</th>
                        <th width="100">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $registro): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($registro['fecha_cambio'])) ?></td>
                            <td><?= htmlspecialchars($registro['usuario']) ?></td>
                            <td>
                                <span class="badge badge-descripcion">
                                    <?= htmlspecialchars($registro['descripcion']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($registro['motivo_cambio'])): ?>
                                    <span class="badge badge-motivo">
                                        <?= htmlspecialchars($registro['motivo_cambio']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">No especificado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                        data-bs-target="#modalDetalle<?= $registro['id'] ?>">
                                    <i class="bi bi-eye"></i> Detalles
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modales para detalles del historial -->
<?php foreach ($historial as $registro): ?>
<div class="modal fade" id="modalDetalle<?= $registro['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Detalles del Cambio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Fecha:</div>
                    <div class="col-md-8"><?= date('d/m/Y H:i', strtotime($registro['fecha_cambio'])) ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Usuario:</div>
                    <div class="col-md-8"><?= htmlspecialchars($registro['usuario']) ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Descripción:</div>
                    <div class="col-md-8"><?= htmlspecialchars($registro['descripcion']) ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Motivo:</div>
                    <div class="col-md-8">
                        <?= !empty($registro['motivo_cambio']) ? htmlspecialchars($registro['motivo_cambio']) : 'No especificado' ?>
                    </div>
                </div>
                <?php if (!empty($registro['detalles_adicionales'])): ?>
                <div class="row">
                    <div class="col-md-4 fw-bold">Detalles:</div>
                    <div class="col-md-8"><?= nl2br(htmlspecialchars($registro['detalles_adicionales'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>