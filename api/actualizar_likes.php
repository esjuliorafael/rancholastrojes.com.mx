<?php
/* -------------------------------------------------------------------------- */
/* CONFIGURACIÓN                               */
/* -------------------------------------------------------------------------- */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar solicitud OPTIONS (preflight para CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../config/database.php';
include_once '../models/Medio.php';

$database = new Database();
$db = $database->getConnection();
$medio = new Medio($db);

/* -------------------------------------------------------------------------- */
/* LÓGICA                                    */
/* -------------------------------------------------------------------------- */

// Obtener datos del POST (enviados como JSON)
$data = json_decode(file_get_contents("php://input"));

// Validar que lleguen los datos necesarios
if (
    !empty($data->id) &&
    isset($data->likes) &&
    isset($data->isLiked)
) {
    // Sanitizar datos
    $id      = (int)$data->id;
    $likes   = (int)$data->likes;
    $isLiked = (bool)$data->isLiked;

    // Actualizar en la base de datos
    if ($medio->actualizarLikes($id, $likes, $isLiked)) {
        http_response_code(200);
        echo json_encode(array(
            "success" => true, 
            "message" => "Likes actualizados"
        ));
    } else {
        http_response_code(503);
        echo json_encode(array(
            "success" => false, 
            "message" => "No se pudo actualizar los likes"
        ));
    }
} else {
    // Error de datos incompletos
    http_response_code(400);
    echo json_encode(array(
        "success" => false, 
        "message" => "Datos incompletos"
    ));
}
?>