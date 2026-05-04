<?php
$page_name = 'configuracion';
$page_title = 'Estructura Escolar';
require_once '../../includes/db.php';
$pdo = db_connect();

// Procesar Acciones (CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $table = $_POST['table'];
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nombre = isset($_POST['nombre']) ? sanitize($_POST['nombre']) : '';

    try {
        if ($action === 'crear') {
            if ($table === 'turnos') {
                $turno = $_POST['turno'];
                $entrada = $_POST['hora_entrada'];
                $salida = $_POST['hora_salida'];
                $stmt = $pdo->prepare("INSERT INTO turnos (nombre, turno, hora_entrada, hora_salida) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre, $turno, $entrada, $salida]);
            } elseif ($table === 'aulas') {
                $tipo = $_POST['tipo'];
                $capacidad = intval($_POST['capacidad']);
                $stmt = $pdo->prepare("INSERT INTO aulas (nombre, tipo, capacidad) VALUES (?, ?, ?)");
                $stmt->execute([$nombre, $tipo, $capacidad]);
            } elseif ($table === 'materias') {
                $grado_id = intval($_POST['grado_id']);
                $horas = intval($_POST['horas_semanales']);
                $lab = isset($_POST['requiere_laboratorio']) ? 1 : 0;
                $color = $_POST['color'];
                $stmt = $pdo->prepare("INSERT INTO materias (nombre, nivel_tipo, grado_id, horas_semanales, requiere_laboratorio, color) VALUES (?, 'secundaria', ?, ?, ?, ?)");
                $stmt->execute([$nombre, $grado_id, $horas, $lab, $color]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO $table (nombre) VALUES (?)");
                $stmt->execute([$nombre]);
            }
            $_SESSION['message'] = "Elemento creado exitosamente.";
        } 
        elseif ($action === 'editar') {
            if ($table === 'turnos') {
                $turno = $_POST['turno'];
                $entrada = $_POST['hora_entrada'];
                $salida = $_POST['hora_salida'];
                $stmt = $pdo->prepare("UPDATE turnos SET nombre = ?, turno = ?, hora_entrada = ?, hora_salida = ? WHERE id = ?");
                $stmt->execute([$nombre, $turno, $entrada, $salida, $id]);
            } elseif ($table === 'aulas') {
                $tipo = $_POST['tipo'];
                $capacidad = intval($_POST['capacidad']);
                $stmt = $pdo->prepare("UPDATE aulas SET nombre = ?, tipo = ?, capacidad = ? WHERE id = ?");
                $stmt->execute([$nombre, $tipo, $capacidad, $id]);
            } elseif ($table === 'materias') {
                $grado_id = intval($_POST['grado_id']);
                $horas = intval($_POST['horas_semanales']);
                $lab = isset($_POST['requiere_laboratorio']) ? 1 : 0;
                $color = $_POST['color'];
                $stmt = $pdo->prepare("UPDATE materias SET nombre = ?, grado_id = ?, horas_semanales = ?, requiere_laboratorio = ?, color = ? WHERE id = ?");
                $stmt->execute([$nombre, $grado_id, $horas, $lab, $color, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE $table SET nombre = ? WHERE id = ?");
                $stmt->execute([$nombre, $id]);
            }
            $_SESSION['message'] = "Elemento actualizado exitosamente.";
        }
        elseif ($action === 'eliminar') {
            $en_uso = false;
            
            if ($table === 'especialidades') {
                // Especialidades se chequean en docentes
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM docentes WHERE especialidad_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "No se puede eliminar: La especialidad está asignada a docentes.";
                    $en_uso = true;
                }
            } elseif ($table === 'aulas') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM horarios_clases WHERE aula_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "No se puede eliminar: El aula tiene clases asignadas en el horario.";
                    $en_uso = true;
                }
            } elseif ($table === 'materias') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM horarios_clases WHERE materia_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "No se puede eliminar: La materia tiene clases asignadas en el horario.";
                    $en_uso = true;
                }
            } else {
                // Estructura académica se chequea en estudiantes
                $col_id = substr($table, 0, -2) . "_id";
                if ($table === 'secciones') $col_id = "seccion_id";
                if ($table === 'niveles') $col_id = "nivel_id";
                if ($table === 'turnos') $col_id = "turno_id";
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM estudiantes WHERE $col_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "No se puede eliminar: El elemento está siendo utilizado por estudiantes.";
                    $en_uso = true;
                }
            }

            if (!$en_uso) {
                $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['message'] = "Elemento eliminado exitosamente.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: estructura.php");
    exit;
}

require_once '../../includes/header.php';

