<?php
include_once 'common.php';
include_once '../../models/Configuracion.php';

$config = new Configuracion($db);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Obtenemos todas las configuraciones y las enviamos como un objeto JSON simple
    $stmt = $config->obtenerTodas();
    $configs_arr = array();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $configs_arr[$row['clave']] = $row['valor'];
    }
    
    echo json_encode($configs_arr);
}

if ($method === 'POST') {
    // Recibir JSON desde React
    $data = json_decode(file_get_contents("php://input"), true);
    
    if ($data && is_array($data)) {
        $errores = 0;
        
        // Iteramos sobre las llaves enviadas y las actualizamos
        foreach ($data as $clave => $valor) {
            // Asegurar que estamos trabajando con strings seguros
            $clave_limpia = htmlspecialchars(strip_tags($clave));
            $valor_limpio = htmlspecialchars(strip_tags($valor ?? ''));
            
            if (!$config->actualizar($clave_limpia, $valor_limpio)) {
                $errores++;
            }
        }
        
        if ($errores === 0) {
            jsonResponse(true, "Configuraciones actualizadas correctamente");
        } else {
            jsonResponse(false, "Hubo problemas al actualizar algunas configuraciones");
        }
    } else {
        jsonResponse(false, "Datos no válidos");
    }
}
?>