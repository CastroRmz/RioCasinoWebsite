<?php
// Configuración de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("../../../conexion.php");

// Función para sanitizar entradas
function sanitizarInput($input) {
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

// Función para ejecutar consultas preparadas
function ejecutarConsulta($conn, $sql, $params = [], $types = '') {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }
    
    return $stmt;
}

$msg = "";
$usuario = $_SESSION['usuario'] ?? 'SuperAdmin'; // Usar sesión real en producción

try {
    // Procesar formulario POST
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['accion'])) {
        // Agregar máquina
        if ($_POST['accion'] === "agregar") {
            $nombre = sanitizarInput($_POST["nombre"]);
            $modelo = sanitizarInput($_POST["modelo"]);
            $marca = sanitizarInput($_POST["marca"]);
            $numero_serie = sanitizarInput($_POST["numero_serie"]);
            $ebox_mac = sanitizarInput($_POST["ebox_mac"]);
            $estado = sanitizarInput($_POST["estado"]);
            $fecha_mtto = !empty($_POST["fecha_mtto"]) ? date('Y-m-d', strtotime($_POST["fecha_mtto"])) : null;
            $proximo_mtto = !empty($_POST["proximo_mtto"]) ? date('Y-m-d', strtotime($_POST["proximo_mtto"])) : null;
            $garantia_vigente = isset($_POST["garantia_vigente"]) ? 1 : 0;

            if ($nombre && $modelo && $marca && $numero_serie) {
                $sql = "INSERT INTO maquinas (nombre, modelo, marca, numero_serie, ebox_mac, estado, fecha_ultimo_mtto, proximo_mtto, garantia_vigente, usuario) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = ejecutarConsulta($conn, $sql, [
                    $nombre, $modelo, $marca, $numero_serie, $ebox_mac, $estado, 
                    $fecha_mtto, $proximo_mtto, $garantia_vigente, $usuario
                ], "ssssssssis");
                
                $idmac = $stmt->insert_id;
                
                // Registrar en historial
                $desc = "Máquina creada";
                ejecutarConsulta($conn, 
                    "INSERT INTO historial_maquinas (idmac, descripcion, usuario, motivo_cambio) VALUES (?, ?, ?, ?)",
                    [$idmac, $desc, $usuario, "Creación inicial"], "isss");
                
                $msg = "Máquina agregada correctamente.";
                $stmt->close();
            } else {
                $msg = "Todos los campos obligatorios deben estar completos.";
            }
        }

        // Editar máquina
        if ($_POST['accion'] === "editar") {
            $idmac = intval($_POST["idmac"] ?? 0);
            $nombre = sanitizarInput($_POST["nombre"]);
            $modelo = sanitizarInput($_POST["modelo"]);
            $marca = sanitizarInput($_POST["marca"]);
            $numero_serie = sanitizarInput($_POST["numero_serie"]);
            $ebox_mac = sanitizarInput($_POST["ebox_mac"]);
            $estado = sanitizarInput($_POST["estado"]);
            $fecha_mtto = !empty($_POST["fecha_mtto"]) ? date('Y-m-d', strtotime($_POST["fecha_mtto"])) : null;
            $proximo_mtto = !empty($_POST["proximo_mtto"]) ? date('Y-m-d', strtotime($_POST["proximo_mtto"])) : null;
            $garantia_vigente = isset($_POST["garantia_vigente"]) ? 1 : 0;
            $motivo = sanitizarInput($_POST["motivo"] ?? "Actualización de datos");

            if ($idmac > 0 && $nombre && $modelo && $marca && $numero_serie) {
                $sql = "UPDATE maquinas SET nombre=?, modelo=?, marca=?, numero_serie=?, ebox_mac=?, estado=?, 
                        fecha_ultimo_mtto=?, proximo_mtto=?, garantia_vigente=? 
                        WHERE idmac=?";
                
                $stmt = ejecutarConsulta($conn, $sql, [
                    $nombre, $modelo, $marca, $numero_serie, $ebox_mac, $estado, 
                    $fecha_mtto, $proximo_mtto, $garantia_vigente, $idmac
                ], "ssssssssii");
                
                // Registrar en historial
                $desc = "Máquina editada";
                ejecutarConsulta($conn, 
                    "INSERT INTO historial_maquinas (idmac, descripcion, usuario, motivo_cambio) VALUES (?, ?, ?, ?)",
                    [$idmac, $desc, $usuario, $motivo], "isss");
                
                $msg = "Máquina actualizada correctamente.";
                $stmt->close();
            } else {
                $msg = "Faltan campos obligatorios o ID inválido.";
            }
        }
    }

    // Eliminar máquina
    if (isset($_GET["accion"]) && $_GET["accion"] === "eliminar" && isset($_GET["idmac"])) {
        $idmac = intval($_GET["idmac"]);
        $motivo = sanitizarInput($_GET["motivo"] ?? "Eliminación solicitada");

        // Registrar en historial primero
        ejecutarConsulta($conn, 
            "INSERT INTO historial_maquinas (idmac, descripcion, usuario, motivo_cambio) VALUES (?, ?, ?, ?)",
            [$idmac, "Máquina eliminada", $usuario, $motivo], "isss");

        // Eliminar máquina
        $stmt = ejecutarConsulta($conn, "DELETE FROM maquinas WHERE idmac = ?", [$idmac], "i");
        
        $msg = "Máquina eliminada correctamente.";
        $stmt->close();
    }

} catch (Exception $e) {
    error_log("Error en gestión de máquinas: " . $e->getMessage());
    $msg = "Ocurrió un error al procesar la solicitud: " . $e->getMessage();
}