// Cargar datos
$niveles = $pdo->query("SELECT * FROM niveles ORDER BY nombre")->fetchAll();
$grados = $pdo->query("SELECT * FROM grados ORDER BY nombre")->fetchAll();
$secciones = $pdo->query("SELECT * FROM secciones ORDER BY nombre")->fetchAll();
$horarios = $pdo->query("SELECT * FROM turnos ORDER BY turno, nombre")->fetchAll();
$especialidades = $pdo->query("SELECT * FROM especialidades ORDER BY nombre")->fetchAll();
$aulas = $pdo->query("SELECT * FROM aulas ORDER BY nombre")->fetchAll();
$materias = $pdo->query("SELECT m.*, g.nombre as grado_nombre FROM materias m LEFT JOIN grados g ON m.grado_id = g.id ORDER BY g.nombre, m.nombre")->fetchAll();

$message = $_SESSION['message'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['message'], $_SESSION['error']);
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6">
        <h2 class="fw-bold"><i class="fas fa-sitemap me-2 text-primary"></i>Estructura Escolar</h2>
        <p class="text-muted">Gestión de Niveles, Grados, Secciones y Horarios</p>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="index.php" class="btn btn-secondary shadow-sm">
            <i class="fas fa-arrow-left me-2"></i>Volver a Ajustes
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4"><i class="fas fa-check-circle me-2"></i><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white p-0 border-0">
        <ul class="nav nav-pills nav-justified p-2" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold py-3" data-bs-toggle="pill" data-bs-target="#tab-niveles" type="button">Niveles</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-3" data-bs-toggle="pill" data-bs-target="#tab-grados" type="button">Grados / Años</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-3" data-bs-toggle="pill" data-bs-target="#tab-secciones" type="button">Secciones</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-3" data-bs-toggle="pill" data-bs-target="#tab-horarios" type="button">Horarios / Turnos</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-3" data-bs-toggle="pill" data-bs-target="#tab-especialidades" type="button">Especialidades</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-3" data-bs-toggle="pill" data-bs-target="#tab-materias" type="button">Materias</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-3" data-bs-toggle="pill" data-bs-target="#tab-aulas" type="button">Aulas</button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4">
        <div class="tab-content" id="pills-tabContent">
            
            <!-- TABS CONTENIDO (Niveles, Grados, Secciones) -->
            <?php 
            $tabs = [
                ['id' => 'niveles', 'title' => 'Niveles', 'data' => $niveles, 'table' => 'niveles'],
                ['id' => 'grados', 'title' => 'Grados / Años', 'data' => $grados, 'table' => 'grados'],
                ['id' => 'secciones', 'title' => 'Secciones', 'data' => $secciones, 'table' => 'secciones'],
                ['id' => 'especialidades', 'title' => 'Especialidades', 'data' => $especialidades, 'table' => 'especialidades']
            ];
            foreach ($tabs as $t):
            ?>
            <div class="tab-pane fade <?php echo $t['id'] == 'niveles' ? 'show active' : ''; ?>" id="tab-<?php echo $t['id']; ?>">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Listado de <?php echo $t['title']; ?></h5>
                    <button class="btn btn-primary btn-sm px-3" onclick="abrirModalCrear('<?php echo $t['table']; ?>', '<?php echo $t['title']; ?>')">
                        <i class="fas fa-plus me-1"></i> Agregar
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Nombre</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($t['data'] as $item): ?>
                            <tr>
                                <td class="fw-bold"><?php echo $item['nombre']; ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary border-0 rounded-circle me-1" 
                                            onclick='abrirModalCrear("<?php echo $t['table']; ?>", "<?php echo $t['title']; ?>", <?php echo $item['id']; ?>, "<?php echo addslashes($item['nombre']); ?>")'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este elemento?')">
                                        <input type="hidden" name="action" value="eliminar">
                                        <input type="hidden" name="table" value="<?php echo $t['table']; ?>">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- TAB HORARIOS -->
            <div class="tab-pane fade" id="tab-horarios">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Gestión de Turnos y Horarios</h5>
                    <button class="btn btn-primary btn-sm px-3" onclick="abrirModalHorario()">
                        <i class="fas fa-plus me-1"></i> Agregar Horario
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Turno / Nombre</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horarios as $h): ?>
                            <tr>
                                <td>
                                    <span class="badge <?php echo $h['turno'] == 'manana' ? 'bg-primary' : ($h['turno'] == 'tarde' ? 'bg-warning text-dark' : 'bg-dark'); ?> me-2">
                                        <?php echo ucfirst($h['turno']); ?>
                                    </span>
                                    <span class="fw-bold"><?php echo $h['nombre']; ?></span>
                                </td>
                                <td><i class="far fa-clock text-success me-1"></i> <?php echo date('h:i A', strtotime($h['hora_entrada'])); ?></td>
                                <td><i class="far fa-clock text-danger me-1"></i> <?php echo date('h:i A', strtotime($h['hora_salida'])); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary border-0 rounded-circle me-1" 
                                            onclick='abrirModalEditarHorario(<?php echo json_encode($h); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este horario?')">
                                        <input type="hidden" name="action" value="eliminar">
                                        <input type="hidden" name="table" value="turnos">
                                        <input type="hidden" name="id" value="<?php echo $h['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB MATERIAS -->
            <div class="tab-pane fade" id="tab-materias">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold mb-0">Currículo Académico (Materias)</h5>
                        <p class="text-muted small mb-0">Organizado por Año y Especialidad</p>
                    </div>
                    <button class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalMateria()">
                        <i class="fas fa-plus me-1"></i> Agregar Materia
                    </button>
                </div>

                <?php 
                // Agrupar materias por grado en PHP
                $materias_por_grado = [];
                foreach ($materias as $m) {
                    $grado = $m['grado_nombre'] ?: 'Sin Grado Asignado';
                    $materias_por_grado[$grado][] = $m;
                }
                ?>

                <div class="row g-4">
                    <?php foreach ($materias_por_grado as $grado_nombre => $lista_materias): ?>
                    <div class="col-12 col-xl-6">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-header bg-light border-0 p-3 d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary"><i class="fas fa-graduation-cap me-2"></i><?php echo $grado_nombre; ?></span>
                                <span class="badge bg-white text-primary border"><?php echo count($lista_materias); ?> Materias</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <tbody>
                                            <?php foreach ($lista_materias as $m): ?>
                                            <tr>
                                                <td class="ps-3" style="width: 10px;">
                                                    <div class="rounded-circle" style="width: 10px; height: 10px; background-color: <?php echo $m['color']; ?>;"></div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold small"><?php echo $m['nombre']; ?></div>
                                                    <?php if($m['requiere_laboratorio']): ?>
                                                        <span class="badge bg-info-subtle text-info smaller py-0 px-2">Laboratorio</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark fw-normal"><?php echo $m['horas_semanales']; ?>h</span>
                                                </td>
                                                <td class="text-end pe-3">
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta materia?')">
                                                        <input type="hidden" name="action" value="eliminar">
                                                        <input type="hidden" name="table" value="materias">
                                                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TAB AULAS -->
            <div class="tab-pane fade" id="tab-aulas">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Infraestructura (Aulas y Espacios)</h5>
                    <button class="btn btn-primary btn-sm px-3" onclick="abrirModalAula()">
                        <i class="fas fa-plus me-1"></i> Agregar Aula
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Nombre del Espacio</th>
                                <th>Tipo</th>
                                <th>Capacidad</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aulas as $a): ?>
                            <tr>
                                <td class="fw-bold"><?php echo $a['nombre']; ?></td>
                                <td>
                                    <span class="badge bg-light text-dark text-uppercase"><?php echo str_replace('_', ' ', $a['tipo']); ?></span>
                                </td>
                                <td><?php echo $a['capacidad']; ?> Alumnos</td>
                                <td class="text-end">
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este espacio?')">
                                        <input type="hidden" name="action" value="eliminar">
                                        <input type="hidden" name="table" value="aulas">
                                        <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Genérico para Crear (Niveles, Grados, Secciones) -->
