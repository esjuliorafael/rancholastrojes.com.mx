<?php
class Orden {
    private $conn;
    private $table = "ordenes";
    private $table_detalles = "ordenes_detalles";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Crea una orden verificando disponibilidad en tiempo real (Atomicidad)
     * Retorna array: ['success' => bool, 'orden_id' => int|null, 'message' => string]
     */
    public function crear($datos_cliente, $carrito, $costos) {
        try {
            $this->conn->beginTransaction();

            // 1. Insertar Encabezado de la Orden
            $query = "INSERT INTO " . $this->table . " 
                      (cliente_nombre, cliente_telefono, direccion_envio, estado_envio, subtotal, costo_envio, total) 
                      VALUES 
                      (:nombre, :tel, :dir, :edo, :sub, :envio, :total)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":nombre", $datos_cliente['nombre']);
            $stmt->bindParam(":tel", $datos_cliente['telefono']);
            $stmt->bindParam(":dir", $datos_cliente['direccion']);
            $stmt->bindParam(":edo", $datos_cliente['estado']);
            $stmt->bindParam(":sub", $costos['subtotal']);
            $stmt->bindParam(":envio", $costos['envio']);
            $stmt->bindParam(":total", $costos['total']);
            $stmt->execute();
            
            $orden_id = $this->conn->lastInsertId();

            // 2. Procesar Detalles con VERIFICACIÓN DE STOCK (Atomic Check)
            foreach ($carrito as $item) {
                
                // A. Intentar reservar/descontar stock PRIMERO
                if ($item['tipo'] == 'ave') {
                    // Solo actualiza SI el estado actual es 'disponible'
                    // Esto previene que dos personas compren la misma ave simultáneamente
                    $q_upd = "UPDATE productos 
                              SET estado_venta = 'reservado', stock = 0 
                              WHERE id = :id AND estado_venta = 'disponible'";
                    
                    $stmt_upd = $this->conn->prepare($q_upd);
                    $stmt_upd->bindParam(":id", $item['id']);
                    $stmt_upd->execute();

                    // Si rowCount es 0, significa que ya NO estaba disponible (Race Condition perdida)
                    if ($stmt_upd->rowCount() == 0) {
                        throw new Exception("El ave '" . $item['nombre'] . "' ya no está disponible. Alguien acaba de comprarla.");
                    }

                } else {
                    // Artículos: Solo actualiza SI el stock es suficiente
                    $q_upd = "UPDATE productos 
                              SET stock = stock - :cant 
                              WHERE id = :id AND stock >= :cant";
                    
                    $stmt_upd = $this->conn->prepare($q_upd);
                    $stmt_upd->bindParam(":cant", $item['cantidad']);
                    $stmt_upd->bindParam(":id", $item['id']);
                    $stmt_upd->execute();

                    // Si rowCount es 0, no había suficiente stock
                    if ($stmt_upd->rowCount() == 0) {
                        throw new Exception("Stock insuficiente para '" . $item['nombre'] . "'.");
                    }
                }

                // B. Si pasó la validación de stock, insertamos el detalle
                $q_det = "INSERT INTO " . $this->table_detalles . " 
                          (orden_id, producto_id, nombre_producto, tipo_producto, cantidad, precio_unitario) 
                          VALUES 
                          (:oid, :pid, :nom, :tipo, :cant, :precio)";
                
                $stmt_det = $this->conn->prepare($q_det);
                $stmt_det->bindParam(":oid", $orden_id);
                $stmt_det->bindParam(":pid", $item['id']);
                $stmt_det->bindParam(":nom", $item['nombre']);
                $stmt_det->bindParam(":tipo", $item['tipo']);
                $stmt_det->bindParam(":cant", $item['cantidad']);
                $stmt_det->bindParam(":precio", $item['precio']);
                $stmt_det->execute();
            }

            $this->conn->commit();
            return ['success' => true, 'orden_id' => $orden_id, 'message' => 'Orden creada'];

        } catch (Exception $e) {
            $this->conn->rollBack();
            // Retornamos el error específico (ej. "El ave X ya no está disponible")
            return ['success' => false, 'orden_id' => null, 'message' => $e->getMessage()];
        }
    }

    public function obtenerTodas() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY fecha_creacion DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDetalles($orden_id) {
        // Hacemos un LEFT JOIN para traer la 'portada' de la tabla productos
        $query = "SELECT od.*, p.portada 
                  FROM " . $this->table_detalles . " od
                  LEFT JOIN productos p ON od.producto_id = p.id
                  WHERE od.orden_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $orden_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cancelarOrden($id) {
        try {
            $this->conn->beginTransaction();

            $detalles = $this->obtenerDetalles($id);
            
            foreach ($detalles as $item) {
                if ($item['tipo_producto'] == 'ave') {
                    // Ave vuelve a estar disponible
                    $q = "UPDATE productos SET estado_venta = 'disponible', stock = 1 WHERE id = :id";
                    $stmt = $this->conn->prepare($q);
                    $stmt->bindParam(":id", $item['producto_id']);
                    $stmt->execute();
                } else {
                    // Articulo devuelve stock
                    $q = "UPDATE productos SET stock = stock + :cant WHERE id = :id";
                    $stmt = $this->conn->prepare($q);
                    $stmt->bindParam(":cant", $item['cantidad']);
                    $stmt->bindParam(":id", $item['producto_id']);
                    $stmt->execute();
                }
            }

            $query = "UPDATE " . $this->table . " SET estatus = 'cancelado' WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?>