// Obtener y sanitizar parámetros de búsqueda
$busqueda = sanitizarInput($_GET['busqueda'] ?? '');
$filtro_estado = sanitizarInput($_GET['filtro_estado'] ?? '');
$filtro_marca = sanitizarInput($_GET['filtro_marca'] ?? '');
$filtro_modelo = sanitizarInput($_GET['filtro_modelo'] ?? '');
$filtro_mtto = sanitizarInput($_GET['filtro_mtto'] ?? '');

// Configuración de paginación
$por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;

// Configuración de ordenación
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'idmac';
$direccion = isset($_GET['dir']) ? $_GET['dir'] : 'DESC';

// Validar campos de ordenación permitidos
$campos_orden = ['idmac', 'nombre', 'modelo', 'marca', 'numero_serie', 'estado', 'fecha_creacion'];
if (!in_array($orden, $campos_orden)) {
    $orden = 'idmac';
}
$direccion = strtoupper($direccion) === 'ASC' ? 'ASC' : 'DESC';

// Construir consulta SQL con filtros
$sql = "SELECT * FROM maquinas WHERE 1=1";
$params = [];
$types = '';

if (!empty($busqueda)) {
    $sql .= " AND (nombre LIKE ? OR modelo LIKE ? OR marca LIKE ? OR numero_serie LIKE ? OR ebox_mac LIKE ?)";
    $searchTerm = "%$busqueda%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sssss';
}

if (!empty($filtro_estado)) {
    $sql .= " AND estado = ?";
    $params[] = $filtro_estado;
    $types .= 's';
}

if (!empty($filtro_marca)) {
    $sql .= " AND marca = ?";
    $params[] = $filtro_marca;
    $types .= 's';
}

if (!empty($filtro_modelo)) {
    $sql .= " AND modelo = ?";
    $params[] = $filtro_modelo;
    $types .= 's';
}

if (!empty($filtro_mtto)) {
    $now = date('Y-m-d');
    switch ($filtro_mtto) {
        case 'vencido':
            $sql .= " AND (fecha_ultimo_mtto IS NULL OR fecha_ultimo_mtto < ?)";
            $params[] = $now;
            $types .= 's';
            break;
        case 'proximo':
            $nextMonth = date('Y-m-d', strtotime('+1 month'));
            $sql .= " AND (proximo_mtto BETWEEN ? AND ?)";
            $params[] = $now;
            $params[] = $nextMonth;
            $types .= 'ss';
            break;
        case 'al_dia':
            $sql .= " AND (fecha_ultimo_mtto >= ?)";
            $params[] = $now;
            $types .= 's';
            break;
        case 'sin_garantia':
            $sql .= " AND (garantia_vigente = 0)";
            break;
    }
}

