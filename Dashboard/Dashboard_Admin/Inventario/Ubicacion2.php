<?php
include($_SERVER['DOCUMENT_ROOT'] . '/conexion.php');

$msg = "";
$dir = $_SERVER['DOCUMENT_ROOT'] . '/Images/mapas';
$webPath = '/Images/mapas/';

// Crear directorio si no existe
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Procesar eliminación de mapa
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    $stmt = $conn->prepare("SELECT nombre_archivo FROM mapas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $archivo = $row['nombre_archivo'];
        $ruta_archivo = $dir . '/' . $archivo;
        
        $stmt = $conn->prepare("DELETE FROM mapas WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if (file_exists($ruta_archivo)) {
                unlink($ruta_archivo);
            }
            $msg = "Mapa eliminado correctamente.";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            $msg = "Error al eliminar el mapa de la base de datos.";
        }
    } else {
        $msg = "El mapa no existe en la base de datos.";
    }
}

// Procesar edición de nombres
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_nombre'])) {
    $id = $_POST['id'];
    $nuevoNombreArchivo = $_POST['nuevo_nombre_archivo'];
    $nuevoNombreMostrar = $_POST['nuevo_nombre_mostrar'];
    
    // Verificar si el nombre de archivo ya existe
    $stmt = $conn->prepare("SELECT id FROM mapas WHERE nombre_archivo = ? AND id != ?");
    $stmt->bind_param("si", $nuevoNombreArchivo, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $msg = "Error: Ya existe un mapa con ese nombre de archivo.";
    } else {
        // Obtener el nombre actual del archivo
        $stmt = $conn->prepare("SELECT nombre_archivo FROM mapas WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $nombreActual = $row['nombre_archivo'];
        
        // Actualizar en la base de datos
        $stmt = $conn->prepare("UPDATE mapas SET nombre_archivo = ?, nombre_mostrar = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nuevoNombreArchivo, $nuevoNombreMostrar, $id);
        
        if ($stmt->execute()) {
            // Renombrar el archivo físico si existe
            $rutaActual = $dir . '/' . $nombreActual;
            $nuevaRuta = $dir . '/' . $nuevoNombreArchivo;
            
            if (file_exists($rutaActual)) {
                rename($rutaActual, $nuevaRuta);
            }
            
            $msg = "Mapa actualizado correctamente.";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            $msg = "Error al actualizar el mapa: " . $conn->error;
        }
    }
}

// Subir nuevo mapa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['nuevo_mapa'])) {
    $archivo = $_FILES['nuevo_mapa'];
    $permitidos = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];

    if ($archivo['error'] === 0 && in_array($archivo['type'], $permitidos)) {
        $ext = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombreArchivo = 'mapa_' . time() . '.' . $ext;
        $nombreMostrar = pathinfo($archivo['name'], PATHINFO_FILENAME);
        $ruta_destino = $dir . '/' . $nombreArchivo;

        if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
            $stmt = $conn->prepare("INSERT INTO mapas (nombre_archivo, nombre_mostrar, usuario) VALUES (?, ?, ?)");
            $usuario = 'SuperAdmin';
            $stmt->bind_param("sss", $nombreArchivo, $nombreMostrar, $usuario);
            
            if ($stmt->execute()) {
                $msg = "Mapa subido y registrado correctamente.";
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            } else {
                $msg = "Error al registrar el mapa: " . $conn->error;
                // Eliminar el archivo subido si falla la inserción en la BD
                if (file_exists($ruta_destino)) {
                    unlink($ruta_destino);
                }
            }
            $stmt->close();
        } else {
            $msg = "Error al guardar el nuevo mapa.";
        }
    } else {
        $msg = "Formato no válido. Solo se permiten PNG, JPG, JPEG, WEBP.";
    }
}

