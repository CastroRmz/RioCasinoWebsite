<?php
session_start();
require_once '../../../conexion.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

$usuario_id = $_SESSION["usuario_id"];
$rol = $_SESSION["rol"];
$empleado_id = $_SESSION["empleado_id"] ?? 0;
$mensaje = "";

// Configuración de paginación
$por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $por_pagina;

// Procesar eliminación de ticket
if (isset($_GET['eliminar'])) {
    if ($rol == 'SuperAdmin') {
        $ticket_id = intval($_GET['eliminar']);
        
        // Registrar en historial antes de eliminar
        $stmt_hist = $conn->prepare("INSERT INTO historial_tickets (ticket_id, accion, usuario_id, detalles) 
                                    SELECT id, 'Eliminación', ?, CONCAT('Ticket eliminado: ', asunto) 
                                    FROM tickets WHERE id = ?");
        $stmt_hist->bind_param("ii", $usuario_id, $ticket_id);
        $stmt_hist->execute();
        $stmt_hist->close();
        
        // Eliminar ticket
        $stmt = $conn->prepare("DELETE FROM tickets WHERE id = ?");
        $stmt->bind_param("i", $ticket_id);
        
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Ticket eliminado correctamente.";
            header("Location: ".str_replace('&eliminar='.$ticket_id, '', $_SERVER['REQUEST_URI']));
            exit();
        } else {
            $mensaje = "Error al eliminar ticket: " . $stmt->error;
        }
    } else {
        $mensaje = "No tienes permisos para eliminar tickets.";
    }
}

