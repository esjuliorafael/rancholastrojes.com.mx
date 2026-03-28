<?php
include_once 'common.php';
include_once '../../models/Usuario.php';

$usuario = new Usuario($db);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $userData = $usuario->obtenerPorId($_GET['id']);
        if ($userData) {
            echo json_encode($userData);
        } else {
            jsonResponse(false, "Usuario no encontrado");
        }
    } else {
        echo json_encode($usuario->obtenerTodos());
    }
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data || !isset($data['action'])) {
        jsonResponse(false, "Acción no especificada");
        exit;
    }

    $action = $data['action'];

    // CREAR USUARIO
    if ($action === 'create') {
        $rol = $data['role'] ?? 'staff';
        if ($usuario->crearUsuario($data['username'], $data['password'], $data['fullName'], $data['email'], $rol)) {
            jsonResponse(true, "Usuario creado exitosamente");
        } else {
            jsonResponse(false, "El nombre de usuario o correo ya están en uso");
        }
    } 
    // ACTUALIZAR USUARIO
    elseif ($action === 'update') {
        $password = !empty($data['password']) ? $data['password'] : null;
        $rol = $data['role'] ?? 'staff';
        if ($usuario->actualizarAdministrador($data['id'], $data['fullName'], $data['email'], $data['username'], $rol, $password)) {
            jsonResponse(true, "Usuario actualizado correctamente");
        } else {
            jsonResponse(false, "El nombre de usuario o correo ya están en uso por otra persona");
        }
    }
    // CAMBIAR ESTADO (Activo / Inactivo)
    elseif ($action === 'toggleStatus') {
        $success = $data['isActive'] ? $usuario->activar($data['id']) : $usuario->desactivar($data['id']);
        if ($success) {
            jsonResponse(true, "Estado actualizado");
        } else {
            jsonResponse(false, "No puedes desactivar tu propia cuenta mientras estás en sesión");
        }
    }
    // ELIMINAR USUARIO
    elseif ($action === 'delete') {
        if ($usuario->eliminar($data['id'])) {
            jsonResponse(true, "Usuario eliminado");
        } else {
            jsonResponse(false, "No puedes eliminar tu propia cuenta mientras estás en sesión");
        }
    }
    // ACTUALIZAR NOTIFICACIONES
    elseif ($action === 'update_notifications') {
        $email_notif = $data['email_notificaciones'] ?? null;
        if ($usuario->actualizarPreferenciasNotificacion($data['id'], $data['recibir_notificaciones'], $email_notif)) {
            jsonResponse(true, "Preferencias actualizadas");
        } else {
            jsonResponse(false, "Error al actualizar preferencias");
        }
    } 
    else {
        jsonResponse(false, "Acción desconocida");
    }
}
?>