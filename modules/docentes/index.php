<?php
$page_name = 'docentes';
$page_title = 'Directorio de Docentes';
require_once '../../includes/db.php';
$pdo = db_connect();

// Procesar Acciones (Crear/Editar/Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($action === 'eliminar') {
        try {
            $stmt = $pdo->prepare("DELETE FROM docentes WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['message'] = "Docente eliminado correctamente.";
        } catch (Exception $e) {
            $_SESSION['error'] = "No se puede eliminar: El docente tiene registros asociados.";
        }
        header("Location: index.php");
        exit;
    }

    $nombre = sanitize($_POST['nombre'] ?? '');
    $apellido = sanitize($_POST['apellido'] ?? '');
    $cedula = sanitize($_POST['cedula'] ?? '');
    $telefono = sanitize($_POST['telefono'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $especialidad_id = !empty($_POST['especialidad_id']) ? intval($_POST['especialidad_id']) : null;
    $rfid_uid = !empty($_POST['rfid_uid']) ? sanitize($_POST['rfid_uid']) : null;
    $estatus = $_POST['estatus'] ?? 'activo';

    try {
        if ($action === 'crear') {
            $stmt = $pdo->prepare("INSERT INTO docentes (nombre, apellido, cedula, telefono, email, especialidad_id, rfid_uid, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $apellido, $cedula, $telefono, $email, $especialidad_id, $rfid_uid, $estatus]);
            $_SESSION['message'] = "Docente registrado correctamente.";
        } elseif ($action === 'editar') {
            $stmt = $pdo->prepare("UPDATE docentes SET nombre=?, apellido=?, cedula=?, telefono=?, email=?, especialidad_id=?, rfid_uid=?, estatus=? WHERE id=?");
            $stmt->execute([$nombre, $apellido, $cedula, $telefono, $email, $especialidad_id, $rfid_uid, $estatus, $id]);
            $_SESSION['message'] = "Datos actualizados correctamente.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: index.php");
    exit;
}

require_once '../../includes/header.php';

// Obtener listado de docentes
$docentes = $pdo->query("
    SELECT d.*, esp.nombre as especialidad_nombre 
    FROM docentes d 
    LEFT JOIN especialidades esp ON d.especialidad_id = esp.id 
    ORDER BY d.apellido, d.nombre
")->fetchAll();

$especialidades_lista = $pdo->query("SELECT * FROM especialidades ORDER BY nombre")->fetchAll();

$message = $_SESSION['message'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['message'], $_SESSION['error']);
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h2 class="fw-bold text-dark"><i class="fas fa-chalkboard-teacher me-3 text-primary"></i>Gestión de Docentes</h2>
        <p class="text-muted mb-0">Listado oficial del personal académico de la institución</p>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <button class="btn btn-primary shadow-sm px-4 fw-bold rounded-3" onclick="abrirModalDocente()">
            <i class="fas fa-plus-circle me-2"></i>Nuevo Docente
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4"><i class="fas fa-check-circle me-2"></i><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
<?php endif; ?>

<!-- Buscador Interactivo -->
<div class="row mb-4">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden">
            <span class="input-group-text bg-white border-0 ps-4">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" id="busquedaDocente" class="form-control border-0 fs-6 py-3" 
                   placeholder="Buscar por nombre, apellido o cédula..." 
                   onkeyup="buscarEnListado()">
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4 py-3 text-muted small fw-bold text-uppercase">Docente</th>
                    <th class="py-3 text-muted small fw-bold text-uppercase">Identificación</th>
                    <th class="py-3 text-muted small fw-bold text-uppercase">Especialidad</th>
                    <th class="py-3 text-muted small fw-bold text-uppercase">Estatus</th>
                    <th class="py-3 text-muted small fw-bold text-uppercase text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($docentes)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">No hay docentes registrados.</td>
                    </tr>
                <?php else: foreach ($docentes as $d): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold me-3" style="width: 40px; height: 40px;">
                                    <?php echo strtoupper(substr($d['nombre'], 0, 1) . substr($d['apellido'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo $d['apellido'] . ', ' . $d['nombre']; ?></div>
                                    <div class="smaller text-muted"><?php echo $d['email']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="small fw-bold"><?php echo $d['cedula']; ?></div>
                            <div class="smaller text-muted"><?php echo $d['telefono']; ?></div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border fw-normal"><?php echo $d['especialidad_nombre'] ?: 'No asignada'; ?></span>
                        </td>
                        <td>
                            <span class="badge <?php echo $d['estatus'] == 'activo' ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 text-<?php echo $d['estatus'] == 'activo' ? 'success' : 'danger'; ?> rounded-pill px-3">
                                <?php echo ucfirst($d['estatus']); ?>
                            </span>
                        </td>
                        <td class="text-center pe-3">
                            <div class="btn-group shadow-sm rounded-3 overflow-hidden border">
                                <a href="ficha.php?id=<?php echo $d['id']; ?>" class="btn btn-white btn-sm border-end" title="Ver Perfil">
                                    <i class="fas fa-eye text-primary"></i>
                                </a>
                                <button class="btn btn-white btn-sm border-end" onclick='editarDocente(<?php echo json_encode($d); ?>)' title="Editar">
                                    <i class="fas fa-edit text-warning"></i>
                                </button>
                                <button class="btn btn-white btn-sm" onclick="eliminarDocente(<?php echo $d['id']; ?>)" title="Eliminar">
                                    <i class="fas fa-trash-alt text-danger"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
  /* Forzar centrado absoluto en el medio de la pantalla */
  #modalDocente {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(0, 0, 0, 0.4) !important;
    z-index: 99999 !important;
    display: none;
  }

  #modalDocente.show {
    display: flex !important;
    align-items: center;
    justify-content: center;
  }

  #modalDocente .modal-dialog {
    margin: auto !important;
    max-width: 600px;
    width: 95%;
  }
</style>

<!-- Modal Docente -->
<div class="modal" id="modalDocente" tabindex="-1" aria-hidden="true" data-bs-backdrop="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0 p-4">
                <h5 class="modal-title fw-bold" id="modalTitulo">Registrar Docente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="actionInput" value="crear">
                <input type="hidden" name="id" id="idInput">
                <div class="modal-body p-4 bg-white">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Nombres</label>
                            <input type="text" name="nombre" id="nombreInput" class="form-control bg-light border-0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Apellidos</label>
                            <input type="text" name="apellido" id="apellidoInput" class="form-control bg-light border-0" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted small fw-bold text-uppercase">Cédula</label>
                            <input type="text" name="cedula" id="cedulaInput" class="form-control bg-light border-0" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted small fw-bold text-uppercase">Especialidad</label>
                            <select name="especialidad_id" id="especialidadInput" class="form-select bg-light border-0">
                                <option value="">- Seleccionar -</option>
                                <?php foreach ($especialidades_lista as $esp): ?>
                                    <option value="<?php echo $esp['id']; ?>"><?php echo $esp['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Teléfono</label>
                            <input type="text" name="telefono" id="telefonoInput" class="form-control bg-light border-0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Estatus</label>
                            <select name="estatus" id="estatusInput" class="form-select bg-light border-0">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-bold text-uppercase">Correo</label>
                            <input type="email" name="email" id="emailInput" class="form-control bg-light border-0">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-bold text-uppercase">RFID UID (Opcional)</label>
                            <input type="text" name="rfid_uid" id="rfidInput" class="form-control bg-light border-0">
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

<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="eliminar">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
let modalDocente;
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('modalDocente');
    if (modalEl) {
        modalDocente = new bootstrap.Modal(modalEl, { 
            backdrop: false,
            keyboard: true 
        });
    }
});

function abrirModalDocente() {
    document.getElementById('actionInput').value = 'crear';
    document.getElementById('idInput').value = '';
    document.getElementById('modalTitulo').textContent = 'Registrar Nuevo Docente';
    document.getElementById('nombreInput').value = '';
    document.getElementById('apellidoInput').value = '';
    document.getElementById('cedulaInput').value = '';
    document.getElementById('telefonoInput').value = '';
    document.getElementById('emailInput').value = '';
    document.getElementById('especialidadInput').value = '';
    document.getElementById('rfidInput').value = '';
    document.getElementById('estatusInput').value = 'activo';
    modalDocente.show();
}

function editarDocente(d) {
    document.getElementById('actionInput').value = 'editar';
    document.getElementById('idInput').value = d.id;
    document.getElementById('modalTitulo').textContent = 'Editar Docente';
    document.getElementById('nombreInput').value = d.nombre;
    document.getElementById('apellidoInput').value = d.apellido;
    document.getElementById('cedulaInput').value = d.cedula;
    document.getElementById('telefonoInput').value = d.telefono;
    document.getElementById('emailInput').value = d.email;
    document.getElementById('especialidadInput').value = d.especialidad_id || '';
    document.getElementById('rfidInput').value = d.rfid_uid || '';
    document.getElementById('estatusInput').value = d.estatus;
    modalDocente.show();
}

function eliminarDocente(id) {
    if (confirm('¿Está seguro de eliminar este docente? Esta acción no se puede deshacer.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
function buscarEnListado() {
    const input = document.getElementById('busquedaDocente');
    const filter = input.value.toLowerCase();
    const table = document.querySelector('table');
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        let visible = false;
        const td = tr[i].getElementsByTagName('td');
        for (let j = 0; j < td.length; j++) {
            if (td[j]) {
                const textValue = td[j].textContent || td[j].innerText;
                if (textValue.toLowerCase().indexOf(filter) > -1) {
                    visible = true;
                    break;
                }
            }
        }
        tr[i].style.display = visible ? "" : "none";
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