// Contar total de registros para paginación
$sql_count = "SELECT COUNT(*) AS total FROM maquinas WHERE 1=1";
if (!empty($busqueda)) {
    $sql_count .= " AND (nombre LIKE ? OR modelo LIKE ? OR marca LIKE ? OR numero_serie LIKE ? OR ebox_mac LIKE ?)";
}
if (!empty($filtro_estado)) {
    $sql_count .= " AND estado = ?";
}
if (!empty($filtro_marca)) {
    $sql_count .= " AND marca = ?";
}
if (!empty($filtro_modelo)) {
    $sql_count .= " AND modelo = ?";
}
if (!empty($filtro_mtto)) {
    switch ($filtro_mtto) {
        case 'vencido':
            $sql_count .= " AND (fecha_ultimo_mtto IS NULL OR fecha_ultimo_mtto < ?)";
            break;
        case 'proximo':
            $sql_count .= " AND (proximo_mtto BETWEEN ? AND ?)";
            break;
        case 'al_dia':
            $sql_count .= " AND (fecha_ultimo_mtto >= ?)";
            break;
        case 'sin_garantia':
            $sql_count .= " AND (garantia_vigente = 0)";
            break;
    }
}

$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_registros = $result_count->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $por_pagina);
$stmt_count->close();

// Aplicar ordenación y límite para paginación
$sql .= " ORDER BY $orden $direccion LIMIT " . (($pagina_actual - 1) * $por_pagina) . ", $por_pagina";

