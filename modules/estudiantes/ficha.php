<?php
$page_name = 'estudiantes';
$page_title = 'Ficha del Estudiante';
require_once '../../includes/db.php'; // Incluir db.php primero para arrancar session y obtener config
require_once '../../includes/header.php';

$pdo = db_connect();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
  echo "<div class='alert alert-danger'>Estudiante no especificado.</div>";
  require_once '../../includes/footer.php';
  exit;
}

// Obtener datos del estudiante con su grado
$stmt = $pdo->prepare("
    SELECT e.*, g.nombre as grado, s.nombre as seccion, n.nombre as nivel, h.turno 
    FROM estudiantes e 
    LEFT JOIN niveles n ON e.nivel_id = n.id
    LEFT JOIN grados g ON e.grado_id = g.id
    LEFT JOIN secciones s ON e.seccion_id = s.id
    LEFT JOIN turnos h ON e.turno_id = h.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$estudiante = $stmt->fetch();

if (!$estudiante) {
  echo "<div class='alert alert-warning'>Estudiante no encontrado.</div>";
  require_once '../../includes/footer.php';
  exit;
}

// Obtener registros de asistencia recientes (últimos 10)
$stmt_asistencia = $pdo->prepare("
    SELECT * 
    FROM asistencia 
    WHERE estudiante_id = ? 
    ORDER BY fecha_hora DESC 
    LIMIT 10
");
$stmt_asistencia->execute([$id]);
$asistencias = $stmt_asistencia->fetchAll();

// Calcular estadísticas de asistencia
$stmt_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_registros,
        SUM(CASE WHEN tipo = 'entrada' THEN 1 ELSE 0 END) as total_entradas,
        SUM(CASE WHEN tipo = 'salida' THEN 1 ELSE 0 END) as total_salidas
    FROM asistencia 
    WHERE estudiante_id = ?
");
$stmt_stats->execute([$id]);
$stats = $stmt_stats->fetch();

// Obtener historial de pagos del estudiante
$stmt_pagos = $pdo->prepare("SELECT * FROM pagos WHERE estudiante_id = ? ORDER BY fecha_pago DESC, created_at DESC");
$stmt_pagos->execute([$id]);
$pagos_estudiante = $stmt_pagos->fetchAll();

// Función auxiliar para formatear la edad
function calcular_edad($fecha_nacimiento)
{
  $nacimiento = new DateTime($fecha_nacimiento);
  $hoy = new DateTime();
  return $hoy->diff($nacimiento)->y;
}

// Procesar envío de mensaje personalizado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_contacto'])) {
  $mensaje_personalizado = sanitize($_POST['mensaje']);
  $telefono = $estudiante['telefono_representante'];

  if (!empty($telefono)) {
    // Encolar en notificaciones_log siguiendo la lógica del sistema
    $stmtMsg = $pdo->prepare("INSERT INTO notificaciones_log (estudiante_id, telefono, tipo, mensaje, estado) VALUES (?, ?, 'whatsapp', ?, 'pendiente')");
    $stmtMsg->execute([$id, $telefono, $mensaje_personalizado]);
    $_SESSION['message'] = 'Mensaje encolado exitosamente para envío por WhatsApp.';
  } else {
    $_SESSION['error'] = 'Error: El representante no tiene un teléfono registrado.';
  }
  header("Location: ficha.php?id=$id");
  exit;
}

$message = $_SESSION['message'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['message'], $_SESSION['error']);
?>

<div class="row mb-4 align-items-center">
  <div class="col-12 col-md-6">
    <h2><i class="fas fa-id-card me-2 text-primary"></i>Ficha del Estudiante</h2>
  </div>
  <div class="col-12 col-md-6 text-md-end mt-3 mt-md-0">
    <a href="index.php" class="btn btn-secondary me-2">
      <i class="fas fa-arrow-left me-2"></i>Volver
    </a>
    <button class="btn btn-primary" onclick="window.print()">
      <i class="fas fa-print me-2"></i>Imprimir Ficha
    </button>
  </div>
</div>

<?php if ($message): ?>
  <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4"><i
      class="fas fa-check-circle me-2"></i><?php echo $message; ?><button type="button" class="btn-close"
      data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4"><i
      class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?><button type="button" class="btn-close"
      data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-4">
  <!-- Panel Izquierdo: Perfil y Estadísticas -->
  <div class="col-12 col-lg-4">
    <!-- Tarjeta de Perfil -->
    <div class="card shadow-sm mb-4">
      <div class="card-body text-center p-4">
        <div class="mb-3">
          <?php if ($estudiante['foto']): ?>
            <img src="<?php echo APP_URL; ?>/assets/uploads/estudiantes/<?php echo $estudiante['foto']; ?>"
              class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
          <?php else: ?>
            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center border"
              style="width: 150px; height: 150px;">
              <i class="fas fa-user-graduate fa-4x text-secondary"></i>
            </div>
          <?php endif; ?>
        </div>

        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido']); ?>
        </h4>
        <p class="text-muted mb-3">Cédula Escolar: <?php echo htmlspecialchars($estudiante['cedula_escolar']); ?></p>

        <span class="badge badge-<?php echo $estudiante['estatus']; ?> mb-3 px-3 py-2" style="font-size: 0.9rem;">
          Estatus: <?php echo ucfirst($estudiante['estatus']); ?>
        </span>

        <div class="d-grid gap-2">
          <a href="index.php?edit_id=<?php echo $id; ?>" class="btn btn-outline-primary"><i
              class="fas fa-edit me-2"></i>Editar Perfil</a>
          <?php if ($estudiante['telefono_representante']): ?>
            <button type="button" class="btn btn-success" onclick="abrirModalContacto()">
              <i class="fab fa-whatsapp me-2"></i>Contactar Representante
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Tarjeta de Estadísticas Rápidas -->
    <div class="card shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-warning"></i>Estadísticas</h5>
      </div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
          <span class="text-muted">Total Entradas</span>
          <span class="fw-bold text-success"><?php echo $stats['total_entradas'] ?? 0; ?></span>
        </div>
        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
          <span class="text-muted">Total Salidas</span>
          <span class="fw-bold text-warning"><?php echo $stats['total_salidas'] ?? 0; ?></span>
        </div>
        <div class="d-flex justify-content-between">
          <span class="text-muted">UID Tarjeta RFID</span>
          <code
            class="fw-bold px-2 bg-light rounded text-dark"><?php echo $estudiante['rfid_uid'] ? htmlspecialchars($estudiante['rfid_uid']) : 'No asignada'; ?></code>
        </div>
      </div>
    </div>
  </div>

  <!-- Panel Derecho: Información Detallada -->
  <div class="col-12 col-lg-8">
    <!-- Información Académica y Personal -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white">
        <h5 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary"></i>Información General</h5>
      </div>
      <div class="card-body p-4">
        <div class="row g-4">
          <div class="col-md-4">
            <label class="text-muted small text-uppercase fw-bold mb-1">Nivel</label>
            <p class="mb-0 fs-6 fw-medium text-primary">
              <?php echo htmlspecialchars($estudiante['nivel'] ?? 'No asignado'); ?>
            </p>
          </div>
          <div class="col-md-4">
            <label class="text-muted small text-uppercase fw-bold mb-1">Grado / Año</label>
            <p class="mb-0 fs-6 fw-medium"><?php echo htmlspecialchars($estudiante['grado'] ?? '-'); ?></p>
          </div>
          <div class="col-md-4">
            <label class="text-muted small text-uppercase fw-bold mb-1">Sección</label>
            <p class="mb-0 fs-6 fw-medium"><span class="badge bg-light text-dark border">Sección
                "<?php echo htmlspecialchars($estudiante['seccion'] ?? '-'); ?>"</span></p>
          </div>
          <div class="col-md-6">
            <label class="text-muted small text-uppercase fw-bold mb-1">Año Escolar</label>
            <p class="mb-0 fs-6 fw-medium"><?php echo htmlspecialchars($estudiante['anno_escolar'] ?? '-'); ?></p>
          </div>

          <div class="col-md-4">
            <label class="text-muted small text-uppercase fw-bold mb-1">Fecha de Nacimiento</label>
            <p class="mb-0 fs-6 fw-medium">
              <?php echo date('d/m/Y', strtotime($estudiante['fecha_nacimiento'])); ?>
              <span class="badge bg-light text-dark ms-2"><?php echo calcular_edad($estudiante['fecha_nacimiento']); ?>
                años</span>
            </p>
          </div>
          <div class="col-md-4">
            <label class="text-muted small text-uppercase fw-bold mb-1">Género</label>
            <p class="mb-0 fs-6 fw-medium"><?php echo $estudiante['genero'] === 'M' ? 'Masculino' : 'Femenino'; ?></p>
          </div>
          <div class="col-md-4">
            <label class="text-muted small text-uppercase fw-bold mb-1">Dirección</label>
            <p class="mb-0 fs-6 fw-medium"><?php echo htmlspecialchars($estudiante['direccion'] ?? '-'); ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Información del Representante -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white">
        <h5 class="mb-0 fw-bold"><i class="fas fa-user-shield me-2 text-info"></i>Datos del Representante</h5>
      </div>
      <div class="card-body p-4">
        <div class="row g-4">
          <div class="col-md-6">
            <label class="text-muted small text-uppercase fw-bold mb-1">Nombre del Representante</label>
            <p class="mb-0 fs-6 fw-medium"><?php echo htmlspecialchars($estudiante['nombre_representante'] ?? '-'); ?>
            </p>
          </div>
          <div class="col-md-6">
            <label class="text-muted small text-uppercase fw-bold mb-1">Parentesco</label>
            <p class="mb-0 fs-6 fw-medium"><?php echo htmlspecialchars($estudiante['parentesco'] ?? '-'); ?></p>
          </div>
          <div class="col-md-6">
            <label class="text-muted small text-uppercase fw-bold mb-1">Teléfono Principal</label>
            <p class="mb-0 fs-6 fw-medium">
              <?php echo htmlspecialchars($estudiante['telefono_representante'] ?? '-'); ?>
            </p>
          </div>
          <div class="col-md-6">
            <label class="text-muted small text-uppercase fw-bold mb-1">Teléfono Alternativo</label>
            <p class="mb-0 fs-6 fw-medium"><?php echo htmlspecialchars($estudiante['telefono_alternativo'] ?? '-'); ?>
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Historial de Pagos (Estado de Cuenta) -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="fas fa-money-bill-wave me-2 text-success"></i>Historial de Pagos</h5>
        <a href="../cobranza/index.php" class="btn btn-sm btn-outline-success">Gestionar Pagos</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="bg-light">
            <tr>
              <th>Concepto</th>
              <th>Mes/Año</th>
              <th>Monto</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($pagos_estudiante)): ?>
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">No se han registrado pagos para este estudiante.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($pagos_estudiante as $p): ?>
                <tr>
                  <td>
                    <span class="fw-bold"><?php echo htmlspecialchars($p['concepto']); ?></span><br>
                    <small class="text-muted"><?php echo $p['metodo_pago']; ?></small>
                  </td>
                  <td><?php echo date("F", mktime(0, 0, 0, $p['mes_pagado'], 10)) . ' ' . $p['anio_pagado']; ?></td>
                  <td class="fw-bold text-dark">
                    <?php echo $p['moneda'] === 'USD' ? '$' : 'Bs.'; ?> <?php echo number_format($p['monto'], 2); ?>
                  </td>
                  <td><?php echo date('d/m/Y', strtotime($p['fecha_pago'])); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Historial Reciente -->
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-secondary"></i>Últimas Asistencias</h5>
        <a href="../reportes/index.php?estudiante_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary">Ver
          todas</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="bg-light">
            <tr>
              <th>Fecha</th>
              <th>Hora</th>
              <th>Tipo</th>
              <th>Método</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($asistencias)): ?>
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">No hay registros de asistencia recientes.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($asistencias as $asistencia):
                $fecha = new DateTime($asistencia['fecha_hora']);
                ?>
                <tr>
                  <td class="fw-medium text-dark"><?php echo $fecha->format('d/m/Y'); ?></td>
                  <td><i class="far fa-clock text-muted me-1"></i> <?php echo $fecha->format('h:i A'); ?></td>
                  <td>
                    <span class="badge badge-<?php echo $asistencia['tipo']; ?>">
                      <?php echo ucfirst($asistencia['tipo']); ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($asistencia['metodo'] == 'rfid'): ?>
                      <i class="fas fa-wifi text-primary me-1" title="Tarjeta RFID"></i> RFID
                    <?php else: ?>
                      <i class="fas fa-keyboard text-secondary me-1" title="Registro Manual"></i> Manual
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Modal Contactar Representante -->
<div class="modal fade" id="modalContacto" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-light border-0 p-4">
        <h5 class="modal-title fw-bold text-dark"><i class="fab fa-whatsapp me-2 text-success"></i>Enviar Mensaje de
          WhatsApp</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="accion_contacto" value="1">
        <div class="modal-body p-4">
          <div class="mb-4 p-3 bg-light rounded-3 border-start border-4 border-success">
            <h6 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($estudiante['nombre_representante']); ?></h6>
            <p class="text-muted small mb-0"><i class="fas fa-phone me-1"></i>
              <?php echo htmlspecialchars($estudiante['telefono_representante']); ?></p>
            <small class="text-muted">Representante de:
              <strong><?php echo htmlspecialchars($estudiante['nombre']); ?></strong></small>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold text-uppercase">Plantillas Rápidas</label>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill" onclick="setTemplate('pago')">💰
                Recordatorio Pago</button>
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill" onclick="setTemplate('cita')">📅
                Cita Docente</button>
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill"
                onclick="setTemplate('asistencia')">⚠️ Inasistencia</button>
              <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill"
                onclick="setTemplate('limpiar')"><i class="fas fa-eraser"></i> Limpiar</button>
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label text-muted small fw-bold text-uppercase">Mensaje a Enviar</label>
            <textarea name="mensaje" id="mensajeRepresentante" class="form-control bg-light" rows="5" required
              placeholder="Escribe aquí el mensaje que deseas enviar al representante..."></textarea>
            <div class="form-text mt-2">
              <i class="fas fa-info-circle me-1"></i> El mensaje se enviará automáticamente a través del bot de la
              institución.
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 bg-light p-4">
          <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success px-5 shadow-sm fw-bold">
            <i class="fas fa-paper-plane me-2"></i>Enviar Mensaje
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  let modalContacto;

  document.addEventListener('DOMContentLoaded', function() {
    // Mover el modal al body para evitar problemas de z-index y backdrop
    const modalEl = document.getElementById('modalContacto');
    document.body.appendChild(modalEl);
    modalContacto = new bootstrap.Modal(modalEl);
  });

  function abrirModalContacto() {
    modalContacto.show();
  }

  function setTemplate(tipo) {
    const textarea = document.getElementById('mensajeRepresentante');
    const nombre = "<?php echo htmlspecialchars($estudiante['nombre']); ?>";
    const rep = "<?php echo htmlspecialchars($estudiante['nombre_representante']); ?>";

    let msj = "";
    switch (tipo) {
      case 'pago':
        msj = "Hola " + rep + ", le saludamos de la institución. Le recordamos amablemente que tiene una mensualidad pendiente por su representado(a) " + nombre + ". Por favor, pase por administración a la brevedad posible. ¡Feliz día!";
        break;
      case 'cita':
        msj = "Estimado " + rep + ", se requiere su presencia en la institución para una reunión con el docente de " + nombre + ". Por favor, confirme su asistencia. Saludos.";
        break;
      case 'asistencia':
        msj = "Hola " + rep + ", le informamos que el estudiante " + nombre + " no ha asistido a clases el día de hoy. ¿Existe algún inconveniente que debamos conocer?";
        break;
      case 'limpiar':
        msj = "";
        break;
    }
    textarea.value = msj;
    textarea.focus();
  }
</script>

<?php require_once '../../includes/footer.php'; ?>