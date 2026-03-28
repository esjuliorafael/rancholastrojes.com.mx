<?php
class Usuario {
    private $conn;
    private $table_name = "usuarios";

    public $id;
    public $username;
    public $password_hash;
    public $nombre;
    public $email;
    public $rol; // <-- NUEVO
    public $recibir_notificaciones;
    public $email_notificaciones;
    public $fecha_creacion;
    public $activo;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        $query = "SELECT id, username, password_hash, nombre, email, rol, recibir_notificaciones, email_notificaciones 
                  FROM " . $this->table_name . " 
                  WHERE (username = :username OR email = :email) 
                  AND activo = 1 LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":email", $username);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['password_hash'])) {
                $this->id = $row['id'];
                $this->username = $row['username'];
                $this->nombre = $row['nombre'];
                $this->email = $row['email'];
                $this->rol = $row['rol'];
                $this->recibir_notificaciones = (bool)$row['recibir_notificaciones'];
                $this->email_notificaciones = $row['email_notificaciones'];
                return true;
            }
        }
        return false;
    }

    public function crearUsuario($username, $password, $nombre, $email, $rol) {
        if ($this->existeUsuario($username, $email)) return false;

        $query = "INSERT INTO " . $this->table_name . " 
                  (username, password_hash, nombre, email, rol, recibir_notificaciones, email_notificaciones) 
                  VALUES (:username, :password_hash, :nombre, :email, :rol, 1, :email)";
        
        $stmt = $this->conn->prepare($query);
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":password_hash", $password_hash);
        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":rol", $rol);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function existeUsuario($username, $email, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE (username = :username OR email = :email)";
        if ($exclude_id) $query .= " AND id != :exclude_id";
        $query .= " LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":email", $email);
        if ($exclude_id) $stmt->bindParam(":exclude_id", $exclude_id);
        
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function cambiarPassword($usuario_id, $nueva_password) {
        $query = "UPDATE " . $this->table_name . " SET password_hash = :password_hash WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
        $stmt->bindParam(":password_hash", $password_hash);
        $stmt->bindParam(":id", $usuario_id);
        return $stmt->execute();
    }

    public function obtenerPorId($id) {
        $query = "SELECT id, username, nombre, email, rol, recibir_notificaciones, email_notificaciones, fecha_creacion, activo 
                  FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        if ($stmt->rowCount() > 0) return $stmt->fetch(PDO::FETCH_ASSOC);
        return false;
    }

    public function obtenerTodos() {
        $query = "SELECT id, username, nombre, email, rol, recibir_notificaciones, email_notificaciones, fecha_creacion, activo 
                  FROM " . $this->table_name . " ORDER BY nombre ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $usuarios_arr = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($usuarios_arr, $row);
        }
        return $usuarios_arr;
    }

    public function contarTotal() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE activo = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    public function desactivar($id) {
        if (isset($_SESSION['usuario_id']) && $id == $_SESSION['usuario_id']) return false;
        $query = "UPDATE " . $this->table_name . " SET activo = 0 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }

    public function activar($id) {
        $query = "UPDATE " . $this->table_name . " SET activo = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }

    public function eliminar($id) {
        if (isset($_SESSION['usuario_id']) && $id == $_SESSION['usuario_id']) return false;
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }

    public function actualizarPerfil($id, $nombre, $email) {
        if ($this->existeUsuario('', $email, $id)) return false;
        $query = "UPDATE " . $this->table_name . " SET nombre = :nombre, email = :email WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }

    public function actualizarAdministrador($id, $nombre, $email, $username, $rol, $password = null) {
        if ($this->existeUsuario($username, $email, $id)) return false;

        $query = "UPDATE " . $this->table_name . " SET nombre = :nombre, email = :email, username = :username, rol = :rol";
        if ($password) $query .= ", password_hash = :password_hash";
        $query .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":rol", $rol);
        $stmt->bindParam(":id", $id);
        
        if ($password) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bindParam(":password_hash", $password_hash);
        }
        
        return $stmt->execute();
    }

    public function actualizarPreferenciasNotificacion($id, $recibir_notificaciones, $email_notificaciones) {
        $query = "UPDATE " . $this->table_name . " SET recibir_notificaciones = :recibir_notificaciones, email_notificaciones = :email_notificaciones WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $recibir_int = $recibir_notificaciones ? 1 : 0;
        $stmt->bindParam(":recibir_notificaciones", $recibir_int, PDO::PARAM_INT);
        $stmt->bindParam(":email_notificaciones", $email_notificaciones);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }
}
?>