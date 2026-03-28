<?php
class Facturacion {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // --- SERVICIOS ANUALES ---
    public function obtenerServicios() {
        $query = "SELECT * FROM servicios_anuales ORDER BY fecha_vencimiento ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearServicio($data) {
        $query = "INSERT INTO servicios_anuales (concepto, descripcion, monto, fecha_contrato, fecha_vencimiento, tipo_icono) 
                  VALUES (:concepto, :descripcion, :monto, :fecha_contrato, :fecha_vencimiento, :tipo_icono)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':concepto' => $data['concept'],
            ':descripcion' => $data['description'] ?? null,
            ':monto' => $data['amount'],
            ':fecha_contrato' => $data['contractDate'] ?: null,
            ':fecha_vencimiento' => $data['dueDate'] ?: null,
            ':tipo_icono' => $data['iconType'] ?? 'default'
        ]);
        return $this->conn->lastInsertId();
    }

    public function actualizarServicio($id, $data) {
        $query = "UPDATE servicios_anuales 
                  SET concepto = :concepto, descripcion = :descripcion, monto = :monto, 
                      fecha_contrato = :fecha_contrato, fecha_vencimiento = :fecha_vencimiento 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':concepto' => $data['concept'],
            ':descripcion' => $data['description'] ?? null,
            ':monto' => $data['amount'],
            ':fecha_contrato' => $data['contractDate'] ?: null,
            ':fecha_vencimiento' => $data['dueDate'] ?: null,
            ':id' => $id
        ]);
    }

    public function toggleEstadoServicio($id, $pagado) {
        $query = "UPDATE servicios_anuales SET pagado = :pagado WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':pagado' => $pagado ? 1 : 0, ':id' => $id]);
    }

    public function eliminarServicio($id) {
        $query = "DELETE FROM servicios_anuales WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':id' => $id]);
    }

    // --- CARGOS EXTRA ---
    public function obtenerCargos() {
        $query = "SELECT * FROM cargos_extra ORDER BY fecha_cargo DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearCargo($data) {
        $query = "INSERT INTO cargos_extra (concepto, monto, fecha_cargo) VALUES (:concepto, :monto, :fecha_cargo)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':concepto' => $data['concept'],
            ':monto' => $data['amount'],
            ':fecha_cargo' => $data['date']
        ]);
        return $this->conn->lastInsertId();
    }

    public function actualizarCargo($id, $data) {
        $query = "UPDATE cargos_extra SET concepto = :concepto, monto = :monto WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':concepto' => $data['concept'],
            ':monto' => $data['amount'],
            ':id' => $id
        ]);
    }

    public function toggleEstadoCargo($id, $pagado) {
        $query = "UPDATE cargos_extra SET pagado = :pagado WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':pagado' => $pagado ? 1 : 0, ':id' => $id]);
    }

    public function eliminarCargo($id) {
        $query = "DELETE FROM cargos_extra WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':id' => $id]);
    }
}
?>