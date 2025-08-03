<?php
require_once '../../conexion.php';

// Consultas para estadísticas
$query_maquinas = "SELECT estado, COUNT(*) as total FROM maquinas GROUP BY estado";
$result_maquinas = mysqli_query($conn, $query_maquinas);
$maquinas_data = [];
while($row = mysqli_fetch_assoc($result_maquinas)) {
    $maquinas_data[$row['estado']] = $row['total'];
}

$query_tickets = "SELECT estado, COUNT(*) as total FROM tickets GROUP BY estado";
$result_tickets = mysqli_query($conn, $query_tickets);
$tickets_data = [];
while($row = mysqli_fetch_assoc($result_tickets)) {
    $tickets_data[$row['estado']] = $row['total'];
}

$query_categorias = "SELECT cp.nombre, COUNT(t.id) as total 
                    FROM categorias_problema cp
                    LEFT JOIN tickets t ON cp.categoria_id = t.categoria_id
                    GROUP BY cp.nombre";
$result_categorias = mysqli_query($conn, $query_categorias);

$query_prioridades = "SELECT p.nombre, COUNT(t.id) as total 
                     FROM prioridades_ticket p
                     LEFT JOIN tickets t ON p.prioridad_id = t.prioridad_id
                     GROUP BY p.nombre";
$result_prioridades = mysqli_query($conn, $query_prioridades);

$query_empleados_dept = "SELECT d.nombre, COUNT(e.empleado_id) as total 
                        FROM departamentos d
                        LEFT JOIN empleados e ON d.departamento_id = e.departamentoID
                        GROUP BY d.nombre";
$result_empleados_dept = mysqli_query($conn, $query_empleados_dept);

$query_empleados_cargo = "SELECT c.nombre, COUNT(e.empleado_id) as total 
                         FROM cargos c
                         LEFT JOIN empleados e ON c.Cargo_id = e.cargoID
                         GROUP BY c.nombre";
$result_empleados_cargo = mysqli_query($conn, $query_empleados_cargo);

$query_lista_empleados = "SELECT e.*, c.nombre as cargo, d.nombre as departamento 
                         FROM empleados e
                         JOIN cargos c ON e.cargoID = c.Cargo_id
                         JOIN departamentos d ON e.departamentoID = d.departamento_id
                         ORDER BY e.nombre";
$result_lista_empleados = mysqli_query($conn, $query_lista_empleados);

$query_lista_departamentos = "SELECT * FROM departamentos ORDER BY nombre";
$result_lista_departamentos = mysqli_query($conn, $query_lista_departamentos);

$query_lista_cargos = "SELECT * FROM cargos ORDER BY nombre";
$result_lista_cargos = mysqli_query($conn, $query_lista_cargos);

$query_usuarios = "SELECT u.Usuario_id, u.usuario, u.Rol, 
                  COUNT(DISTINCT m.idmac) as maquinas_asignadas,
                  COUNT(DISTINCT t.id) as tickets_asignados
                  FROM usuarios u
                  LEFT JOIN tickets t ON u.Usuario_id = t.asignado_a OR u.Usuario_id = t.creado_por
                  LEFT JOIN maquinas m ON u.Usuario_id = m.usuario_id
                  GROUP BY u.Usuario_id";
$result_usuarios = mysqli_query($conn, $query_usuarios);

$query_marcas = "SELECT marca, COUNT(*) as total FROM maquinas GROUP BY marca";
$result_marcas = mysqli_query($conn, $query_marcas);

$query_modelos = "SELECT modelo, COUNT(*) as total FROM maquinas GROUP BY modelo";
$result_modelos = mysqli_query($conn, $query_modelos);

$query_tipos_maquinas = "SELECT 
                        CASE 
                            WHEN nombre LIKE '%LINK%' THEN 'Link' 
                            WHEN nombre LIKE '%WONDER%' THEN 'Wonder' 
                            WHEN nombre LIKE '%CLOVER%' THEN 'Clover' 
                            ELSE 'Otro' 
                        END as tipo, 
                        COUNT(*) as total 
                        FROM maquinas 
                        GROUP BY tipo";
$result_tipos_maquinas = mysqli_query($conn, $query_tipos_maquinas);

$query_lista_maquinas = "SELECT * FROM maquinas ORDER BY nombre";
$result_lista_maquinas = mysqli_query($conn, $query_lista_maquinas);

