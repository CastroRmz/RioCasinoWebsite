<?php
session_start();
include("../../../conexion.php");

// Verificar autenticación y rol Admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'Admin') {
    header("Location: ../../../login.php");
    exit();
}

$usuario_id = $_SESSION["usuario_id"];
$mensaje = "";

// Procesar actualización de ticket (comentarios y estado)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["accion"]) && $_POST["accion"] == "actualizar_ticket") {
        $ticket_id = intval($_POST["ticket_id"]);
        $nuevo_estado = $_POST["nuevo_estado"];
        $comentarios = trim($_POST["comentarios"]);
        
        // Verificar que el ticket esté asignado al técnico actual o sea sin asignar
        $stmt_verificar = $conn->prepare("SELECT id FROM tickets WHERE id = ? AND (asignado_a = ? OR asignado_a IS NULL OR asignado_a = 0)");
        $stmt_verificar->bind_param("ii", $ticket_id, $usuario_id);
        $stmt_verificar->execute();
        $stmt_verificar->store_result();
        
        if ($stmt_verificar->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE tickets SET 
                estado = ?, 
                comentarios = CONCAT(IFNULL(comentarios, ''), '\n[', NOW(), ' - ', ?, ']\n', ?),
                fecha_proceso = IF(? = 'En proceso' AND fecha_proceso IS NULL, NOW(), fecha_proceso),
                fecha_cierre = IF(? = 'Cerrado' AND fecha_cierre IS NULL, NOW(), fecha_cierre)
                WHERE id = ?");
            
            $nombre_usuario = $_SESSION['usuario'];
            $stmt->bind_param("sssssi", 
                $nuevo_estado, 
                $nombre_usuario,
                $comentarios,
                $nuevo_estado,
                $nuevo_estado,
                $ticket_id);
            
            if ($stmt->execute()) {
                $_SESSION['notificacion'] = [
                    'mensaje' => "Ticket actualizado correctamente.",
                    'tipo' => 'success'
                ];
                header("Location: ".strtok($_SERVER['REQUEST_URI'], '?'));
                exit();
            } else {
                $_SESSION['notificacion'] = [
                    'mensaje' => "Error al actualizar ticket: " . $stmt->error,
                    'tipo' => 'danger'
                ];
            }
        } else {
            $_SESSION['notificacion'] = [
                'mensaje' => "No tienes permisos para modificar este ticket.",
                'tipo' => 'warning'
            ];
        }
    }
    elseif (isset($_POST["accion"]) && $_POST["accion"] == "asignar_ticket") {
        $ticket_id = intval($_POST["ticket_id"]);
        
        // Solo asignar, sin cambiar el estado
        $stmt = $conn->prepare("UPDATE tickets SET 
            asignado_a = ?
            WHERE id = ? AND (asignado_a IS NULL OR asignado_a = 0)");
        
        $stmt->bind_param("ii", $usuario_id, $ticket_id);
        
        if ($stmt->execute()) {
            $_SESSION['notificacion'] = [
                'mensaje' => "Ticket asignado correctamente (se mantiene en estado Abierto).",
                'tipo' => 'success'
            ];
        } else {
            $_SESSION['notificacion'] = [
                'mensaje' => "Error al asignar ticket: " . $stmt->error,
                'tipo' => 'danger'
            ];
        }
        
        header("Location: ".strtok($_SERVER['REQUEST_URI'], '?'));
        exit();
    }
}

// Obtener parámetros de filtrado
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'asignados';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$filtro_prioridad = isset($_GET['prioridad']) ? intval($_GET['prioridad']) : 0;

// Construir consulta con filtros
$query_tickets = "
    SELECT t.*, e.nombre AS empleado_nombre, u.usuario AS creador, 
           a.usuario AS asignado, d.nombre AS nombre_departamento,
           m.nombre AS nombre_maquina, m.modelo, m.marca, m.ebox_mac, m.estado AS estado_maquina,
           p.nombre AS prioridad, c.nombre AS categoria
    FROM tickets t
    LEFT JOIN empleados e ON t.empleado_id = e.empleado_id
    LEFT JOIN usuarios u ON t.creado_por = u.Usuario_id
    LEFT JOIN usuarios a ON t.asignado_a = a.Usuario_id
    LEFT JOIN departamentos d ON t.departamento_id = d.departamento_id
    LEFT JOIN maquinas m ON t.numero_serie = m.numero_serie OR t.numero_serie = m.ebox_mac
    LEFT JOIN prioridades_ticket p ON t.prioridad_id = p.prioridad_id
    LEFT JOIN categorias_problema c ON t.categoria_id = c.categoria_id
    WHERE 1=1
";

$params = array();
$types = '';

