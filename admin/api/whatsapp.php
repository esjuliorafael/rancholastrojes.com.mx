<?php
include_once 'common.php';
include_once '../../models/Whatsapp.php';

$whatsapp = new Whatsapp($db);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode($whatsapp->obtenerTodos());
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data || !isset($data['action'])) {
        jsonResponse(false, "Acción no especificada");
        exit;
    }

    $action = $data['action'];
    
    if ($action === 'create') {
        $id = $whatsapp->crear($data);
        jsonResponse(true, "Canal creado", ['id' => $id]);
    } elseif ($action === 'update') {
        $whatsapp->actualizar($data['id'], $data);
        jsonResponse(true, "Canal actualizado");
    } elseif ($action === 'delete') {
        $whatsapp->eliminar($data['id']);
        jsonResponse(true, "Canal eliminado");
    } elseif ($action === 'toggle') {
        $whatsapp->toggleActivo($data['id'], $data['active']);
        jsonResponse(true, "Estado actualizado");
    } else {
        jsonResponse(false, "Acción desconocida");
    }
}
?>