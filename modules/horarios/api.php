<?php
header('Content-Type: application/json');
error_reporting(0);
ob_start();
require_once '../../includes/db.php';
$pdo = db_connect();

$action = $_POST['action'] ?? '';

if ($action === 'save_clase') {
    $grado_id = $_POST['grado_id'];
    $seccion_id = $_POST['seccion_id'];
    $materia_id = $_POST['materia_id'];
    $docente_id = $_POST['docente_id'];
    $aula_id = $_POST['aula_id'];
    $dia = $_POST['dia_semana'];
    $bloque_id = $_POST['bloque_id'];

    // 0. Validar límite de horas semanales para esta materia en esta sección
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM horarios_clases WHERE seccion_id = ? AND materia_id = ?");
    $stmt->execute([$seccion_id, $materia_id]);
    $horas_ya_asignadas = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT horas_semanales FROM materias WHERE id = ?");
    $stmt->execute([$materia_id]);
    $horas_permitidas = $stmt->fetchColumn();

    if ($horas_ya_asignadas >= $horas_permitidas) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => "Esta materia ya alcanzó su límite de $horas_permitidas horas semanales para esta sección."]);
        exit;
    }

    // 1. Validar disponibilidad del docente (Tabla disponibilidad_docente)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM disponibilidad_docente WHERE docente_id = ? AND dia_semana = ? AND bloque_id = ?");
    $stmt->execute([$docente_id, $dia, $bloque_id]);
    if ($stmt->fetchColumn() == 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'El docente no está disponible en este horario.']);
        exit;
    }

    // 2. Validar conflictos
    // 2. Validar conflictos antes de insertar para dar error específico
    try {
        // Verificar Sección
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM horarios_clases WHERE seccion_id = ? AND dia_semana = ? AND bloque_id = ?");
        $stmt->execute([$seccion_id, $dia, $bloque_id]);
        if ($stmt->fetchColumn() > 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'La sección ya tiene una materia asignada en este bloque.']);
            exit;
        }

        // Verificar Docente
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM horarios_clases WHERE docente_id = ? AND dia_semana = ? AND bloque_id = ?");
        $stmt->execute([$docente_id, $dia, $bloque_id]);
        if ($stmt->fetchColumn() > 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'El docente ya tiene una clase asignada en otra sección en este bloque.']);
            exit;
        }

        // Verificar Aula
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM horarios_clases WHERE aula_id = ? AND dia_semana = ? AND bloque_id = ?");
        $stmt->execute([$aula_id, $dia, $bloque_id]);
        if ($stmt->fetchColumn() > 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'El aula ya está ocupada por otra clase en este bloque.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO horarios_clases (grado_id, seccion_id, materia_id, docente_id, aula_id, dia_semana, bloque_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$grado_id, $seccion_id, $materia_id, $docente_id, $aula_id, $dia, $bloque_id]);
        ob_end_clean();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
    }
}

elseif ($action === 'delete_clase') {
    $id = $_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM horarios_clases WHERE id = ?");
    $stmt->execute([$id]);
    ob_end_clean();
    echo json_encode(['success' => true]);
}

