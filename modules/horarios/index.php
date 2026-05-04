<?php
$page_name = 'horarios';
$page_title = 'Gestión de Horarios';
require_once '../../includes/db.php';
require_once '../../includes/header.php';

$pdo = db_connect();

// Cargar filtros (Solo Secundaria y Media: 1er Año a 5to Año)
$grados = $pdo->query("SELECT * FROM grados WHERE nombre LIKE '%Año%' ORDER BY id")->fetchAll();
$secciones = $pdo->query("SELECT * FROM secciones ORDER BY nombre")->fetchAll();

$grado_id = isset($_GET['grado_id']) ? intval($_GET['grado_id']) : 0;
$seccion_id = isset($_GET['seccion_id']) ? intval($_GET['seccion_id']) : 0;

// Verificar si el horario está finalizado
$is_finalizado = false;
if ($grado_id && $seccion_id) {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM configuracion WHERE clave = ?");
  $stmt->execute(["horario_finalizado_{$grado_id}_{$seccion_id}"]);
  $is_finalizado = $stmt->fetchColumn() > 0;
}

// Obtener bloques horarios
$bloques = $pdo->query("SELECT * FROM bloques_horarios ORDER BY turno, orden")->fetchAll();
$dias = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'];

// Obtener materias del grado seleccionado con su progreso de asignación
$materias_disponibles = [];
if ($grado_id) {
  $stmt = $pdo->prepare("
    SELECT m.*, 
           (SELECT COUNT(*) FROM horarios_clases h 
            WHERE h.materia_id = m.id AND h.seccion_id = ?) as horas_asignadas
    FROM materias m 
    WHERE m.grado_id = ?
    ORDER BY m.nombre
  ");
  $stmt->execute([$seccion_id, $grado_id]);
  $materias_disponibles = $stmt->fetchAll();
}

// Obtener docentes con su especialidad y aulas para los selectores
$docentes = $pdo->query("
    SELECT d.id, d.nombre, d.apellido, esp.nombre as especialidad 
    FROM docentes d 
    LEFT JOIN especialidades esp ON d.especialidad_id = esp.id 
    WHERE d.estatus = 'activo' 
    ORDER BY d.apellido
")->fetchAll();
$aulas = $pdo->query("SELECT id, nombre, tipo FROM aulas ORDER BY nombre")->fetchAll();

// Obtener horario actual si hay sección seleccionada
$horario_actual = [];
if ($seccion_id && $grado_id) {
  $stmt = $pdo->prepare("
        SELECT h.*, m.nombre as materia_nombre, m.color, d.nombre as docente_nombre, d.apellido as docente_apellido, au.nombre as aula_nombre
        FROM horarios_clases h
        JOIN materias m ON h.materia_id = m.id
        JOIN docentes d ON h.docente_id = d.id
        JOIN aulas au ON h.aula_id = au.id
        WHERE h.seccion_id = ? AND h.grado_id = ?
    ");
  $stmt->execute([$seccion_id, $grado_id]);
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $key = $row['dia_semana'] . '|' . $row['bloque_id'];
    $horario_actual[$key] = $row;
  }
}
?>

<div class="row mb-4 align-items-center">
  <div class="col-12 col-md-6">
    <h2 class="fw-bold"><i class="fas fa-calendar-alt me-2 text-primary"></i>Planificador de Horarios</h2>
    <p class="text-muted">Diseño interactivo de carga académica por sección</p>
  </div>
  <div class="col-12 col-md-6 text-md-end">
    <?php if ($grado_id && !$is_finalizado): ?>
      <button class="btn btn-outline-success shadow-sm px-3 fw-bold me-2" onclick="generarTodoElGrado()">
        <i class="fas fa-layer-group me-2"></i>Generar Todo el Año
      </button>
      
      <?php if ($seccion_id): ?>
        <button class="btn btn-success shadow-sm px-3 fw-bold me-2" onclick="generarAutomatico()">
          <i class="fas fa-magic me-2"></i>Automático
        </button>
        <button class="btn btn-outline-danger shadow-sm px-3 fw-bold me-2" onclick="resetHorario()">
          <i class="fas fa-trash-alt me-2"></i>Resetear
        </button>
        <button class="btn btn-primary shadow-sm px-3 fw-bold me-2" onclick="finalizarHorario()">
          <i class="fas fa-check-double me-2"></i>Finalizar
        </button>
      <?php endif; ?>
    <?php endif; ?>

    <button class="btn btn-dark shadow-sm px-3 fw-bold" onclick="exportarPDF()">
      <i class="fas fa-file-pdf me-2"></i>Exportar PDF
    </button>
  </div>
</div>

<!-- Filtros de Sección -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
  <div class="card-body p-4">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label text-muted small fw-bold text-uppercase">Grado / Año</label>
        <select name="grado_id" class="form-select bg-light border-0" onchange="this.form.submit()">
          <option value="0">Seleccionar Grado</option>
          <?php foreach ($grados as $g): ?>
            <option value="<?php echo $g['id']; ?>" <?php echo $grado_id == $g['id'] ? 'selected' : ''; ?>>
              <?php echo $g['nombre']; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label text-muted small fw-bold text-uppercase">Sección</label>
        <select name="seccion_id" class="form-select bg-light border-0" onchange="this.form.submit()">
          <option value="0">Seleccionar Sección</option>
          <?php foreach ($secciones as $s): ?>
            <option value="<?php echo $s['id']; ?>" <?php echo $seccion_id == $s['id'] ? 'selected' : ''; ?>>
              <?php echo $s['nombre']; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <div class="alert alert-info mb-0 py-2 border-0 small">
          <i class="fas fa-info-circle me-1"></i> Seleccione grado y sección para empezar.
        </div>
      </div>
    </form>
  </div>
</div>

<div class="row g-4">
  <!-- Panel de Materias (Referencia) -->
  <div class="col-12 col-lg-3">
    <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
      <div class="card-header bg-white p-4 border-0">
        <h6 class="fw-bold mb-0">Materias del Grado</h6>
      </div>
      <div class="card-body p-4 pt-0">
        <?php if (empty($materias_disponibles)): ?>
          <p class="text-muted small">Seleccione un grado para ver las materias disponibles.</p>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($materias_disponibles as $m): 
                $completada = $m['horas_asignadas'] >= $m['horas_semanales'];
            ?>
              <div class="list-group-item px-0 py-3 border-0 <?php echo $completada ? 'opacity-50' : ''; ?>">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="d-flex align-items-center">
                    <div class="rounded-circle me-2"
                      style="width: 12px; height: 12px; background-color: <?php echo $m['color']; ?>;"></div>
                    <div class="flex-grow-1">
                      <div class="fw-bold small"><?php echo $m['nombre']; ?></div>
                      <div class="smaller text-muted">
                        <?php echo $m['horas_asignadas']; ?> / <?php echo $m['horas_semanales']; ?> horas
                      </div>
                    </div>
                  </div>
                  <div>
                    <?php if ($completada): ?>
                        <span class="badge bg-success rounded-pill"><i class="fas fa-check"></i></span>
                    <?php else: ?>
                        <span class="badge bg-light text-primary rounded-pill border"><?php echo $m['horas_semanales'] - $m['horas_asignadas']; ?> rest.</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <hr class="my-4">
        <div class="alert alert-info border-0 small p-3">
          <i class="fas fa-info-circle me-2"></i> Haga clic en cualquier espacio en blanco del horario para asignar una
          materia.
        </div>
      </div>
    </div>
  </div>

  <!-- Calendario Semanal -->
  <div class="col-12 col-lg-9">
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
      <div class="table-responsive">
        <table class="table table-bordered mb-0 text-center align-middle" id="horario-table">
          <thead class="bg-light">
            <tr>
              <th style="width: 120px;" class="py-3">Bloque</th>
              <?php foreach ($dias as $d): ?>
                <th class="py-3"><?php echo $d; ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bloques as $b): ?>
              <tr class="<?php echo $b['turno'] == 'tarde' ? 'bg-light bg-opacity-50' : ''; ?>">
                <td class="bg-light text-start p-3">
                  <div class="fw-bold smaller"><?php echo $b['nombre']; ?></div>
                  <div class="text-muted smaller" style="font-size: 0.75rem;">
                    <?php echo date('h:i', strtotime($b['hora_inicio'])); ?> -
                    <?php echo date('h:i A', strtotime($b['hora_fin'])); ?>
                  </div>
                </td>
                <?php foreach ($dias as $d):
                  $key = "$d|" . $b['id'];
                  $clase = $horario_actual[$key] ?? null;
                  ?>
                  <td class="p-1 <?php echo $is_finalizado ? 'bg-light' : 'clickable-slot'; ?>" data-dia="<?php echo $d; ?>"
                    data-bloque-id="<?php echo $b['id']; ?>"
                    onclick="<?php echo !$is_finalizado ? 'abrirModalAsignacion(this)' : ''; ?>"
                    style="height: 100px; min-width: 140px; cursor: <?php echo $is_finalizado ? 'default' : 'pointer'; ?>;">
                    <?php if ($clase): ?>
                      <div class="h-100 p-2 rounded-3 text-start shadow-sm border-start border-4 position-relative"
                        style="border-color: <?php echo $clase['color']; ?>; background: <?php echo $clase['color']; ?>15;"
                        onclick="event.stopPropagation()">
                        <div class="fw-bold smaller mb-1 text-truncate"><?php echo $clase['materia_nombre']; ?></div>
                        <div class="smaller text-muted text-truncate"><i
                            class="fas fa-user-tie me-1"></i><?php echo $clase['docente_apellido']; ?></div>
                        <div class="smaller text-muted text-truncate"><i
                            class="fas fa-door-open me-1"></i><?php echo $clase['aula_nombre']; ?></div>
                        <?php if (!$is_finalizado): ?>
                          <button class="btn btn-sm text-danger position-absolute top-0 end-0 p-1"
                            onclick="eliminarClase(<?php echo $clase['id']; ?>)">
                            <i class="fas fa-times-circle"></i>
                          </button>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  let modalAsignar;

  document.addEventListener('DOMContentLoaded', function () {
    // Form submission
    const formAsignar = document.getElementById('formAsignar');
    if (formAsignar) {
      formAsignar.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'save_clase');

        fetch('api.php', {
          method: 'POST',
          body: formData
        })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              location.reload();
            } else {
              alert('Error: ' + data.error);
            }
          });
      });
    }
  });

  function abrirModalAsignacion(zone) {
    if (!modalAsignar) {
      const modalEl = document.getElementById('modalAsignar');
      if (modalEl) modalAsignar = new bootstrap.Modal(modalEl, { 
        backdrop: false,
        keyboard: true 
      });
    }

    if (!modalAsignar) return;
    if (zone.querySelector('.rounded-3')) return;

    document.getElementById('dia_input').value = zone.dataset.dia;
    document.getElementById('bloque_id_input').value = zone.dataset.bloqueId;
    modalAsignar.show();
  }

  function autoSeleccionarDocente(select) {
    const option = select.options[select.selectedIndex];
    const docenteId = option.getAttribute('data-docente-id');
    const docenteSelect = document.getElementById('docente_select');
    if (docenteId && docenteSelect) {
      docenteSelect.value = docenteId;
    }
  }

  function eliminarClase(id) {
    if (confirm('¿Eliminar esta asignación?')) {
      const formData = new FormData();
      formData.append('action', 'delete_clase');
      formData.append('id', id);

      fetch('api.php', {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) location.reload();
        });
    }
  }

  function generarAutomatico() {
    const seccionId = <?php echo $seccion_id ?: 0; ?>;
    const gradoId = <?php echo $grado_id ?: 0; ?>;
    if (!seccionId || !gradoId) {
      alert('Seleccione un grado y una sección primero.');
      return;
    }
    if (confirm('Se generará un horario automático respetando disponibilidades y aulas. ¿Continuar?')) {
      const formData = new FormData();
      formData.append('action', 'generate_auto');
      formData.append('grado_id', gradoId);
      formData.append('seccion_id', seccionId);

      fetch('api.php', {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            alert('¡Horario generado con éxito!');
            location.reload();
          } else {
            alert('No se pudo completar el horario: ' + (data.error || 'Conflictos insolubles.'));
          }
        });
    }
  }

  function generarTodoElGrado() {
    const gradoId = <?php echo $grado_id ?: 0; ?>;
    if (!gradoId) {
      alert('Seleccione un año primero.');
      return;
    }
    if (confirm('Se generarán los horarios para TODAS las secciones de este año automáticamente. ¿Desea continuar?')) {
      const formData = new FormData();
      formData.append('action', 'generate_bulk_grado');
      formData.append('grado_id', gradoId);

      fetch('api.php', {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            alert('¡Todos los horarios del año han sido generados con éxito!');
            location.reload();
          } else {
            alert('Error en generación masiva: ' + (data.error || 'Conflictos insolubles.'));
          }
        });
    }
  }

  function exportarPDF() {
    const seccionId = <?php echo $seccion_id ?: 0; ?>;
    const gradoId = <?php echo $grado_id ?: 0; ?>;
    if (!seccionId || !gradoId) {
      alert('Seleccione un grado y una sección primero.');
      return;
    }
    window.open(`exportar_pdf.php?seccion_id=${seccionId}&grado_id=${gradoId}`, '_blank');
  }

  function resetHorario() {
    const seccionId = <?php echo $seccion_id ?: 0; ?>;
    const gradoId = <?php echo $grado_id ?: 0; ?>;
    if (confirm('¿Está seguro de borrar TODO el horario de esta sección?')) {
      const formData = new FormData();
      formData.append('action', 'reset_horario');
      formData.append('grado_id', gradoId);
      formData.append('seccion_id', seccionId);

      fetch('api.php', {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) location.reload();
        });
    }
  }

  function finalizarHorario() {
    const seccionId = <?php echo $seccion_id ?: 0; ?>;
    const gradoId = <?php echo $grado_id ?: 0; ?>;
    if (confirm('Al finalizar, el horario quedará bloqueado y no se podrá modificar. ¿Desea continuar?')) {
      const formData = new FormData();
      formData.append('action', 'finalizar_horario');
      formData.append('grado_id', gradoId);
      formData.append('seccion_id', seccionId);

      fetch('api.php', {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            alert('Horario finalizado y bloqueado con éxito.');
            location.reload();
          }
        });
    }
  }