<div class="modal fade" id="modalCrear" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0 p-4">
                <h5 class="modal-title fw-bold text-dark" id="modalTitulo">Agregar Elemento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="actionInput" value="crear">
                <input type="hidden" name="table" id="inputTable">
                <input type="hidden" name="id" id="inputId">
                <div class="modal-body p-4">
                    <div class="mb-0">
                        <label class="form-label text-muted small fw-bold text-uppercase">Nombre</label>
                        <input type="text" name="nombre" id="inputNombre" class="form-control bg-light" placeholder="Ej: 1er Grado, Sección A, etc." required autofocus>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-5 shadow-sm fw-bold">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Horario -->
<div class="modal fade" id="modalHorario" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0 p-4">
                <h5 class="modal-title fw-bold text-dark" id="modalHorarioTitulo">Nuevo Horario / Turno</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="horarioAction" value="crear">
                <input type="hidden" name="table" value="turnos">
                <input type="hidden" name="id" id="horarioId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Nombre del Bloque</label>
                        <input type="text" name="nombre" id="horarioNombre" class="form-control bg-light" placeholder="Ej: Bloque 1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Turno</label>
                        <select name="turno" id="horarioTurno" class="form-select bg-light">
                            <option value="manana">Mañana</option>
                            <option value="tarde">Tarde</option>
                            <option value="noche">Noche</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Hora Entrada</label>
                            <input type="time" name="hora_entrada" id="horarioEntrada" class="form-control bg-light" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Hora Salida</label>
                            <input type="time" name="hora_salida" id="horarioSalida" class="form-control bg-light" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-5 shadow-sm fw-bold">Guardar Horario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Materia -->
