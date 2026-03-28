<?php
// admin/api/galeria.php
include_once 'common.php';
include_once '../../models/Medio.php';
include_once '../../models/Categoria.php';

$medio = new Medio($db);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $medios = $medio->obtenerTodos();
    echo json_encode($medios);
}

if ($method === 'POST') {
    // Crear o Editar
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // Datos básicos
    $titulo = $_POST['titulo'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $categoria_id = $_POST['categoria_id'] ?? 0;
    $subcategoria_id = $_POST['subcategoria_id'] ?? null; // NUEVO: Recibimos subcategoría
    // Si viene vacío o "0", lo convertimos a null
    if (empty($subcategoria_id) || $subcategoria_id === '0') {
        $subcategoria_id = null;
    }

    $tipo = $_POST['tipo'] ?? 'foto';
    $ubicacion = $_POST['ubicacion'] ?? '';
    $fecha_media = $_POST['fecha_media'] ?? date('Y-m-d');
    $video_thumbnail = $_POST['video_thumbnail'] ?? null; 

    // Verificar archivo
    $archivo = isset($_FILES['archivo']) ? $_FILES['archivo'] : null;
    
    if ($id == 0) {
        // --- CREAR ---
        $medio->titulo = $titulo;
        $medio->descripcion = $descripcion;
        $medio->tipo = $tipo;
        $medio->categoria_id = $categoria_id;
        $medio->subcategoria_id = $subcategoria_id; // Asignamos
        $medio->ubicacion = $ubicacion;
        $medio->fecha_media = $fecha_media;
        
        if ($archivo && $medio->subirMedio($archivo['tmp_name'], $archivo['name'], $tipo, $video_thumbnail)) {
             jsonResponse(true, "Medio subido correctamente");
        } else {
             jsonResponse(false, "Error al subir archivo");
        }
    } else {
        // --- ACTUALIZAR ---
        $tmp_name = $archivo ? $archivo['tmp_name'] : null;
        $name = $archivo ? $archivo['name'] : null;
        
        // Pasamos el nuevo argumento subcategoria_id
        if ($medio->actualizar($id, $titulo, $descripcion, $tipo, $categoria_id, $subcategoria_id, $ubicacion, $fecha_media, $tmp_name, $name, $video_thumbnail)) {
            jsonResponse(true, "Medio actualizado");
        } else {
            jsonResponse(false, "Error al actualizar");
        }
    }
}

// ... DELETE se queda igual
if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($medio->eliminar($id)) {
        jsonResponse(true, "Medio eliminado");
    } else {
        jsonResponse(false, "Error al eliminar");
    }
}
?>