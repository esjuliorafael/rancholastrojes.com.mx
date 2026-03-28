<?php
include_once 'common.php';
include_once '../../models/Envio.php';

$envio = new Envio($db);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode($envio->obtenerZonas());
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (is_array($data)) {
        if ($envio->actualizarZonas($data)) {
            jsonResponse(true, "Zonas actualizadas correctamente");
        } else {
            jsonResponse(false, "Error al actualizar las zonas");
        }
    } else {
        jsonResponse(false, "Datos inválidos");
    }
}
?>