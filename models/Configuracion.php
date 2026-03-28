<?php
class Configuracion {
    private $conn;
    private $table_name = "configuracion";

    public $clave;
    public $valor;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Método nuevo para traer todo de golpe (Key-Value)
    public function obtenerTodas() {
        $query = "SELECT clave, valor FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Método para obtener una sola clave (por si lo ocupas en el checkout de la tienda)
    public function obtenerPorClave($clave) {
        $query = "SELECT valor FROM " . $this->table_name . " WHERE clave = :clave LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":clave", $clave);
        $stmt->execute();
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row['valor'];
        }
        return null;
    }

    // Método inteligente para Actualizar (Update) o Insertar (Insert)
    public function actualizar($clave, $valor) {
        // 1. Verificamos si la clave ya existe
        $query_check = "SELECT clave FROM " . $this->table_name . " WHERE clave = :clave LIMIT 1";
        $stmt_check = $this->conn->prepare($query_check);
        $stmt_check->bindParam(":clave", $clave);
        $stmt_check->execute();

        if ($stmt_check->rowCount() > 0) {
            // Ya existe, hacemos UPDATE
            $query = "UPDATE " . $this->table_name . " SET valor = :valor WHERE clave = :clave";
        } else {
            // No existe, hacemos INSERT
            $query = "INSERT INTO " . $this->table_name . " (clave, valor) VALUES (:clave, :valor)";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":clave", $clave);
        $stmt->bindParam(":valor", $valor);

        return $stmt->execute();
    }
}
?>