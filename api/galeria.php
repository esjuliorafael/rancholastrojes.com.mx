<?php
/* -------------------------------------------------------------------------- */
/* CONFIGURACIÓN                               */
/* -------------------------------------------------------------------------- */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../models/Logo.php';
include_once '../models/Categoria.php';
include_once '../models/Medio.php';

$database = new Database();
$db = $database->getConnection();

/* -------------------------------------------------------------------------- */
/* LÓGICA                                    */
/* -------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // 1. Obtener logo activo
    $logo = new Logo($db);
    $logo_activo = $logo->obtenerLogoActivo();
    
    // 2. Obtener categorías de la galería
    $categoria = new Categoria($db);
    $categorias = $categoria->obtenerTodas();
    
    // 3. Obtener medios (fotos/videos)
    $medio = new Medio($db);
    $medios = $medio->obtenerTodos();
    
    // 4. Preparar respuesta unificada
    $response = array(
        "logo"       => $logo_activo,
        "categorias" => $categorias,
        "medios"     => $medios
    );
    
    echo json_encode($response);
}
?>