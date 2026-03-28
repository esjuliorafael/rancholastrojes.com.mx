<?php
include_once 'common.php';
include_once '../../models/Facturacion.php';

$facturacion = new Facturacion($db);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Devolvemos ambos arrays en un solo objeto para ahorrar peticiones
    echo json_encode([
        'services' => $facturacion->obtenerServicios(),
        'charges' => $facturacion->obtenerCargos()
    ]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data || !isset($data['action']) || !isset($data['type'])) {
        jsonResponse(false, "Datos incompletos");
        exit;
    }

    $action = $data['action']; // create, update, delete, toggle
    $type = $data['type']; // 'service' o 'charge'
    
    if ($type === 'service') {
        if ($action === 'create') {
            $id = $facturacion->crearServicio($data);
            jsonResponse(true, "Servicio creado", ['id' => $id]);
        } elseif ($action === 'update') {
            $facturacion->actualizarServicio($data['id'], $data);
            jsonResponse(true, "Servicio actualizado");
        } elseif ($action === 'delete') {
            $facturacion->eliminarServicio($data['id']);
            jsonResponse(true, "Servicio eliminado");
        } elseif ($action === 'toggle') {
            $facturacion->toggleEstadoServicio($data['id'], $data['isPaid']);
            jsonResponse(true, "Estado actualizado");
        }
    } 
    elseif ($type === 'charge') {
        if ($action === 'create') {
            $id = $facturacion->crearCargo($data);
            jsonResponse(true, "Cargo creado", ['id' => $id]);
        } elseif ($action === 'update') {
            $facturacion->actualizarCargo($data['id'], $data);
            jsonResponse(true, "Cargo actualizado");
        } elseif ($action === 'delete') {
            $facturacion->eliminarCargo($data['id']);
            jsonResponse(true, "Cargo eliminado");
        } elseif ($action === 'toggle') {
            $facturacion->toggleEstadoCargo($data['id'], $data['isPaid']);
            jsonResponse(true, "Estado actualizado");
        }
    }
}
?>