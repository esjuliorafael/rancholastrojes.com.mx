<?php
// admin/api/categorias.php
include_once 'common.php';
include_once '../../models/Categoria.php';
include_once '../../models/Subcategoria.php';

$categoria = new Categoria($db);
$subcategoria = new Subcategoria($db);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $categoria->leerTodas();
    echo json_encode($stmt);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    $nombre = $data->nombre ?? '';
    $icono = $data->icono ?? 'folder';
    $id = $data->id ?? 0;
    // Recibimos array de nombres de subcategorías
    $subs = $data->subcategorias ?? []; 

    if (!empty($nombre)) {
        $categoria->nombre = $nombre;
        $categoria->icono = $icono;
        $categoria_id_final = 0;

        if ($id > 0) {
            $categoria->id = $id;
            if ($categoria->actualizar()) {
                $categoria_id_final = $id;
            } else {
                jsonResponse(false, "Error al actualizar categoría");
                exit;
            }
        } else {
            if ($categoria->crear()) {
                $categoria_id_final = $categoria->id;
            } else {
                jsonResponse(false, "Error al crear categoría");
                exit;
            }
        }

        // Manejo de Subcategorías (Borrar anteriores e insertar nuevas para simplificar)
        if ($categoria_id_final > 0) {
            $subcategoria->eliminarPorCategoria($categoria_id_final);
            if (!empty($subs) && is_array($subs)) {
                foreach ($subs as $subNombre) {
                    if (!empty(trim($subNombre))) {
                        $subcategoria->categoria_id = $categoria_id_final;
                        $subcategoria->nombre = trim($subNombre);
                        $subcategoria->crear();
                    }
                }
            }
            jsonResponse(true, "Categoría guardada correctamente");
        }
    } else {
        jsonResponse(false, "Datos incompletos");
    }
}

if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0 && $categoria->eliminar($id)) {
        jsonResponse(true, "Categoría eliminada");
    } else {
        jsonResponse(false, "Error al eliminar");
    }
}
?>