$query_estados_maquinas = "SELECT estado, COUNT(*) as total FROM maquinas GROUP BY estado";
$result_estados_maquinas = mysqli_query($conn, $query_estados_maquinas);

$query_lista_tickets = "SELECT t.*, 
                       e1.nombre as empleado_reporta, 
                       e2.nombre as empleado_asignado,
                       cp.nombre as categoria,
                       p.nombre as prioridad
                       FROM tickets t
                       LEFT JOIN empleados e1 ON t.empleado_id = e1.empleado_id
                       LEFT JOIN empleados e2 ON t.asignado_a = e2.empleado_id
                       LEFT JOIN categorias_problema cp ON t.categoria_id = cp.categoria_id
                       LEFT JOIN prioridades_ticket p ON t.prioridad_id = p.prioridad_id
                       ORDER BY t.fecha_creacion DESC";
$result_lista_tickets = mysqli_query($conn, $query_lista_tickets);

$query_all_categorias = "SELECT * FROM categorias_problema";
$result_all_categorias = mysqli_query($conn, $query_all_categorias);

$query_all_prioridades = "SELECT * FROM prioridades_ticket";
$result_all_prioridades = mysqli_query($conn, $query_all_prioridades);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard General - Sistema RCDB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #5a5c69;
            --purple-color: #6f42c1;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.35rem;
            font-weight: 700;
        }
        
        .bg-purple {
            background-color: var(--purple-color) !important;
        }
        
        .stat-card {
            border-left: 0.25rem solid;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .card-body {
            padding: 1rem 1.5rem;
        }
        
        .stat-card i {
            font-size: 2.5rem;
            opacity: 0.7;
        }
        
        .nav-tabs .nav-link {
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .nav-tabs .nav-link.active {
            font-weight: 700;
            border-bottom: 3px solid var(--primary-color);
        }
        
        .table-responsive {
            border-radius: 0.35rem;
            overflow: hidden;
        }
        
        .badge-primary {
            background-color: var(--primary-color);
        }
        
        .badge-success {
            background-color: var(--success-color);
        }
        
        .badge-info {
            background-color: var(--info-color);
        }
        
        .badge-warning {
            background-color: var(--warning-color);
        }
        
        .badge-danger {
            background-color: var(--danger-color);
        }
        
        .badge-purple {
            background-color: var(--purple-color);
        }
        
        .tab-content {
            padding: 20px 0;
        }
        
        .entity-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .entity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .entity-card.Alta {
            border-left-color: #1cc88a;
        }
        .entity-card.Proceso {
            border-left-color: #f6c23e;
        }
        .entity-card.Baja {
            border-left-color: #e74a3b;
        }
        .entity-card.Abierto {
            border-left-color: #e74a3b;
        }
        .entity-card.Cerrado {
            border-left-color: #1cc88a;
        }
        .entity-card.Admin {
            border-left-color: #4e73df;
        }
        .entity-card.SuperAdmin {
            border-left-color: #e74a3b;
        }
        .entity-card.Usuario {
            border-left-color: #858796;
        }
        .badge-estado {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h1 class="h3 mb-4 text-gray-800">Dashboard General</h1>
        
        <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="maquinas-tab" data-bs-toggle="tab" data-bs-target="#maquinas" type="button" role="tab">
                    <i class="fas fa-gamepad mr-1"></i> Máquinas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tickets-tab" data-bs-toggle="tab" data-bs-target="#tickets" type="button" role="tab">
                    <i class="fas fa-ticket-alt mr-1"></i> Tickets
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="empleados-tab" data-bs-toggle="tab" data-bs-target="#empleados" type="button" role="tab">
                    <i class="fas fa-users mr-1"></i> Empleados
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="departamentos-tab" data-bs-toggle="tab" data-bs-target="#departamentos" type="button" role="tab">
                    <i class="fas fa-building mr-1"></i> Departamentos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cargos-tab" data-bs-toggle="tab" data-bs-target="#cargos" type="button" role="tab">
                    <i class="fas fa-id-badge mr-1"></i> Cargos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios" type="button" role="tab">
                    <i class="fas fa-user-shield mr-1"></i> Usuarios
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="registros-tab" data-bs-toggle="tab" data-bs-target="#registros" type="button" role="tab">
                    <i class="fas fa-database mr-1"></i> Todos los Registros
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="dashboardTabsContent">
            <!-- Pestaña de Máquinas -->
            <div class="tab-pane fade show active" id="maquinas" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-gamepad mr-2"></i>Máquinas de Juego
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-4">
                                        <div class="card stat-card border-left-success">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="text-success font-weight-bold text-uppercase mb-1">Máquinas Activas</h6>
                                                        <h2 class="mb-0"><?php echo $maquinas_data['Alta'] ?? 0; ?></h2>
                                                    </div>
                                                    <i class="fas fa-check-circle fa-3x text-success"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div class="card stat-card border-left-warning">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="text-warning font-weight-bold text-uppercase mb-1">En Proceso</h6>
                                                        <h2 class="mb-0"><?php echo $maquinas_data['Proceso'] ?? 0; ?></h2>
                                                    </div>
                                                    <i class="fas fa-tools fa-3x text-warning"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div class="card stat-card border-left-danger">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="text-danger font-weight-bold text-uppercase mb-1">Máquinas Inactivas</h6>
                                                        <h2 class="mb-0"><?php echo $maquinas_data['Baja'] ?? 0; ?></h2>
                                                    </div>
                                                    <i class="fas fa-times-circle fa-3x text-danger"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Estados de Máquinas</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="estadosMaquinasChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Máquinas por Marca</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="marcasChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Máquinas por Modelo</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="modelosChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Máquinas por Tipo</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="tiposMaquinasChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pestaña de Tickets -->
            <div class="tab-pane fade" id="tickets" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-ticket-alt mr-2"></i>Tickets
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-4">
                                        <div class="card stat-card border-left-danger">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="text-danger font-weight-bold text-uppercase mb-1">Tickets Abiertos</h6>
                                                        <h2 class="mb-0"><?php echo $tickets_data['Abierto'] ?? 0; ?></h2>
                                                    </div>
                                                    <i class="fas fa-exclamation-circle fa-3x text-danger"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div class="card stat-card border-left-warning">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="text-warning font-weight-bold text-uppercase mb-1">En Proceso</h6>
                                                        <h2 class="mb-0"><?php echo $tickets_data['En proceso'] ?? 0; ?></h2>
                                                    </div>
                                                    <i class="fas fa-spinner fa-3x text-warning"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div class="card stat-card border-left-success">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="text-success font-weight-bold text-uppercase mb-1">Tickets Cerrados</h6>
                                                        <h2 class="mb-0"><?php echo $tickets_data['Cerrado'] ?? 0; ?></h2>
                                                    </div>
                                                    <i class="fas fa-check-circle fa-3x text-success"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6 mb-4">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Tickets por Estado</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="estadoTicketsChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Tickets por Categoría</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="categoriasChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6 mb-4">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Tickets por Prioridad</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="prioridadesChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Distribución de Tickets</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="distribucionTicketsChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pestaña de Empleados -->
            <div class="tab-pane fade" id="empleados" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow">
                            <div class="card-header bg-purple text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-users mr-2"></i>Empleados
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Empleados por Departamento</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="empleadosDeptChart" height="250"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Empleados por Cargo</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="empleadosCargoChart" height="250"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card shadow mt-3">
                                    <div class="card-header bg-white">
                                        <h6 class="m-0 font-weight-bold text-primary">Lista de Empleados Registrados</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Nombre</th>
                                                        <th>Cargo</th>
                                                        <th>Departamento</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while($empleado = mysqli_fetch_assoc($result_lista_empleados)): ?>
                                                    <tr>
                                                        <td><?php echo $empleado['nombre']; ?></td>
                                                        <td><?php echo $empleado['cargo']; ?></td>
                                                        <td><?php echo $empleado['departamento']; ?></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                                            <button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></button>
                                                        </td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pestaña de Departamentos -->
            <div class="tab-pane fade" id="departamentos" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-building mr-2"></i>Departamentos
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Total de Departamentos</h6>
                                            </div>
                                            <div class="card-body text-center">
                                                <h1 class="display-4"><?php echo mysqli_num_rows($result_lista_departamentos); ?></h1>
                                                <p class="text-muted">Departamentos registrados</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Lista de Departamentos</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-bordered table-hover">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>Nombre</th>
                                                                <th>Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php mysqli_data_seek($result_lista_departamentos, 0); ?>
                                                            <?php while($depto = mysqli_fetch_assoc($result_lista_departamentos)): ?>
                                                            <tr>
                                                                <td><?php echo $depto['departamento_id']; ?></td>
                                                                <td><?php echo $depto['nombre']; ?></td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                                                    <button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></button>
                                                                </td>
                                                            </tr>
                                                            <?php endwhile; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pestaña de Cargos -->
            <div class="tab-pane fade" id="cargos" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow">
                            <div class="card-header bg-warning text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-id-badge mr-2"></i>Cargos
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Total de Cargos</h6>
                                            </div>
                                            <div class="card-body text-center">
                                                <h1 class="display-4"><?php echo mysqli_num_rows($result_lista_cargos); ?></h1>
                                                <p class="text-muted">Cargos registrados</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Lista de Cargos</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-bordered table-hover">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>Nombre</th>
                                                                <th>Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php while($cargo = mysqli_fetch_assoc($result_lista_cargos)): ?>
                                                            <tr>
                                                                <td><?php echo $cargo['Cargo_id']; ?></td>
                                                                <td><?php echo $cargo['nombre']; ?></td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                                                    <button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></button>
                                                                </td>
                                                            </tr>
                                                            <?php endwhile; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pestaña de Usuarios -->
            <div class="tab-pane fade" id="usuarios" role="tabpanel">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-shield mr-2"></i>Usuarios del Sistema
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php while($user = mysqli_fetch_assoc($result_usuarios)): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card shadow">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <i class="fas fa-user-circle fa-3x mr-3 
                                                        <?php echo $user['Rol'] == 'SuperAdmin' ? 'text-danger' : 
                                                              ($user['Rol'] == 'Admin' ? 'text-primary' : 'text-secondary'); ?>"></i>
                                                    <div>
                                                        <h5 class="mb-0"><?php echo $user['usuario']; ?></h5>
                                                        <span class="badge 
                                                            <?php echo $user['Rol'] == 'SuperAdmin' ? 'bg-danger' : 
                                                                  ($user['Rol'] == 'Admin' ? 'bg-primary' : 'bg-secondary'); ?>">
                                                            <?php echo $user['Rol']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="text-center">
                                                            <h4 class="mb-0"><?php echo $user['maquinas_asignadas']; ?></h4>
                                                            <small class="text-muted">Máquinas</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="text-center">
                                                            <h4 class="mb-0"><?php echo $user['tickets_asignados']; ?></h4>
                                                            <small class="text-muted">Tickets</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Distribución de Roles</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="rolesChart" height="250"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card shadow">
                                            <div class="card-header bg-white">
                                                <h6 class="m-0 font-weight-bold text-primary">Actividad de Usuarios</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="actividadUsuariosChart" height="250"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pestaña de Todos los Registros -->
            <div class="tab-pane fade" id="registros" role="tabpanel">
                <div class="row">
                    <!-- Sección de Máquinas -->
                    <div class="col-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-gamepad mr-2"></i> Todas las Máquinas Registradas
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php mysqli_data_seek($result_lista_maquinas, 0); ?>
                                    <?php while($maquina = mysqli_fetch_assoc($result_lista_maquinas)): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card entity-card h-100 <?php echo $maquina['estado']; ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo $maquina['nombre']; ?></h5>
                                                <h6 class="card-subtitle mb-2 text-muted"><?php echo $maquina['marca']; ?> - <?php echo $maquina['modelo']; ?></h6>
                                                <p class="card-text">
                                                    <small class="text-muted">Serial: <?php echo $maquina['numero_serie']; ?></small><br>
                                                    <span class="badge badge-estado bg-<?php echo $maquina['estado'] == 'Alta' ? 'success' : ($maquina['estado'] == 'Baja' ? 'danger' : 'warning'); ?>">
                                                        <?php echo $maquina['estado']; ?>
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Ver</button>
                                                <button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Editar</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de Tickets -->
                    <div class="col-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-ticket-alt mr-2"></i> Todos los Tickets Registrados
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php mysqli_data_seek($result_lista_tickets, 0); ?>
                                    <?php while($ticket = mysqli_fetch_assoc($result_lista_tickets)): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card entity-card h-100 <?php echo $ticket['estado']; ?>">
                                            <div class="card-body">
                                                <h5 class="card-title">Ticket #<?php echo $ticket['id']; ?></h5>
                                                <h6 class="card-subtitle mb-2 text-muted"><?php echo $ticket['asunto']; ?></h6>
                                                <p class="card-text">
                                                    <small class="text-muted">Reportado por: <?php echo $ticket['empleado_reporta']; ?></small><br>
                                                    <small class="text-muted">Asignado a: <?php echo $ticket['empleado_asignado'] ?? 'No asignado'; ?></small><br>
                                                    <span class="badge badge-estado bg-<?php echo $ticket['estado'] == 'Abierto' ? 'danger' : ($ticket['estado'] == 'Cerrado' ? 'success' : 'warning'); ?>">
                                                        <?php echo $ticket['estado']; ?>
                                                    </span>
                                                    <span class="badge badge-estado bg-primary">
                                                        <?php echo $ticket['categoria']; ?>
                                                    </span>
                                                    <span class="badge badge-estado bg-<?php 
                                                        if($ticket['prioridad'] == 'Crítica (Alta)') echo 'danger';
                                                        elseif($ticket['prioridad'] == 'Media') echo 'warning';
                                                        else echo 'secondary';
                                                    ?>">
                                                        <?php echo $ticket['prioridad']; ?>
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Ver</button>
                                                <button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Editar</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de Empleados -->
                    <div class="col-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-purple text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-users mr-2"></i> Todos los Empleados Registrados
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php mysqli_data_seek($result_lista_empleados, 0); ?>
                                    <?php while($empleado = mysqli_fetch_assoc($result_lista_empleados)): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card entity-card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo $empleado['nombre']; ?></h5>
                                                <h6 class="card-subtitle mb-2 text-muted"><?php echo $empleado['cargo']; ?></h6>
                                                <p class="card-text">
                                                    <span class="badge badge-estado bg-info">
                                                        <?php echo $empleado['departamento']; ?>
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Ver</button>
                                                <button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Editar</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de Departamentos -->
                    <div class="col-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-building mr-2"></i> Todos los Departamentos
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php mysqli_data_seek($result_lista_departamentos, 0); ?>
                                    <?php while($depto = mysqli_fetch_assoc($result_lista_departamentos)): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card entity-card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo $depto['nombre']; ?></h5>
                                                <p class="card-text">
                                                    <small class="text-muted">ID: <?php echo $depto['departamento_id']; ?></small>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Ver</button>
                                                <button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Editar</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de Cargos -->
                    <div class="col-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-warning text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-id-badge mr-2"></i> Todos los Cargos
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php mysqli_data_seek($result_lista_cargos, 0); ?>
                                    <?php while($cargo = mysqli_fetch_assoc($result_lista_cargos)): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card entity-card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo $cargo['nombre']; ?></h5>
                                                <p class="card-text">
                                                    <small class="text-muted">ID: <?php echo $cargo['Cargo_id']; ?></small>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Ver</button>
                                                <button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Editar</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de Categorías de Problemas -->
                    <div class="col-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-bug mr-2"></i> Todas las Categorías de Problemas
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php while($categoria = mysqli_fetch_assoc($result_all_categorias)): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card entity-card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo $categoria['nombre']; ?></h5>
                                                <p class="card-text">
                                                    <small class="text-muted">ID: <?php echo $categoria['categoria_id']; ?></small>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Ver</button>
                                                <button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Editar</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de Prioridades -->
                    <div class="col-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle mr-2"></i> Todas las Prioridades
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php while($prioridad = mysqli_fetch_assoc($result_all_prioridades)): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card entity-card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo $prioridad['nombre']; ?></h5>
                                                <p class="card-text">
                                                    <small class="text-muted">Nivel: <?php echo $prioridad['nivel_urgencia']; ?></small><br>
                                                    <small class="text-muted">ID: <?php echo $prioridad['prioridad_id']; ?></small>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Ver</button>
                                                <button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Editar</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gráfico de estados de máquinas
        var ctxEstadosMaquinas = document.getElementById('estadosMaquinasChart').getContext('2d');
        var estadosMaquinasChart = new Chart(ctxEstadosMaquinas, {
            type: 'pie',
            data: {
                labels: [
                    <?php 
                    mysqli_data_seek($result_estados_maquinas, 0);
                    while($estado = mysqli_fetch_assoc($result_estados_maquinas)) {
                        echo "'".$estado['estado']."',";
                    }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                        mysqli_data_seek($result_estados_maquinas, 0);
                        while($estado = mysqli_fetch_assoc($result_estados_maquinas)) {
                            echo $estado['total'].",";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        '#1cc88a', // Alta
                        '#f6c23e', // Proceso
                        '#e74a3b'  // Baja
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
        
        // Gráfico de marcas de máquinas
        var ctxMarcas = document.getElementById('marcasChart').getContext('2d');
        var marcasChart = new Chart(ctxMarcas, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    mysqli_data_seek($result_marcas, 0);
                    while($marca = mysqli_fetch_assoc($result_marcas)) {
                        echo "'".$marca['marca']."',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Máquinas',
                    data: [
                        <?php 
                        mysqli_data_seek($result_marcas, 0);
                        while($marca = mysqli_fetch_assoc($result_marcas)) {
                            echo $marca['total'].",";
                        }
                        ?>
                    ],
                    backgroundColor: '#4e73df'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Gráfico de modelos de máquinas
        var ctxModelos = document.getElementById('modelosChart').getContext('2d');
        var modelosChart = new Chart(ctxModelos, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php 
                    mysqli_data_seek($result_modelos, 0);
                    while($modelo = mysqli_fetch_assoc($result_modelos)) {
                        echo "'".$modelo['modelo']."',";
                    }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                        mysqli_data_seek($result_modelos, 0);
                        while($modelo = mysqli_fetch_assoc($result_modelos)) {
                            echo $modelo['total'].",";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e'
                    ]
                }]
            }
        });
        
        // Gráfico de tipos de máquinas
        var ctxTipos = document.getElementById('tiposMaquinasChart').getContext('2d');
        var tiposChart = new Chart(ctxTipos, {
            type: 'pie',
            data: {
                labels: [
                    <?php 
                    mysqli_data_seek($result_tipos_maquinas, 0);
                    while($tipo = mysqli_fetch_assoc($result_tipos_maquinas)) {
                        echo "'".$tipo['tipo']."',";
                    }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                        mysqli_data_seek($result_tipos_maquinas, 0);
                        while($tipo = mysqli_fetch_assoc($result_tipos_maquinas)) {
                            echo $tipo['total'].",";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e'
                    ]
                }]
            }
        });
        
        // Gráfico de tickets por estado
        var ctxEstadoTickets = document.getElementById('estadoTicketsChart').getContext('2d');
        var estadoTicketsChart = new Chart(ctxEstadoTickets, {
            type: 'pie',
            data: {
                labels: ['Abiertos', 'En Proceso', 'Cerrados'],
                datasets: [{
                    data: [
                        <?php echo $tickets_data['Abierto'] ?? 0; ?>,
                        <?php echo $tickets_data['En proceso'] ?? 0; ?>,
                        <?php echo $tickets_data['Cerrado'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#e74a3b',
                        '#f6c23e',
                        '#1cc88a'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.raw || 0;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Gráfico de categorías de tickets
        var ctxCategorias = document.getElementById('categoriasChart').getContext('2d');
        var categoriasChart = new Chart(ctxCategorias, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php 
                    mysqli_data_seek($result_categorias, 0);
                    while($cat = mysqli_fetch_assoc($result_categorias)) {
                        echo "'".$cat['nombre']."',";
                    }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                        mysqli_data_seek($result_categorias, 0);
                        while($cat = mysqli_fetch_assoc($result_categorias)) {
                            echo $cat['total'].",";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF'
                    ]
                }]
            }
        });
        
        // Gráfico de prioridades de tickets
        var ctxPrioridades = document.getElementById('prioridadesChart').getContext('2d');
        var prioridadesChart = new Chart(ctxPrioridades, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    mysqli_data_seek($result_prioridades, 0);
                    while($pri = mysqli_fetch_assoc($result_prioridades)) {
                        echo "'".$pri['nombre']."',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Tickets',
                    data: [
                        <?php 
                        mysqli_data_seek($result_prioridades, 0);
                        while($pri = mysqli_fetch_assoc($result_prioridades)) {
                            echo $pri['total'].",";
                        }
                        ?>
                    ],
                    backgroundColor: '#4e73df'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Gráfico de distribución de tickets
        var ctxDistribucion = document.getElementById('distribucionTicketsChart').getContext('2d');
        var distribucionChart = new Chart(ctxDistribucion, {
            type: 'polarArea',
            data: {
                labels: ['Abiertos', 'En Proceso', 'Cerrados'],
                datasets: [{
                    data: [
                        <?php echo $tickets_data['Abierto'] ?? 0; ?>,
                        <?php echo $tickets_data['En proceso'] ?? 0; ?>,
                        <?php echo $tickets_data['Cerrado'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#e74a3b',
                        '#f6c23e',
                        '#1cc88a'
                    ]
                }]
            }
        });
        
        // Gráfico de empleados por departamento
        var ctxEmpleadosDept = document.getElementById('empleadosDeptChart').getContext('2d');
        var empleadosDeptChart = new Chart(ctxEmpleadosDept, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    mysqli_data_seek($result_empleados_dept, 0);
                    while($dept = mysqli_fetch_assoc($result_empleados_dept)) {
                        echo "'".$dept['nombre']."',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Empleados',
                    data: [
                        <?php 
                        mysqli_data_seek($result_empleados_dept, 0);
                        while($dept = mysqli_fetch_assoc($result_empleados_dept)) {
                            echo $dept['total'].",";
                        }
                        ?>
                    ],
                    backgroundColor: '#4e73df'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Gráfico de empleados por cargo
        var ctxEmpleadosCargo = document.getElementById('empleadosCargoChart').getContext('2d');
        var empleadosCargoChart = new Chart(ctxEmpleadosCargo, {
            type: 'pie',
            data: {
                labels: [
                    <?php 
                    mysqli_data_seek($result_empleados_cargo, 0);
                    while($cargo = mysqli_fetch_assoc($result_empleados_cargo)) {
                        echo "'".$cargo['nombre']."',";
                    }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                        mysqli_data_seek($result_empleados_cargo, 0);
                        while($cargo = mysqli_fetch_assoc($result_empleados_cargo)) {
                            echo $cargo['total'].",";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e',
                        '#e74a3b',
                        '#6f42c1'
                    ]
                }]
            }
        });
        
        // Gráfico de distribución de roles
        var ctxRoles = document.getElementById('rolesChart').getContext('2d');
        var rolesChart = new Chart(ctxRoles, {
            type: 'doughnut',
            data: {
                labels: ['SuperAdmin', 'Admin', 'Usuario'],
                datasets: [{
                    data: [
                        <?php
                        $countSuperAdmin = 0;
                        $countAdmin = 0;
                        $countUsuario = 0;
                        mysqli_data_seek($result_usuarios, 0);
                        while($user = mysqli_fetch_assoc($result_usuarios)) {
                            if($user['Rol'] == 'SuperAdmin') $countSuperAdmin++;
                            elseif($user['Rol'] == 'Admin') $countAdmin++;
                            else $countUsuario++;
                        }
                        echo $countSuperAdmin.", ".$countAdmin.", ".$countUsuario;
                        ?>
                    ],
                    backgroundColor: [
                        '#e74a3b',
                        '#4e73df',
                        '#858796'
                    ]
                }]
            }
        });
        
        // Gráfico de actividad de usuarios
        var ctxActividad = document.getElementById('actividadUsuariosChart').getContext('2d');
        var actividadChart = new Chart(ctxActividad, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    mysqli_data_seek($result_usuarios, 0);
                    while($user = mysqli_fetch_assoc($result_usuarios)) {
                        echo "'".$user['usuario']."',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Máquinas',
                    data: [
                        <?php 
                        mysqli_data_seek($result_usuarios, 0);
                        while($user = mysqli_fetch_assoc($result_usuarios)) {
                            echo $user['maquinas_asignadas'].",";
                        }
                        ?>
                    ],
                    backgroundColor: '#4e73df'
                }, {
                    label: 'Tickets',
                    data: [
                        <?php 
                        mysqli_data_seek($result_usuarios, 0);
                        while($user = mysqli_fetch_assoc($result_usuarios)) {
                            echo $user['tickets_asignados'].",";
                        }
                        ?>
                    ],
                    backgroundColor: '#1cc88a'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
    </script>
</body>
</html>