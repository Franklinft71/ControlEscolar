<?php
require_once __DIR__ . '/includes/db.php'; // Esto carga config.php y session_start()

// Si ya hay sesión iniciada, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = sanitize($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($usuario) || empty($password)) {
        $error = 'Por favor, ingrese su usuario y contraseña.';
    } else {
        $pdo = db_connect();
        $stmt = $pdo->prepare("SELECT id, nombre, usuario, password_hash, rol, estatus FROM usuarios WHERE usuario = ? LIMIT 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();
        
        // Verificar contraseña (bcrypt)
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['estatus'] === 'inactivo') {
                $error = 'Su cuenta está inactiva. Contacte al administrador.';
            } else {
                // Iniciar sesión
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre'];
                $_SESSION['usuario_rol'] = $user['rol'];
                
                // Actualizar último acceso
                $update = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
                $update->execute([$user['id']]);
                
                header('Location: index.php');
                exit;
            }
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .login-card {
            max-width: 420px;
            width: 100%;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0,0,0,0.1);
            overflow: hidden;
            background: #fff;
        }
        .login-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
            padding: 2.5rem 2rem;
            text-align: center;
            color: white;
        }
        .btn-login {
            background: #0d6efd;
            border: none;
            padding: 0.75rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(13, 110, 253, 0.2);
        }
        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
        }
        .form-control {
            border-left: none;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #dee2e6;
        }
        .input-group:focus-within {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            border-radius: 0.375rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <i class="fas fa-graduation-cap fa-3x mb-3 shadow-sm rounded-circle p-3 bg-white text-primary"></i>
        <h3 class="fw-bold mb-1">ControlEscolar</h3>
        <p class="text-white-50 mb-0">Sistema de Control de Acceso</p>
    </div>
    <div class="p-4 p-md-5">
        <h5 class="text-center mb-4 text-muted fw-normal">Bienvenido, ingresa tus credenciales</h5>
        
        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="form-label fw-semibold text-secondary">Usuario</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" name="usuario" class="form-control form-control-lg" placeholder="Ej. admin" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold text-secondary">Contraseña</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control form-control-lg" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-login w-100 text-white shadow-sm mt-2">
                <i class="fas fa-sign-in-alt me-2"></i> Entrar al Sistema
            </button>
            <div class="text-center mt-4 text-muted small">
                ¿Olvidaste tu contraseña? Contacta a soporte técnico.
            </div>
        </form>
    </div>
</div>

</body>
</html>
