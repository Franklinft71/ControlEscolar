<?php
$page_name = 'estudiantes';
$page_title = 'Directorio de Estudiantes';
require_once '../../includes/db.php';
require_once '../../includes/header.php';

$pdo = db_connect();

// 1. Procesar Formularios (Crear, Actualizar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    
    // Procesar subida de foto
    $foto_nombre = null;
    $foto_representante_nombre = null;
    $upload_dir = '../../assets/uploads/estudiantes/';
    
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto_nombre = uniqid('est_') . '.' . strtolower($ext);
        move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto_nombre);
    }
    
    if (isset($_FILES['foto_representante']) && $_FILES['foto_representante']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto_representante']['name'], PATHINFO_EXTENSION);
        $foto_representante_nombre = uniqid('rep_') . '.' . strtolower($ext);
        move_uploaded_file($_FILES['foto_representante']['tmp_name'], $upload_dir . $foto_representante_nombre);
    }

    if ($action === 'crear') {
        $nivel_id = intval($_POST['nivel_id']);
        $horario_id = ($nivel_id == 2) ? 2 : 1; // 1: Primaria (Mañana), 2: Secundaria (Tarde)

        $stmt = $pdo->prepare("INSERT INTO estudiantes (cedula_escolar, nombre, apellido, fecha_nacimiento, genero, direccion, nivel_id, grado_id, seccion_id, horario_id, rfid_uid, anno_escolar, nombre_representante, telefono_representante, parentesco, observaciones, foto, foto_representante, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')");
        try {
            $stmt->execute([
                sanitize($_POST['cedula']), sanitize($_POST['nombre']), sanitize($_POST['apellido']), 
                sanitize($_POST['fecha_nacimiento']), sanitize($_POST['genero']), sanitize($_POST['direccion']), 
                $nivel_id, intval($_POST['grado_id']), intval($_POST['seccion_id']), $horario_id,
                sanitize($_POST['rfid_uid']), sanitize($_POST['anno_escolar']), 
                sanitize($_POST['nombre_representante']), sanitize($_POST['telefono_representante']), 
                sanitize($_POST['parentesco']), sanitize($_POST['observaciones']),
                $foto_nombre, $foto_representante_nombre
            ]);
            $_SESSION['message'] = 'Estudiante matriculado exitosamente';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error: Cédula o UID RFID ya registrado en otro estudiante.';
        }
        
    } elseif ($action === 'actualizar') {
        $id = intval($_POST['id']);
        $nivel_id = intval($_POST['nivel_id']);
        $horario_id = ($nivel_id == 2) ? 2 : 1;
        
        // Conservar fotos viejas si no se subieron nuevas
        if (!$foto_nombre) {
            $stmt = $pdo->prepare("SELECT foto FROM estudiantes WHERE id = ?");
            $stmt->execute([$id]);
            $foto_nombre = $stmt->fetchColumn();
        }
        if (!$foto_representante_nombre) {
            $stmt = $pdo->prepare("SELECT foto_representante FROM estudiantes WHERE id = ?");
            $stmt->execute([$id]);
            $foto_representante_nombre = $stmt->fetchColumn();
        }
        
        $stmt = $pdo->prepare("UPDATE estudiantes SET cedula_escolar=?, nombre=?, apellido=?, fecha_nacimiento=?, genero=?, direccion=?, nivel_id=?, grado_id=?, seccion_id=?, horario_id=?, rfid_uid=?, anno_escolar=?, nombre_representante=?, telefono_representante=?, parentesco=?, observaciones=?, foto=?, foto_representante=?, estatus=? WHERE id=?");
        try {
            $stmt->execute([
                sanitize($_POST['cedula']), sanitize($_POST['nombre']), sanitize($_POST['apellido']), 
                sanitize($_POST['fecha_nacimiento']), sanitize($_POST['genero']), sanitize($_POST['direccion']), 
                $nivel_id, intval($_POST['grado_id']), intval($_POST['seccion_id']), $horario_id,
                sanitize($_POST['rfid_uid']), sanitize($_POST['anno_escolar']), 
                sanitize($_POST['nombre_representante']), sanitize($_POST['telefono_representante']), 
                sanitize($_POST['parentesco']), sanitize($_POST['observaciones']),
                $foto_nombre, $foto_representante_nombre, sanitize($_POST['estatus']), $id
            ]);
            $_SESSION['message'] = 'Datos del estudiante actualizados';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error: No se pudo actualizar (Cédula duplicada).';
        }
    } elseif ($action === 'cambiar_estatus') {
        $id = intval($_POST['id']);
        $nuevo_estatus = sanitize($_POST['estatus']);
        $motivo = sanitize($_POST['motivo']);
        $usuario_id = $_SESSION['usuario_id'];

        try {
            $pdo->beginTransaction();
            
            // Actualizar estatus
            $stmt = $pdo->prepare("UPDATE estudiantes SET estatus = ? WHERE id = ?");
            $stmt->execute([$nuevo_estatus, $id]);
            
            // Registrar en historial
            $stmt = $pdo->prepare("INSERT INTO historial_acciones (estudiante_id, usuario_id, accion, motivo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $usuario_id, "Cambio de estatus a $nuevo_estatus", $motivo]);
            
            $pdo->commit();
            $_SESSION['message'] = 'Estatus actualizado y registrado correctamente';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Error al actualizar el estatus: ' . $e->getMessage();
        }
    }
    header('Location: index.php');
    exit;
}