// Obtener todos los mapas
$query = "SELECT id, nombre_archivo, nombre_mostrar, fecha_subida FROM mapas ORDER BY fecha_subida DESC";
$result = $conn->query($query);
$mapas = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $mapas[] = [
            'id' => $row['id'],
            'nombre_archivo' => $row['nombre_archivo'],
            'nombre_mostrar' => $row['nombre_mostrar'] ?: $row['nombre_archivo'],
            'ruta' => $webPath . $row['nombre_archivo'],
            'fecha' => $row['fecha_subida']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Layout del Casino</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --casino-primary: #2c3e50;
            --casino-secondary: #e74c3c;
            --casino-accent: #f1c40f;
            --casino-success: #2ecc71;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, var(--casino-primary), #34495e);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .carousel-container {
            max-width: 900px;
            margin: 0 auto 3rem;
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .carousel-item img {
            max-height: 500px;
            object-fit: contain;
            background-color: #f0f0f0;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .carousel-item img:hover {
            transform: scale(1.02);
        }
        
        .mapa-details {
            background-color: white;
            padding: 15px;
            border-top: 1px solid #eee;
        }
        
        .mapa-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .mapa-filename {
            font-size: 0.8rem;
            color: #666;
            word-break: break-all;
            margin-bottom: 5px;
        }
        
        .mapa-date {
            font-size: 0.85rem;
            color: #666;
        }
        
        .btn-accion {
            margin-top: 10px;
        }
        
        .btn-ver {
            background-color: var(--casino-primary);
            color: white;
        }
        
        .btn-editar {
            background-color: var(--casino-accent);
            color: #333;
        }
        
        .btn-eliminar {
            background-color: var(--casino-secondary);
            color: white;
        }
        
        .btn-upload {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .modal-content {
            border: none;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .modal-header {
            background: var(--casino-primary);
            color: white;
        }
        
        .btn-casino {
            background-color: var(--casino-success);
            border-color: var(--casino-success);
            color: white;
        }
        
        .btn-casino:hover {
            background-color: #27ae60;
            border-color: #27ae60;
        }
        
        .alert-casino {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            z-index: 1100;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            opacity: 0.95;
        }
        
        .carousel-indicators button {
            background-color: var(--casino-primary);
        }
        
        .carousel-control-prev, .carousel-control-next {
            background-color: rgba(0,0,0,0.2);
            width: 50px;
            height: 50px;
            top: 50%;
            transform: translateY(-50%);
            border-radius: 50%;
        }
        
        .modal-imagen .modal-dialog {
            max-width: 90%;
            max-height: 90vh;
        }
        
        .modal-imagen .modal-content {
            background-color: transparent;
            border: none;
        }
        
        .modal-imagen .modal-body {
            padding: 0;
            text-align: center;
        }
        
        .modal-imagen img {
            max-height: 80vh;
            width: auto;
            max-width: 100%;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header text-center">
        <h1><i class="fas fa-map-marked-alt me-2"></i>Layout del Casino</h1>
        <p class="lead mb-0">Gestión completa de planos del establecimiento</p>
    </div>

    <!-- Mensajes de alerta -->
    <?php if ($msg): ?>
        <div class="alert alert-casino alert-<?= strpos($msg, 'Error') === 0 ? 'danger' : 'success' ?> alert-dismissible fade show">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="container">
        <?php if (empty($mapas)): ?>
            <div class="text-center py-5">
                <div class="alert alert-warning py-4">
                    <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                    <h4>No hay Layout registrados</h4>
                    <p class="mb-0">Use el botón + para subir el primer layout</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Carrusel de mapas -->
            <div class="carousel-container">
                <div id="mapasCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        <?php foreach ($mapas as $key => $mapa): ?>
                            <button type="button" data-bs-target="#mapasCarousel" data-bs-slide-to="<?= $key ?>" <?= $key === 0 ? 'class="active"' : '' ?>></button>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="carousel-inner">
                        <?php foreach ($mapas as $key => $mapa): ?>
                            <div class="carousel-item <?= $key === 0 ? 'active' : '' ?>">
                                <img src="<?= $mapa['ruta'] ?>?v=<?= time() ?>" class="d-block w-100" alt="Mapa del Casino" 
                                     data-bs-toggle="modal" data-bs-target="#imagenModal" data-bs-imagen="<?= $mapa['ruta'] ?>">
                                <div class="mapa-details">
                                    <div class="mapa-name"><?= htmlspecialchars($mapa['nombre_mostrar']) ?></div>
                                    <div class="mapa-filename">Archivo: <?= htmlspecialchars($mapa['nombre_archivo']) ?></div>
                                    <div class="mapa-date">Subido el <?= date('d/m/Y H:i', strtotime($mapa['fecha'])) ?></div>
                                    
                                    <div class="d-flex gap-2 mt-3">
                                        <button class="btn btn-sm btn-ver btn-accion flex-grow-1" 
                                                data-bs-toggle="modal" data-bs-target="#imagenModal" 
                                                data-bs-imagen="<?= $mapa['ruta'] ?>">
                                            <i class="fas fa-expand me-1"></i> Ver completo
                                        </button>
                                        
                                        <button class="btn btn-sm btn-editar btn-accion flex-grow-1" 
                                                data-bs-toggle="modal" data-bs-target="#editarModal"
                                                data-bs-id="<?= $mapa['id'] ?>" 
                                                data-bs-nombre-archivo="<?= htmlspecialchars($mapa['nombre_archivo']) ?>"
                                                data-bs-nombre-mostrar="<?= htmlspecialchars($mapa['nombre_mostrar']) ?>">
                                            <i class="fas fa-edit me-1"></i> Editar
                                        </button>
                                        
                                        <a href="?eliminar=<?= $mapa['id'] ?>" class="btn btn-sm btn-eliminar btn-accion flex-grow-1" 
                                           onclick="return confirm('¿Estás seguro de eliminar este mapa?')">
                                            <i class="fas fa-trash me-1"></i> Eliminar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button class="carousel-control-prev" type="button" data-bs-target="#mapasCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#mapasCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Botón flotante para subir -->
    <button type="button" class="btn btn-casino btn-upload" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Modal para subir nuevo mapa -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel"><i class="fas fa-upload me-2"></i>Subir Nuevo Layout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="nuevo_mapa" class="form-label">Seleccionar imagen:</label>
                            <input type="file" class="form-control" name="nuevo_mapa" id="nuevo_mapa" accept="image/*" required>
                            <div class="form-text">Formatos permitidos: PNG, JPG, JPEG, WEBP (Máx. 5MB)</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="uploadForm" class="btn btn-casino">
                        <i class="fas fa-upload me-1"></i> Subir Mapa
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para ver imagen completa -->
    <div class="modal fade modal-imagen" id="imagenModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <img id="imagenCompleta" src="" alt="Mapa completo" class="img-fluid">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar nombres -->
    <div class="modal fade" id="editarModal" tabindex="-1" aria-labelledby="editarModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarModalLabel"><i class="fas fa-edit me-2"></i>Editar Mapa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="editarId">
                        <div class="mb-3">
                            <label for="nuevo_nombre_archivo" class="form-label">Nombre de archivo:</label>
                            <input type="text" class="form-control" name="nuevo_nombre_archivo" id="nuevo_nombre_archivo" required>
                            <div class="form-text">Incluye la extensión (ej: mapa_salon.jpg)</div>
                        </div>
                        <div class="mb-3">
                            <label for="nuevo_nombre_mostrar" class="form-label">Nombre para mostrar:</label>
                            <input type="text" class="form-control" name="nuevo_nombre_mostrar" id="nuevo_nombre_mostrar">
                            <div class="form-text">Nombre descriptivo para mostrar a los usuarios</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="editar_nombre" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cerrar alertas automáticamente
        window.addEventListener('DOMContentLoaded', (event) => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // Inicializar carrusel
            const myCarousel = new bootstrap.Carousel(document.getElementById('mapasCarousel'), {
                interval: 5000,
                ride: 'carousel'
            });
            
            // Configurar modal de imagen completa
            const imagenModal = document.getElementById('imagenModal');
            if (imagenModal) {
                imagenModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const imagenSrc = button.getAttribute('data-bs-imagen');
                    const modalImagen = imagenModal.querySelector('#imagenCompleta');
                    modalImagen.src = imagenSrc + '?v=' + new Date().getTime();
                });
            }
            
            // Configurar modal de edición
            const editarModal = document.getElementById('editarModal');
            if (editarModal) {
                editarModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-bs-id');
                    const nombreArchivo = button.getAttribute('data-bs-nombre-archivo');
                    const nombreMostrar = button.getAttribute('data-bs-nombre-mostrar');
                    
                    const idInput = editarModal.querySelector('#editarId');
                    const nombreArchivoInput = editarModal.querySelector('#nuevo_nombre_archivo');
                    const nombreMostrarInput = editarModal.querySelector('#nuevo_nombre_mostrar');
                    
                    idInput.value = id;
                    nombreArchivoInput.value = nombreArchivo;
                    nombreMostrarInput.value = nombreMostrar;
                });
            }
        });
    </script>
</body>
</html>