// Obtener datos para formularios
$empleados = $conn->query("
    SELECT e.empleado_id, e.nombre, c.nombre AS cargo, d.nombre AS area
    FROM empleados e
    LEFT JOIN cargos c ON e.cargoID = c.Cargo_id
    LEFT JOIN departamentos d ON e.departamentoID = d.departamento_id
    ORDER BY e.nombre
") or die("Error al obtener empleados: " . $conn->error);

$maquinas = $conn->query("
    SELECT idmac, nombre AS nombre_maquina, modelo, marca, numero_serie, ebox_mac, estado
    FROM maquinas
    WHERE estado = 'Alta'
    ORDER BY nombre
") or die("Error al obtener máquinas: " . $conn->error);

$series_validas = [];
$info_maquinas = [];
while ($m = $maquinas->fetch_assoc()) {
    $series_validas[] = $m['numero_serie'];
    $series_validas[] = $m['ebox_mac'];
    $info_maquinas[$m['numero_serie'] = $m;
    $info_maquinas[$m['ebox_mac']] = $m;
}

$departamentos = $conn->query("SELECT departamento_id, nombre FROM departamentos ORDER BY nombre") 
                or die("Error al obtener departamentos: " . $conn->error);

$administradores = $conn->query("SELECT Usuario_id, usuario FROM usuarios WHERE Rol IN ('Admin', 'SuperAdmin') ORDER BY usuario") 
                 or die("Error al obtener administradores: " . $conn->error);

// Procesar formulario de tickets
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["accion"]) && $_POST["accion"] == "crear_ticket") {
        $tipo_equipo = $_POST["tipo_equipo"] ?? '';
        $empleado_id = intval($_POST["empleado_id"] ?? 0);
        $numero_serie = trim($_POST["numero_serie"] ?? '');
        $descripcion = trim($_POST["descripcion"] ?? '');
        $asunto = trim($_POST["asunto"] ?? '');
        $departamento_id = intval($_POST["departamento_id"] ?? 0);
        $estado = "Abierto";
        $ubicacion = '';
        
        // Validaciones
        if (empty($asunto) || empty($descripcion) || empty($departamento_id) || empty($empleado_id)) {
            $mensaje = "Error: Todos los campos obligatorios deben completarse";
        } elseif ($tipo_equipo == 'maquina_juego' && !in_array($numero_serie, $series_validas)) {
            $mensaje = "Error: El número de serie no está registrado en el sistema";
        }
        
        if (empty($mensaje)) {
            // Obtener ubicación
            $stmt_dep = $conn->prepare("SELECT nombre FROM departamentos WHERE departamento_id = ?");
            $stmt_dep->bind_param("i", $departamento_id);
            $stmt_dep->execute();
            $result_dep = $stmt_dep->get_result();
            if ($dep = $result_dep->fetch_assoc()) {
                $ubicacion = $dep['nombre'];
            }
            $stmt_dep->close();
            
            // Crear ticket
            $stmt = $conn->prepare("INSERT INTO tickets (empleado_id, numero_serie, descripcion, estado, 
                                  creado_por, asunto, tipo_equipo, ubicacion, departamento_id) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssisssi", $empleado_id, $numero_serie, $descripcion, $estado, 
                            $usuario_id, $asunto, $tipo_equipo, $ubicacion, $departamento_id);

            if ($stmt->execute()) {
                $ticket_id = $stmt->insert_id;
                
                // Registrar en historial
                $stmt_hist = $conn->prepare("INSERT INTO historial_tickets (ticket_id, accion, usuario_id, detalles) 
                                           VALUES (?, 'Creación', ?, 'Ticket creado')");
                $stmt_hist->bind_param("ii", $ticket_id, $usuario_id);
                $stmt_hist->execute();
                $stmt_hist->close();
                
                $_SESSION['mensaje'] = "Ticket creado correctamente.";
                header("Location: ver_ticket.php?id=$ticket_id");
                exit();
            } else {
                $mensaje = "Error al crear ticket: " . $stmt->error;
            }
            $stmt->close();
        }
    } 
    elseif (isset($_POST["accion"]) && $_POST["accion"] == "actualizar_ticket" && in_array($rol, ['SuperAdmin', 'Admin'])) {
        // Procesar actualización de ticket
        $ticket_id = intval($_POST["ticket_id"]);
        $nuevo_estado = $_POST["nuevo_estado"];
        $comentarios = trim($_POST["comentarios"] ?? '');
        $asignado_a = intval($_POST["asignado_a"] ?? 0);
        $asunto = trim($_POST["asunto"] ?? '');
        $descripcion = trim($_POST["descripcion"] ?? '');
        $tipo_equipo = $_POST["tipo_equipo"] ?? '';
        $empleado_id = intval($_POST["empleado_id"] ?? 0);
        $numero_serie = trim($_POST["numero_serie"] ?? '');
        $departamento_id = intval($_POST["departamento_id"] ?? 0);
        $ubicacion = '';
        
        // Validaciones
        if (empty($asunto) || empty($descripcion) || empty($departamento_id) || empty($empleado_id)) {
            $mensaje = "Error: Todos los campos obligatorios deben completarse";
        } elseif ($tipo_equipo == 'maquina_juego' && !in_array($numero_serie, $series_validas)) {
            $mensaje = "Error: El número de serie no está registrado en el sistema";
        }
        
        if (empty($mensaje)) {
            // Obtener ubicación
            $stmt_dep = $conn->prepare("SELECT nombre FROM departamentos WHERE departamento_id = ?");
            $stmt_dep->bind_param("i", $departamento_id);
            $stmt_dep->execute();
            $result_dep = $stmt_dep->get_result();
            if ($dep = $result_dep->fetch_assoc()) {
                $ubicacion = $dep['nombre'];
            }
            $stmt_dep->close();
            
            // Obtener estado anterior para el historial
            $stmt_ant = $conn->prepare("SELECT estado FROM tickets WHERE id = ?");
            $stmt_ant->bind_param("i", $ticket_id);
            $stmt_ant->execute();
            $result_ant = $stmt_ant->get_result();
            $estado_anterior = $result_ant->fetch_assoc()['estado'] ?? '';
            $stmt_ant->close();
            
            // Actualizar ticket
            $stmt = $conn->prepare("UPDATE tickets SET 
                estado = ?, 
                comentarios = ?, 
                asignado_a = ?,
                asunto = ?,
                descripcion = ?,
                tipo_equipo = ?,
                empleado_id = ?,
                numero_serie = ?,
                departamento_id = ?,
                ubicacion = ?,
                fecha_proceso = IF(? = 'En proceso' AND estado != 'En proceso', NOW(), fecha_proceso),
                fecha_cierre = IF(? = 'Cerrado' AND estado != 'Cerrado', NOW(), fecha_cierre)
                WHERE id = ?");
            
            $stmt->bind_param("ssissssissii", 
                $nuevo_estado, 
                $comentarios, 
                $asignado_a,
                $asunto,
                $descripcion,
                $tipo_equipo,
                $empleado_id,
                $numero_serie,
                $departamento_id,
                $ubicacion,
                $nuevo_estado,
                $nuevo_estado,
                $ticket_id);
            
            if ($stmt->execute()) {
                // Registrar cambio en el historial
                $detalles_historial = "Ticket actualizado";
                
                if ($estado_anterior != $nuevo_estado) {
                    $detalles_historial .= ". Cambio de estado: $estado_anterior → $nuevo_estado";
                }
                
                if (!empty($comentarios)) {
                    $detalles_historial .= ". Comentarios añadidos";
                }
                
                $stmt_hist = $conn->prepare("INSERT INTO historial_tickets (ticket_id, accion, usuario_id, detalles) 
                                           VALUES (?, 'Actualización', ?, ?)");
                $stmt_hist->bind_param("iis", $ticket_id, $usuario_id, $detalles_historial);
                $stmt_hist->execute();
                $stmt_hist->close();
                
                $_SESSION['mensaje'] = "Ticket actualizado correctamente.";
                header("Location: ver_ticket.php?id=$ticket_id");
                exit();
            } else {
                $mensaje = "Error al actualizar ticket: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Mostrar mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

// Construir consulta base para tickets
$query_base = "
    SELECT t.*, e.nombre AS empleado_nombre, u.usuario AS creador, 
           a.usuario AS asignado, d.nombre AS nombre_departamento,
           m.nombre AS nombre_maquina, m.modelo, m.marca, m.ebox_mac, m.estado AS estado_maquina
    FROM tickets t
    LEFT JOIN empleados e ON t.empleado_id = e.empleado_id
    LEFT JOIN usuarios u ON t.creado_por = u.Usuario_id
    LEFT JOIN usuarios a ON t.asignado_a = a.Usuario_id
    LEFT JOIN departamentos d ON t.departamento_id = d.departamento_id
    LEFT JOIN maquinas m ON t.numero_serie = m.numero_serie OR t.numero_serie = m.ebox_mac
";

// Filtros según rol de usuario
if ($rol == 'SuperAdmin') {
    $query = $query_base . " ORDER BY t.fecha_creacion DESC LIMIT $por_pagina OFFSET $offset";
    $query_count = "SELECT COUNT(*) AS total FROM tickets";
} else {
    $query = $query_base . " WHERE (t.creado_por = $usuario_id OR t.asignado_a = $usuario_id) 
                            ORDER BY t.fecha_creacion DESC LIMIT $por_pagina OFFSET $offset";
    $query_count = "SELECT COUNT(*) AS total FROM tickets WHERE creado_por = $usuario_id OR asignado_a = $usuario_id";
}

// Obtener tickets paginados
$tickets = $conn->query($query) or die("Error al obtener tickets: " . $conn->error);

// Obtener total de tickets para paginación
$result_count = $conn->query($query_count);
$total_tickets = $result_count->fetch_assoc()['total'];
$total_paginas = ceil($total_tickets / $por_pagina);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistema de Tickets - Rio Casino</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <style>
    :root {
      --primary-color: #2c3e50;
      --secondary-color: #3498db;
      --success-color: #28a745;
      --warning-color: #ffc107;
      --danger-color: #dc3545;
      --light-color: #f8f9fa;
      --dark-color: #343a40;
    }
    
    body {
      background-color: #f5f5f5;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .card-header {
      background-color: var(--primary-color);
      color: white;
    }
    
    .nav-tabs .nav-link {
      font-weight: 500;
      color: var(--dark-color);
      border: none;
      border-bottom: 3px solid transparent;
      padding: 0.75rem 1.5rem;
    }
    
    .nav-tabs .nav-link.active {
      color: var(--secondary-color);
      background-color: transparent;
      border-color: var(--secondary-color);
      font-weight: 600;
    }
    
    .badge-estado {
      font-size: 0.85rem;
      padding: 0.35rem 0.75rem;
      font-weight: 500;
    }
    
    .estado-abierto { background-color: var(--danger-color); }
    .estado-proceso { background-color: var(--warning-color); color: var(--dark-color); }
    .estado-cerrado { background-color: var(--success-color); }
    
    .card-ticket {
      transition: all 0.3s ease;
      margin-bottom: 1.5rem;
      border: 1px solid rgba(0,0,0,0.1);
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .card-ticket:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 12px rgba(0,0,0,0.1);
    }
    
    .equipo-info {
      background-color: #f0f8ff;
      border-left: 4px solid var(--secondary-color);
      padding: 1rem;
      margin-bottom: 1rem;
      border-radius: 0 0.25rem 0.25rem 0;
    }
    
    .info-detallada {
      background-color: var(--light-color);
      padding: 1rem;
      border-radius: 0.25rem;
      margin-top: 1rem;
    }
    
    .ticket-fechas {
      font-size: 0.85rem;
      color: #6c757d;
    }
    
    .pagination .page-item.active .page-link {
      background-color: var(--secondary-color);
      border-color: var(--secondary-color);
    }
    
    .pagination .page-link {
      color: var(--secondary-color);
    }
    
    @media (max-width: 768px) {
      .card-ticket {
        margin-bottom: 1rem;
      }
      
      .nav-tabs .nav-link {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
      }
    }
  </style>
</head>
<body>
<div class="container-fluid py-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h2 class="mb-0"><i class="bi bi-ticket-detailed me-2"></i>Gestión de Tickets</h2>
      <div>
        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalTicket">
          <i class="bi bi-plus-circle me-2"></i>Nuevo Ticket
        </button>
      </div>
    </div>
    
    <div class="card-body">
      <?php if ($mensaje != ""): ?>
        <div class="alert alert-info alert-dismissible fade show">
          <?php echo htmlspecialchars($mensaje); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
      <?php endif; ?>
      
      <!-- Filtros y búsqueda -->
      <div class="row mb-4">
        <div class="col-md-6">
          <div class="input-group">
            <input type="text" class="form-control" placeholder="Buscar tickets..." id="buscarTickets">
            <button class="btn btn-outline-secondary" type="button">
              <i class="bi bi-search"></i>
            </button>
          </div>
        </div>
        <div class="col-md-6">
          <div class="d-flex justify-content-end">
            <div class="btn-group" role="group">
              <button type="button" class="btn btn-outline-secondary active">Todos</button>
              <button type="button" class="btn btn-outline-secondary">Abiertos</button>
              <button type="button" class="btn btn-outline-secondary">En proceso</button>
              <button type="button" class="btn btn-outline-secondary">Cerrados</button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Listado de Tickets -->
      <div class="row">
        <?php if ($tickets->num_rows > 0): ?>
          <?php while ($ticket = $tickets->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4">
              <div class="card card-ticket">
                <div class="card-header">
                  <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Ticket #<?php echo $ticket['id']; ?></h5>
                    <span class="badge badge-estado estado-<?php echo strtolower(str_replace(' ', '-', $ticket['estado'])); ?>">
                      <?php echo $ticket['estado']; ?>
                    </span>
                  </div>
                  <div class="ticket-fechas mt-2">
                    <small>
                      <i class="bi bi-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($ticket['fecha_creacion'])); ?>
                    </small>
                  </div>
                </div>
                
                <div class="card-body">
                  <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($ticket['asunto']); ?></h6>
                  
                  <?php if ($ticket['tipo_equipo'] == 'maquina_juego' && !empty($ticket['numero_serie'])): ?>
                    <div class="equipo-info">
                      <p class="mb-1"><strong>Máquina:</strong> <?php echo htmlspecialchars($ticket['nombre_maquina'] ?? 'N/A'); ?></p>
                      <p class="mb-1"><strong>Modelo:</strong> <?php echo htmlspecialchars($ticket['modelo'] ?? 'N/A'); ?></p>
                      <p class="mb-1"><strong>N° Serie:</strong> <?php echo htmlspecialchars($ticket['numero_serie']); ?></p>
                    </div>
                  <?php elseif (!empty($ticket['tipo_equipo'])): ?>
                    <div class="equipo-info">
                      <p class="mb-1"><strong>Tipo:</strong> <?php echo ucfirst(str_replace('_', ' ', $ticket['tipo_equipo'])); ?></p>
                      <?php if (!empty($ticket['numero_serie'])): ?>
                        <p class="mb-1"><strong>N° Serie:</strong> <?php echo htmlspecialchars($ticket['numero_serie']); ?></p>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                  
                  <p class="card-text text-truncate"><?php echo htmlspecialchars($ticket['descripcion']); ?></p>
                  
                  <div class="d-flex justify-content-between text-muted small">
                    <div>
                      <strong>Solicitante:</strong> <?php echo htmlspecialchars($ticket['empleado_nombre']); ?>
                    </div>
                    <div>
                      <strong>Ubicación:</strong> <?php echo htmlspecialchars($ticket['nombre_departamento'] ?? 'N/A'); ?>
                    </div>
                  </div>
                  
                  <?php if (!empty($ticket['asignado'])): ?>
                    <div class="mt-2">
                      <strong>Asignado a:</strong> <?php echo htmlspecialchars($ticket['asignado']); ?>
                    </div>
                  <?php endif; ?>
                </div>
                
                <div class="card-footer bg-transparent">
                  <div class="d-flex justify-content-between">
                    <a href="ver_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-eye"></i> Ver
                    </a>
                    
                    <?php if ($rol == 'SuperAdmin' || ($rol == 'Admin' && $ticket['creado_por'] == $usuario_id)): ?>
                      <a href="editar_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-warning">
                        <i class="bi bi-pencil"></i> Editar
                      </a>
                    <?php endif; ?>
                    
                    <?php if ($rol == 'SuperAdmin'): ?>
                      <a href="?eliminar=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-danger" 
                         onclick="return confirm('¿Estás seguro de eliminar este ticket?')">
                        <i class="bi bi-trash"></i> Eliminar
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="col-12">
            <div class="alert alert-info text-center">
              <i class="bi bi-info-circle-fill me-2"></i> No se encontraron tickets
            </div>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- Paginación -->
      <?php if ($total_paginas > 1): ?>
        <nav aria-label="Paginación de tickets">
          <ul class="pagination justify-content-center">
            <?php if ($pagina_actual > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?>" aria-label="Anterior">
                  <span aria-hidden="true">&laquo;</span>
                </a>
              </li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
              <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
              </li>
            <?php endfor; ?>
            
            <?php if ($pagina_actual < $total_paginas): ?>
              <li class="page-item">
                <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?>" aria-label="Siguiente">
                  <span aria-hidden="true">&raquo;</span>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal para crear ticket -->
<div class="modal fade" id="modalTicket" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Crear Nuevo Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="accion" value="crear_ticket">

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label fw-bold required-field">Tipo de Equipo</label>
            <select name="tipo_equipo" id="tipoEquipo" class="form-select" required onchange="toggleCamposEquipo()">
              <option value="">-- Seleccione el tipo --</option>
              <option value="maquina_juego">Máquina de Juego</option>
              <option value="impresora">Impresora</option>
              <option value="computadora">Computadora</option>
              <option value="laptop">Laptop</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          
          <div class="col-md-6">
            <label class="form-label fw-bold required-field">Asunto</label>
            <input type="text" name="asunto" class="form-control" required placeholder="Resumen del problema">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label fw-bold required-field">Departamento</label>
            <select name="departamento_id" class="form-select" required>
              <option value="">-- Seleccione departamento --</option>
              <?php 
              $departamentos->data_seek(0);
              while ($dep = $departamentos->fetch_assoc()): ?>
                <option value="<?php echo $dep['departamento_id']; ?>">
                  <?php echo htmlspecialchars($dep['nombre']); ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <div id="camposMaquina" style="display: none;">
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label fw-bold required-field">Número de Serie/MAC</label>
              <div class="input-group">
                <input type="text" name="numero_serie" id="numeroSerie" class="form-control" 
                       placeholder="Ingrese o escanee el número de serie">
                <button type="button" class="btn btn-outline-primary" onclick="iniciarEscaneo()">
                  <i class="bi bi-upc-scan"></i> Escanear
                </button>
              </div>
              <div id="serieValida" class="alert alert-success mt-2" style="display: none;">
                <i class="bi bi-check-circle-fill"></i> <span id="validoText"></span>
              </div>
              <div id="serieInvalida" class="alert alert-danger mt-2" style="display: none;">
                <i class="bi bi-exclamation-circle-fill"></i> Número de serie no registrado
              </div>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label fw-bold required-field">Seleccionar Empleado</label>
            <select name="empleado_id" id="empleadoSelect" class="form-select" required onchange="actualizarDatosEmpleado()">
              <option value="">-- Selecciona un empleado --</option>
              <?php 
              $empleados->data_seek(0);
              while ($e = $empleados->fetch_assoc()): ?>
                <option 
                  value="<?php echo $e['empleado_id']; ?>" 
                  data-nombre="<?php echo htmlspecialchars($e['nombre']); ?>"
                  data-cargo="<?php echo htmlspecialchars($e['cargo']); ?>"
                  data-area="<?php echo htmlspecialchars($e['area']); ?>"
                >
                  <?php echo htmlspecialchars($e['nombre']); ?> (<?php echo htmlspecialchars($e['cargo']); ?>)
                </option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <div id="datosEmpleado" class="alert alert-info mb-4" style="display: none;">
          <h6 class="mb-2">Información del Solicitante</h6>
          <div class="row">
            <div class="col-md-4">
              <p><strong>Nombre:</strong> <span id="nombreEmp" class="text-primary"></span></p>
            </div>
            <div class="col-md-4">
              <p><strong>Cargo:</strong> <span id="cargoEmp"></span></p>
            </div>
            <div class="col-md-4">
              <p><strong>Área:</strong> <span id="areaEmp"></span></p>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold required-field">Descripción del Problema</label>
          <textarea name="descripcion" class="form-control" rows="4" required 
                    placeholder="Describa el problema con detalle..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle"></i> Cancelar
        </button>
        <button type="submit" class="btn btn-success">
          <i class="bi bi-check-circle"></i> Crear Ticket
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Contenedor para el escáner -->
<div id="scanner-container">
  <video id="scanner-video"></video>
  <button id="scanner-close" class="btn btn-danger btn-lg">
    <i class="bi bi-x-circle"></i> Cerrar
  </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
<script>
// Mostrar/ocultar campos según tipo de equipo
function toggleCamposEquipo() {
  const tipo = document.getElementById("tipoEquipo").value;
  const camposMaquina = document.getElementById("camposMaquina");
  
  if (tipo === 'maquina_juego') {
    camposMaquina.style.display = 'block';
    document.getElementById("numeroSerie").required = true;
  } else {
    camposMaquina.style.display = 'none';
    document.getElementById("numeroSerie").required = false;
    document.getElementById("serieValida").style.display = 'none';
    document.getElementById("serieInvalida").style.display = 'none';
  }
}

// Actualizar info del empleado seleccionado
function actualizarDatosEmpleado() {
  const select = document.getElementById("empleadoSelect");
  const selected = select.options[select.selectedIndex];
  const datosDiv = document.getElementById("datosEmpleado");

  if (selected.value !== "") {
    document.getElementById("nombreEmp").textContent = selected.dataset.nombre || 'No disponible';
    document.getElementById("cargoEmp").textContent = selected.dataset.cargo || 'No disponible';
    document.getElementById("areaEmp").textContent = selected.dataset.area || 'No disponible';
    datosDiv.style.display = 'block';
  } else {
    datosDiv.style.display = 'none';
  }
}

// Validar número de serie en tiempo real
document.getElementById("numeroSerie").addEventListener('input', function() {
  const serie = this.value.trim();
  const valido = document.getElementById("serieValida");
  const invalido = document.getElementById("serieInvalida");
  const tipoEquipo = document.getElementById("tipoEquipo").value;
  
  if (serie.length === 0 || tipoEquipo !== 'maquina_juego') {
    valido.style.display = 'none';
    invalido.style.display = 'none';
    return;
  }
  
  const seriesValidas = <?php echo json_encode($series_validas); ?>;
  const infoMaquinas = <?php echo json_encode($info_maquinas); ?>;
  
  if (seriesValidas.includes(serie)) {
    const maquina = infoMaquinas[serie];
    let infoAdicional = `<strong>Número de serie válido</strong>`;
    
    if (maquina) {
      infoAdicional += `<br><strong>Máquina:</strong> ${maquina.nombre_maquina || 'No disponible'}`;
      infoAdicional += `<br><strong>Modelo:</strong> ${maquina.modelo || 'No disponible'}`;
      infoAdicional += `<br><strong>Marca:</strong> ${maquina.marca || 'No disponible'}`;
      infoAdicional += `<br><strong>Estado:</strong> ${maquina.estado || 'No disponible'}`;
    }
    
    document.getElementById("validoText").innerHTML = infoAdicional;
    valido.style.display = 'block';
    invalido.style.display = 'none';
  } else {
    valido.style.display = 'none';
    invalido.style.display = 'block';
  }
});

// Escáner de código de barras
function iniciarEscaneo(targetField = 'numeroSerie') {
  const scannerContainer = document.getElementById("scanner-container");
  const video = document.getElementById("scanner-video");
  
  scannerContainer.style.display = 'block';
  
  Quagga.init({
    inputStream: {
      name: "Live",
      type: "LiveStream",
      target: video,
      constraints: {
        width: 480,
        height: 320,
        facingMode: "environment"
      },
    },
    decoder: {
      readers: ["code_128_reader", "ean_reader", "ean_8_reader", "code_39_reader"]
    },
  }, function(err) {
    if (err) {
      console.error(err);
      alert("Error al iniciar el escáner: " + err.message);
      scannerContainer.style.display = 'none';
      return;
    }
    Quagga.start();
  });
  
  Quagga.onDetected(function(result) {
    const code = result.codeResult.code;
    document.getElementById(targetField).value = code;
    Quagga.stop();
    scannerContainer.style.display = 'none';
    
    // Disparar evento input para validar automáticamente
    const event = new Event('input');
    document.getElementById(targetField).dispatchEvent(event);
    
    // Mostrar notificación
    mostrarNotificacion('Código escaneado: ' + code, 'success');
  });
}

// Cerrar escáner
document.getElementById("scanner-close").addEventListener('click', function() {
  if (typeof Quagga !== 'undefined' && Quagga) {
    Quagga.stop();
  }
  document.getElementById("scanner-container").style.display = 'none';
});

// Mostrar notificación
function mostrarNotificacion(mensaje, tipo = 'info') {
  const toastHTML = `
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
      <div class="toast show align-items-center text-white bg-${tipo} border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">
            ${mensaje}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
        </div>
      </div>
    </div>
  `;
  
  const toastContainer = document.createElement('div');
  toastContainer.innerHTML = toastHTML;
  document.body.appendChild(toastContainer);
  
  setTimeout(() => {
    toastContainer.remove();
  }, 3000);
}

// Inicializar tooltips
document.addEventListener('DOMContentLoaded', function() {
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>
</body>
</html>