<?php
include_once 'common.php';
include_once '../../models/Pago.php';

$pago = new Pago($db);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode($pago->obtenerTodos());
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data || !isset($data['action'])) {
        jsonResponse(false, "Acción no especificada");
        exit;
    }

    $action = $data['action'];
    
    if ($action === 'create') {
        $id = $pago->crear($data);
        jsonResponse(true, "Canal creado", ['id' => $id]);
    } elseif ($action === 'update') {
        $pago->actualizar($data['id'], $data);
        jsonResponse(true, "Canal actualizado");
    } elseif ($action === 'delete') {
        $pago->eliminar($data['id']);
        jsonResponse(true, "Canal eliminado");
    } else {
        jsonResponse(false, "Acción desconocida");
    }
}
?>