</script>

<style>
  .clickable-slot {
    transition: background 0.2s;
    border: 1px dashed #ddd !important;
  }

  .clickable-slot:hover {
    background-color: rgba(13, 110, 253, 0.05);
  }

  .smaller {
    font-size: 0.8rem;
  }

  /* Forzar centrado absoluto en el medio de la pantalla */
  #modalAsignar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(0, 0, 0, 0.4) !important;
    z-index: 99999 !important;
    display: none;
  }

  #modalAsignar.show {
    display: flex !important;
    align-items: center;
    justify-content: center;
  }

  #modalAsignar .modal-dialog {
    margin: auto !important;
    max-width: 500px;
    width: 90%;
  }
</style>

<!-- Modal Asignar Clase -->
<div class="modal fade" id="modalAsignar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg border-primary rounded-4">
      <div class="modal-header bg-primary text-white p-4">
        <h5 class="modal-title fw-bold">Asignar Clase</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="formAsignar">
        <input type="hidden" name="grado_id" value="<?php echo $grado_id; ?>">
        <input type="hidden" name="seccion_id" value="<?php echo $seccion_id; ?>">
        <input type="hidden" name="dia_semana" id="dia_input">
        <input type="hidden" name="bloque_id" id="bloque_id_input">

        <div class="modal-body p-4 bg-white">
          <div class="mb-3">
            <label class="form-label text-muted small fw-bold text-uppercase">Materia</label>
            <select name="materia_id" id="materia_select" class="form-select bg-light border-0" required
              onchange="autoSeleccionarDocente(this)">
              <option value="">Seleccionar Materia</option>
              <?php foreach ($materias_disponibles as $mat): ?>
                <option value="<?php echo $mat['id']; ?>" data-docente-id="<?php echo $mat['docente_id'] ?? ''; ?>">
                  <?php echo $mat['nombre']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-bold text-uppercase">Docente</label>
            <select name="docente_id" id="docente_select" class="form-select bg-light border-0" required>
              <option value="">Seleccionar Docente</option>
              <?php foreach ($docentes as $doc): ?>
                <option value="<?php echo $doc['id']; ?>">
                  <?php echo $doc['apellido'] . ', ' . $doc['nombre']; ?> 
                  <?php echo $doc['especialidad'] ? '(' . $doc['especialidad'] . ')' : ''; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-0">
            <label class="form-label text-muted small fw-bold text-uppercase">Aula / Espacio</label>
            <select name="aula_id" id="aula_select" class="form-select bg-light border-0" required>
              <option value="">Seleccionar Aula</option>
              <?php foreach ($aulas as $au): ?>
                <option value="<?php echo $au['id']; ?>"><?php echo $au['nombre'] . ' (' . $au['tipo'] . ')'; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer border-0 bg-light p-4">
          <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-5 shadow-sm fw-bold">Guardar Asignación</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>