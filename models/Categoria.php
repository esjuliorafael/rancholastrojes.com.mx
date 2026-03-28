<?php
class Categoria {
    private $conn;
    private $table_name = "categorias";

    public $id;
    public $nombre;
    public $icono;
    public $activo;
    
    // Propiedad para almacenar subcategorías
    public $subcategorias = [];

    public function __construct($db) {
        $this->conn = $db;
    }

    public function leerTodas() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE activo = 1 ORDER BY nombre ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cargar subcategorías para cada categoría
        include_once 'Subcategoria.php';
        $subCatModel = new Subcategoria($this->conn);
        
        foreach ($categorias as &$cat) {
            $cat['subcategorias'] = $subCatModel->leerPorCategoria($cat['id']);
        }
        
        return $categorias;
    }

    public function leerUna($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($categoria) {
            include_once 'Subcategoria.php';
            $subCatModel = new Subcategoria($this->conn);
            $categoria['subcategorias'] = $subCatModel->leerPorCategoria($id);
        }
        return $categoria;
    }

    public function crear() {
        $query = "INSERT INTO " . $this->table_name . " (nombre, icono) VALUES (:nombre, :icono)";
        $stmt = $this->conn->prepare($query);

        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->icono = htmlspecialchars(strip_tags($this->icono));

        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":icono", $this->icono);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function actualizar() {
        $query = "UPDATE " . $this->table_name . " SET nombre = :nombre, icono = :icono WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->icono = htmlspecialchars(strip_tags($this->icono));

        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":icono", $this->icono);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    public function eliminar($id) {
        $query = "UPDATE " . $this->table_name . " SET activo = 0 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
}
?>