// Preparar y ejecutar consulta
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Obtener opciones únicas para filtros
$marcas = $conn->query("SELECT DISTINCT marca FROM maquinas WHERE marca IS NOT NULL AND marca != '' ORDER BY marca");
$modelos = $conn->query("SELECT DISTINCT modelo FROM maquinas WHERE modelo IS NOT NULL AND modelo != '' ORDER BY modelo");

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

    .filtros-container {
        background-color: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
        margin-bottom: 25px;
    }

    .filtros-container .form-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 5px;
    }

    .filtros-container .form-control,
    .filtros-container .form-select {
        font-size: 14px;
        height: 40px;
        border-radius: 6px;
        border: 1px solid #ced4da;
    }

    .filtros-container .btn {
        font-size: 14px;
        padding: 10px 16px;
        border-radius: 6px;
        width: 100%;
    }

    .filtros-container .btn i {
        margin-right: 5px;
    }

    .search-box {
        position: relative;
    }

    .search-box .form-control {
        padding-left: 38px;
    }

    .search-box i {
        position: absolute;
        left: 12px;
        top: 11px;
        font-size: 16px;
        color: #6c757d;
    }

    .pagination .page-item.active .page-link {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }

    .pagination .page-link {
        color: #0d6efd;
    }

    .table th a {
        text-decoration: none;
        color: inherit;
        display: block;
    }

    .table th a:hover {
        color: #0d6efd;
    }

    @media (min-width: 768px) {
        .filtros-container .col-md-2,
        .filtros-container .col-md-4 {
            display: flex;
            flex-direction: column;
            justify-content: end;
        }
    }

    @media (max-width: 767px) {
        .filtros-container .row > div {
            width: 100%;
        }

        .filtros-container .btn {
            margin-top: 10px;
        }

        .filtros-container .mt-2 {
            margin-top: 10px !important;
        }
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

    <!-- Filtros y búsqueda -->
    <div class="filtros-container mb-4">
        <form method="get" class="row g-3">
            <input type="hidden" name="orden" value="<?= htmlspecialchars($orden) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($direccion) ?>">
            
            <div class="col-md-4 search-box">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" name="busqueda" placeholder="Buscar..." 
                       value="<?= htmlspecialchars($busqueda) ?>">
            </div>

            <div class="col-md-2">
                <label class="filter-label">Estado</label>
                <select name="filtro_estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="Alta" <?= $filtro_estado === 'Alta' ? 'selected' : '' ?>>Alta</option>
                    <option value="Baja" <?= $filtro_estado === 'Baja' ? 'selected' : '' ?>>Baja</option>
                    <option value="Proceso" <?= $filtro_estado === 'Proceso' ? 'selected' : '' ?>>Proceso</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="filter-label">Marca</label>
                <select name="filtro_marca" class="form-select">
                    <option value="">Todas</option>
                    <?php while ($marca = $marcas->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($marca['marca']) ?>" 
                            <?= $filtro_marca === $marca['marca'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($marca['marca']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="filter-label">Modelo</label>
                <select name="filtro_modelo" class="form-select">
                    <option value="">Todos</option>
                    <?php while ($modelo = $modelos->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($modelo['modelo']) ?>" 
                            <?= $filtro_modelo === $modelo['modelo'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($modelo['modelo']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="filter-label">Mantenimiento</label>
                <select name="filtro_mtto" class="form-select">
                    <option value="">Todos</option>
                    <option value="al_dia" <?= $filtro_mtto === 'al_dia' ? 'selected' : '' ?>>Al día</option>
                    <option value="proximo" <?= $filtro_mtto === 'proximo' ? 'selected' : '' ?>>Próximo</option>
                    <option value="vencido" <?= $filtro_mtto === 'vencido' ? 'selected' : '' ?>>Vencido</option>
                    <option value="sin_garantia" <?= $filtro_mtto === 'sin_garantia' ? 'selected' : '' ?>>Sin garantía</option>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary filter-reset">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <a href="?" class="btn btn-outline-secondary filter-reset mt-2">
                    <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                </a>
            </div>
        </form>
    </div>

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
        <table class="table table-striped table-bordered bg-white">
            <thead class="table-dark">
                <tr>
                    <th width="50">
                        <a href="?<?= http_build_query(array_merge($_GET, ['orden' => 'idmac', 'dir' => $orden === 'idmac' && $direccion === 'DESC' ? 'ASC' : 'DESC'])) ?>">
                            ID <?= $orden === 'idmac' ? ($direccion === 'ASC' ? '↑' : '↓') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="?<?= http_build_query(array_merge($_GET, ['orden' => 'nombre', 'dir' => $orden === 'nombre' && $direccion === 'DESC' ? 'ASC' : 'DESC'])) ?>">
                            Nombre <?= $orden === 'nombre' ? ($direccion === 'ASC' ? '↑' : '↓') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="?<?= http_build_query(array_merge($_GET, ['orden' => 'modelo', 'dir' => $orden === 'modelo' && $direccion === 'DESC' ? 'ASC' : 'DESC'])) ?>">
                            Modelo <?= $orden === 'modelo' ? ($direccion === 'ASC' ? '↑' : '↓') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="?<?= http_build_query(array_merge($_GET, ['orden' => 'marca', 'dir' => $orden === 'marca' && $direccion === 'DESC' ? 'ASC' : 'DESC'])) ?>">
                            Marca <?= $orden === 'marca' ? ($direccion === 'ASC' ? '↑' : '↓') : '' ?>
                        </a>
                    </th>
                    <th>
                        <a href="?<?= http_build_query(array_merge($_GET, ['orden' => 'numero_serie', 'dir' => $orden === 'numero_serie' && $direccion === 'DESC' ? 'ASC' : 'DESC'])) ?>">
                            N° Serie <?= $orden === 'numero_serie' ? ($direccion === 'ASC' ? '↑' : '↓') : '' ?>
                        </a>
                    </th>
                    <th>Ebox MAC</th>
                    <th width="100">
                        <a href="?<?= http_build_query(array_merge($_GET, ['orden' => 'estado', 'dir' => $orden === 'estado' && $direccion === 'DESC' ? 'ASC' : 'DESC'])) ?>">
                            Estado <?= $orden === 'estado' ? ($direccion === 'ASC' ? '↑' : '↓') : '' ?>
                        </a>
                    </th>
                    <th width="100">Usuario</th>
                    <th width="250">Acciones</th>
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
                            <a href="?<?= http_build_query(array_merge($_GET, ['accion' => 'eliminar', 'idmac' => $row['idmac']])) ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('¿Estás seguro de eliminar esta máquina?');">
                                <i class="bi bi-trash"></i> Eliminar
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center py-4">No se encontraron máquinas con los filtros aplicados</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($total_paginas > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $pagina_actual <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_actual - 1])) ?>" aria-label="Anterior">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <li class="page-item <?= $i == $pagina_actual ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            
            <li class="page-item <?= $pagina_actual >= $total_paginas ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_actual + 1])) ?>" aria-label="Siguiente">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="text-center text-muted">
        Mostrando <?= (($pagina_actual - 1) * $por_pagina) + 1 ?> a <?= min($pagina_actual * $por_pagina, $total_registros) ?> de <?= $total_registros ?> máquinas
    </div>
    <?php endif; ?>
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

        <div class="mb-3">
          <label for="fechaMttoAgregar" class="form-label">Último Mantenimiento:</label>
          <input type="date" id="fechaMttoAgregar" name="fecha_mtto" class="form-control">
        </div>

        <div class="mb-3">
          <label for="proximoMttoAgregar" class="form-label">Próximo Mantenimiento:</label>
          <input type="date" id="proximoMttoAgregar" name="proximo_mtto" class="form-control">
        </div>

        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="garantiaAgregar" name="garantia_vigente" checked>
          <label class="form-check-label" for="garantiaAgregar">Garantía vigente</label>
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
                  <th>Motivo</th>
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
                    <td><?= htmlspecialchars($hg['motivo_cambio'] ?? '') ?></td>
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
                <div class="col-md-4 fw-bold">Último Mantenimiento:</div>
                <div class="col-md-8"><?= !empty($row['fecha_ultimo_mtto']) ? date('d/m/Y', strtotime($row['fecha_ultimo_mtto'])) : 'Nunca' ?></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4 fw-bold">Próximo Mantenimiento:</div>
                <div class="col-md-8"><?= !empty($row['proximo_mtto']) ? date('d/m/Y', strtotime($row['proximo_mtto'])) : 'No programado' ?></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4 fw-bold">Garantía:</div>
                <div class="col-md-8">
                    <span class="badge bg-<?= $row['garantia_vigente'] ? 'success' : 'danger' ?>">
                        <?= $row['garantia_vigente'] ? 'Vigente' : 'Vencida' ?>
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
              
              <div class="mb-3">
                <label for="fechaMttoEditar<?= $row['idmac'] ?>" class="form-label">Último Mantenimiento</label>
                <input type="date" class="form-control" id="fechaMttoEditar<?= $row['idmac'] ?>" 
                       name="fecha_mtto" value="<?= !empty($row['fecha_ultimo_mtto']) ? date('Y-m-d', strtotime($row['fecha_ultimo_mtto'])) : '' ?>">
              </div>
              
              <div class="mb-3">
                <label for="proximoMttoEditar<?= $row['idmac'] ?>" class="form-label">Próximo Mantenimiento</label>
                <input type="date" class="form-control" id="proximoMttoEditar<?= $row['idmac'] ?>" 
                       name="proximo_mtto" value="<?= !empty($row['proximo_mtto']) ? date('Y-m-d', strtotime($row['proximo_mtto'])) : '' ?>">
              </div>
              
              <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="garantiaEditar<?= $row['idmac'] ?>" 
                       name="garantia_vigente" <?= $row['garantia_vigente'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="garantiaEditar<?= $row['idmac'] ?>">Garantía vigente</label>
              </div>
              
              <div class="mb-3">
                <label for="motivoEditar<?= $row['idmac'] ?>" class="form-label">Motivo del Cambio</label>
                <textarea class="form-control" id="motivoEditar<?= $row['idmac'] ?>" 
                          name="motivo" rows="2"></textarea>
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
                                        <th>Motivo</th>
                                        <th width="120">Usuario</th>
                                        <th width="180">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historialPorMaquina[$row['idmac']] as $hist): ?>
                                        <tr>
                                            <td><?= $hist['id'] ?></td>
                                            <td><?= htmlspecialchars($hist['descripcion'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($hist['motivo_cambio'] ?? '') ?></td>
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