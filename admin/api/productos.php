<?php
// admin/api/productos.php
include_once 'common.php';
include_once '../../models/Producto.php';

$producto = new Producto($db);
$method = $_SERVER['REQUEST_METHOD'];

// --- GET: Listar Productos ---
if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if ($id) {
        $item = $producto->leerUno($id);
        echo json_encode($item ? $item : ['error' => 'No encontrado']);
    } else {
        $stmt = $producto->leerTodos();
        echo json_encode($stmt); // leerTodos ya devuelve un array asociativo en tu modelo actual
    }
}

// --- POST: Crear o Actualizar (React enviará FormData) ---
if ($method === 'POST') {
    // Si viene un ID, es actualización
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // Asignar valores generales del POST al modelo
    $producto->id = $id;
    $producto->tipo = $_POST['tipo'] ?? 'articulo';
    $producto->nombre = $_POST['nombre'] ?? '';
    $producto->descripcion = $_POST['descripcion'] ?? '';
    $producto->precio = $_POST['precio'] ?? 0;
    $producto->stock = $_POST['stock'] ?? 1;
    
    // CORRECCIÓN: El estado de venta aplica para aves y artículos
    $producto->estado_venta = $_POST['estado_venta'] ?? 'disponible';
    
    // Campos específicos de Ave
    if ($producto->tipo === 'ave') {
        $producto->anillo = $_POST['anillo'] ?? '';
        $producto->edad = $_POST['edad'] ?? '';
        $producto->proposito = $_POST['proposito'] ?? '';
    }

    // Manejo de Portada (Si se subió una nueva)
    if (isset($_FILES['portada']) && $_FILES['portada']['error'] === UPLOAD_ERR_OK) {
        $ruta = $producto->subirPortada($_FILES['portada']);
        if ($ruta) {
            $producto->portada = $ruta;
        }
    }

    if ($id > 0) {
        // Actualizar
        if ($producto->actualizar()) {
            // Manejo de Galería Extra
            if (isset($_FILES['galeria'])) {
                $producto->agregarGaleria($id, $_FILES['galeria']);
            }
            jsonResponse(true, "Producto actualizado", ['id' => $id]);
        } else {
            jsonResponse(false, "Error al actualizar producto");
        }
    } else {
        // Crear
        if ($producto->crear()) {
            if (isset($_FILES['galeria'])) {
                $producto->agregarGaleria($producto->id, $_FILES['galeria']);
            }
            jsonResponse(true, "Producto creado", ['id' => $producto->id]);
        } else {
            jsonResponse(false, "Error al crear producto");
        }
    }
}

// --- DELETE: Eliminar ---
if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0 && $producto->eliminar($id)) {
        jsonResponse(true, "Producto eliminado");
    } else {
        jsonResponse(false, "Error al eliminar");
    }
}
?>