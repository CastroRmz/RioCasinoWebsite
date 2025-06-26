<?php
session_start();
include("../../../conexion.php");

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

$usuario_id = $_SESSION["usuario_id"];
$rol = $_SESSION["rol"];
$empleado_id = isset($_SESSION["empleado_id"]) ? $_SESSION["empleado_id"] : 0;
$mensaje = "";

// Procesar eliminación de ticket
if (isset($_GET['eliminar'])) {
    if ($rol == 'SuperAdmin') {
        $ticket_id = intval($_GET['eliminar']);
        $stmt = $conn->prepare("DELETE FROM tickets WHERE id = ?");
        $stmt->bind_param("i", $ticket_id);
        if ($stmt->execute()) {
            $mensaje = "Ticket eliminado correctamente.";
            header("Location: ".str_replace('&eliminar='.$ticket_id, '', $_SERVER['REQUEST_URI']));
            exit();
        } else {
            $mensaje = "Error al eliminar ticket: " . $stmt->error;
        }
    } else {
        $mensaje = "No tienes permisos para eliminar tickets.";
    }
}

// Obtener todos los empleados
$empleados = $conn->query("
    SELECT e.empleado_id, e.nombre, c.nombre AS cargo, d.nombre AS area
    FROM empleados e
    LEFT JOIN cargos c ON e.cargoID = c.Cargo_id
    LEFT JOIN departamentos d ON e.departamentoID = d.departamento_id
") or die("Error al obtener empleados: " . $conn->error);

// Obtener máquinas activas
$maquinas = $conn->query("
    SELECT idmac, nombre AS nombre_maquina, modelo, marca, numero_serie, ebox_mac, estado
    FROM maquinas
") or die("Error al obtener máquinas: " . $conn->error);

$series_validas = array();
$info_maquinas = array();
while ($m = $maquinas->fetch_assoc()) {
    $series_validas[] = $m['numero_serie'];
    $series_validas[] = $m['ebox_mac'];
    $info_maquinas[$m['numero_serie']] = $m;
    $info_maquinas[$m['ebox_mac']] = $m;
}

// Obtener todos los departamentos
$departamentos = $conn->query("SELECT departamento_id, nombre FROM departamentos") 
                or die("Error al obtener departamentos: " . $conn->error);

// Obtener usuarios administradores
$administradores = $conn->query("SELECT Usuario_id, usuario FROM usuarios WHERE Rol = 'Admin'") 
                 or die("Error al obtener administradores: " . $conn->error);

// Procesar formulario de tickets
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion"])) {
    if ($_POST["accion"] == "crear_ticket") {
        $tipo_equipo = isset($_POST["tipo_equipo"]) ? $_POST["tipo_equipo"] : '';
        $empleado_id = isset($_POST["empleado_id"]) ? intval($_POST["empleado_id"]) : 0;
        $numero_serie = isset($_POST["numero_serie"]) ? trim($_POST["numero_serie"]) : '';
        $descripcion = isset($_POST["descripcion"]) ? trim($_POST["descripcion"]) : '';
        $asunto = isset($_POST["asunto"]) ? trim($_POST["asunto"]) : '';
        $departamento_id = isset($_POST["departamento_id"]) ? intval($_POST["departamento_id"]) : null;
        $estado = "Abierto";
        
        // Obtener nombre del departamento
        $ubicacion = '';
        if ($departamento_id) {
            $stmt_dep = $conn->prepare("SELECT nombre FROM departamentos WHERE departamento_id = ?");
            $stmt_dep->bind_param("i", $departamento_id);
            $stmt_dep->execute();
            $result_dep = $stmt_dep->get_result();
            if ($dep = $result_dep->fetch_assoc()) {
                $ubicacion = $dep['nombre'];
            }
            $stmt_dep->close();
        }
        
        // Validar número de serie para máquinas de juego
        if ($tipo_equipo == 'maquina_juego' && !in_array($numero_serie, $series_validas)) {
            $mensaje = "Error: El número de serie no está registrado en el sistema";
        }
        
        if ($mensaje == "") {
            $stmt = $conn->prepare("INSERT INTO tickets (empleado_id, numero_serie, descripcion, estado, 
                                  creado_por, asunto, tipo_equipo, ubicacion, departamento_id) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssisssi", $empleado_id, $numero_serie, $descripcion, $estado, 
                            $usuario_id, $asunto, $tipo_equipo, $ubicacion, $departamento_id);

            if ($stmt->execute()) {
                $mensaje = "Ticket creado correctamente.";
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            } else {
                $mensaje = "Error al crear ticket: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($_POST["accion"] == "actualizar_ticket" && $rol == 'SuperAdmin') {
        // Actualizar ticket
        $ticket_id = isset($_POST["ticket_id"]) ? intval($_POST["ticket_id"]) : 0;
        $nuevo_estado = isset($_POST["nuevo_estado"]) ? $_POST["nuevo_estado"] : '';
        $comentarios = isset($_POST["comentarios"]) ? trim($_POST["comentarios"]) : '';
        $asignado_a = isset($_POST["asignado_a"]) ? intval($_POST["asignado_a"]) : 0;
        
        $stmt = $conn->prepare("UPDATE tickets SET estado = ?, comentarios = ?, asignado_a = ?, 
                               fecha_proceso = IF(? = 'En proceso', NOW(), fecha_proceso),
                               fecha_cierre = IF(? = 'Cerrado', NOW(), fecha_cierre)
                               WHERE id = ?");
        $stmt->bind_param("ssisii", $nuevo_estado, $comentarios, $asignado_a, $nuevo_estado, $nuevo_estado, $ticket_id);
        
        if ($stmt->execute()) {
            $mensaje = "Ticket actualizado correctamente.";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            $mensaje = "Error al actualizar ticket: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Obtener tickets con información completa
$tickets = $conn->query("
    SELECT t.*, e.nombre AS empleado_nombre, u.usuario AS creador, 
           a.usuario AS asignado, d.nombre AS nombre_departamento,
           m.nombre AS nombre_maquina, m.modelo, m.marca, m.ebox_mac, m.estado AS estado_maquina
    FROM tickets t
    LEFT JOIN empleados e ON t.empleado_id = e.empleado_id
    LEFT JOIN usuarios u ON t.creado_por = u.Usuario_id
    LEFT JOIN usuarios a ON t.asignado_a = a.Usuario_id
    LEFT JOIN departamentos d ON t.departamento_id = d.departamento_id
    LEFT JOIN maquinas m ON t.numero_serie = m.numero_serie OR t.numero_serie = m.ebox_mac
    ORDER BY t.fecha_creacion DESC
") or die("Error al obtener tickets: " . $conn->error);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Sistema de Tickets - Rio Casino</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <style>
    .datos-empleado {
      background-color: #f8f9fa;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      border: 1px solid #dee2e6;
    }
    .card-ticket {
      transition: all 0.3s ease;
      margin-bottom: 15px;
      position: relative;
    }
    .card-ticket:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .badge-estado {
      font-size: 0.9rem;
      padding: 5px 10px;
    }
    .estado-abierto { background-color: #dc3545; }
    .estado-proceso { background-color: #ffc107; color: #000; }
    .estado-cerrado { background-color: #198754; }
    .equipo-info {
      background-color: #e9ecef;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 10px;
    }
    #scanner-container {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: black;
      z-index: 1000;
      display: none;
    }
    #scanner-video {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    #scanner-close {
      position: absolute;
      top: 20px;
      right: 20px;
      z-index: 1001;
    }
    .required-field::after {
      content: " *";
      color: red;
    }
    .ubicacion-badge {
      background-color: #6c757d;
      color: white;
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 0.8rem;
    }
    .info-maquina {
      background-color: #e7f5ff;
      border-left: 4px solid #339af0;
      padding: 10px;
      margin-bottom: 10px;
      border-radius: 0 4px 4px 0;
    }
    .acciones-ticket {
      display: flex;
      gap: 5px;
      flex-wrap: wrap;
    }
    .info-detallada {
      background-color: #f8f9fa;
      padding: 10px;
      border-radius: 5px;
      margin-top: 10px;
    }
    .ticket-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .ticket-fechas {
      font-size: 0.8rem;
      color: #6c757d;
    }
    .maquina-info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 5px;
    }
    .maquina-info-item {
      margin-bottom: 5px;
    }
    .maquina-info-label {
      font-weight: bold;
    }
    .ticket-detalle {
      margin-bottom: 10px;
    }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Gestión de Tickets</h2>
    <div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTicket">
        <i class="bi bi-plus-circle"></i> Nuevo Ticket
      </button>
    </div>
  </div>

  <?php if ($mensaje != ""): ?>
    <div class="alert alert-info alert-dismissible fade show">
      <?php echo htmlspecialchars($mensaje); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
  <?php endif; ?>

  <!-- Listado de Tickets -->
  <div class="row">
    <?php while ($ticket = $tickets->fetch_assoc()): ?>
      <div class="col-md-6">
        <div class="card card-ticket">
          <div class="card-header">
            <div class="ticket-header">
              <h5 class="card-title mb-0">Ticket #<?php echo $ticket['id']; ?></h5>
              <div>
                <span class="badge badge-estado estado-<?php echo strtolower(str_replace(' ', '-', $ticket['estado'])); ?>">
                  <?php echo $ticket['estado']; ?>
                </span>
                <?php if (!empty($ticket['nombre_departamento'])): ?>
                  <span class="ubicacion-badge ms-2">
                    <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($ticket['nombre_departamento']); ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>
            <div class="ticket-fechas mt-2">
              <small>
                Creado: <?php echo date('d/m/Y H:i', strtotime($ticket['fecha_creacion'])); ?>
                <?php if ($ticket['fecha_proceso']): ?>
                  | En proceso: <?php echo date('d/m/Y H:i', strtotime($ticket['fecha_proceso'])); ?>
                <?php endif; ?>
                <?php if ($ticket['fecha_cierre']): ?>
                  | Cerrado: <?php echo date('d/m/Y H:i', strtotime($ticket['fecha_cierre'])); ?>
                <?php endif; ?>
              </small>
            </div>
          </div>
          <div class="card-body">
            <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($ticket['asunto']); ?></h6>
            
            <?php if ($ticket['tipo_equipo'] == 'maquina_juego' && !empty($ticket['numero_serie'])): ?>
              <div class="equipo-info">
                <div class="maquina-info-grid">
                  <div class="maquina-info-item">
                    <span class="maquina-info-label">Máquina:</span> <?php echo htmlspecialchars($ticket['nombre_maquina'] ?? 'N/A'); ?>
                  </div>
                  <div class="maquina-info-item">
                    <span class="maquina-info-label">Modelo:</span> <?php echo htmlspecialchars($ticket['modelo'] ?? 'N/A'); ?>
                  </div>
                  <div class="maquina-info-item">
                    <span class="maquina-info-label">Marca:</span> <?php echo htmlspecialchars($ticket['marca'] ?? 'N/A'); ?>
                  </div>
                  <div class="maquina-info-item">
                    <span class="maquina-info-label">N° Serie:</span> <?php echo htmlspecialchars($ticket['numero_serie']); ?>
                  </div>
                  <div class="maquina-info-item">
                    <span class="maquina-info-label">eBox MAC:</span> <?php echo htmlspecialchars($ticket['ebox_mac'] ?? 'N/A'); ?>
                  </div>
                  <div class="maquina-info-item">
                    <span class="maquina-info-label">Estado:</span> <?php echo htmlspecialchars($ticket['estado_maquina'] ?? 'N/A'); ?>
                  </div>
                </div>
              </div>
            <?php elseif (!empty($ticket['tipo_equipo'])): ?>
              <div class="equipo-info">
                <p class="mb-1"><strong>Tipo de Equipo:</strong> <?php echo ucfirst(str_replace('_', ' ', $ticket['tipo_equipo'])); ?></p>
                <?php if (!empty($ticket['numero_serie'])): ?>
                  <p class="mb-1"><strong>N° Serie:</strong> <?php echo htmlspecialchars($ticket['numero_serie']); ?></p>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            
            <div class="ticket-detalle">
              <p class="card-text"><?php echo nl2br(htmlspecialchars($ticket['descripcion'])); ?></p>
            </div>
            
            <div class="d-flex justify-content-between text-muted small">
              <div>
                <strong>Solicitante:</strong> <?php echo htmlspecialchars($ticket['empleado_nombre']); ?>
              </div>
              <div>
                <strong>Creado por:</strong> <?php echo htmlspecialchars($ticket['creador']); ?>
              </div>
            </div>
            
            <?php if (!empty($ticket['asignado'])): ?>
              <div class="mt-2">
                <strong>Asignado a:</strong> <?php echo htmlspecialchars($ticket['asignado']); ?>
              </div>
            <?php endif; ?>
            
            <?php if (!empty($ticket['comentarios'])): ?>
              <div class="alert alert-secondary mt-2">
                <strong>Comentarios:</strong>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($ticket['comentarios'])); ?></p>
              </div>
            <?php endif; ?>
          </div>
          
          <div class="card-footer bg-transparent acciones-ticket">
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                    data-bs-target="#modalVerTicket" 
                    data-ticket-id="<?php echo $ticket['id']; ?>">
              <i class="bi bi-eye"></i> Ver
            </button>
            
            <?php if ($rol == 'SuperAdmin'): ?>
              <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" 
                      data-bs-target="#modalEditarTicket" 
                      data-ticket-id="<?php echo $ticket['id']; ?>"
                      data-estado-actual="<?php echo $ticket['estado']; ?>"
                      data-asignado-actual="<?php echo isset($ticket['asignado_a']) ? $ticket['asignado_a'] : ''; ?>"
                      data-comentarios-actual="<?php echo isset($ticket['comentarios']) ? htmlspecialchars($ticket['comentarios']) : ''; ?>">
                <i class="bi bi-pencil"></i> Editar
              </button>
              
              <a href="?eliminar=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-danger" 
                 onclick="return confirm('¿Estás seguro de eliminar este ticket?')">
                <i class="bi bi-trash"></i> Eliminar
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
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
              <div id="serieValida" class="info-maquina mt-2" style="display: none;">
                <i class="bi bi-check-circle text-success"></i> <span id="validoText"></span>
              </div>
              <div id="serieInvalida" class="text-danger mt-2" style="display: none;">
                <i class="bi bi-exclamation-circle"></i> Número de serie no registrado
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

        <div id="datosEmpleado" class="datos-empleado mb-4" style="display: none;">
          <h5 class="mb-3">Información del Solicitante</h5>
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

<!-- Modal para ver ticket -->
<div class="modal fade" id="modalVerTicket" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Detalles del Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="detallesTicket">
        <!-- Los detalles se cargarán aquí mediante JavaScript -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle"></i> Cerrar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal para editar ticket (Solo SuperAdmin) -->
<?php if ($rol == 'SuperAdmin'): ?>
<div class="modal fade" id="modalEditarTicket" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Editar Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="accion" value="actualizar_ticket">
        <input type="hidden" name="ticket_id" id="editarTicketId" value="">

        <div class="mb-3">
          <label class="form-label fw-bold">Estado del Ticket</label>
          <select name="nuevo_estado" id="nuevoEstado" class="form-select">
            <option value="Abierto">Abierto</option>
            <option value="En proceso">En proceso</option>
            <option value="Cerrado">Cerrado</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Asignar a</label>
          <select name="asignado_a" id="asignadoA" class="form-select">
            <option value="0">-- Sin asignar --</option>
            <?php 
            $administradores->data_seek(0);
            while ($admin = $administradores->fetch_assoc()): ?>
              <option value="<?php echo $admin['Usuario_id']; ?>"><?php echo htmlspecialchars($admin['usuario']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Comentarios Adicionales</label>
          <textarea name="comentarios" id="comentariosTicket" class="form-control" rows="3" 
                    placeholder="Agregue comentarios sobre la resolución..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle"></i> Cancelar
        </button>
        <button type="submit" class="btn btn-success">
          <i class="bi bi-check-circle"></i> Guardar Cambios
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

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
function iniciarEscaneo() {
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
    document.getElementById("numeroSerie").value = code;
    Quagga.stop();
    scannerContainer.style.display = 'none';
    
    // Disparar evento input para validar automáticamente
    const event = new Event('input');
    document.getElementById("numeroSerie").dispatchEvent(event);
    
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

// Configurar modal de visualización
const modalVer = document.getElementById('modalVerTicket');
if (modalVer) {
  modalVer.addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const ticketId = button.getAttribute('data-ticket-id');
    const ticketCard = button.closest('.card-ticket');
    
    if (ticketCard) {
      const ticketContent = ticketCard.querySelector('.card-body').innerHTML;
      document.getElementById('detallesTicket').innerHTML = `
        <div class="card">
          <div class="card-body">
            ${ticketContent}
          </div>
        </div>
      `;
    }
  });
}

// Configurar modal de edición para SuperAdmin
<?php if ($rol == 'SuperAdmin'): ?>
const modalEditar = document.getElementById('modalEditarTicket');
if (modalEditar) {
  modalEditar.addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const ticketId = button.getAttribute('data-ticket-id');
    const estadoActual = button.getAttribute('data-estado-actual');
    const asignadoActual = button.getAttribute('data-asignado-actual');
    const comentariosActual = button.getAttribute('data-comentarios-actual');
    
    document.getElementById('editarTicketId').value = ticketId;
    document.getElementById('nuevoEstado').value = estadoActual;
    document.getElementById('asignadoA').value = asignadoActual || 0;
    document.getElementById('comentariosTicket').value = comentariosActual || '';
  });
}
<?php endif; ?>

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