<div class="modal fade" id="modalMateria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0 p-4">
                <h5 class="modal-title fw-bold text-dark">Nueva Materia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="crear">
                <input type="hidden" name="table" value="materias">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Nombre de la Materia</label>
                        <input type="text" name="nombre" class="form-control bg-light" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Grado / Año</label>
                        <select name="grado_id" class="form-select bg-light" required>
                            <?php foreach ($grados as $g): ?>
                                <option value="<?php echo $g['id']; ?>"><?php echo $g['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Horas Semanales</label>
                            <input type="number" name="horas_semanales" class="form-control bg-light" value="2" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Color</label>
                            <input type="color" name="color" class="form-control form-control-color w-100 bg-light border-0" value="#0d6efd">
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="requiere_laboratorio" id="reqLab">
                        <label class="form-check-label fw-bold small text-uppercase" for="reqLab">Requiere Laboratorio</label>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-5 shadow-sm fw-bold">Guardar Materia</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Aula -->
<div class="modal fade" id="modalAula" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0 p-4">
                <h5 class="modal-title fw-bold text-dark">Nueva Aula / Espacio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="crear">
                <input type="hidden" name="table" value="aulas">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Nombre del Aula</label>
                        <input type="text" name="nombre" class="form-control bg-light" placeholder="Ej: Laboratorio 1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Tipo</label>
                        <select name="tipo" class="form-select bg-light">
                            <option value="regular">Regular</option>
                            <option value="laboratorio">Laboratorio</option>
                            <option value="deportivo">Espacio Deportivo</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-muted small fw-bold text-uppercase">Capacidad (Alumnos)</label>
                        <input type="number" name="capacidad" class="form-control bg-light" value="30" required>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-5 shadow-sm fw-bold">Guardar Aula</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let modalCrear, modalHorario, modalMateria, modalAula;

document.addEventListener('DOMContentLoaded', function() {
    modalCrear = new bootstrap.Modal(document.getElementById('modalCrear'));
    modalHorario = new bootstrap.Modal(document.getElementById('modalHorario'));
    modalMateria = new bootstrap.Modal(document.getElementById('modalMateria'));
    modalAula = new bootstrap.Modal(document.getElementById('modalAula'));
    
    // Mover al body
    document.body.appendChild(document.getElementById('modalCrear'));
    document.body.appendChild(document.getElementById('modalHorario'));
    document.body.appendChild(document.getElementById('modalMateria'));
    document.body.appendChild(document.getElementById('modalAula'));
});

function abrirModalCrear(table, title, id = null, nombre = '') {
    document.getElementById('inputTable').value = table;
    document.getElementById('inputId').value = id || '';
    document.getElementById('inputNombre').value = nombre;
    document.getElementById('actionInput').value = id ? 'editar' : 'crear';
    document.getElementById('modalTitulo').textContent = (id ? 'Editar ' : 'Agregar a ') + title;
    modalCrear.show();
}

function abrirModalHorario() {
    document.getElementById('modalHorarioTitulo').textContent = 'Nuevo Horario / Turno';
    document.getElementById('horarioAction').value = 'crear';
    document.getElementById('horarioId').value = '';
    document.getElementById('horarioNombre').value = '';
    document.getElementById('horarioEntrada').value = '';
    document.getElementById('horarioSalida').value = '';
    modalHorario.show();
}

function abrirModalEditarHorario(data) {
    document.getElementById('modalHorarioTitulo').textContent = 'Editar Horario / Turno';
    document.getElementById('horarioAction').value = 'editar';
    document.getElementById('horarioId').value = data.id;
    document.getElementById('horarioNombre').value = data.nombre;
    document.getElementById('horarioTurno').value = data.turno;
    document.getElementById('horarioEntrada').value = data.hora_entrada;
    document.getElementById('horarioSalida').value = data.hora_salida;
    modalHorario.show();
}

function abrirModalMateria() {
    modalMateria.show();
}

function abrirModalAula() {
    modalAula.show();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
