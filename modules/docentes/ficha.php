<?php
$page_name = 'docentes';
$page_title = 'Ficha del Docente';
require_once '../../includes/db.php';
$pdo = db_connect();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Procesar Actualización de Disponibilidad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_availability'])) {
    $pdo->beginTransaction();
    try {
        // Limpiar disponibilidad previa
        $stmt = $pdo->prepare("DELETE FROM disponibilidad_docente WHERE docente_id = ?");
        $stmt->execute([$id]);
        
        // Insertar nueva disponibilidad
        if (isset($_POST['bloques'])) {
            $stmt = $pdo->prepare("INSERT INTO disponibilidad_docente (docente_id, dia_semana, bloque_id) VALUES (?, ?, ?)");
            foreach ($_POST['bloques'] as $slot) {
                list($dia, $bloque_id) = explode('|', $slot);
                $stmt->execute([$id, $dia, $bloque_id]);
            }
        }
        $pdo->commit();
        $_SESSION['message'] = "Disponibilidad actualizada correctamente.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: ficha.php?id=$id");
    exit;
}

// Procesar Cambio de Foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_docente'])) {
    if ($_FILES['foto_docente']['error'] === 0) {
        $ext = pathinfo($_FILES['foto_docente']['name'], PATHINFO_EXTENSION);
        $filename = "docente_" . $id . "_" . time() . "." . $ext;
        $target = "../../assets/uploads/docentes/" . $filename;
        
        if (move_uploaded_file($_FILES['foto_docente']['tmp_name'], $target)) {
            $stmt = $pdo->prepare("UPDATE docentes SET foto = ? WHERE id = ?");
            $stmt->execute([$filename, $id]);
            $_SESSION['message'] = "Foto actualizada con éxito.";
        } else {
            $_SESSION['error'] = "Error al mover el archivo.";
        }
    } else {
        $_SESSION['error'] = "Error en el archivo subido.";
    }
    header("Location: ficha.php?id=$id");
    exit;
}

require_once '../../includes/header.php';

if (!$id) {
    echo "<div class='alert alert-danger m-4'>Docente no especificado.</div>";
    require_once '../../includes/footer.php';
    exit;
}

