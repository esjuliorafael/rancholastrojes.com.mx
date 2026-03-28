<?php
/**
 * CRON JOB: Liberación de Stock y Cancelación de Órdenes Vencidas
 * * Este script debe ejecutarse automáticamente (ej. cada hora) o 
 * invocarse manualmente desde el admin si se prefiere un "pseudo-cron".
 * * Lógica:
 * 1. Verifica si el sistema de apartado está activo.
 * 2. Busca órdenes 'pendientes' que superen el tiempo límite.
 * 3. Itera sobre los detalles de la orden:
 * - Si es 'ave': Cambia estado_venta de 'reservado' a 'disponible'.
 * - Si es 'articulo': Suma la cantidad devuelta al stock.
 * 4. Marca la orden como 'cancelado'.
 */

// Ajustar rutas relativas según la ubicación de este archivo (carpeta /cron/)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Configuracion.php';

// Encabezado para respuesta texto plano (útil si se ejecuta vía navegador/curl)
header('Content-Type: text/plain; charset=utf-8');

try {
    $database = new Database();
    $db = $database->getConnection();
    $confModel = new Configuracion($db);
    $config = $confModel->obtenerConfiguracion();

    // 1. Verificar si el sistema está activo
    $sistemaActivo = isset($config['sistema_apartado_activo']) && $config['sistema_apartado_activo'] == '1';
    
    if (!$sistemaActivo) {
        die("LOG: El sistema de liberación automática está DESACTIVADO en la configuración.\n");
    }

    // 2. Calcular Tiempo Límite
    $horasLimite = isset($config['tiempo_limite_horas']) ? intval($config['tiempo_limite_horas']) : 24;
    // Restamos las horas a la fecha actual para obtener la "fecha de corte"
    // Ejemplo: Si son las 10:00 y límite es 2h, buscamos órdenes antes de las 08:00
    $fechaCorte = date('Y-m-d H:i:s', strtotime("-{$horasLimite} hours"));

    echo "LOG: Iniciando proceso de liberación.\n";
    echo "LOG: Buscando órdenes pendientes creadas antes de: $fechaCorte\n";

    // 3. Buscar Órdenes Vencidas
    // Solo estatus 'pendiente'
    $query = "SELECT id FROM ordenes WHERE estatus = 'pendiente' AND fecha_creacion <= :fecha_corte";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':fecha_corte', $fechaCorte);
    $stmt->execute();
    $ordenesVencidas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($ordenesVencidas)) {
        die("LOG: No se encontraron órdenes vencidas para liberar.\n");
    }

    echo "LOG: Se encontraron " . count($ordenesVencidas) . " órdenes vencidas. Procesando...\n";

    // 4. Procesar cada orden (Con Transacción para integridad)
    $ordenesProcesadas = 0;

    foreach ($ordenesVencidas as $ordenId) {
        $db->beginTransaction();

        try {
            // A. Obtener detalles de la orden para saber qué devolver
            $qDetalles = "SELECT d.producto_id, d.cantidad, p.tipo 
                          FROM ordenes_detalles d
                          JOIN productos p ON d.producto_id = p.id
                          WHERE d.orden_id = :orden_id";
            $stmtDet = $db->prepare($qDetalles);
            $stmtDet->bindParam(':orden_id', $ordenId);
            $stmtDet->execute();
            $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            // B. Devolver Stock / Estado por producto
            foreach ($detalles as $item) {
                if ($item['tipo'] === 'ave') {
                    // Ave: Volver a 'disponible'
                    // Solo si estaba 'reservado' (protección extra)
                    $updAve = "UPDATE productos SET estado_venta = 'disponible' 
                               WHERE id = :id AND estado_venta = 'reservado'";
                    $stmtAve = $db->prepare($updAve);
                    $stmtAve->bindParam(':id', $item['producto_id']);
                    $stmtAve->execute();
                    
                } elseif ($item['tipo'] === 'articulo') {
                    // Artículo: Devolver stock
                    $updArt = "UPDATE productos SET stock = stock + :cant 
                               WHERE id = :id";
                    $stmtArt = $db->prepare($updArt);
                    $stmtArt->bindParam(':cant', $item['cantidad']);
                    $stmtArt->bindParam(':id', $item['producto_id']);
                    $stmtArt->execute();
                }
            }

            // C. Cancelar la Orden
            $updOrden = "UPDATE ordenes SET estatus = 'cancelado' WHERE id = :id";
            $stmtOrd = $db->prepare($updOrden);
            $stmtOrd->bindParam(':id', $ordenId);
            $stmtOrd->execute();

            $db->commit();
            $ordenesProcesadas++;
            echo "LOG: Orden #$ordenId cancelada y stock liberado exitosamente.\n";

        } catch (Exception $e) {
            $db->rollBack();
            echo "ERROR: Falló liberación de orden #$ordenId. " . $e->getMessage() . "\n";
        }
    }

    echo "LOG: Finalizado. Total procesadas: $ordenesProcesadas.\n";

} catch (PDOException $e) {
    echo "ERROR CRÍTICO DE BASE DE DATOS: " . $e->getMessage();
}
?>