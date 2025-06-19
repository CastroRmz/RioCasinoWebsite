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
        $idmac = trim($_POST["idmac"] ?? '');
        $nombre = trim($_POST["nombre"] ?? '');
        $modelo = trim($_POST["modelo"] ?? '');
        $marca = trim($_POST["marca"] ?? '');
        $numero_serie = trim($_POST["numero_serie"] ?? '');
        $ebox_mac = trim($_POST["ebox_mac"] ?? '');
        $estado = $_POST["estado"] ?? '';

        if ($idmac && $nombre && $modelo && $marca && $numero_serie) {
            $stmt = $conn->prepare("INSERT INTO maquinas (idmac, nombre, modelo, marca, numero_serie, ebox_mac, estado, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $idmac, $nombre, $modelo, $marca, $numero_serie, $ebox_mac, $estado, $usuario);
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
        $idmac = trim($_POST["idmac"] ?? '');
        $nombre = trim($_POST["nombre"] ?? '');
        $modelo = trim($_POST["modelo"] ?? '');
        $marca = trim($_POST["marca"] ?? '');
        $numero_serie = trim($_POST["numero_serie"] ?? '');
        $ebox_mac = trim($_POST["ebox_mac"] ?? '');
        $estado = $_POST["estado"] ?? '';

        if ($idmac > 0 && $idmac && $nombre && $modelo && $marca && $numero_serie) {
            $stmt = $conn->prepare("UPDATE maquinas SET idmac=?, nombre=?, modelo=?, marca=?, numero_serie=?, ebox_mac=?, estado=? WHERE idmac=?");
            $stmt->bind_param("sssssssi", $idmac, $nombre, $modelo, $marca, $numero_serie, $ebox_mac, $estado, $idmac);

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

// Obtener historial general
$historialGeneral = $conn->query("
    SELECT h.*, m.nombre AS nombre_maquina, m.idmac 
    FROM historial_maquinas h 
    LEFT JOIN maquinas m ON h.idmac = m.idmac 
    ORDER BY h.id DESC
");

// Preparar historial por máquina
$historialPorMaquina = [];
if ($result->num_rows > 0) {
    $result->data_seek(0); // Reiniciar el puntero del resultado
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
    $result->data_seek(0); // Reiniciar el puntero del resultado para usarlo más tarde
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Gestión de Máquinas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        .table-responsive {
            overflow-x: auto;
        }
        .modal-lg {
            max-width: 800px;
        }
        .modal-xl {
            max-width: 1140px;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4">Gestión de Máquinas</h2>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="mb-3 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAgregar">Agregar Máquina</button>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalHistorialGeneral">Ver Historial General</button>
    </div>

    <!-- Contenedor responsivo para tabla -->
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Modelo</th>
                    <th>Marca</th>
                    <th>Número de Serie</th>
                    <th>Ebox MAC</th>
                    <th>Estado</th>
                    <th>Fecha Creación</th>
                    <th>Usuario</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['idmac'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['nombre'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['modelo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['marca'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['numero_serie'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['ebox_mac'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['estado'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['fecha_creacion'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['usuario'] ?? '') ?></td>
                    <td class="text-nowrap">
                        <button class="btn btn-info btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#modalVer<?= $row['idmac'] ?>">Ver</button>
                        <button class="btn btn-primary btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $row['idmac'] ?>">Editar</button>
                        <button class="btn btn-secondary btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#modalHistorial<?= $row['idmac'] ?>">Historial</button>
                        <a href="?accion=eliminar&idmac=<?= $row['idmac'] ?>" class="btn btn-danger btn-sm mb-1" onclick="return confirm('¿Eliminar esta máquina?');">Eliminar</a>
                    </td>
                </tr>

                <!-- Modal Ver -->
                <div class="modal fade" id="modalVer<?= $row['idmac'] ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                      <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">Detalles de Máquina</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                      </div>
                      <div class="modal-body">
                        <p><strong>ID:</strong> <?= htmlspecialchars($row['idmac'] ?? '') ?></p>
                        <p><strong>Nombre:</strong> <?= htmlspecialchars($row['nombre'] ?? '') ?></p>
                        <p><strong>Modelo:</strong> <?= htmlspecialchars($row['modelo'] ?? '') ?></p>
                        <p><strong>Marca:</strong> <?= htmlspecialchars($row['marca'] ?? '') ?></p>
                        <p><strong>Número de Serie:</strong> <?= htmlspecialchars($row['numero_serie'] ?? '') ?></p>
                        <p><strong>Ebox MAC:</strong> <?= htmlspecialchars($row['ebox_mac'] ?? '') ?></p>
                        <p><strong>Estado:</strong> <?= htmlspecialchars($row['estado'] ?? '') ?></p>
                        <p><strong>Fecha Creación:</strong> <?= htmlspecialchars($row['fecha_creacion'] ?? '') ?></p>
                        <p><strong>Usuario:</strong> <?= htmlspecialchars($row['usuario'] ?? '') ?></p>
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
                            <label for="idMaquinaEditar<?= $row['idmac'] ?>" class="form-label">ID Máquina</label>
                            <input type="text" class="form-control" id="idMaquinaEditar<?= $row['idmac'] ?>" 
                                   name="idmac" value="<?= htmlspecialchars($row['idmac'] ?? '') ?>" required>
                          </div>
                          
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
                    <div class="modal-dialog modal-fullscreen-md-down modal-dialog-scrollable">
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
                                                    <th style="width: 10%;">ID</th>
                                                    <th style="width: 40%;">Descripción</th>
                                                    <th style="width: 20%;">Usuario</th>
                                                    <th style="width: 30%;">Fecha</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($historialPorMaquina[$row['idmac']] as $hist): ?>
                                                    <tr>
                                                        <td><?= $hist['id'] ?></td>
                                                        <td><?= htmlspecialchars($hist['descripcion'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($hist['usuario'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($hist['fecha_cambio'] ?? '') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3">
                                        <p class="mb-0">No hay historial para esta máquina.</p>
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
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Agregar Máquina -->
<div class="modal fade" id="modalAgregar" tabindex="-1" aria-labelledby="modalAgregarLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <form method="post" class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="modalAgregarLabel">Agregar Máquina</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="accion" value="agregar">

        <div class="mb-3">
          <label for="idMaquinaAgregar" class="form-label">ID Máquina:</label>
          <input type="text" id="idMaquinaAgregar" name="idmac" class="form-control" required>
        </div>

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
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title">Historial General</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-0">
        <?php if ($historialGeneral && $historialGeneral->num_rows > 0): ?>
          <div class="table-responsive m-3">
            <table class="table table-striped table-bordered mb-0">
              <thead>
                <tr>
                  <th>ID Historial</th>
                  <th>Máquina</th>
                  <th>Descripción</th>
                  <th>Usuario</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($hg = $historialGeneral->fetch_assoc()): ?>
                  <tr>
                    <td><?= $hg['id'] ?></td>
                    <td><?= htmlspecialchars($hg['idmac'] ?? '') ?> - <?= htmlspecialchars($hg['nombre_maquina'] ?? "Sin máquina") ?></td>
                    <td><?= htmlspecialchars($hg['descripcion'] ?? '') ?></td>
                    <td><?= htmlspecialchars($hg['usuario'] ?? '') ?></td>
                    <td><?= htmlspecialchars($hg['fecha_cambio'] ?? '') ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>