// Obtener datos del docente con su especialidad
$stmt = $pdo->prepare("
    SELECT d.*, esp.nombre as especialidad_nombre 
    FROM docentes d 
    LEFT JOIN especialidades esp ON d.especialidad_id = esp.id 
    WHERE d.id = ?
");
$stmt->execute([$id]);
$docente = $stmt->fetch();

// Obtener todos los bloques horarios
$bloques = $pdo->query("SELECT * FROM bloques_horarios ORDER BY turno, orden")->fetchAll();

// Obtener disponibilidad actual
$stmt = $pdo->prepare("SELECT CONCAT(dia_semana, '|', bloque_id) FROM disponibilidad_docente WHERE docente_id = ?");
$stmt->execute([$id]);
$disponibilidad_actual = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!$docente) {
    echo "<div class='alert alert-warning m-4'>Docente no encontrado.</div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h2 class="fw-bold"><i class="fas fa-id-badge me-2 text-primary"></i>Ficha del Docente</h2>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <?php if (isset($_SESSION['message'])): ?>
            <span class="text-success me-3 small fw-bold"><i class="fas fa-check me-1"></i><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></span>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary me-2">
            <i class="fas fa-arrow-left me-2"></i>Volver
        </a>
        <button class="btn btn-primary shadow-sm" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Imprimir
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Perfil Card -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-body text-center p-4">
                <div class="mb-3 position-relative d-inline-block">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center border shadow-sm" style="width: 150px; height: 150px; overflow: hidden;">
                        <?php if ($docente['foto']): ?>
                            <img src="../../assets/uploads/docentes/<?php echo $docente['foto']; ?>" class="w-100 h-100" style="object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user-tie fa-4x text-secondary"></i>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" id="formFoto" class="position-absolute bottom-0 end-0">
                        <label for="foto_docente" class="btn btn-primary btn-sm rounded-circle shadow-sm p-2" style="width: 35px; height: 35px; cursor: pointer;">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" name="foto_docente" id="foto_docente" class="d-none" accept="image/*" onchange="document.getElementById('formFoto').submit()">
                    </form>
                </div>
                <h4 class="fw-bold mb-1"><?php echo $docente['nombre'] . ' ' . $docente['apellido']; ?></h4>
                <p class="text-muted mb-3 small fw-bold text-uppercase"><?php echo $docente['especialidad_nombre'] ?: 'Sin Especialidad'; ?></p>
                
                <div class="mb-4">
                    <span class="badge <?php echo $docente['estatus'] == 'activo' ? 'bg-success' : 'bg-secondary'; ?> px-4 py-2 rounded-pill">
                        <i class="fas <?php echo $docente['estatus'] == 'activo' ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                        <?php echo strtoupper($docente['estatus']); ?>
                    </span>
                </div>

                <div class="bg-light p-3 rounded-3 text-start mb-0">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small fw-bold">CÉDULA</span>
                        <span class="fw-bold"><?php echo $docente['cedula']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small fw-bold">RFID UID</span>
                        <code class="text-dark fw-bold"><?php echo $docente['rfid_uid'] ?: 'NO ASIGNADO'; ?></code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Card -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
            <div class="card-header bg-white p-4 border-0">
                <h5 class="fw-bold mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>Información de Contacto</h5>
            </div>
            <div class="card-body p-4 pt-0">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold text-uppercase mb-1">Teléfono Móvil</label>
                        <p class="fs-5 fw-medium"><?php echo $docente['telefono'] ?: 'No registrado'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold text-uppercase mb-1">Correo Electrónico</label>
                        <p class="fs-5 fw-medium"><?php echo $docente['email'] ?: 'No registrado'; ?></p>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted small fw-bold text-uppercase mb-1">Especialidad y Observaciones</label>
                        <p class="fs-5 fw-medium mb-0">
                            <?php echo $docente['especialidad_nombre'] ?: 'Docente General'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disponibilidad Semanal Card -->
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
            <div class="card-header bg-white p-4 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="fas fa-calendar-check me-2 text-success"></i>Disponibilidad Semanal</h5>
                <small class="text-muted">Marca los bloques donde el docente puede dar clases</small>
            </div>
            <form method="POST">
                <input type="hidden" name="save_availability" value="1">
                <div class="card-body p-4 pt-0">
                    <div class="table-responsive">
                        <table class="table table-bordered text-center align-middle small">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 150px;">Bloque / Hora</th>
                                    <?php $dias = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes']; 
                                    foreach($dias as $d): echo "<th>$d</th>"; endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bloques as $b): ?>
                                <tr class="<?php echo $b['turno'] == 'tarde' ? 'bg-light bg-opacity-50' : ''; ?>">
                                    <td class="text-start py-2">
                                        <div class="fw-bold"><?php echo $b['nombre']; ?></div>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($b['hora_inicio'])); ?></small>
                                    </td>
                                    <?php foreach ($dias as $d): 
                                        $val = "$d|".$b['id'];
                                        $checked = in_array($val, $disponibilidad_actual) ? 'checked' : '';
                                    ?>
                                    <td class="p-0">
                                        <label class="d-block p-3 cursor-pointer h-100 w-100 mb-0">
                                            <input type="checkbox" name="bloques[]" value="<?php echo $val; ?>" <?php echo $checked; ?> class="form-check-input">
                                        </label>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-success px-5 fw-bold shadow-sm">
                            <i class="fas fa-save me-2"></i>Guardar Disponibilidad
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Attendance / Assignment Card (Placeholder) -->
        <div class="card border-0 shadow-sm rounded-4 bg-white">
            <div class="card-header bg-white p-4 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2 text-warning"></i>Carga Académica / Actividad</h5>
                <span class="badge bg-light text-dark fw-normal">Próximamente</span>
            </div>
            <div class="card-body p-5 text-center text-muted">
                <i class="fas fa-tools fa-3x mb-3 text-light"></i>
                <p>La vinculación con horarios y grados específicos para docentes estará disponible en la siguiente actualización.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