if (isset($_SESSION['message'])) { $message = $_SESSION['message']; unset($_SESSION['message']); }
if (isset($_SESSION['error'])) { $error = $_SESSION['error']; unset($_SESSION['error']); }

// 2. Obtener datos para la vista
$stmt = $pdo->query("
    SELECT e.*, g.nombre as grado, s.nombre as seccion, n.nombre as nivel 
    FROM estudiantes e 
    LEFT JOIN niveles n ON e.nivel_id = n.id
    LEFT JOIN grados g ON e.grado_id = g.id
    LEFT JOIN secciones s ON e.seccion_id = s.id
    WHERE e.estatus != 'retirado' 
    ORDER BY e.apellido, e.nombre
");
$estudiantes = $stmt->fetchAll();

$niveles = $pdo->query("SELECT * FROM niveles ORDER BY nombre")->fetchAll();
$grados_lista = $pdo->query("SELECT * FROM grados ORDER BY nombre")->fetchAll();
$secciones_lista = $pdo->query("SELECT * FROM secciones ORDER BY nombre")->fetchAll();
$horarios_lista = $pdo->query("SELECT * FROM turnos ORDER BY nombre")->fetchAll();
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-sm-4">
        <h2><i class="fas fa-user-graduate me-2 text-primary"></i>Estudiantes</h2>
    </div>
    <div class="col-12 col-sm-4 text-center mt-3 mt-sm-0">
        <!-- Buscador -->
        <div class="input-group shadow-sm rounded-pill overflow-hidden bg-white">
            <span class="input-group-text bg-white border-0 text-muted ps-4"><i class="fas fa-search"></i></span>
            <input type="text" id="buscador" class="form-control border-0 shadow-none" placeholder="Buscar por nombre o cédula...">
        </div>
    </div>
    <div class="col-12 col-sm-4 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary shadow-sm" onclick="mostrarFormulario()">
            <i class="fas fa-user-plus me-2"></i>Nueva Matrícula
        </button>
    </div>
</div>

<?php if (isset($message)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0"><i class="fas fa-check-circle me-2"></i><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaEstudiantes">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4">Estudiante</th>
                        <th>Cédula</th>
                        <th>Grado / Sección</th>
                        <th>UID Tarjeta</th>
                        <th>Estatus</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($estudiantes)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No hay estudiantes registrados.</td></tr>
                    <?php else: foreach ($estudiantes as $e): 
                        $avatar = $e['foto'] ? '../../assets/uploads/estudiantes/' . $e['foto'] : 'https://ui-avatars.com/api/?name='.urlencode($e['nombre'].'+'.$e['apellido']).'&background=random';
                    ?>
                    <tr class="estudiante-row">
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <img src="<?php echo $avatar; ?>" class="rounded-circle me-3 object-fit-cover shadow-sm" style="width: 45px; height: 45px;">
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark search-target"><?php echo htmlspecialchars($e['apellido'] . ', ' . $e['nombre']); ?></h6>
                                    <small class="text-muted search-target"><?php echo htmlspecialchars($e['cedula_escolar']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="text-muted"><?php echo htmlspecialchars($e['cedula_escolar']); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars(($e['grado'] ?? '-') . ' ' . ($e['seccion'] ?? '')); ?></span></td>
                        <td><code class="bg-light px-2 py-1 rounded text-dark fw-bold"><?php echo htmlspecialchars($e['rfid_uid'] ?? '-'); ?></code></td>
                        <td>
                            <span class="badge badge-<?php echo $e['estatus']; ?> px-3 py-2 rounded-pill"><?php echo ucfirst($e['estatus']); ?></span>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-light text-warning me-1" title="Cambiar Estatus" onclick="abrirModalEstatus(<?php echo $e['id']; ?>)">
                                <i class="fas fa-user-tag"></i>
                            </button>
                            <button class="btn btn-sm btn-light text-primary me-1" title="Editar" onclick="editarEstudiante(<?php echo $e['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="ficha.php?id=<?php echo $e['id']; ?>" class="btn btn-sm btn-light text-info" title="Ver Ficha Completa">
                                <i class="fas fa-id-card"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Formulario Estudiante (Con Tabs) -->
<div class="modal fade" id="modalEstudiante" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-0 p-4">
                <h5 class="modal-title fw-bold text-dark" id="modalTitulo">Nuevo Estudiante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form method="POST" id="formEstudiante" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="crear">
                <input type="hidden" name="id" id="estudianteId">
                
                <div class="modal-body p-0">
                    <!-- Tabs Nav -->
                    <ul class="nav nav-tabs bg-light px-4 border-0" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active bg-white border-0 fw-bold text-dark py-3" data-bs-toggle="tab" data-bs-target="#tab-personal" type="button"><i class="fas fa-user me-2 text-primary"></i>Personal</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link bg-light border-0 text-muted py-3" data-bs-toggle="tab" data-bs-target="#tab-academico" type="button"><i class="fas fa-book me-2"></i>Académico</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link bg-light border-0 text-muted py-3" data-bs-toggle="tab" data-bs-target="#tab-representante" type="button"><i class="fas fa-users me-2"></i>Representante</button>
                        </li>
                    </ul>

                    <div class="tab-content p-4">
                        <!-- Tab Personal -->
                        <div class="tab-pane fade show active" id="tab-personal">
                            <div class="row g-3">
                                <div class="col-12 text-center mb-3">
                                    <div class="d-inline-block position-relative">
                                        <img src="https://ui-avatars.com/api/?name=Nuevo+Estudiante&size=150&background=e9ecef&color=6c757d" id="preview-foto" class="rounded-circle object-fit-cover border shadow-sm" style="width: 120px; height: 120px;">
                                        <label for="foto" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2 cursor-pointer shadow" style="cursor:pointer;">
                                            <i class="fas fa-camera"></i>
                                        </label>
                                        <input type="file" name="foto" id="foto" class="d-none" accept="image/*" onchange="previewImage(this, 'preview-foto')">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Nombres</label>
                                    <input type="text" name="nombre" id="nombre" class="form-control bg-light" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Apellidos</label>
                                    <input type="text" name="apellido" id="apellido" class="form-control bg-light" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Cédula</label>
                                    <input type="text" name="cedula" id="cedula" class="form-control bg-light fw-bold" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Fecha Nac.</label>
                                    <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control bg-light" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Género</label>
                                    <select name="genero" id="genero" class="form-select bg-light">
                                        <option value="M">Masculino</option>
                                        <option value="F">Femenino</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Dirección</label>
                                    <input type="text" name="direccion" id="direccion" class="form-control bg-light">
                                </div>
                            </div>
                        </div>

                        <!-- Tab Académico -->
                        <div class="tab-pane fade" id="tab-academico">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Nivel</label>
                                    <select name="nivel_id" id="nivel_id" class="form-select bg-light" required>
                                        <option value="">- Nivel -</option>
                                        <?php foreach ($niveles as $n): ?>
                                            <option value="<?php echo $n['id']; ?>"><?php echo $n['nombre']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Grado / Año</label>
                                    <select name="grado_id" id="grado_id" class="form-select bg-light" required>
                                        <option value="">- Grado -</option>
                                        <?php foreach ($grados_lista as $g): ?>
                                            <option value="<?php echo $g['id']; ?>"><?php echo $g['nombre']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Sección</label>
                                    <select name="seccion_id" id="seccion_id" class="form-select bg-light" required>
                                        <option value="">- Sección -</option>
                                        <?php foreach ($secciones_lista as $s): ?>
                                            <option value="<?php echo $s['id']; ?>"><?php echo $s['nombre']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Período / Año Escolar</label>
                                    <input type="text" name="anno_escolar" id="anno_escolar" class="form-control bg-light" placeholder="Ej: 2025-2026">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label text-muted small fw-bold text-uppercase text-primary"><i class="fas fa-wifi me-1"></i> Tarjeta RFID (UID)</label>
                                    <input type="text" name="rfid_uid" id="rfid_uid" class="form-control bg-light fw-bold" placeholder="Haz clic aquí y pasa la tarjeta por el lector...">
                                </div>
                                <div class="col-md-12" id="campoEstatus" style="display:none;">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Estatus del Alumno</label>
                                    <select name="estatus" id="estatus" class="form-select bg-light text-dark fw-bold">
                                        <option value="activo">Activo (Matriculado)</option>
                                        <option value="inactivo">Inactivo (Suspendido)</option>
                                        <option value="retirado">Retirado (Egresado/Baja)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Representante -->
                        <div class="tab-pane fade" id="tab-representante">
                            <div class="row g-4">
                                <div class="col-md-8">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Nombre del Representante</label>
                                    <input type="text" name="nombre_representante" id="nombre_representante" class="form-control bg-light">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Parentesco</label>
                                    <select name="parentesco" id="parentesco" class="form-select bg-light">
                                        <option value="">- Seleccionar -</option>
                                        <option value="Madre">Madre</option>
                                        <option value="Padre">Padre</option>
                                        <option value="Representante">Representante Legal</option>
                                        <option value="Tio/a">Tío/a</option>
                                        <option value="Abuelo/a">Abuelo/a</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold text-uppercase"><i class="fab fa-whatsapp text-success me-1"></i>Teléfono (Para Alertas)</label>
                                    <input type="text" name="telefono_representante" id="telefono_representante" class="form-control bg-light" placeholder="Ej: 584121234567">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold text-uppercase">Notas Médicas / Observaciones</label>
                                    <input type="text" name="observaciones" id="observaciones" class="form-control bg-light">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-5 shadow-sm fw-bold">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Cambiar Estatus -->
<div class="modal fade" id="modalEstatus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0 p-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-user-tag me-2 text-warning"></i>Gestión de Estatus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="cambiar_estatus">
                <input type="hidden" name="id" id="estatusEstudianteId">
                <div class="modal-body p-4">
                    <div id="estatusInfoEstudiante" class="mb-4 p-3 bg-light rounded-3 border-start border-4 border-warning">
                        <h6 class="mb-1 fw-bold text-dark" id="estatusNombreEstudiante"></h6>
                        <small class="text-muted" id="estatusCedulaEstudiante"></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Nuevo Estatus</label>
                        <select name="estatus" id="selectNuevoEstatus" class="form-select bg-light fw-bold" required>
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                            <option value="suspendido">Suspendido (Mora/Conducta)</option>
                            <option value="permiso">Permiso Especial</option>
                            <option value="retirado">Retirado</option>
                        </select>
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label text-muted small fw-bold text-uppercase">Motivo de la acción</label>
                        <textarea name="motivo" class="form-control bg-light" rows="3" placeholder="Ej: Falta de pago mensualidad Marzo, Retiro voluntario, etc." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning px-5 shadow-sm fw-bold">Actualizar Estatus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.badge-suspendido { background-color: #ffc107; color: #000; }
.badge-retirado { background-color: #6c757d; color: #fff; }
.badge-activo { background-color: #198754; color: #fff; }
.badge-inactivo { background-color: #dc3545; color: #fff; }
</style>

<script>
let modal, modalEstatus;
const estudiantes = <?php echo json_encode($estudiantes); ?>;

function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Mover el modal al body para evitar problemas de z-index con el sidebar
    document.body.appendChild(document.getElementById('modalEstudiante'));
    document.body.appendChild(document.getElementById('modalEstatus'));
    
    modal = new bootstrap.Modal(document.getElementById('modalEstudiante'));
    modalEstatus = new bootstrap.Modal(document.getElementById('modalEstatus'));

    // Soporte para abrir edición desde URL (Ficha -> Editar)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('edit_id')) {
        editarEstudiante(urlParams.get('edit_id'));
    }
});

const PERIODO_ACTUAL = "<?php echo $config['periodo_escolar_actual'] ?? ''; ?>";

// Buscador en tiempo real
document.getElementById('buscador').addEventListener('keyup', function() {
    const term = this.value.toLowerCase();
    const rows = document.querySelectorAll('.estudiante-row');
    rows.forEach(row => {
        const text = row.querySelector('.search-target').innerText.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
});

// Manejo de diseño de las Tabs del Modal
document.addEventListener('DOMContentLoaded', function() {
    const tabLinks = document.querySelectorAll('#modalEstudiante .nav-link');
    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            tabLinks.forEach(l => {
                l.classList.remove('bg-white', 'text-dark', 'fw-bold');
                l.classList.add('bg-light', 'text-muted');
            });
            this.classList.add('bg-white', 'text-dark', 'fw-bold');
            this.classList.remove('bg-light', 'text-muted');
        });
    });
});

// Previsualización de imagen subida
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function mostrarFormulario() { 
    document.getElementById('formAction').value = 'crear'; 
    document.getElementById('formEstudiante').reset(); 
    document.getElementById('preview-foto').src = 'https://ui-avatars.com/api/?name=Nuevo+Estudiante&size=150&background=e9ecef&color=6c757d';
    document.getElementById('modalTitulo').textContent = 'Nueva Matrícula'; 
    document.getElementById('campoEstatus').style.display = 'none'; 
    
    // Pre-llenar año escolar desde la configuración
    document.getElementById('anno_escolar').value = PERIODO_ACTUAL;

    // Ir a la primera pestaña
    document.querySelector('#modalEstudiante .nav-link').click();
    modal.show(); 
}

function editarEstudiante(id) { 
    const e = estudiantes.find(x => x.id == id); 
    if (!e) return; 
    
    document.getElementById('formAction').value = 'actualizar'; 
    document.getElementById('estudianteId').value = id; 
    
    // Llenar campos de texto
    document.getElementById('cedula').value = e.cedula_escolar || '';
    Object.keys(e).forEach(k => { 
        const f = document.getElementById(k); 
        if (f && f.type !== 'file') f.value = e[k] || ''; 
    }); 
    
    // Mostrar foto actual
    if (e.foto) {
        document.getElementById('preview-foto').src = '../../assets/uploads/estudiantes/' + e.foto;
    } else {
        document.getElementById('preview-foto').src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(e.nombre + ' ' + e.apellido) + '&size=150&background=random';
    }
    
    document.getElementById('modalTitulo').textContent = 'Ficha de: ' + e.nombre; 
    document.getElementById('campoEstatus').style.display = 'block'; 
    
    document.querySelector('#modalEstudiante .nav-link').click();
    modal.show(); 
}
function abrirModalEstatus(id) {
    const e = estudiantes.find(x => x.id == id);
    if (!e) return;
    
    document.getElementById('estatusEstudianteId').value = id;
    document.getElementById('estatusNombreEstudiante').textContent = e.apellido + ', ' + e.nombre;
    document.getElementById('estatusCedulaEstudiante').textContent = 'Cédula: ' + e.cedula_escolar;
    document.getElementById('selectNuevoEstatus').value = e.estatus;
    
    modalEstatus.show();
}
</script>

<?php require_once '../../includes/footer.php'; ?>