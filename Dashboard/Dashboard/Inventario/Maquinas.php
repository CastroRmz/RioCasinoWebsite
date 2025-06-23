<?php
// Mostrar errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("../../../conexion.php");

$msg = "";

// Procesar formulario POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['accion'])) {
    $usuario = "SuperAdmin"; // Usuario fijo para demo

    // Agregar
    if ($_POST['accion'] === "agregar") {
        $nombre = trim($_POST["nombre"] ?? '');
        $modelo = trim($_POST["modelo"] ?? '');
        $marca = trim($_POST["marca"] ?? '');
        $numero_serie = trim($_POST["numero_serie"] ?? '');
        $ebox_mac = trim($_POST["ebox_mac"] ?? '');
        $estado = $_POST["estado"] ?? '';

        if ($nombre && $modelo && $marca && $numero_serie) {
            $stmt = $conn->prepare("INSERT INTO maquinas (nombre, modelo, marca, numero_serie, ebox_mac, estado, usuario) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $nombre, $modelo, $marca, $numero_serie, $ebox_mac, $estado, $usuario);
            
            if ($stmt->execute()) {
                $idmac = $stmt->insert_id;
                $desc = "Máquina creada";
                $historial = $conn->prepare("INSERT INTO historial_maquinas (idmac, descripcion, usuario) VALUES (?, ?, ?)");
                $historial->bind_param("iss", $idmac, $desc, $usuario);
                $historial->execute();
                $historial->close();
                $msg = "Máquina agregada correctamente.";
            } else {
                $msg = "Error al agregar máquina: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $msg = "Todos los campos obligatorios deben estar completos.";
        }
    }

    // Editar
    if ($_POST['accion'] === "editar") {
        $idmac = intval($_POST["idmac"] ?? 0);
        $nombre = trim($_POST["nombre"] ?? '');
        $modelo = trim($_POST["modelo"] ?? '');
        $marca = trim($_POST["marca"] ?? '');
        $numero_serie = trim($_POST["numero_serie"] ?? '');
        $ebox_mac = trim($_POST["ebox_mac"] ?? '');
        $estado = $_POST["estado"] ?? '';

        if ($idmac > 0 && $nombre && $modelo && $marca && $numero_serie) {
            $stmt = $conn->prepare("UPDATE maquinas SET nombre=?, modelo=?, marca=?, numero_serie=?, ebox_mac=?, estado=? WHERE idmac=?");
            $stmt->bind_param("ssssssi", $nombre, $modelo, $marca, $numero_serie, $ebox_mac, $estado, $idmac);

            if ($stmt->execute()) {
                $desc = "Máquina editada";
                $historial = $conn->prepare("INSERT INTO historial_maquinas (idmac, descripcion, usuario) VALUES (?, ?, ?)");
                $historial->bind_param("iss", $idmac, $desc, $usuario);
                $historial->execute();
                $historial->close();
                $msg = "Máquina actualizada correctamente.";
            } else {
                $msg = "Error al actualizar máquina: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $msg = "Faltan campos obligatorios o id inválido.";
        }
    }
}

// Eliminar máquina
if (isset($_GET["accion"]) && $_GET["accion"] === "eliminar" && isset($_GET["idmac"])) {
    $idmac = intval($_GET["idmac"]);
    $usuario = "SuperAdmin";
    $desc = "Máquina eliminada";

    $historial = $conn->prepare("INSERT INTO historial_maquinas (idmac, descripcion, usuario) VALUES (?, ?, ?)");
    $historial->bind_param("iss", $idmac, $desc, $usuario);
    $historial->execute();
    $historial->close();

    $stmt = $conn->prepare("DELETE FROM maquinas WHERE idmac = ?");
    $stmt->bind_param("i", $idmac);
    if ($stmt->execute()) {
        $msg = "Máquina eliminada correctamente.";
    } else {
        $msg = "Error al eliminar máquina: " . $stmt->error;
    }
    $stmt->close();
}

// Obtener máquinas para tabla
$result = $conn->query("SELECT * FROM maquinas ORDER BY idmac DESC");

// Preparar historial por máquina
$historialPorMaquina = [];
if ($result->num_rows > 0) {
    $result->data_seek(0);
    while ($maquina = $result->fetch_assoc()) {
        $idmac = $maquina['idmac'];
        $historialQuery = $conn->prepare("SELECT * FROM historial_maquinas WHERE idmac = ? ORDER BY id DESC");
        $historialQuery->bind_param("i", $idmac);
        $historialQuery->execute();
        $historialResult = $historialQuery->get_result();
        
        $historialPorMaquina[$idmac] = [];
        while ($historial = $historialResult->fetch_assoc()) {
            $historialPorMaquina[$idmac][] = $historial;
        }
        $historialQuery->close();
    }
    $result->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Gestión de Máquinas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
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
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background-color: white;
        }
        .table {
            width: 100%;
            margin-bottom: 0;
            color: #212529;
            font-size: 14px;
        }
        .table th {
            background-color: #343a40;
            color: white;
            font-weight: 500;
            padding: 12px 15px;
            vertical-align: middle;
        }
        .table td {
            padding: 10px 15px;
            vertical-align: middle;
            border-top: 1px solid #e9ecef;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        .badge {
            font-size: 12px;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .bg-success {
            background-color: #28a745 !important;
        }
        .bg-danger {
            background-color: #dc3545 !important;
        }
        .bg-warning {
            background-color: #ffc107 !important;
            color: #212529;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .btn-group-sm .btn {
            padding: 5px 10px;
            font-size: 12px;
        }
        .page-title {
            color: #343a40;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .modal-header {
            padding: 15px 20px;
        }
        .modal-title {
            font-weight: 500;
        }
        .alert {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .historial-table {
            background-color: #f8f9fa;
        }
        .historial-table th {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <h2 class="page-title">Gestión de Máquinas</h2>

    <?php if ($msg): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between mb-4">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAgregar">
            <i class="bi bi-plus-circle"></i> Agregar Máquina
        </button>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalHistorialGeneral">
            <i class="bi bi-clock-history"></i> Historial General
        </button>
    </div>

    <!-- Tabla de máquinas -->
    <div class="table-responsive">
        <table class="table table-striped table-hover table-bordered">
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th>Nombre</th>
                    <th>Modelo</th>
                    <th>Marca</th>
                    <th>N° Serie</th>
                    <th>Ebox MAC</th>
                    <th width="100">Estado</th>
                    <th width="120">Fecha Creación</th>
                    <th width="100">Usuario</th>
                    <th width="220">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['idmac'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['nombre'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['modelo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['marca'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['numero_serie'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['ebox_mac'] ?? '') ?></td>
                    <td>
                        <span class="badge bg-<?= 
                            ($row['estado'] == 'Alta') ? 'success' : 
                            (($row['estado'] == 'Baja') ? 'danger' : 'warning') ?>">
                            <?= htmlspecialchars($row['estado'] ?? '') ?>
                        </span>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($row['fecha_creacion'])) ?></td>
                    <td><?= htmlspecialchars($row['usuario'] ?? '') ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalVer<?= $row['idmac'] ?>">
                                <i class="bi bi-eye"></i> Ver
                            </button>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $row['idmac'] ?>">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalHistorial<?= $row['idmac'] ?>">
                                <i class="bi bi-clock-history"></i> Historial
                            </button>
                            <a href="?accion=eliminar&idmac=<?= $row['idmac'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de eliminar esta máquina?');">
                                <i class="bi bi-trash"></i> Eliminar
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" class="text-center py-4">No hay máquinas registradas</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Agregar Máquina -->
<div class="modal fade" id="modalAgregar" tabindex="-1" aria-labelledby="modalAgregarLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="modalAgregarLabel">Agregar Máquina</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="accion" value="agregar">

        <div class="mb-3">
          <label for="nombreAgregar" class="form-label">Nombre:</label>
          <input type="text" id="nombreAgregar" name="nombre" class="form-control" required>
        </div>

        <div class="mb-3">
          <label for="modeloAgregar" class="form-label">Modelo:</label>
          <input type="text" id="modeloAgregar" name="modelo" class="form-control" required>
        </div>

        <div class="mb-3">
          <label for="marcaAgregar" class="form-label">Marca:</label>
          <input type="text" id="marcaAgregar" name="marca" class="form-control" required>
        </div>

        <div class="mb-3">
          <label for="numeroSerieAgregar" class="form-label">Número de Serie:</label>
          <input type="text" id="numeroSerieAgregar" name="numero_serie" class="form-control" required>
        </div>

        <div class="mb-3">
          <label for="eboxMacAgregar" class="form-label">Ebox MAC:</label>
          <input type="text" id="eboxMacAgregar" name="ebox_mac" class="form-control">
        </div>

        <div class="mb-3">
          <label for="estadoAgregar" class="form-label">Estado:</label>
          <select id="estadoAgregar" name="estado" class="form-select" required>
            <option value="Alta">Alta</option>
            <option value="Baja">Baja</option>
            <option value="Proceso">Proceso</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Agregar</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Historial General -->
<div class="modal fade" id="modalHistorialGeneral" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title">Historial General</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-0">
        <?php 
        $historialGeneral = $conn->query("
            SELECT h.*, m.nombre AS nombre_maquina, m.idmac 
            FROM historial_maquinas h 
            LEFT JOIN maquinas m ON h.idmac = m.idmac 
            ORDER BY h.id DESC
        ");
        
        if ($historialGeneral && $historialGeneral->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0">
              <thead>
                <tr>
                  <th width="100">ID Historial</th>
                  <th width="100">Máquina ID</th>
                  <th>Nombre Máquina</th>
                  <th>Descripción</th>
                  <th width="120">Usuario</th>
                  <th width="180">Fecha</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($hg = $historialGeneral->fetch_assoc()): ?>
                  <tr>
                    <td><?= $hg['id'] ?></td>
                    <td><?= htmlspecialchars($hg['idmac'] ?? '') ?></td>
                    <td><?= htmlspecialchars($hg['nombre_maquina'] ?? "Sin máquina") ?></td>
                    <td><?= htmlspecialchars($hg['descripcion'] ?? '') ?></td>
                    <td><?= htmlspecialchars($hg['usuario'] ?? '') ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($hg['fecha_cambio'])) ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="m-3">No hay registros en el historial general.</p>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Ver -->
<?php if ($result->num_rows > 0): ?>
    <?php $result->data_seek(0); ?>
    <?php while ($row = $result->fetch_assoc()): ?>
    <div class="modal fade" id="modalVer<?= $row['idmac'] ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-info text-white">
            <h5 class="modal-title">Detalles de Máquina</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div class="row mb-3">
                <div class="col-md-4 fw-bold">ID:</div>
                <div class="col-md-8"><?= htmlspecialchars($row['idmac'] ?? '') ?></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4 fw-bold">Nombre:</div>
                <div class="col-md-8"><?= htmlspecialchars($row['nombre'] ?? '') ?></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4 fw-bold">Modelo:</div>
                <div class="col-md-8"><?= htmlspecialchars($row['modelo'] ?? '') ?></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4 fw-bold">Marca:</div>
                <div class="col-md-8"><?= htmlspecialchars($row['marca'] ?? '') ?></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4 fw-bold">Número de Serie:</div>
                <div class="col-md-8"><?= htmlspecialchars($row['numero_serie'] ?? '') ?></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4 fw-bold">Ebox MAC:</div>
                <div class="col-md-8"><?= htmlspecialchars($row['ebox_mac'] ?? '') ?></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4 fw-bold">Estado:</div>
                <div class="col-md-8">
                    <span class="badge bg-<?= 
                        ($row['estado'] == 'Alta') ? 'success' : 
                        (($row['estado'] == 'Baja') ? 'danger' : 'warning') ?>">
                        <?= htmlspecialchars($row['estado'] ?? '') ?>
                    </span>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4 fw-bold">Fecha Creación:</div>
                <div class="col-md-8"><?= date('d/m/Y H:i', strtotime($row['fecha_creacion'])) ?></div>
            </div>
            <div class="row">
                <div class="col-md-4 fw-bold">Usuario:</div>
                <div class="col-md-8"><?= htmlspecialchars($row['usuario'] ?? '') ?></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Editar -->
    <div class="modal fade" id="modalEditar<?= $row['idmac'] ?>" tabindex="-1" aria-labelledby="modalEditarLabel<?= $row['idmac'] ?>" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="post">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title" id="modalEditarLabel<?= $row['idmac'] ?>">Editar Máquina</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body">
              <input type="hidden" name="accion" value="editar">
              <input type="hidden" name="idmac" value="<?= $row['idmac'] ?>">
              
              <div class="mb-3">
                <label for="nombreEditar<?= $row['idmac'] ?>" class="form-label">Nombre</label>
                <input type="text" class="form-control" id="nombreEditar<?= $row['idmac'] ?>" 
                       name="nombre" value="<?= htmlspecialchars($row['nombre'] ?? '') ?>" required>
              </div>
              
              <div class="mb-3">
                <label for="modeloEditar<?= $row['idmac'] ?>" class="form-label">Modelo</label>
                <input type="text" class="form-control" id="modeloEditar<?= $row['idmac'] ?>" 
                       name="modelo" value="<?= htmlspecialchars($row['modelo'] ?? '') ?>" required>
              </div>
              
              <div class="mb-3">
                <label for="marcaEditar<?= $row['idmac'] ?>" class="form-label">Marca</label>
                <input type="text" class="form-control" id="marcaEditar<?= $row['idmac'] ?>" 
                       name="marca" value="<?= htmlspecialchars($row['marca'] ?? '') ?>" required>
              </div>
              
              <div class="mb-3">
                <label for="serieEditar<?= $row['idmac'] ?>" class="form-label">Número de Serie</label>
                <input type="text" class="form-control" id="serieEditar<?= $row['idmac'] ?>" 
                       name="numero_serie" value="<?= htmlspecialchars($row['numero_serie'] ?? '') ?>" required>
              </div>
              
              <div class="mb-3">
                <label for="macEditar<?= $row['idmac'] ?>" class="form-label">Ebox MAC</label>
                <input type="text" class="form-control" id="macEditar<?= $row['idmac'] ?>" 
                       name="ebox_mac" value="<?= htmlspecialchars($row['ebox_mac'] ?? '') ?>">
              </div>
              
              <div class="mb-3">
                <label for="estadoEditar<?= $row['idmac'] ?>" class="form-label">Estado</label>
                <select class="form-select" id="estadoEditar<?= $row['idmac'] ?>" name="estado" required>
                  <option value="Alta" <?= ($row['estado'] ?? '') === 'Alta' ? 'selected' : '' ?>>Alta</option>
                  <option value="Baja" <?= ($row['estado'] ?? '') === 'Baja' ? 'selected' : '' ?>>Baja</option>
                  <option value="Proceso" <?= ($row['estado'] ?? '') === 'Proceso' ? 'selected' : '' ?>>Proceso</option>
                </select>
              </div>
            </div>
            
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Historial por máquina -->
    <div class="modal fade" id="modalHistorial<?= $row['idmac'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title">Historial - Máquina: <?= htmlspecialchars($row['idmac'] ?? '') ?> - <?= htmlspecialchars($row['nombre'] ?? '') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <?php if (!empty($historialPorMaquina[$row['idmac']])): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="80">ID</th>
                                        <th>Descripción</th>
                                        <th width="120">Usuario</th>
                                        <th width="180">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historialPorMaquina[$row['idmac']] as $hist): ?>
                                        <tr>
                                            <td><?= $hist['id'] ?></td>
                                            <td><?= htmlspecialchars($hist['descripcion'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($hist['usuario'] ?? '') ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($hist['fecha_cambio'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-3 text-center">
                            <div class="alert alert-info mb-0">No hay historial para esta máquina.</div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>