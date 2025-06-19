<?php
include("../../../conexion.php");

$idmac = isset($_GET['idmac']) ? intval($_GET['idmac']) : 0;

if ($idmac <= 0) {
    echo "ID de máquina inválido.";
    exit;
}

$stmt = $conn->prepare("SELECT * FROM historial_maquinas WHERE idmac = ? ORDER BY fecha DESC");
$stmt->bind_param("i", $idmac);
$stmt->execute();
$result = $stmt->get_result();

$historial = [];
while ($row = $result->fetch_assoc()) {
    $historial[] = $row;
}
$stmt->close();

// Obtener datos de la máquina para mostrar encabezado
$stmt = $conn->prepare("SELECT nombre FROM maquinas WHERE idmac = ? LIMIT 1");
$stmt->bind_param("i", $idmac);
$stmt->execute();
$stmt->bind_result($nombreMaquina);
$stmt->fetch();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Cambios - <?= htmlspecialchars($nombreMaquina) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h3 class="mb-4">Historial de Cambios - <?= htmlspecialchars($nombreMaquina) ?></h3>

    <a href="Maquinas.php" class="btn btn-secondary mb-3">Volver a listado</a>

    <table class="table table-striped table-bordered">
        <thead class="table-dark">
        <tr>
            <th>Fecha</th>
            <th>Usuario</th>
            <th>Acción</th>
            <th>Detalles</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($historial)): ?>
            <tr><td colspan="4" class="text-center">Sin registros.</td></tr>
        <?php else: ?>
            <?php foreach ($historial as $h): ?>
                <tr>
                    <td><?= htmlspecialchars($h['fecha']) ?></td>
                    <td><?= htmlspecialchars($h['usuario']) ?></td>
                    <td><?= htmlspecialchars($h['accion']) ?></td>
                    <td><?= nl2br(htmlspecialchars($h['detalles'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
