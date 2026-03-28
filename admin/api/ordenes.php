<?php
// admin/api/ordenes.php
include_once 'common.php';
include_once '../../models/Orden.php';

$orden = new Orden($db);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $ordenes = $orden->obtenerTodas();
    
    // Enriquecer el arreglo con los detalles de cada orden
    foreach ($ordenes as &$ord) {
        $ord['detalles'] = $orden->obtenerDetalles($ord['id']);
    }
    
    echo json_encode($ordenes);
}

if ($method === 'POST') {
    // Para acciones como cancelar
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (isset($data['accion']) && $data['accion'] === 'cancelar' && isset($data['id'])) {
        if ($orden->cancelarOrden($data['id'])) {
            jsonResponse(true, "Orden cancelada y stock restaurado");
        } else {
            jsonResponse(false, "No se pudo cancelar la orden");
        }
    }
}
?>