elseif ($action === 'generate_auto' || $action === 'generate_bulk_grado') {
    $grado_id = intval($_POST['grado_id']);
    $seccion_ids = [];

    if ($action === 'generate_bulk_grado') {
        $stmt = $pdo->prepare("SELECT id FROM secciones WHERE grado_id = ?");
        $stmt->execute([$grado_id]);
        $seccion_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $seccion_ids[] = intval($_POST['seccion_id']);
    }

    // 1. Cargar Datos Globales
    $stmt = $pdo->prepare("SELECT * FROM materias WHERE grado_id = ? ORDER BY requiere_laboratorio DESC, horas_semanales DESC");
    $stmt->execute([$grado_id]);
    $materias = $stmt->fetchAll();
    $bloques = $pdo->query("SELECT * FROM bloques_horarios ORDER BY turno, orden")->fetchAll();
    $dias = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'];
    $aulas = $pdo->query("SELECT id, tipo FROM aulas")->fetchAll();

    $pdo->beginTransaction();
    try {
        foreach ($seccion_ids as $seccion_id) {
            // Limpiar horario actual de esta sección
            $pdo->prepare("DELETE FROM horarios_clases WHERE seccion_id = ?")->execute([$seccion_id]);
            $asignaciones = [];

            foreach ($materias as $m) {
                $horas_a_asignar = intval($m['horas_semanales']);
                $docente_id = $m['docente_id'];

                if (!$docente_id) {
                    $stmt = $pdo->prepare("SELECT id FROM docentes WHERE especialidad_id = (SELECT especialidad_id FROM materias WHERE id = ?) AND estatus = 'activo' LIMIT 1");
                    $stmt->execute([$m['id']]);
                    $docente_id = $stmt->fetchColumn();
                }

                if (!$docente_id) continue;

                $intentos_totales = 0;
                while ($horas_a_asignar > 0 && $intentos_totales < 150) {
                    $intentos_totales++;
                    $dia = $dias[array_rand($dias)];
                    $bloque = $bloques[array_rand($bloques)];
                    $bloque_id = $bloque['id'];

                    // Reglas de Distribución
                    $conteo_dia = 0;
                    foreach($asignaciones as $a) {
                        if($a['dia'] == $dia && $a['materia_id'] == $m['id']) $conteo_dia++;
                    }
                    if ($m['horas_semanales'] <= 4 && $conteo_dia >= 1) continue;
                    if ($m['horas_semanales'] > 4 && $conteo_dia >= 2) continue;

                    // Disponibilidad Docente
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM disponibilidad_docente WHERE docente_id = ? AND dia_semana = ? AND bloque_id = ?");
                    $stmt->execute([$docente_id, $dia, $bloque_id]);
                    if ($stmt->fetchColumn() == 0) continue;

                    // Conflicto Docente (Global)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM horarios_clases WHERE docente_id = ? AND dia_semana = ? AND bloque_id = ?");
                    $stmt->execute([$docente_id, $dia, $bloque_id]);
                    if ($stmt->fetchColumn() > 0) continue;

                    // Conflicto Sección
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM horarios_clases WHERE seccion_id = ? AND dia_semana = ? AND bloque_id = ?");
                    $stmt->execute([$seccion_id, $dia, $bloque_id]);
                    if ($stmt->fetchColumn() > 0) continue;

                    // Tipo de Aula
                    $tipo_requerido = $m['requiere_laboratorio'] ? 'laboratorio' : 'regular';
                    $aula_id = null;
                    foreach ($aulas as $au) {
                        if ($au['tipo'] == $tipo_requerido) {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM horarios_clases WHERE aula_id = ? AND dia_semana = ? AND bloque_id = ?");
                            $stmt->execute([$au['id'], $dia, $bloque_id]);
                            if ($stmt->fetchColumn() == 0) {
                                $aula_id = $au['id'];
                                break;
                            }
                        }
                    }
                    if (!$aula_id) continue;

                    // Máximo 2 teóricas seguidas
                    $orden_actual = $bloque['orden'];
                    if ($tipo_requerido == 'regular' && $orden_actual > 2) {
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) FROM horarios_clases h
                            JOIN materias m2 ON h.materia_id = m2.id
                            JOIN bloques_horarios b2 ON h.bloque_id = b2.id
                            WHERE h.seccion_id = ? AND h.dia_semana = ? 
                            AND m2.requiere_laboratorio = 0
                            AND b2.orden IN (?, ?)
                        ");
                        $stmt->execute([$seccion_id, $dia, $orden_actual - 1, $orden_actual - 2]);
                        if ($stmt->fetchColumn() >= 2) continue;
                    }

                    // ASIGNAR
                    $stmt = $pdo->prepare("INSERT INTO horarios_clases (grado_id, seccion_id, materia_id, docente_id, aula_id, dia_semana, bloque_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$grado_id, $seccion_id, $m['id'], $docente_id, $aula_id, $dia, $bloque_id]);
                    
                    $asignaciones[] = ['dia' => $dia, 'bloque_id' => $bloque_id, 'materia_id' => $m['id']];
                    $horas_a_asignar--;
                }
                
                if ($horas_a_asignar > 0) {
                    $sec_nombre = $pdo->query("SELECT nombre FROM secciones WHERE id = $seccion_id")->fetchColumn();
                    throw new Exception("No se pudo completar el horario para la sección $sec_nombre (Materia: {$m['nombre']}). Verifique disponibilidad global de docentes.");
                }
            }
        }
        $pdo->commit();
        ob_end_clean();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

elseif ($action === 'reset_horario') {
    $grado_id = $_POST['grado_id'];
    $seccion_id = $_POST['seccion_id'];
    $stmt = $pdo->prepare("DELETE FROM horarios_clases WHERE grado_id = ? AND seccion_id = ?");
    $stmt->execute([$grado_id, $seccion_id]);
    ob_end_clean();
    echo json_encode(['success' => true]);
}

elseif ($action === 'finalizar_horario') {
    $grado_id = $_POST['grado_id'];
    $seccion_id = $_POST['seccion_id'];
    $clave = "horario_finalizado_{$grado_id}_{$seccion_id}";
    $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, '1')");
    $stmt->execute([$clave]);
    ob_end_clean();
    echo json_encode(['success' => true]);
}
