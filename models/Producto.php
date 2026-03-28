<?php
class Producto {
    private $conn;
    private $table_name = "productos";
    private $table_gallery = "productos_galeria";

    public $id;
    public $tipo;
    public $nombre;
    public $descripcion;
    public $precio;
    public $portada;
    public $stock;
    public $anillo;
    public $edad;
    public $proposito;
    public $estado_venta;
    public $activo;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function leerTodos($filtro_tipo = null) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE activo = 1";

        if ($filtro_tipo) {
            $query .= " AND tipo = :tipo";
        }

        $query .= " ORDER BY fecha_creacion DESC";

        $stmt = $this->conn->prepare($query);

        if ($filtro_tipo) {
            $stmt->bindParam(":tipo", $filtro_tipo);
        }

        $stmt->execute();
        
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($productos as &$prod) {
            $prod['galeria'] = $this->obtenerGaleria($prod['id']);
        }

        return $productos;
    }

    public function leerUno($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($producto) {
            $producto['galeria'] = $this->obtenerGaleria($id);
        }

        return $producto;
    }

    public function obtenerGaleria($producto_id) {
        $query = "SELECT * FROM " . $this->table_gallery . " WHERE producto_id = :pid";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":pid", $producto_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerRelacionados($tipo, $exclude_id, $limit = 4) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE tipo = :tipo 
                  AND id != :exclude_id 
                  AND activo = 1 
                  ORDER BY RAND() 
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":tipo", $tipo);
        $stmt->bindParam(":exclude_id", $exclude_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (tipo, nombre, descripcion, precio, portada, stock, anillo, edad, proposito, estado_venta) 
                  VALUES 
                  (:tipo, :nombre, :descripcion, :precio, :portada, :stock, :anillo, :edad, :proposito, :estado_venta)";

        $stmt = $this->conn->prepare($query);

        if ($this->tipo === 'ave') {
            $this->stock = ($this->estado_venta === 'disponible') ? 1 : 0;
        }

        $stmt->bindParam(":tipo", $this->tipo);
        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":descripcion", $this->descripcion);
        $stmt->bindParam(":precio", $this->precio);
        $stmt->bindParam(":portada", $this->portada);
        $stmt->bindParam(":stock", $this->stock);
        $stmt->bindParam(":anillo", $this->anillo);
        $stmt->bindParam(":edad", $this->edad);
        $stmt->bindParam(":proposito", $this->proposito);
        $stmt->bindParam(":estado_venta", $this->estado_venta);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function actualizar() {
        $query = "UPDATE " . $this->table_name . " 
                  SET nombre=:nombre, descripcion=:descripcion, precio=:precio, 
                      stock=:stock, anillo=:anillo, edad=:edad, proposito=:proposito, estado_venta=:estado_venta";

        if (!empty($this->portada)) {
            $query .= ", portada=:portada";
        }

        $query .= " WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        if ($this->tipo === 'ave') {
            $this->stock = ($this->estado_venta === 'disponible') ? 1 : 0;
        }

        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":descripcion", $this->descripcion);
        $stmt->bindParam(":precio", $this->precio);
        $stmt->bindParam(":stock", $this->stock);
        $stmt->bindParam(":anillo", $this->anillo);
        $stmt->bindParam(":edad", $this->edad);
        $stmt->bindParam(":proposito", $this->proposito);
        $stmt->bindParam(":estado_venta", $this->estado_venta);
        $stmt->bindParam(":id", $this->id);

        if (!empty($this->portada)) {
            $stmt->bindParam(":portada", $this->portada);
        }

        return $stmt->execute();
    }

    // --- FUNCIÓN ELIMINAR ACTUALIZADA (HARD DELETE) ---
    public function eliminar($id) {
        // 1. Obtener la información del producto antes de borrarlo
        $producto = $this->leerUno($id);
        if (!$producto) return false;

        try {
            $this->conn->beginTransaction();

            // 2. Eliminar fotos de la Galería Física
            // Obtenemos la galería asociada a este ID
            $galeria = $this->obtenerGaleria($id);
            foreach ($galeria as $foto) {
                // Ruta absoluta al archivo
                $ruta_foto = dirname(__DIR__) . "/" . $foto['ruta_archivo'];
                if (file_exists($ruta_foto) && is_file($ruta_foto)) {
                    unlink($ruta_foto); // Borrado físico
                }
            }
            
            // Borrar registros de galería en la BD
            $queryGal = "DELETE FROM " . $this->table_gallery . " WHERE producto_id = :pid";
            $stmtGal = $this->conn->prepare($queryGal);
            $stmtGal->bindParam(":pid", $id);
            $stmtGal->execute();

            // 3. Eliminar Portada Física
            if (!empty($producto['portada'])) {
                $ruta_portada = dirname(__DIR__) . "/" . $producto['portada'];
                if (file_exists($ruta_portada) && is_file($ruta_portada)) {
                    unlink($ruta_portada); // Borrado físico
                }
            }

            // 4. Eliminar el Registro del Producto en la BD
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            
            if ($stmt->execute()) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                return false;
            }

        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    public function subirPortada($archivo) {
        return $this->procesarSubida($archivo, 'tienda/portadas');
    }

    public function agregarGaleria($producto_id, $archivos) {
        $uploaded = 0;
        if (isset($archivos['tmp_name']) && is_array($archivos['tmp_name'])) {
            foreach ($archivos['tmp_name'] as $key => $tmp_name) {
                if ($archivos['error'][$key] === UPLOAD_ERR_OK) {
                    $file_array = [
                        'name' => $archivos['name'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => 0
                    ];
    
                    $ruta = $this->procesarSubida($file_array, 'tienda/galeria');
    
                    if ($ruta) {
                        $query = "INSERT INTO " . $this->table_gallery . " (producto_id, ruta_archivo) VALUES (:pid, :ruta)";
                        $stmt = $this->conn->prepare($query);
                        $stmt->bindParam(":pid", $producto_id);
                        $stmt->bindParam(":ruta", $ruta);
                        if ($stmt->execute()) $uploaded++;
                    }
                }
            }
        }
        return $uploaded;
    }

    private function procesarSubida($archivo, $subcarpeta) {
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $uuid = uniqid();
        $nuevo_nombre = $uuid . '.' . $extension;

        $ruta_db = "assets/uploads/" . $subcarpeta . "/" . $nuevo_nombre;
        
        $ruta_fisica = dirname(__DIR__) . "/" . $ruta_db;

        $directorio = dirname($ruta_fisica);
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        if (move_uploaded_file($archivo['tmp_name'], $ruta_fisica)) {
            return $ruta_db;
        }
        return false;
    }
}
?>