<?php
include_once 'common.php';
include_once '../../models/Configuracion.php';

$config = new Configuracion($db);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Verificar si se subió un archivo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        
        $uploadDir = '../../assets/images/';
        // Crear el directorio si no existe
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['logo']['tmp_name'];
        $fileName = $_FILES['logo']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validar extensión
        $allowedExts = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
        if (!in_array($fileExtension, $allowedExts)) {
            jsonResponse(false, 'Formato de imagen no permitido. Usa JPG, PNG, SVG o WEBP.');
            exit;
        }

        // Generar un nombre único basado en el tiempo para evitar la caché del navegador
        $newFileName = 'logo_' . time() . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // 1. Buscar si ya hay un logo viejo y borrarlo del servidor para ahorrar espacio
            $oldLogoPath = $config->obtenerPorClave('sistema_logo');
            if ($oldLogoPath && file_exists('../../' . $oldLogoPath)) {
                unlink('../../' . $oldLogoPath);
            }

            // 2. Guardar la nueva ruta relativa en la base de datos
            $dbPath = 'assets/images/' . $newFileName;
            $config->actualizar('sistema_logo', $dbPath);

            jsonResponse(true, 'Logo actualizado exitosamente', ['path' => $dbPath]);
        } else {
            jsonResponse(false, 'Error al guardar el archivo en el servidor.');
        }
    } else {
        jsonResponse(false, 'No se recibió ningún archivo válido o superó el peso máximo.');
    }
} else {
    jsonResponse(false, 'Método no permitido');
}
?>