<?php
class Envio {
    private $conn;
    private $table_zonas = "zonas_envio";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function obtenerZonas() {
        // Mapeamos los nombres de columnas para que coincidan con React (id, name, zone)
        $query = "SELECT id, estado as name, tipo_zona as zone FROM " . $this->table_zonas . " ORDER BY estado ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function actualizarZonas($zonas_array) {
        try {
            $this->conn->beginTransaction();
            $query = "UPDATE " . $this->table_zonas . " SET tipo_zona = :zone WHERE id = :id";
            $stmt = $this->conn->prepare($query);

            foreach ($zonas_array as $zona) {
                // Validamos que venga la información correcta
                if (isset($zona['id']) && isset($zona['zone'])) {
                    $stmt->bindParam(':zone', $zona['zone']);
                    $stmt->bindParam(':id', $zona['id']);
                    $stmt->execute();
                }
            }
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?>