// Filtrar por asignación
if ($filtro == 'asignados') {
    $query_tickets .= " AND t.asignado_a = ?";
    $params[] = $usuario_id;
    $types .= 'i';
} elseif ($filtro == 'sin-asignar') {
    $query_tickets .= " AND (t.asignado_a IS NULL OR t.asignado_a = 0)";
}

// Filtrar por prioridad
if ($filtro_prioridad > 0) {
    $query_tickets .= " AND t.prioridad_id = ?";
    $params[] = $filtro_prioridad;
    $types .= 'i';
}

if ($busqueda != '') {
    $query_tickets .= " AND (t.asunto LIKE ? OR t.descripcion LIKE ? OR t.numero_serie LIKE ? OR e.nombre LIKE ? OR m.nombre LIKE ?)";
    $search_term = "%$busqueda%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sssss';
}

$query_tickets .= " ORDER BY 
    CASE WHEN t.estado = 'Abierto' THEN 1
         WHEN t.estado = 'En proceso' THEN 2
         ELSE 3 END,
    t.fecha_creacion DESC";

// Preparar y ejecutar consulta con filtros
$stmt_tickets = $conn->prepare($query_tickets);
if (!empty($params)) {
    $stmt_tickets->bind_param($types, ...$params);
}
$stmt_tickets->execute();
$tickets = $stmt_tickets->get_result();

