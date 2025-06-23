<?php
$msg = "";

// Procesar subida de archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['nuevo_mapa'])) {
    $archivo = $_FILES['nuevo_mapa'];
    $permitidos = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];

    if ($archivo['error'] === 0 && in_array($archivo['type'], $permitidos)) {
        $ruta_destino = __DIR__ . '/images/mapa.png';
        
        // Mover archivo y sobrescribir mapa.png
        if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
            $msg = "Mapa actualizado correctamente.";
        } else {
            $msg = "Error al guardar el nuevo mapa.";
        }
    } else {
        $msg = "Formato no válido. Solo se permiten PNG, JPG, JPEG, WEBP.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ubicación del Casino</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .mapa-img {
            width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 0 12px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    <h2 class="text-center mb-4">Mapa del Casino</h2>

    <?php if ($msg): ?>
        <div class="alert alert-info text-center"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="text-center mb-4">
        <img src="/images/mapa.png?<?= time() ?>" alt="Mapa del Casino" class="mapa-img img-fluid">
    </div>

    <div class="card mx-auto" style="max-width: 500px;">
        <div class="card-header bg-primary text-white">Actualizar Mapa</div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="nuevo_mapa" class="form-label">Selecciona una nueva imagen del mapa:</label>
                    <input type="file" class="form-control" name="nuevo_mapa" id="nuevo_mapa" accept="image/*" required>
                </div>
                <button type="submit" class="btn btn-success">Actualizar Mapa</button>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
