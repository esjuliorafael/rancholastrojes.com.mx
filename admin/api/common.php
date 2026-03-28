<?php
// admin/api/common.php

// 1. Manejo de CORS (Permitir que React Vite se conecte en desarrollo)
header("Access-Control-Allow-Origin: *"); // En producción cambiar * por tu dominio
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Manejo de petición OPTIONS (Pre-flight de React)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Incluir Configuración y Modelos Globales
include_once '../../config/database.php';

// 3. Inicializar Conexión
$database = new Database();
$db = $database->getConnection();

// Función helper para responder JSON
function jsonResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}
?>