// Obtener prioridades para el filtro
$prioridades = $conn->query("SELECT prioridad_id, nombre FROM prioridades_ticket ORDER BY nivel_urgencia DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Sistema de Tickets - Técnico Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
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
    .prioridad-critica { border-left: 4px solid #dc3545; }
    .prioridad-media { border-left: 4px solid #ffc107; }
    .prioridad-baja { border-left: 4px solid #198754; }
    .filtros-container {
      background-color: #f8f9fa;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
    }
    .badge-prioridad {
      font-size: 0.8rem;
      padding: 4px 8px;
    }
    .badge-categoria {
      font-size: 0.8rem;
      padding: 4px 8px;
      background-color: #6c757d;
    }
    .nav-pills .nav-link.active {
      background-color: #0d6efd;
    }
    .comentario-fecha {
      font-size: 0.8rem;
      color: #6c757d;
      font-weight: bold;
    }
    .comentario-contenido {
      background-color: #f8f9fa;
      padding: 8px;
      border-radius: 5px;
      margin-bottom: 10px;
      border-left: 3px solid #0d6efd;
    }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">
      <?= $filtro == 'asignados' ? 'Mis Tickets' : 'Tickets Sin Asignar' ?>
      <span class="badge bg-primary ms-2">
        <?= $tickets->num_rows ?>
      </span>
    </h2>
    <div class="btn-group">
      <a href="?filtro=asignados" class="btn btn-<?= $filtro == 'asignados' ? 'primary' : 'outline-primary' ?>">
        <i class="bi bi-person-check"></i> Mis Tickets
      </a>
      <a href="?filtro=sin-asignar" class="btn btn-<?= $filtro == 'sin-asignar' ? 'primary' : 'outline-primary' ?>">
        <i class="bi bi-person-x"></i> Sin Asignar
      </a>
    </div>
  </div>

  <!-- Filtros de búsqueda -->
  <div class="filtros-container">
    <form method="get" class="row g-3">
      <input type="hidden" name="filtro" value="<?= $filtro ?>">
      
      <div class="col-md-4">
        <label class="form-label">Prioridad</label>
        <select name="prioridad" class="form-select">
          <option value="0">Todas las prioridades</option>
          <?php while ($p = $prioridades->fetch_assoc()): ?>
            <option value="<?= $p['prioridad_id'] ?>" <?= $filtro_prioridad == $p['prioridad_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      
      <div class="col-md-6">
        <label class="form-label">Buscar</label>
        <input type="text" name="busqueda" class="form-control" placeholder="Buscar..." value="<?= htmlspecialchars($busqueda) ?>">
      </div>
      
      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-search"></i> Buscar
        </button>
      </div>
      
      <?php if ($filtro_prioridad > 0 || $busqueda != ''): ?>
        <div class="col-12">
          <a href="?filtro=<?= $filtro ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-x-circle"></i> Limpiar filtros
          </a>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <!-- Notificación -->
  <?php if (isset($_SESSION['notificacion'])): ?>
    <div class="alert alert-<?= $_SESSION['notificacion']['tipo'] ?> alert-dismissible fade show">
      <?= $_SESSION['notificacion']['mensaje'] ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php unset($_SESSION['notificacion']); ?>
  <?php endif; ?>

  <!-- Listado de Tickets -->
  <?php if ($tickets->num_rows > 0): ?>
    <div class="row">
      <?php while ($ticket = $tickets->fetch_assoc()): ?>
        <?php
        // Determinar clase de prioridad
        $prioridad_class = '';
        if ($ticket['prioridad'] == 'Crítica (Alta)') {
            $prioridad_class = 'prioridad-critica';
        } elseif ($ticket['prioridad'] == 'Media') {
            $prioridad_class = 'prioridad-media';
        } elseif ($ticket['prioridad'] == 'Baja') {
            $prioridad_class = 'prioridad-baja';
        }
        ?>
        <div class="col-md-6">
          <div class="card card-ticket <?= $prioridad_class ?>">
            <div class="card-header">
              <div class="ticket-header">
                <h5 class="card-title mb-0">Ticket #<?= $ticket['id'] ?></h5>
                <div>
                  <span class="badge badge-estado estado-<?= strtolower(str_replace(' ', '-', $ticket['estado'])) ?>">
                    <?= $ticket['estado'] ?>
                  </span>
                  <?php if (!empty($ticket['nombre_departamento'])): ?>
                    <span class="ubicacion-badge ms-2">
                      <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($ticket['nombre_departamento']) ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="ticket-fechas mt-2">
                <small>
                  Creado: <?= date('d/m/Y H:i', strtotime($ticket['fecha_creacion'])) ?>
                  <?php if ($ticket['fecha_proceso']): ?>
                    | En proceso: <?= date('d/m/Y H:i', strtotime($ticket['fecha_proceso'])) ?>
                  <?php endif; ?>
                  <?php if ($ticket['fecha_cierre']): ?>
                    | Cerrado: <?= date('d/m/Y H:i', strtotime($ticket['fecha_cierre'])) ?>
                  <?php endif; ?>
                </small>
              </div>
            </div>
            <div class="card-body">
              <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($ticket['asunto']) ?></h6>
              
              <div class="d-flex gap-2 mb-2">
                <?php if (!empty($ticket['prioridad'])): ?>
                  <span class="badge bg-danger badge-prioridad">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($ticket['prioridad']) ?>
                  </span>
                <?php endif; ?>
                <?php if (!empty($ticket['categoria'])): ?>
                  <span class="badge badge-categoria">
                    <i class="bi bi-tag"></i> <?= htmlspecialchars($ticket['categoria']) ?>
                  </span>
                <?php endif; ?>
              </div>
              
              <?php if ($ticket['tipo_equipo'] == 'maquina_juego' && !empty($ticket['numero_serie'])): ?>
                <div class="equipo-info">
                  <div class="maquina-info-grid">
                    <div class="maquina-info-item">
                      <span class="maquina-info-label">Máquina:</span> <?= htmlspecialchars($ticket['nombre_maquina'] ?? 'N/A') ?>
                    </div>
                    <div class="maquina-info-item">
                      <span class="maquina-info-label">Modelo:</span> <?= htmlspecialchars($ticket['modelo'] ?? 'N/A') ?>
                    </div>
                    <div class="maquina-info-item">
                      <span class="maquina-info-label">Marca:</span> <?= htmlspecialchars($ticket['marca'] ?? 'N/A') ?>
                    </div>
                    <div class="maquina-info-item">
                      <span class="maquina-info-label">N° Serie:</span> <?= htmlspecialchars($ticket['numero_serie']) ?>
                    </div>
                    <div class="maquina-info-item">
                      <span class="maquina-info-label">eBox MAC:</span> <?= htmlspecialchars($ticket['ebox_mac'] ?? 'N/A') ?>
                    </div>
                    <div class="maquina-info-item">
                      <span class="maquina-info-label">Estado:</span> <?= htmlspecialchars($ticket['estado_maquina'] ?? 'N/A') ?>
                    </div>
                  </div>
                </div>
              <?php elseif (!empty($ticket['tipo_equipo'])): ?>
                <div class="equipo-info">
                  <p class="mb-1"><strong>Tipo de Equipo:</strong> <?= ucfirst(str_replace('_', ' ', $ticket['tipo_equipo'])) ?></p>
                  <?php if (!empty($ticket['numero_serie'])): ?>
                    <p class="mb-1"><strong>N° Serie:</strong> <?= htmlspecialchars($ticket['numero_serie']) ?></p>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              
              <div class="ticket-detalle">
                <p class="card-text"><?= nl2br(htmlspecialchars($ticket['descripcion'])) ?></p>
              </div>
              
              <div class="d-flex justify-content-between text-muted small">
                <div>
                  <strong>Solicitante:</strong> <?= htmlspecialchars($ticket['empleado_nombre']) ?>
                </div>
                <div>
                  <strong>Creado por:</strong> <?= htmlspecialchars($ticket['creador']) ?>
                </div>
              </div>
              
              <?php if (!empty($ticket['asignado'])): ?>
                <div class="mt-2">
                  <strong>Asignado a:</strong> <?= htmlspecialchars($ticket['asignado']) ?>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($ticket['comentarios'])): ?>
                <div class="mt-3">
                  <h6 class="mb-2"><strong>Historial de Comentarios:</strong></h6>
                  <?php 
                  $comentarios = explode("\n", $ticket['comentarios']);
                  $in_comentario = false;
                  $current_comentario = '';
                  
                  foreach ($comentarios as $linea) {
                      if (preg_match('/^\[(.*?)\]/', $linea, $matches)) {
                          if ($in_comentario) {
                              echo '<div class="comentario-contenido">'.nl2br(htmlspecialchars(trim($current_comentario))).'</div>';
                          }
                          echo '<div class="comentario-fecha">'.$matches[1].'</div>';
                          $current_comentario = '';
                          $in_comentario = true;
                      } elseif ($in_comentario) {
                          $current_comentario .= $linea."\n";
                      }
                  }
                  
                  if ($in_comentario && !empty(trim($current_comentario))) {
                      echo '<div class="comentario-contenido">'.nl2br(htmlspecialchars(trim($current_comentario))).'</div>';
                  }
                  ?>
                </div>
              <?php endif; ?>
            </div>
            
            <div class="card-footer bg-transparent acciones-ticket">
              <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" 
                      data-bs-target="#modalComentarTicket" 
                      data-ticket-id="<?= $ticket['id'] ?>"
                      data-estado-actual="<?= $ticket['estado'] ?>">
                <i class="bi bi-chat-left-text"></i> Actualizar
              </button>
              
              <?php if ($filtro == 'sin-asignar' && (empty($ticket['asignado_a']) || $ticket['asignado_a'] == 0)): ?>
                <form method="post" style="display: inline;">
                  <input type="hidden" name="accion" value="asignar_ticket">
                  <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-person-check"></i> Asignarme
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-info">
      No se encontraron tickets <?= $filtro == 'asignados' ? 'asignados a ti' : 'sin asignar' ?>.
    </div>
  <?php endif; ?>
</div>

<!-- Modal para comentar/actualizar ticket -->
<div class="modal fade" id="modalComentarTicket" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Gestionar Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="accion" value="actualizar_ticket">
        <input type="hidden" name="ticket_id" id="comentarTicketId" value="">
        
        <div class="mb-3">
          <label class="form-label fw-bold">Estado del Ticket</label>
          <select name="nuevo_estado" id="comentarEstado" class="form-select">
            <option value="Abierto" selected>Mantener abierto</option>
            <option value="En proceso">Poner en proceso</option>
            <option value="Cerrado">Cerrar ticket</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Añadir Comentario</label>
          <textarea name="comentarios" id="comentarComentarios" class="form-control" rows="4" 
                    placeholder="Describe las acciones realizadas o la solución aplicada..." required></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle"></i> Cancelar
        </button>
        <button type="submit" class="btn btn-success">
          <i class="bi bi-check-circle"></i> Guardar
        </button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Mostrar notificación si existe
<?php if (isset($_SESSION['notificacion'])): ?>
  Swal.fire({
    icon: '<?= $_SESSION['notificacion']['tipo'] === 'success' ? 'success' : 
             ($_SESSION['notificacion']['tipo'] === 'danger' ? 'error' : 'warning') ?>',
    title: '<?= $_SESSION['notificacion']['mensaje'] ?>',
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
  });
  <?php unset($_SESSION['notificacion']); ?>
<?php endif; ?>

// Configurar modal de comentarios
const modalComentar = document.getElementById('modalComentarTicket');
if (modalComentar) {
  modalComentar.addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const ticketId = button.getAttribute('data-ticket-id');
    const estadoActual = button.getAttribute('data-estado-actual');
    
    document.getElementById('comentarTicketId').value = ticketId;
    
    // Configurar opciones según estado actual
    if (estadoActual === 'Abierto') {
      document.getElementById('comentarEstado').innerHTML = `
        <option value="Abierto" selected>Mantener abierto</option>
        <option value="En proceso">Poner en proceso</option>
        <option value="Cerrado">Cerrar ticket</option>
      `;
    } else if (estadoActual === 'En proceso') {
      document.getElementById('comentarEstado').innerHTML = `
        <option value="Abierto">Reabrir ticket</option>
        <option value="En proceso" selected>Continuar en proceso</option>
        <option value="Cerrado">Cerrar ticket</option>
      `;
    } else {
      // Si está cerrado
      document.getElementById('comentarEstado').innerHTML = `
        <option value="Abierto">Reabrir ticket</option>
        <option value="Cerrado" selected>Cerrado</option>
      `;
      document.getElementById('comentarEstado').disabled = true;
      document.getElementById('comentarComentarios').placeholder = "Añadir comentarios adicionales...";
    }
  });
  
  // Restaurar estado del modal cuando se cierra
  modalComentar.addEventListener('hidden.bs.modal', function() {
    document.getElementById('comentarEstado').disabled = false;
  });
}
</script>
</body>
</html>