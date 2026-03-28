<?php
include_once 'common.php';
include_once '../../models/Usuario.php';

$usuario = new Usuario($db);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data || !isset($data['action'])) {
        jsonResponse(false, "Acción no especificada");
        exit;
    }

    if ($data['action'] === 'login') {
        if (empty($data['username']) || empty($data['password'])) {
            jsonResponse(false, "Por favor, ingresa tu usuario y contraseña.");
            exit;
        }
        
        // El método login() verifica tanto username como email
        if ($usuario->login($data['username'], $data['password'])) {
            // Aquí iniciaríamos las variables de sesión reales en PHP
            session_start();
            $_SESSION['usuario_id'] = $usuario->id;
            $_SESSION['usuario_nombre'] = $usuario->nombre;
            $_SESSION['usuario_rol'] = $usuario->rol; // <-- NUEVO: Guardar rol en sesión
            
            jsonResponse(true, "Login exitoso", [
                'id' => $usuario->id,
                'name' => $usuario->nombre,
                'email' => $usuario->email,
                'role' => $usuario->rol // <-- NUEVO: Enviar rol al frontend
            ]);
        } else {
            // El usuario o la contraseña fallaron, o el usuario está inactivo
            jsonResponse(false, "Credenciales incorrectas o cuenta inactiva. Verifica tus datos.");
        }
    } else {
        jsonResponse(false, "Acción desconocida");
    }
} else {
    jsonResponse(false, "Método no permitido");
}
?>