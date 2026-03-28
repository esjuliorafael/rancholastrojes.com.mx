<?php
include_once 'common.php';
include_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stats = [];

    // 1. Productos Activos
    $queryProducts = "SELECT COUNT(*) as total FROM productos WHERE stock > 0 OR estado_venta IN ('disponible', 'reservado')";
    $stmt = $db->prepare($queryProducts);
    $stmt->execute();
    $stats['activeProducts'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // 2. Categorías Activas
    $queryCategories = "SELECT COUNT(*) as total FROM categorias WHERE activo = 1";
    $stmt = $db->prepare($queryCategories);
    $stmt->execute();
    $stats['activeCategories'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // 3. Medios en Galería
    $queryMedia = "SELECT COUNT(*) as total FROM medios";
    $stmt = $db->prepare($queryMedia);
    $stmt->execute();
    $stats['totalMedia'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // 4. Estado de Órdenes (Cantidades y Montos)
    $queryOrders = "SELECT estatus, COUNT(*) as count, SUM(total) as amount FROM ordenes GROUP BY estatus";
    $stmt = $db->prepare($queryOrders);
    $stmt->execute();
    
    $stats['orders'] = [
        'paid' => ['count' => 0, 'amount' => 0],
        'pending' => ['count' => 0, 'amount' => 0],
        'cancelled' => ['count' => 0, 'amount' => 0],
        'totalCount' => 0,
        'totalAmount' => 0
    ];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $estatus = strtolower($row['estatus']);
        $count = (int)$row['count'];
        $amount = (float)$row['amount'];

        $stats['orders']['totalCount'] += $count;
        
        if ($estatus === 'pagado' || $estatus === 'enviado' || $estatus === 'entregado') {
            $stats['orders']['paid']['count'] += $count;
            $stats['orders']['paid']['amount'] += $amount;
            $stats['orders']['totalAmount'] += $amount; // Solo sumamos ventas reales al total
        } elseif ($estatus === 'cancelado') {
            $stats['orders']['cancelled']['count'] += $count;
            $stats['orders']['cancelled']['amount'] += $amount;
        } else {
            $stats['orders']['pending']['count'] += $count;
            $stats['orders']['pending']['amount'] += $amount;
        }
    }

    // 5. Últimos 3 Medios
    $queryLatestMedia = "SELECT m.id, m.titulo, m.tipo, m.ruta_archivo, m.fecha_creacion, c.nombre as categoria 
                         FROM medios m 
                         LEFT JOIN categorias c ON m.categoria_id = c.id 
                         ORDER BY m.fecha_creacion DESC LIMIT 3";
    $stmt = $db->prepare($queryLatestMedia);
    $stmt->execute();
    $stats['latestMedia'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Últimos 3 Productos
    $queryLatestProducts = "SELECT id, nombre, precio, tipo, stock, estado_venta, fecha_creacion, portada 
                            FROM productos 
                            ORDER BY fecha_creacion DESC LIMIT 3";
    $stmt = $db->prepare($queryLatestProducts);
    $stmt->execute();
    $stats['latestProducts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Ventas de los últimos 7 días
    // Obtenemos los últimos 7 días reales agrupadados por fecha
    $querySales = "SELECT DATE(fecha_creacion) as fecha, SUM(total) as total_dia 
                   FROM ordenes 
                   WHERE estatus IN ('pagado', 'enviado', 'entregado') 
                   AND fecha_creacion >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                   GROUP BY DATE(fecha_creacion) 
                   ORDER BY fecha ASC";
    $stmt = $db->prepare($querySales);
    $stmt->execute();
    $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Rellenamos los días vacíos
    $last7Days = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $last7Days[$date] = 0;
    }
    foreach ($salesData as $row) {
        $last7Days[$row['fecha']] = (float)$row['total_dia'];
    }
    $stats['sales7Days'] = $last7Days;

    echo json_encode($stats);
} else {
    jsonResponse(false, 'Método no permitido');
}
?>