<?php
session_start();
include("../../../conexion.php");

$usuario_id = $_SESSION["usuario_id"] ?? 0;
$rol = $_SESSION["rol"] ?? '';
$empleado_id = $_SESSION["empleado_id"] ?? 0;
$msg = "";

// Obtener todos los empleados (CONSULTA CORREGIDA SEGÚN TU ESQUEMA)
$empleados = $conn->query("
    SELECT e.empleado_id, e.nombre, c.nombre AS cargo, d.nombre AS area
    FROM empleados e
    LEFT JOIN cargos c ON e.cargoID = c.Cargo_id
    LEFT JOIN departamentos d ON e.departamentoID = d.departamento_id
");

if (!$empleados) {
    die("Error en la consulta: " . $conn->error);
}

// Insertar ticket
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"]) && $_POST["accion"] === "crear_ticket") {
    $empleado_id = intval($_POST["empleado_id"] ?? 0);
    $numero_serie = trim($_POST["numero_serie"] ?? '');
    $descripcion = trim($_POST["descripcion"] ?? '');
    $estado = "Abierto";

    $stmt = $conn->prepare("INSERT INTO tickets (empleado_id, numero_serie, descripcion, estado, creado_por) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $empleado_id, $numero_serie, $descripcion, $estado, $usuario_id);

    if ($stmt->execute()) {
        $msg = "Ticket creado correctamente.";
        // Recargar la página para ver el nuevo ticket
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        $msg = "Error al crear ticket: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Sistema de Tickets</title>
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
    .card-empleado {
      transition: all 0.3s ease;
    }
    .card-empleado:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Gestión de Tickets</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTicket">
      <i class="bi bi-plus-circle"></i> Nuevo Ticket
    </button>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-info alert-dismissible fade show">
      <?= htmlspecialchars($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- Modal para crear ticket -->
  <div class="modal fade" id="modalTicket" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <form method="post" class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Crear Nuevo Ticket</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="accion" value="crear_ticket">

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Seleccionar Empleado</label>
              <select name="empleado_id" id="empleadoSelect" class="form-select" required onchange="actualizarDatosEmpleado()">
                <option value="">-- Selecciona un empleado --</option>
                <?php while ($e = $empleados->fetch_assoc()): ?>
                  <option 
                    value="<?= $e['empleado_id'] ?>" 
                    data-nombre="<?= htmlspecialchars($e['nombre']) ?>"
                    data-cargo="<?= htmlspecialchars($e['cargo']) ?>"
                    data-area="<?= htmlspecialchars($e['area']) ?>"
                  >
                    <?= htmlspecialchars($e['nombre']) ?> (<?= htmlspecialchars($e['cargo']) ?>)
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>

          <div id="datosEmpleado" class="datos-empleado mb-4" style="display: none;">
            <h5 class="mb-3">Información del Empleado</h5>
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

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Número de Serie</label>
              <div class="input-group">
                <input type="text" name="numero_serie" class="form-control" required placeholder="Ingrese o escanee el número de serie">
                <button type="button" class="btn btn-outline-primary" onclick="simularEscaneo()">
                  <i class="bi bi-upc-scan"></i> Escanear
                </button>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Descripción del Problema</label>
            <textarea name="descripcion" class="form-control" rows="4" required placeholder="Describa el problema con detalle..."></textarea>
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
</div>

<script>
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

function simularEscaneo() {
  // Simulación de escaneo - genera un número aleatorio similar a tus formatos
  const numeroSerie = 'SN-' + Math.floor(Math.random() * 10000).toString().padStart(4, '0');
  document.querySelector('input[name="numero_serie"]').value = numeroSerie;
  
  // Mostrar notificación con Toast de Bootstrap
  const toastHTML = `
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
      <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-success text-white">
          <strong class="me-auto">Escaneo exitoso</strong>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
          Número de serie escaneado: <strong>${numeroSerie}</strong>
        </div>
      </div>
    </div>
  `;
  
  // Crear y mostrar el toast
  const toastContainer = document.createElement('div');
  toastContainer.innerHTML = toastHTML;
  document.body.appendChild(toastContainer);
  
  // Eliminar el toast después de 3 segundos
  setTimeout(() => {
    toastContainer.remove();
  }, 3000);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>