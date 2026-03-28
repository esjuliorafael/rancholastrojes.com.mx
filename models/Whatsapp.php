<?php
class Whatsapp {
    private $conn;
    private $table_name = "canales_whatsapp";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function obtenerTodos() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY fecha_creacion DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (nombre, proposito, telefono, plantilla, activo) 
                  VALUES (:nombre, :proposito, :telefono, :plantilla, :activo)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':nombre' => $data['name'],
            ':proposito' => $data['purposeKey'],
            ':telefono' => $data['phoneNumber'],
            ':plantilla' => $data['template'],
            ':activo' => isset($data['active']) && $data['active'] ? 1 : 0
        ]);
        return $this->conn->lastInsertId();
    }

    public function actualizar($id, $data) {
        $query = "UPDATE " . $this->table_name . " 
                  SET nombre = :nombre, proposito = :proposito, telefono = :telefono, 
                      plantilla = :plantilla 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':nombre' => $data['name'],
            ':proposito' => $data['purposeKey'],
            ':telefono' => $data['phoneNumber'],
            ':plantilla' => $data['template'],
            ':id' => $id
        ]);
    }

    public function toggleActivo($id, $activo) {
        $query = "UPDATE " . $this->table_name . " SET activo = :activo WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':activo' => $activo ? 1 : 0, ':id' => $id]);
    }

    public function eliminar($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':id' => $id]);
    }
}
?>