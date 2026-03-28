<?php
class Subcategoria {
    private $conn;
    private $table_name = "subcategorias";

    public $id;
    public $categoria_id;
    public $nombre;
    public $activo;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Leer todas las subcategorías de una categoría específica
    public function leerPorCategoria($categoria_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE categoria_id = :cid AND activo = 1 ORDER BY nombre ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":cid", $categoria_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear() {
        $query = "INSERT INTO " . $this->table_name . " (categoria_id, nombre) VALUES (:cid, :nombre)";
        $stmt = $this->conn->prepare($query);

        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        
        $stmt->bindParam(":cid", $this->categoria_id);
        $stmt->bindParam(":nombre", $this->nombre);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function eliminar($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
    
    // Función para borrar todas las subcategorías de una categoría (útil al actualizar)
    public function eliminarPorCategoria($categoria_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE categoria_id = :cid";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":cid", $categoria_id);
        return $stmt->execute();
    }
}
?>