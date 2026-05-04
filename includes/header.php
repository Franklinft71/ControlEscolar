<?php
require_once __DIR__ . '/db.php';

// Validar que exista la variable de la página actual para marcarla en el menú
if (!isset($page_name)) {
  $page_name = '';
}

// Validar que haya sesión iniciada
if (!isset($_SESSION['usuario_id'])) {
  header('Location: ' . APP_URL . '/login.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>

<body class="bg-light">
  <div class="d-flex">
    <?php if (!isset($hide_sidebar) || !$hide_sidebar): ?>
      <!-- Sidebar Navigation -->
      <div class="bg-dark text-white p-3 vh-100 position-fixed overflow-auto" style="width: 250px; z-index: 1000;">
        <div class="text-center mb-4 mt-2">
          <h4><i class="fas fa-graduation-cap me-2 text-primary"></i>ControlEscolar</h4>
          <small class="text-muted">Control de Acceso</small>
        </div>

        <ul class="nav flex-column gap-2 mb-5">
          <li class="nav-item">
            <a href="<?php echo APP_URL; ?>/index.php"
              class="nav-link text-white <?php echo $page_name === 'dashboard' ? 'bg-primary rounded' : ''; ?>">
              <i class="fas fa-tachometer-alt me-2 w-20px"></i> Dashboard
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo APP_URL; ?>/modules/estudiantes/index.php"
              class="nav-link text-white <?php echo $page_name === 'estudiantes' ? 'bg-primary rounded' : ''; ?>">
              <i class="fas fa-users me-2 w-20px"></i> Estudiantes
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo APP_URL; ?>/modules/rfid/index.php"
              class="nav-link text-white <?php echo $page_name === 'rfid' ? 'bg-primary rounded' : ''; ?>">
              <i class="fas fa-wifi me-2 w-20px"></i> Escáner RFID
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo APP_URL; ?>/modules/reportes/index.php"
              class="nav-link text-white <?php echo $page_name === 'reportes' ? 'bg-primary rounded' : ''; ?>">
              <i class="fas fa-chart-bar me-2 w-20px"></i> Reportes
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo APP_URL; ?>/modules/cobranza/index.php"
              class="nav-link text-white <?php echo $page_name === 'cobranza' ? 'bg-primary rounded' : ''; ?>">
              <i class="fas fa-hand-holding-usd me-2 w-20px"></i> Cobranza
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo APP_URL; ?>/modules/docentes/index.php"
              class="nav-link text-white <?php echo $page_name === 'docentes' ? 'bg-primary rounded' : ''; ?>">
              <i class="fas fa-chalkboard-teacher me-2 w-20px"></i> Docentes
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo APP_URL; ?>/modules/horarios/index.php"
              class="nav-link text-white <?php echo $page_name === 'horarios' ? 'bg-primary rounded' : ''; ?>">
              <i class="fas fa-calendar-alt me-2 w-20px"></i> Horarios
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo APP_URL; ?>/modules/configuracion/index.php"
              class="nav-link text-white <?php echo $page_name === 'configuracion' ? 'bg-primary rounded' : ''; ?>">
              <i class="fas fa-cogs me-2 w-20px"></i> Configuración
            </a>
          </li>
        </ul>
        <hr class="text-secondary border-2 opacity-50">
        <div class="position-absolute bottom-0 start-0 w-100 p-3">
          <a href="<?php echo APP_URL; ?>/logout.php" class="btn btn-outline-danger w-100">
            <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
          </a>
        </div>
      </div>

      <!-- Main Content Area -->
      <div class="flex-grow-1 p-4" style="margin-left: 250px;">
      <?php else: ?>
        <!-- Main Content Area (Full Width) -->
        <div class="flex-grow-1 p-4">
        <?php endif; ?>
