<?php
class Pago {
    private $conn;
    private $table_name = "canales_pago";

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
                  (nombre, proposito, banco, beneficiario, clabe, tarjeta) 
                  VALUES (:nombre, :proposito, :banco, :beneficiario, :clabe, :tarjeta)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':nombre' => $data['name'],
            ':proposito' => $data['purposeKey'],
            ':banco' => $data['bankName'],
            ':beneficiario' => $data['beneficiary'],
            ':clabe' => $data['clabe'] ?? null,
            ':tarjeta' => $data['cardNumber'] ?? null
        ]);
        return $this->conn->lastInsertId();
    }

    public function actualizar($id, $data) {
        $query = "UPDATE " . $this->table_name . " 
                  SET nombre = :nombre, proposito = :proposito, banco = :banco, 
                      beneficiario = :beneficiario, clabe = :clabe, tarjeta = :tarjeta 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':nombre' => $data['name'],
            ':proposito' => $data['purposeKey'],
            ':banco' => $data['bankName'],
            ':beneficiario' => $data['beneficiary'],
            ':clabe' => $data['clabe'] ?? null,
            ':tarjeta' => $data['cardNumber'] ?? null,
            ':id' => $id
        ]);
    }

    public function eliminar($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':id' => $id]);
    }
}
?>