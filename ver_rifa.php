<?php
// ============================================================================
// 1. DEPENDENCIAS Y CONFIGURACIÓN
// ============================================================================
include_once 'config/database.php';
include_once 'models/Configuracion.php';

// --- Conexión Principal (Las Trojes) ---
$database = new Database();
$db = $database->getConnection();
$config = new Configuracion($db);
$logo_actual = $config->obtenerPorClave('sistema_logo');

// --- Clase de Conexión Específica (Sistema de Rifas) ---
class DatabaseRifas
{
    private $host = "localhost";
    private $db_name = "granlivo_rifas_las_trojes_db";
    private $username = "granlivo_admin";
    private $password = "j10u22l12i9O16*";
    public $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch (PDOException $exception) {
            echo "Error de conexión a Rifas: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// ============================================================================
// 2. LÓGICA DE NEGOCIO Y CONSULTAS
// ============================================================================

$id_rifa = isset($_GET['id']) ? $_GET['id'] : 1;
$dbRifas = (new DatabaseRifas())->getConnection();

// --- A. Consulta de Datos de la Rifa ---
$queryRifa = "SELECT * FROM rifas WHERE id = :id LIMIT 1";
$stmt = $dbRifas->prepare($queryRifa);
$stmt->bindParam(':id', $id_rifa);
$stmt->execute();
$rifa_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rifa_data) die("Rifa no encontrada");

// --- B. Consulta de Boletos Ocupados y Participantes ---
$queryVentas = "SELECT numero_boleto, estado_pago, cliente_nombre, cliente_estado 
                FROM ventas 
                WHERE rifa_id = :id AND estado_pago IN ('pagado', 'pendiente')";
$stmtVentas = $dbRifas->prepare($queryVentas);
$stmtVentas->bindParam(':id', $id_rifa);
$stmtVentas->execute();
$ocupados_raw = $stmtVentas->fetchAll(PDO::FETCH_ASSOC);

// Mapeo de datos para acceso rápido
$mapa_ocupados = [];
$lista_participantes = [];

foreach ($ocupados_raw as $occ) {
    $num = intval($occ['numero_boleto']);
    $mapa_ocupados[$num] = $occ['estado_pago'];

    // Construcción de lista para UI
    $lista_participantes[] = [
        'numero' => str_pad($num, $rifa_data['cifras'], '0', STR_PAD_LEFT),
        'nombre' => $occ['cliente_nombre'],
        'estado' => $occ['cliente_estado'],
        'status' => $occ['estado_pago']
    ];
}

// --- C. Consulta de Oportunidades Extra ---
$queryOportunidades = "SELECT numero_boleto, oportunidades_extra FROM rifas_oportunidades WHERE rifa_id = :id";
$stmtOp = $dbRifas->prepare($queryOportunidades);
$stmtOp->bindParam(':id', $id_rifa);
$stmtOp->execute();
$raw_oportunidades = $stmtOp->fetchAll(PDO::FETCH_ASSOC);

$mapa_oportunidades = [];
if (count($raw_oportunidades) > 0) {
    foreach ($raw_oportunidades as $op) {
        $num_principal = intval($op['numero_boleto']);
        $extras = json_decode($op['oportunidades_extra'], true);
        if (is_array($extras)) {
            $mapa_oportunidades[$num_principal] = $extras;
        }
    }
}

// --- D. Consulta Galería Adicional ---
$queryGaleria = "SELECT ruta_archivo FROM rifas_galeria WHERE rifa_id = :id ORDER BY id ASC";
$stmtGal = $dbRifas->prepare($queryGaleria);
$stmtGal->bindParam(':id', $id_rifa);
$stmtGal->execute();
$galeria_db = $stmtGal->fetchAll(PDO::FETCH_ASSOC);

// Construcción de URLs de imágenes
// CAMBIO: Ahora solo definimos el dominio raíz, porque la BD trae la ruta completa "assets/..."
$base_domain_rifas = "https://rifas.rancholastrojes.com.mx/";
$imagenes_rifa = [];

// 1. Imagen de Portada
if (!empty($rifa_data['imagen'])) {
    // Concatenamos Dominio + Ruta Completa de BD (assets/uploads/portadas/...)
    $imagenes_rifa[] = $base_domain_rifas . $rifa_data['imagen'];
} else {
    $imagenes_rifa[] = "assets/images/placeholder.jpg";
}

// 2. Imágenes de Galería
foreach ($galeria_db as $img) {
    if (!empty($img['ruta_archivo'])) {
        // Concatenamos Dominio + Ruta Completa de BD (assets/uploads/galeria/...)
        $imagenes_rifa[] = $base_domain_rifas . $img['ruta_archivo'];
    }
}

// --- E. Consulta de Configuración WhatsApp ---
$queryConfig = "SELECT clave, valor FROM configuracion WHERE clave IN (
    'whatsapp_mensaje_activo', 'whatsapp_mensaje_texto', 'whatsapp_numero', 
    'banco_nombre', 'banco_beneficiario', 'banco_cuenta', 'sistema_apartado', 'tiempo_limite'
)";
$stmtConf = $dbRifas->prepare($queryConfig);
$stmtConf->execute();
$raw_conf = $stmtConf->fetchAll(PDO::FETCH_KEY_PAIR);

// Asignación de valores con fallbacks
$wa_config = [
    'activo'           => isset($raw_conf['whatsapp_mensaje_activo']) && $raw_conf['whatsapp_mensaje_activo'] == 1,
    'texto'            => isset($raw_conf['whatsapp_mensaje_texto']) ? $raw_conf['whatsapp_mensaje_texto'] : '',
    'numero'           => isset($raw_conf['whatsapp_numero']) ? $raw_conf['whatsapp_numero'] : '',
    'banco'            => $raw_conf['banco_nombre'] ?? '',
    'beneficiario'     => $raw_conf['banco_beneficiario'] ?? '',
    'cuenta'           => $raw_conf['banco_cuenta'] ?? '',
    'sistema_apartado' => isset($raw_conf['sistema_apartado']) && $raw_conf['sistema_apartado'] == 1,
    'tiempo_limite'    => $raw_conf['tiempo_limite'] ?? 48
];

// --- F. Configuración Final del Objeto Rifa ---
$rifa = [
    'id'               => $rifa_data['id'],
    'titulo'           => $rifa_data['titulo'],
    'descripcion'      => $rifa_data['descripcion'],
    'precio_boleto'    => $rifa_data['precio_boleto'],
    'fecha_sorteo'     => $rifa_data['fecha_sorteo'],
    'estado'           => $rifa_data['estado'],
    'meta_boletos'     => $rifa_data['cantidad_boletos'],
    'boletos_vendidos' => count($ocupados_raw),
    'imagenes'         => $imagenes_rifa,
    'cifras'           => $rifa_data['cifras'],
    'usa_cero'         => $rifa_data['usa_cero']
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($rifa['titulo']); ?> - Rancho Las Trojes</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Lora:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/forms.css">
    <link id="darkModeStylesheet" rel="stylesheet" href="assets/css/dark-mode.css" disabled>

    <style>
        /* ==========================================================================
           1. ANIMACIONES
           ========================================================================== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse-glow {
            0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(99, 102, 241, 0); }
            100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
        }

        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }

        /* ==========================================================================
           2. LAYOUT PRINCIPAL
           ========================================================================== */
        .raffle-detail-section {
            /* Box Model */
            padding: 3rem 0;
            margin-top: 2rem;
            /* Visuals */
            background-color: var(--white);
        }

        @media (max-width: 512px) {
            .raffle-detail-section {
                padding: 1rem 0;
            }
        }

        .raffle-container {
            /* Box Model */
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 4rem;
        }

        @media (max-width: 900px) {
            .raffle-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        .layout-grid {
            /* Box Model */
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3rem;
        }

        @media (max-width: 900px) {
            .layout-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ==========================================================================
           3. MÓDULO: GALERÍA DE IMÁGENES
           ========================================================================== */
        .raffle-gallery {
            /* Box Model */
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .main-image-container {
            /* Positioning */
            position: relative;
            /* Box Model */
            aspect-ratio: 1/1;
            overflow: hidden;
            border: 1px solid var(--divider);
            border-radius: 1rem;
            /* Visuals */
            background: var(--off-white-light);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }

        .main-image-container:hover .main-image {
            transform: scale(1.02);
        }

        .main-image {
            /* Box Model */
            width: 100%;
            height: 100%;
            object-fit: cover;
            /* Visuals */
            transition: transform 0.3s;
        }

        .zoom-hint {
            /* Positioning */
            position: absolute;
            right: 10px;
            bottom: 10px;
            pointer-events: none;
            /* Box Model */
            padding: 5px 10px;
            border-radius: 20px;
            /* Typography */
            font-size: 0.8rem;
            color: white;
            /* Visuals */
            background: rgba(0, 0, 0, 0.6);
        }

        .thumbnails-grid {
            /* Box Model */
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.8rem;
        }

        @media (max-width: 512px) {
            .thumbnails-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .thumbnail {
            /* Box Model */
            aspect-ratio: 1/1;
            overflow: hidden;
            border: 2px solid transparent;
            border-radius: 1rem;
            /* Visuals */
            opacity: 0.7;
            cursor: pointer;
            transition: all 0.2s;
        }

        .thumbnail.active,
        .thumbnail:hover {
            /* Visuals */
            border-color: var(--brown);
            opacity: 1;
        }

        .thumbnail img {
            /* Box Model */
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ==========================================================================
           4. MÓDULO: INFORMACIÓN Y ESTADO
           ========================================================================== */
        /* --- Badge Estado --- */
        .raffle-status-badge {
            /* Box Model */
            padding: 0.3rem 1rem;
            border-radius: 20px;
            /* Typography */
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #166534;
            /* Visuals */
            background: #dcfce7;
        }

        /* --- Títulos y Precios --- */
        .raffle-title {
            /* Box Model */
            margin-top: 1rem;
            /* Typography */
            font-family: 'Lora', serif;
            font-size: 2.2rem;
            line-height: 1.2;
            color: var(--black-blue);
        }

        .raffle-price {
            /* Box Model */
            margin: 0.5rem 0;
            /* Typography */
            font-size: 2rem;
            font-weight: 700;
            color: var(--brown);
        }

        .raffle-price-suffix {
            /* Typography */
            font-size: 1rem;
            font-weight: 500;
            color: #666;
        }

        .raffle-description {
            /* Box Model */
            margin-top: 1rem;
            /* Typography */
            line-height: 1.6;
            color: #4b5563;
        }

        /* --- Barra de Progreso --- */
        .progress-container {
            /* Box Model */
            margin-top: 2rem;
        }

        .progress-header {
            /* Box Model */
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            /* Typography */
            font-weight: 600;
        }

        .progress-track {
            /* Box Model */
            width: 100%;
            height: 10px;
            overflow: hidden;
            border-radius: 10px;
            /* Visuals */
            background: #f3f4f6;
        }

        .progress-fill {
            /* Box Model */
            height: 100%;
            /* Visuals */
            background: var(--brown);
            transition: width 0.5s ease;
        }

        /* --- Temporizador --- */
        .raffle-timer-wrapper {
            /* Box Model */
            display: flex;
            justify-content: center;
            width: 100%;
            margin: 1.5rem 0;
            border-radius: 1rem;
            /* Visuals */
            background: var(--black-blue);
        }

        .raffle-timer-box {
            /* Box Model */
            display: flex;
            gap: 2rem;
            padding: 1rem;
            /* Typography */
            color: white;
        }

        @media (max-width: 400px) {
            .raffle-timer-box {
                gap: 1rem;
            }
        }

        .timer-unit {
            /* Box Model */
            display: flex;
            flex-direction: column;
            min-width: 50px;
            /* Typography */
            text-align: center;
        }

        .timer-unit .num {
            /* Typography */
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }

        .timer-unit .label {
            /* Box Model */
            margin-top: 4px;
            /* Typography */
            font-size: 0.7rem;
            text-transform: uppercase;
            /* Visuals */
            opacity: 0.7;
        }

        .timer-finished-msg {
            /* Box Model */
            width: 100%;
            /* Typography */
            text-align: center;
            font-weight: bold;
        }

        /* ==========================================================================
           5. MÓDULO: CONTROLES DE VISTA
           ========================================================================== */
        .controls-wrapper {
            /* Box Model */
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--divider);
        }

        @media (max-width: 768px) {
            .controls-wrapper {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
        }

        .view-tabs {
            /* Box Model */
            display: flex;
            gap: 0.5rem;
            padding: 4px;
            border-radius: 8px;
            /* Visuals */
            background: var(--off-white);
        }

        @media (max-width: 768px) {
            .view-tabs {
                width: 100%;
            }
        }

        .view-tab {
            /* Box Model */
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            /* Typography */
            font-weight: 600;
            color: #666;
            /* Visuals */
            background: transparent;
            cursor: pointer;
            transition: 0.2s;
        }

        @media (max-width: 768px) {
            .view-tab {
                flex: 1;
                justify-content: center;
                text-align: center;
            }
        }

        .view-tab.active {
            /* Typography */
            color: var(--brown);
            /* Visuals */
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .view-tab:hover:not(.active) {
            /* Typography */
            color: var(--black-blue);
            /* Visuals */
            background: rgba(255, 255, 255, 0.5);
        }

        /* --- Búsqueda --- */
        .ticket-search {
            /* Positioning */
            position: relative;
            /* Box Model */
            flex: 1;
            max-width: 300px;
        }

        @media (max-width: 768px) {
            .ticket-search {
                width: 100%;
                max-width: 100%;
            }
        }

        .ticket-search input {
            /* Box Model */
            width: 100%;
            padding: 0.6rem 1rem 0.6rem 2.2rem;
            border: 1px solid var(--divider);
            border-radius: 8px;
            /* Typography */
            font-family: inherit;
        }

        .ticket-search i {
            /* Positioning */
            position: absolute;
            top: 50%;
            left: 10px;
            /* Typography */
            color: #999;
            /* Visuals */
            transform: translateY(-50%);
        }

        /* --- Leyenda y Filtros --- */
        .legend-card {
            /* Box Model */
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 0.75rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--divider);
            border-radius: 1rem;
            /* Visuals */
            background-color: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        @media (min-width: 768px) {
            .legend-card {
                flex-direction: row;
                align-items: center;
                gap: 1.5rem;
                padding: 0.75rem 1rem;
            }
        }

        .filter-toggle {
            /* Box Model */
            display: flex;
            width: 100%;
            padding: 4px;
            border-radius: 0.75rem;
            /* Visuals */
            background-color: #f3f4f6;
        }

        @media (min-width: 768px) {
            .filter-toggle {
                width: auto;
                min-width: 240px;
            }
        }

        .toggle-btn {
            /* Box Model */
            flex: 1;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            /* Typography */
            font-size: 0.85rem;
            font-weight: 600;
            color: #6b7280;
            /* Visuals */
            background: transparent;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .toggle-btn.active {
            /* Typography */
            color: var(--black-blue);
            /* Visuals */
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .legend-divider {
            /* Box Model */
            display: none;
            width: 1px;
            height: 32px;
            /* Visuals */
            background-color: #e5e7eb;
        }

        @media (min-width: 768px) {
            .legend-divider {
                display: block;
            }
        }

        .legend-status-group {
            /* Box Model */
            display: flex;
            flex: 1;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .legend-status-group {
                justify-content: flex-start;
            }
        }

        .legend-item-modern {
            /* Box Model */
            display: flex;
            align-items: center;
            gap: 6px;
            /* Typography */
            font-size: 0.85rem;
            font-weight: 500;
            color: #4b5563;
        }

        .dot-modern {
            /* Box Model */
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .dot-modern.available {
            background: white;
            border: 2px solid #d1d5db;
        }

        .dot-modern.selected {
            background: var(--brown);
            box-shadow: 0 0 0 1px rgba(139, 94, 60, 0.3);
        }

        .dot-modern.occupied {
            background: #fde047;
            border: 1px solid #eab308;
        }

        .dot-modern.sold {
            background: #ef4444;
        }

        /* ==========================================================================
           6. MÓDULO: GRID DE BOLETOS Y PARTICIPANTES
           ========================================================================== */
        .tickets-grid {
            /* Box Model */
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 0.75rem;
            padding: 1rem 0;
        }

        @media (max-width: 768px) {
            .tickets-grid {
                grid-template-columns: repeat(8, 1fr);
            }
        }

        @media (max-width: 512px) {
            .tickets-grid {
                grid-template-columns: repeat(6, 1fr);
            }
        }

        .ticket-btn {
            /* Box Model */
            aspect-ratio: 1;
            border: 1px solid var(--divider);
            border-radius: 8px;
            /* Typography */
            font-size: 0.95rem;
            font-weight: 700;
            color: #4b5563;
            /* Visuals */
            background: white;
            cursor: pointer;
            transition: 0.2s;
        }

        .ticket-btn:hover:not(.sold):not(.pending) {
            /* Box Model */
            border-color: var(--brown);
            /* Typography */
            color: var(--brown);
            /* Visuals */
            background: #fff8f1;
            transform: scale(1.05);
        }

        .ticket-btn.selected {
            /* Box Model */
            border-color: var(--brown);
            /* Typography */
            color: white;
            /* Visuals */
            background: var(--brown);
            box-shadow: 0 4px 6px rgba(165, 42, 42, 0.3);
            transform: scale(1.1);
        }

        .ticket-btn.sold {
            /* Box Model */
            border-color: transparent;
            /* Typography */
            color: #991b1b;
            /* Visuals */
            background: #fee2e2;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .ticket-btn.pending {
            /* Box Model */
            border-color: #fde047;
            /* Typography */
            color: #854d0e;
            /* Visuals */
            background: #fef9c3;
            cursor: not-allowed;
            opacity: 0.8;
        }

        /* --- Lista de Participantes --- */
        .participants-list {
            /* Box Model */
            display: none;
            max-height: 500px;
            overflow-y: auto;
        }

        .participants-list.active {
            display: block;
        }

        .participant-row {
            /* Box Model */
            display: flex;
            justify-content: space-between;
            padding: 0.8rem;
            border-bottom: 1px solid #eee;
            /* Typography */
            font-size: 0.9rem;
        }

        .participant-row:last-child {
            border-bottom: none;
        }

        .p-number {
            /* Box Model */
            padding: 2px 8px;
            border-radius: 4px;
            /* Typography */
            font-weight: 700;
            color: var(--brown);
            /* Visuals */
            background: #fff8f1;
        }

        .p-name {
            /* Box Model */
            margin-left: 10px;
            /* Typography */
            font-weight: 600;
        }

        .p-status {
            /* Typography */
            font-size: 0.8rem;
            color: #666;
        }

        .p-empty-msg {
            /* Box Model */
            padding: 2rem;
            /* Typography */
            text-align: center;
            color: #888;
        }

        /* ==========================================================================
           7. MÓDULO: SIDEBAR Y SELECCIÓN
           ========================================================================== */
        .selection-sidebar {
            /* Positioning */
            position: sticky;
            top: 2rem;
            /* Box Model */
            height: fit-content;
            padding: 1.5rem;
            border: 1px solid var(--divider);
            border-radius: 1.25rem;
            /* Visuals */
            background: var(--white);
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.05);
        }

        @media (max-width: 1023px) {
            .selection-sidebar {
                display: none !important;
            }
        }

        .sidebar-title {
            /* Box Model */
            margin-bottom: 1rem;
            /* Typography */
            font-family: 'Lora', serif;
        }

        /* --- Lista de Tickets Seleccionados --- */
        .selected-tickets-list {
            /* Box Model */
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }

        .selected-ticket-tag {
            /* Box Model */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 0.75rem;
            border-radius: 0.75rem;
            /* Typography */
            font-family: "Space Mono", monospace;
            font-size: 1em;
            font-weight: 600;
            text-align: center;
            color: var(--white);
            /* Visuals */
            background-color: var(--brown);
            cursor: pointer;
            user-select: none;
            transition: background-color 0.2s ease;
        }

        .selected-ticket-tag:hover {
            background-color: #dc2626;
        }

        .selection-helper {
            /* Box Model */
            margin-top: 1rem;
            /* Typography */
            font-size: 0.75rem;
            text-align: center;
            color: #9ca3af;
        }

        /* --- Estado Vacío --- */
        .empty-state-box {
            /* Box Model */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            padding: 2.5rem 0;
            /* Typography */
            text-align: center;
            color: #9ca3af;
        }

        .empty-icon {
            /* Typography */
            font-size: 2.5rem;
            /* Visuals */
            opacity: 0.2;
        }

        .empty-text {
            /* Typography */
            font-size: 0.9rem;
        }

        /* --- Totales --- */
        .sidebar-total-section {
            /* Box Model */
            padding-top: 1rem;
            margin: 1rem 0;
            border-top: 1px solid #eee;
        }

        .sidebar-total-row {
            /* Box Model */
            display: flex;
            justify-content: space-between;
            /* Typography */
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--black-blue);
        }

        /* --- Botones --- */
        .btn-clear {
            /* Box Model */
            width: 100%;
            margin-top: 10px;
            border: none;
            /* Typography */
            font-size: 0.9rem;
            color: #666;
            text-decoration: underline;
            /* Visuals */
            background: transparent;
            cursor: pointer;
        }

        .btn-primary {
            /* Box Model */
            width: 100%;
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.5rem;
            /* Typography */
            font-size: 1em;
            font-weight: 600;
            color: var(--white);
            /* Visuals */
            background: var(--brown);
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        @media (max-width: 768px) {
            .btn-primary {
                text-align: center;
            }
        }

        .btn-primary:hover {
            background: var(--black-blue);
        }

        .btn-primary:disabled {
            /* Typography */
            color: #9ca3af;
            /* Visuals */
            background: var(--divider, #e5e7eb);
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
        }

        /* ==========================================================================
           8. MÓDULO: OPORTUNIDADES EXTRA
           ========================================================================== */
        .opp-wrapper {
            /* Box Model */
            display: none;
            margin-top: 0.5rem;
            /* Visuals */
            animation: fadeIn 0.3s ease;
        }

        .opp-wrapper.visible {
            display: block;
        }

        .opp-card {
            /* Box Model */
            overflow: hidden;
            border: 1px solid #dbeafe;
            border-radius: 0.75rem;
            /* Visuals */
            background-color: #eff6ff;
        }

        .opp-header {
            /* Box Model */
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            /* Visuals */
            background-color: rgba(219, 234, 254, 0.5);
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .opp-header:hover {
            background-color: #dbeafe;
        }

        .opp-title-group {
            /* Box Model */
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .opp-icon {
            /* Typography */
            font-size: 1.1rem;
            color: #2563eb;
        }

        .opp-title-text {
            /* Typography */
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e3a8a;
        }

        .opp-toggle-icon {
            /* Typography */
            font-size: 1.1rem;
            color: #3b82f6;
            /* Visuals */
            transition: transform 0.3s ease;
        }

        .opp-toggle-icon.rotated {
            transform: rotate(180deg);
        }

        .opp-body {
            /* Box Model */
            max-height: 500px;
            overflow-y: auto;
            /* Visuals */
            transition: max-height 0.3s ease-in-out;
        }

        .opp-body.collapsed {
            max-height: 0;
            overflow: hidden;
        }

        .opp-row {
            /* Box Model */
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border-bottom: 1px solid rgba(219, 234, 254, 0.5);
            /* Visuals */
            background-color: rgba(255, 255, 255, 0.6);
            transition: background-color 0.2s;
        }

        .opp-row.layout-column {
            flex-direction: column;
            align-items: flex-start;
        }

        @media (max-width: 400px) {
            .opp-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        .opp-row:hover {
            background-color: #eff6ff;
        }

        .opp-row:last-child {
            border-bottom: none;
        }

        .opp-row-header {
            /* Box Model */
            display: flex;
            align-items: center;
            gap: 0.5rem;
            /* Typography */
            font-size: 0.85rem;
        }

        .opp-main-ticket {
            /* Typography */
            font-family: "Space Mono", monospace;
            font-size: 1rem;
            font-weight: 700;
            color: #374151;
        }

        .opp-divider {
            color: #d1d5db;
        }

        .opp-label {
            /* Typography */
            font-size: 0.75rem;
            color: #6b7280;
        }

        .opp-badges-container {
            /* Box Model */
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        .opp-badge {
            /* Box Model */
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #bfdbfe;
            border-radius: 0.25rem;
            /* Typography */
            font-family: "Space Mono", monospace;
            font-size: 0.75rem;
            font-weight: 600;
            color: #1d4ed8;
            /* Visuals */
            background-color: #dbeafe;
        }

        .opp-footer {
            /* Box Model */
            padding: 0.5rem;
            /* Typography */
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            color: #2563eb;
            /* Visuals */
            background-color: rgba(219, 234, 254, 0.3);
        }

        .opp-info-text {
            /* Box Model */
            margin-top: 0.5rem;
            /* Typography */
            font-size: 0.75rem;
            text-align: center;
            color: #9ca3af;
        }

        /* ==========================================================================
           9. MÓDULO: MODALES
           ========================================================================== */
        /* --- Modal Base --- */
        .modal {
            /* Positioning */
            position: fixed;
            top: 0;
            left: 0;
            z-index: var(--z-modal);
            /* Box Model */
            display: none;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            padding: 1rem;
            /* Visuals */
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(0.25rem);
        }

        .modal-content-form {
            /* Positioning */
            position: relative;
            /* Box Model */
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            padding: 2rem;
            margin: auto;
            overflow-y: auto;
            border-radius: 1.5rem;
            /* Visuals */
            background-color: var(--white);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: fadeIn 0.3s;
        }

        .modal-close {
            /* Positioning */
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            /* Typography */
            font-size: 1.5rem;
            color: #999;
            /* Visuals */
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: var(--black-blue);
        }

        .modal-title {
            /* Box Model */
            margin-bottom: 1.5rem;
            /* Typography */
            font-family: 'Lora', serif;
            font-size: 1.5rem;
        }

        .modal-summary-card {
            /* Box Model */
            padding: 1rem;
            margin: 1.5rem 0;
            border: 1px solid #eee;
            border-radius: 0.5rem;
            /* Visuals */
            background: #f9f9f9;
        }

        .modal-total-highlight {
            /* Typography */
            font-weight: bold;
            color: var(--brown);
        }

        /* --- Modal Galería --- */
        .gallery-modal-overlay {
            /* Positioning */
            position: fixed;
            top: 0;
            left: 0;
            z-index: var(--z-modal);
            /* Box Model */
            display: none;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            /* Visuals */
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(0.25rem);
            transition: all 0.3s ease;
        }

        .gallery-modal-overlay.active {
            display: flex;
        }

        .gallery-modal-content {
            /* Positioning */
            position: relative;
            /* Box Model */
            display: flex;
            justify-content: center;
            align-items: center;
            max-width: 90%;
            max-height: 90vh;
            /* Visuals */
            touch-action: none;
            transition: transform 0.2s ease-out;
        }

        .gallery-modal-img {
            /* Box Model */
            max-width: 100%;
            max-height: 85vh;
            object-fit: contain;
            border-radius: 4px;
            /* Visuals */
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            pointer-events: auto;
            touch-action: none;
            transform-origin: center center;
            will-change: transform;
        }

        /* --- Botones Control Galería --- */
        .gallery-close-btn,
        .gallery-nav-btn {
            /* Positioning */
            position: fixed;
            z-index: var(--z-gallery-ctrl);
            /* Box Model */
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            /* Typography */
            font-size: 1.2rem;
            color: white;
            /* Visuals */
            background: rgba(0, 0, 0, 0.7);
            cursor: pointer;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .gallery-close-btn {
            top: 2rem;
            right: 2rem;
        }

        .gallery-nav-btn {
            top: 50%;
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
            opacity: 0.8;
            transform: translateY(-50%);
        }

        .gallery-nav-btn.prev {
            left: 2rem;
        }

        .gallery-nav-btn.next {
            right: 2rem;
        }

        .gallery-close-btn:hover,
        .gallery-nav-btn:hover {
            background: rgba(139, 94, 60, 0.8);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .gallery-nav-btn:hover {
            opacity: 1;
            transform: translateY(-50%) scale(1.1);
        }

        .gallery-close-btn:hover {
            transform: scale(1.1);
        }

        .gallery-close-btn i {
            pointer-events: none;
        }

        .zooming .gallery-close-btn,
        .zooming .gallery-nav-btn {
            opacity: 0;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .gallery-close-btn {
                top: 1rem;
                right: 1rem;
                width: 45px;
                height: 45px;
            }

            .gallery-nav-btn {
                width: 45px;
                height: 45px;
                font-size: 1.2rem;
            }

            .gallery-nav-btn.prev {
                left: 1rem;
            }

            .gallery-nav-btn.next {
                right: 1rem;
            }
        }

        /* ==========================================================================
           10. MÓDULO: MOBILE UI (FAB & SHEET)
           ========================================================================== */
        /* --- Floating Action Button (FAB) Resumen --- */
        .raffle-fab {
            /* Positioning */
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: var(--z-fab);
            /* Box Model */
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            border-radius: 50px;
            /* Typography */
            color: var(--white);
            /* Visuals */
            background-color: var(--black-blue);
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            transform: scale(0);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @media (min-width: 1024px) {
            .raffle-fab {
                display: none;
            }
        }

        .raffle-fab.visible {
            transform: scale(1);
        }

        .raffle-fab-icon {
            /* Positioning */
            position: relative;
            /* Typography */
            font-size: 1.2rem;
        }

        .raffle-fab-icon span {
            /* Positioning */
            position: absolute;
            top: -8px;
            right: -8px;
            /* Box Model */
            display: flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            /* Typography */
            font-size: 0.7rem;
            font-weight: bold;
            color: white;
            /* Visuals */
            background: var(--brown);
        }

        .raffle-fab-text {
            /* Typography */
            font-size: 0.95rem;
            font-weight: 600;
        }

        /* --- Bottom Sheet --- */
        .raffle-sheet-overlay {
            /* Positioning */
            position: fixed;
            top: 0;
            left: 0;
            z-index: var(--z-overlay);
            /* Box Model */
            display: block;
            width: 100%;
            height: 100%;
            visibility: hidden;
            /* Visuals */
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(2px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        @media (min-width: 1024px) {
            .raffle-sheet-overlay {
                display: none;
            }
        }

        .raffle-sheet-overlay.active {
            visibility: visible;
            opacity: 1;
        }

        .raffle-sheet-container {
            /* Positioning */
            position: fixed;
            bottom: 0;
            left: 0;
            z-index: var(--z-drawer);
            /* Box Model */
            display: flex;
            flex-direction: column;
            width: 100%;
            max-height: 85vh;
            padding-bottom: env(safe-area-inset-bottom);
            border-radius: 20px 20px 0 0;
            /* Visuals */
            background: white;
            box-shadow: 0 -5px 30px rgba(0, 0, 0, 0.15);
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        @media (min-width: 1024px) {
            .raffle-sheet-container {
                display: none;
            }
        }

        .raffle-sheet-container.active {
            transform: translateY(0);
        }

        .raffle-sheet-header {
            /* Box Model */
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .raffle-sheet-header h3 {
            /* Box Model */
            margin: 0;
            /* Typography */
            font-family: 'Lora', serif;
            font-size: 1.2rem;
            color: var(--black-blue);
        }

        .btn-close-sheet {
            /* Box Model */
            padding: 5px;
            border: none;
            /* Typography */
            font-size: 1.2rem;
            color: #999;
            /* Visuals */
            background: none;
            cursor: pointer;
        }

        .raffle-sheet-body {
            /* Box Model */
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
        }

        .raffle-sheet-footer {
            /* Box Model */
            padding: 1.2rem;
            border-top: 1px solid #f3f4f6;
            /* Visuals */
            background: #fff;
        }

        .sheet-total-row {
            /* Box Model */
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            /* Typography */
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--black-blue);
        }

        /* ==========================================================================
           11. MÓDULO: FEEDBACK Y TOASTS
           ========================================================================== */
        /* --- Modal Feedback (Success/Error) --- */
        .modal-feedback-container {
            /* Box Model */
            text-align: center;
            /* Visuals */
            animation: fadeIn 0.3s ease-out;
        }

        .feedback-header {
            /* Box Model */
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--divider);
        }

        .feedback-icon-circle {
            /* Box Model */
            display: flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem auto;
            border-radius: 50%;
            /* Typography */
            font-size: 2.5rem;
        }

        .feedback-success .feedback-icon-circle {
            background-color: #dcfce7;
            color: #166534;
        }

        .feedback-partial .feedback-icon-circle {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .feedback-error .feedback-icon-circle {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .feedback-title {
            /* Box Model */
            margin-bottom: 0.5rem;
            /* Typography */
            font-family: 'Lora', serif;
            font-size: 1.8rem;
            color: var(--black-blue);
        }

        .feedback-subtitle {
            /* Typography */
            font-size: 0.95rem;
            color: #666;
        }

        .feedback-body {
            /* Box Model */
            max-height: 40vh;
            padding-right: 5px;
            margin-bottom: 2rem;
            overflow-y: auto;
            /* Typography */
            text-align: left;
        }

        .feedback-section-title {
            /* Box Model */
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.8rem;
            /* Typography */
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .feedback-grid {
            /* Box Model */
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }

        .ticket-badge {
            /* Box Model */
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 0.6rem;
            border-radius: 0.5rem;
            /* Typography */
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .ticket-badge i {
            font-size: 1rem;
        }

        .ticket-badge.success {
            /* Box Model */
            border: 1px solid #bbf7d0;
            /* Typography */
            color: #166534;
            /* Visuals */
            background-color: #f0fdf4;
        }

        .ticket-badge.failed {
            /* Positioning */
            position: relative;
            /* Box Model */
            border: 1px solid #fecaca;
            /* Typography */
            color: #991b1b;
            text-decoration: line-through;
            /* Visuals */
            background-color: #fef2f2;
            opacity: 0.8;
        }

        .feedback-alert-box {
            /* Box Model */
            display: flex;
            align-items: start;
            gap: 0.8rem;
            padding: 1rem;
            border: 1px solid #fed7aa;
            border-radius: 0.75rem;
            /* Typography */
            font-size: 0.9rem;
            line-height: 1.5;
            color: #9a3412;
            /* Visuals */
            background-color: #fff8f1;
        }

        .feedback-actions {
            /* Box Model */
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--divider);
        }

        /* --- Toast Notification --- */
        .ticket-toast {
            /* Positioning */
            position: fixed;
            z-index: 2200;
            /* Box Model */
            display: flex;
            align-items: center;
            gap: 12px;
            width: max-content;
            max-width: 90%;
            padding: 12px 24px;
            border-radius: 50px;
            /* Typography */
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            color: white;
            /* Visuals */
            background-color: var(--black-blue);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            opacity: 0;
            pointer-events: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .ticket-toast i {
            font-size: 1.1rem;
            color: #4ade80;
        }

        /* Desktop Positioning */
        @media (min-width: 1024px) {
            .ticket-toast {
                bottom: 30px;
                left: 50%;
                transform: translate(-50%, 20px);
            }

            .ticket-toast.active {
                opacity: 1;
                pointer-events: auto;
                transform: translate(-50%, 0);
            }
        }

        /* Mobile Positioning */
        @media (max-width: 1024px) {
            .ticket-toast {
                top: 20px;
                left: 50%;
                transform: translate(-50%, -20px) scale(0.9);
            }

            .ticket-toast.active {
                opacity: 1;
                pointer-events: auto;
                transform: translate(-50%, 10px) scale(1);
            }
        }

        /* ==========================================================================
           12. MÓDULO: RULETA Y MENÚ RÁPIDO
           ========================================================================== */
        /* --- FAB Ruleta --- */
        .fab-random-container {
            /* Positioning */
            position: fixed;
            right: 20px;
            bottom: 90px;
            z-index: var(--z-fab);
        }

        .btn-fab-random {
            /* Box Model */
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border: none;
            border-radius: 50%;
            /* Typography */
            color: white;
            /* Visuals */
            background: linear-gradient(135deg, #6366f1, #a855f7);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            animation: pulse-glow 2s infinite;
        }

        .btn-fab-random:active {
            transform: scale(0.95);
        }

        .btn-fab-random i {
            font-size: 1.5rem;
        }

        /* --- Menú Rápido (Overlay) --- */
        .quick-menu-overlay {
            /* Positioning */
            position: fixed;
            top: 0;
            left: 0;
            z-index: var(--z-modal);
            /* Box Model */
            display: none;
            justify-content: center;
            align-items: flex-end;
            /* Mobile First */
            width: 100%;
            height: 100%;
            /* Visuals */
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(0.25rem);
        }

        @media (min-width: 640px) {
            .quick-menu-overlay {
                align-items: center;
            }
        }

        .quick-menu-content {
            /* Positioning */
            position: relative;
            /* Box Model */
            width: 100%;
            max-width: 400px;
            padding: 1.5rem;
            border-radius: 20px 20px 0 0;
            /* Visuals */
            background: white;
            animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @media (min-width: 640px) {
            .quick-menu-content {
                margin: 20px;
                border-radius: 20px;
            }
        }

        /* --- Botones Opciones --- */
        .quick-grid {
            /* Box Model */
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 1.5rem;
        }

        .btn-quick-option {
            /* Box Model */
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem 0.5rem;
            border: 1px solid #e0e7ff;
            border-radius: 12px;
            /* Visuals */
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-quick-option:hover {
            /* Visuals */
            background: #eef2ff;
            border-color: #6366f1;
            transform: translateY(-2px);
        }

        .quick-num {
            /* Typography */
            font-size: 1.25rem;
            font-weight: 800;
            color: #4f46e5;
        }

        .quick-label {
            /* Box Model */
            margin-top: 4px;
            /* Typography */
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #6b7280;
        }

        /* --- Separador --- */
        .divider-text {
            /* Box Model */
            display: flex;
            align-items: center;
            margin: 1rem 0;
            /* Typography */
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            color: #9ca3af;
        }

        .divider-text::before,
        .divider-text::after {
            /* Box Model */
            flex: 1;
            border-bottom: 1px solid #e5e7eb;
            /* Visuals */
            content: '';
        }

        .divider-text span {
            padding: 0 10px;
        }

        /* --- Botón Ruleta Main --- */
        .btn-roulette-main {
            /* Box Model */
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            /* Typography */
            color: white;
            /* Visuals */
            background: linear-gradient(90deg, #7e22ce, #4f46e5);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-roulette-main:active {
            transform: scale(0.98);
        }

        /* --- Ruleta Visual (Modal) --- */
        .roulette-modal-content {
            /* Positioning */
            position: relative;
            /* Box Model */
            width: 90%;
            max-width: 380px;
            padding: 2rem;
            overflow: hidden;
            border-radius: 20px;
            /* Typography */
            text-align: center;
            /* Visuals */
            background: white;
        }

        .roulette-wheel-container {
            /* Positioning */
            position: relative;
            /* Box Model */
            width: 200px;
            height: 200px;
            margin: 2rem auto;
        }

        .roulette-wheel {
            /* Box Model */
            width: 100%;
            height: 100%;
            border: 8px solid #fbbf24;
            border-radius: 50%;
            /* Visuals */
            background: conic-gradient(#ef4444 0deg 36deg, #3b82f6 36deg 72deg,
                    #10b981 72deg 108deg, #f59e0b 108deg 144deg,
                    #8b5cf6 144deg 180deg, #ec4899 180deg 216deg,
                    #06b6d4 216deg 252deg, #84cc16 252deg 288deg,
                    #f97316 288deg 324deg, #6366f1 324deg 360deg);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            transition: transform 4s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .roulette-pointer {
            /* Positioning */
            position: absolute;
            top: -15px;
            left: 50%;
            z-index: 10;
            /* Typography */
            font-size: 2rem;
            color: #374151;
            /* Visuals */
            transform: translateX(-50%);
            filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.3));
        }

        .roulette-center-star {
            /* Positioning */
            position: absolute;
            top: 50%;
            left: 50%;
            z-index: 5;
            /* Box Model */
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border: 4px solid #f3f4f6;
            border-radius: 50%;
            /* Visuals */
            background: white;
            transform: translate(-50%, -50%);
        }

        /* --- Control Cantidad Ruleta --- */
        .qty-control {
            /* Box Model */
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .btn-qty {
            /* Box Model */
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            /* Typography */
            font-size: 1.2rem;
            font-weight: bold;
            color: #4b5563;
            /* Visuals */
            background: #f3f4f6;
            cursor: pointer;
        }

        .input-qty {
            /* Box Model */
            width: 60px;
            border: none;
            border-bottom: 2px solid #4f46e5;
            /* Typography */
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            color: #4f46e5;
            /* Visuals */
            outline: none;
        }

        /* --- Resultados Ruleta --- */
        .results-grid {
            /* Box Model */
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            max-height: 150px;
            margin: 1.5rem 0;
            overflow-y: auto;
        }

        .result-ball {
            /* Box Model */
            padding: 5px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            /* Typography */
            font-size: 0.9rem;
            font-weight: 700;
            color: #1e40af;
            /* Visuals */
            background: #eff6ff;
        }
    </style>
</head>

<body>

    <?php include 'includes/header.php'; ?>

    <section class="page-header-start container-wide">
        <img src="<?php echo $rifa['imagenes'][0]; ?>" alt="Fondo" class="raffle-hero-bg" style="filter: brightness(0.3) blur(2px);">
        <div class="page-header-overlay">
            <h1 class="page-header-title animated-text">Rifas Las Trojes</h1>
            <p class="page-header-subtitle">Participa y Gana Genética Pura</p>
        </div>
    </section>

    <div class="raffle-detail-section container-wide">

        <div class="container">

            <div class="raffle-container">

                <div class="raffle-gallery fade-up-animation">
                    <div class="main-image-container" onclick="openGalleryModal(currentImageIndex)">
                        <img src="<?php echo $rifa['imagenes'][0]; ?>" class="main-image" id="mainImage" alt="Premio Principal">
                        <div class="zoom-hint"><i class="fas fa-search-plus"></i> Ampliar</div>
                    </div>
                    <div class="thumbnails-grid">
                        <?php foreach ($rifa['imagenes'] as $index => $img): ?>
                            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" onclick="selectImage(<?php echo $index; ?>)">
                                <img src="<?php echo $img; ?>" alt="Thumb">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="raffle-info">
                    <span class="raffle-status-badge">
                        <?php echo ucfirst($rifa['estado']); ?>
                    </span>

                    <h1 class="raffle-title"><?php echo htmlspecialchars($rifa['titulo']); ?></h1>

                    <div class="raffle-price">
                        $<?php echo number_format($rifa['precio_boleto'], 2); ?>
                        <span class="raffle-price-suffix">MXN / boleto</span>
                    </div>

                    <p class="raffle-description"><?php echo nl2br(htmlspecialchars($rifa['descripcion'])); ?></p>

                    <div class="progress-container">
                        <div class="progress-header">
                            <span>Progreso</span>
                            <span><?php echo $rifa['boletos_vendidos']; ?> / <?php echo $rifa['meta_boletos']; ?> Vendidos</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: <?php echo ($rifa['meta_boletos'] > 0) ? ($rifa['boletos_vendidos'] / $rifa['meta_boletos']) * 100 : 0; ?>%;"></div>
                        </div>
                    </div>

                    <?php if ($rifa['fecha_sorteo']): ?>
                        <div class="raffle-timer-wrapper">

                            <div class="raffle-timer-box" data-date="<?php echo $rifa['fecha_sorteo']; ?>">
                                <div class="timer-unit"><span class="num" id="days">00</span><span class="label">Días</span></div>
                                <div class="timer-unit"><span class="num" id="hours">00</span><span class="label">Hrs</span></div>
                                <div class="timer-unit"><span class="num" id="minutes">00</span><span class="label">Min</span></div>
                                <div class="timer-unit"><span class="num" id="seconds">00</span><span class="label">Seg</span></div>
                            </div>

                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="layout-grid">

                <div>
                    <div class="controls-wrapper">
                        <div class="view-tabs">
                            <button class="view-tab active" id="tab-tickets" onclick="switchView('tickets')">
                                <i class="fas fa-ticket-alt"></i> Boletos
                            </button>
                            <button class="view-tab" id="tab-participants" onclick="switchView('participants')">
                                <i class="fas fa-users"></i> Participantes
                            </button>
                        </div>
                        <div class="ticket-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Buscar número..." onkeyup="applyFilters()">
                        </div>
                    </div>

                    <div class="legend-card" id="ticketsLegend">
                        <div class="filter-toggle">
                            <button id="btn-all" class="toggle-btn active" onclick="setFilter('all')">Todos</button>
                            <button id="btn-available" class="toggle-btn" onclick="setFilter('available')">Disponibles</button>
                        </div>

                        <div class="legend-divider"></div>

                        <div class="legend-status-group">
                            <div class="legend-item-modern">
                                <span class="dot-modern available"></span> Disponible
                            </div>
                            <div class="legend-item-modern">
                                <span class="dot-modern selected"></span> Seleccionado
                            </div>
                            <div class="legend-item-modern">
                                <span class="dot-modern occupied"></span> Ocupado
                            </div>
                            <div class="legend-item-modern">
                                <span class="dot-modern sold"></span> Vendido
                            </div>
                        </div>
                    </div>

                    <div id="gridContainer" class="tickets-grid">
                        <?php
                        $tiene_oportunidades = !empty($mapa_oportunidades);

                        if ($tiene_oportunidades) {
                            $inicio = 1;
                            $fin = $rifa['meta_boletos'];
                        } else {
                            // CASO RIFA SIMPLE (Lógica Original Intacta):
                            // Si usa_cero=1, los boletos principales SON los números (0 al 999).
                            $inicio = ($rifa['usa_cero']) ? 0 : 1;
                            $fin = ($rifa['usa_cero']) ? $rifa['meta_boletos'] - 1 : $rifa['meta_boletos'];
                        }

                        for ($i = $inicio; $i <= $fin; $i++):
                            $estado_clase = '';
                            $disabled = '';
                            $onclick = 'onclick="toggleTicket(this)"';

                            if (isset($mapa_ocupados[$i])) {
                                $estado_bd = $mapa_ocupados[$i];
                                $estado_clase = ($estado_bd === 'pagado') ? 'sold' : 'pending';
                                $disabled = 'disabled';
                                $onclick = '';
                            }

                            $numero_visual = str_pad($i, $rifa['cifras'], '0', STR_PAD_LEFT);
                        ?>
                            <button class="ticket-btn <?php echo $estado_clase; ?>"
                                data-number="<?php echo $numero_visual; ?>"
                                <?php echo $onclick; ?>>
                                <?php echo $numero_visual; ?>
                            </button>
                        <?php endfor; ?>
                    </div>

                    <div id="participantsContainer" class="participants-list">
                        <?php if (count($lista_participantes) > 0): ?>
                            <?php foreach ($lista_participantes as $p): ?>
                                <div class="participant-row">
                                    <div>
                                        <span class="p-number">#<?php echo $p['numero']; ?></span>
                                        <span class="p-name"><?php echo htmlspecialchars($p['nombre']); ?></span>
                                    </div>
                                    <div class="p-status">
                                        <?php echo htmlspecialchars($p['estado']); ?>
                                        (<?php echo ucfirst($p['status']); ?>)
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="p-empty-msg">Aún no hay participantes registrados.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="selection-sidebar">
                    <h3 class="sidebar-title">Tu Selección</h3>

                    <div id="selectedList" class="selected-tickets-list">
                        <div class="empty-state-box">
                            <span class="material-symbols-outlined empty-icon">confirmation_number</span>
                            <p class="empty-text">No has seleccionado ningún boleto aún.</p>
                        </div>
                    </div>

                    <p class="selection-helper">Toca un boleto para borrarlo.</p>

                    <div id="opportunitiesContainer" class="opp-wrapper">
                        <div class="opp-card">
                            <div class="opp-header" onclick="toggleOpportunities()">
                                <div class="opp-title-group">
                                    <span class="material-symbols-outlined opp-icon">redeem</span>
                                    <span class="opp-title-text">Oportunidades Extra</span>
                                </div>
                                <span id="oppToggleIcon" class="material-symbols-outlined opp-toggle-icon">expand_less</span>
                            </div>
                            <div id="opportunitiesListContent" class="opp-body"></div>
                            <div class="opp-footer">
                                <span id="totalOpportunitiesCount">0</span> oportunidades adicionales incluidas
                            </div>
                        </div>
                        <p class="opp-info-text">Estas oportunidades se generan automáticamente.</p>
                    </div>

                    <div class="sidebar-total-section">
                        <div class="sidebar-total-row">
                            <span>Total:</span>
                            <span id="totalLabel">$0.00</span>
                        </div>
                    </div>

                    <button id="btnApartar" class="btn-primary" disabled onclick="openCheckoutModal()">
                        Apartar Boletos
                    </button>
                    <button onclick="clearSelection()" class="btn-clear">Limpiar selección</button>
                </div>
            </div>

        </div>
    </div>

    <div id="galleryModal" class="gallery-modal-overlay" onclick="closeGalleryModal(event)">
        <span class="gallery-close-btn" onclick="closeGalleryModal(event)"><i class="fas fa-times"></i></span>

        <?php if (count($rifa['imagenes']) > 1): ?>
            <button class="gallery-nav-btn prev" onclick="changeGalleryImage(-1, event)">
                <i class="fas fa-chevron-left"></i>
            </button>
        <?php endif; ?>

        <div class="gallery-modal-content">
            <img id="galleryModalImage" class="gallery-modal-img" src="" alt="Vista detallada">
        </div>

        <?php if (count($rifa['imagenes']) > 1): ?>
            <button class="gallery-nav-btn next" onclick="changeGalleryImage(1, event)">
                <i class="fas fa-chevron-right"></i>
            </button>
        <?php endif; ?>
    </div>

    <div id="checkoutModal" class="modal">
        <div id="checkoutModalContent" class="modal-content-form">
            <span onclick="closeCheckoutModal()" class="modal-close">&times;</span>

            <div id="checkoutFormContainer">
                <h2 class="modal-title">Datos de Reserva</h2>
                <form id="reserveForm">
                    <input type="hidden" id="rifaId" value="<?php echo $rifa['id']; ?>">

                    <div class="form-group">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" id="nombreCliente" class="form-control" required placeholder="Tu nombre">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Teléfono (WhatsApp)</label>
                        <input type="tel" id="telCliente" class="form-control" required placeholder="55 1234 5678">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado / Ciudad</label>
                        <input type="text" id="estadoCliente" class="form-control" required placeholder="Ej. Jalisco">
                    </div>

                    <div class="modal-summary-card">
                        <p><strong>Boletos:</strong> <span id="modalTicketNumbers"></span></p>
                        <p><strong>Total a Pagar:</strong> <span id="modalTotal" class="modal-total-highlight"></span></p>
                    </div>

                    <button type="submit" class="btn-primary" style="width:100%;">Confirmar Reserva</button>
                </form>
            </div>

            <div id="checkoutResultContainer" style="display: none;"></div>
        </div>
    </div>

    <div id="raffleFloatingSummary" class="raffle-fab" onclick="openRaffleSheet()">
        <div class="raffle-fab-icon">
            <i class="fas fa-ticket-alt"></i>
            <span id="raffleFabCount">0</span>
        </div>
        <span class="raffle-fab-text">Ver Selección</span>
    </div>

    <div id="raffleSheetOverlay" class="raffle-sheet-overlay" onclick="closeRaffleSheet()"></div>

    <div id="raffleBottomSheet" class="raffle-sheet-container">
        <div class="raffle-sheet-header">
            <h3>Tu Selección</h3>
            <button class="btn-close-sheet" onclick="closeRaffleSheet()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="raffle-sheet-body">
            <div id="mobileSelectedList" class="selected-tickets-list"></div>

            <p class="selection-helper">Toca un boleto para borrarlo.</p>

            <div id="mobileOpportunitiesContainer" class="opp-wrapper">
                <div class="opp-card">
                    <div class="opp-header" onclick="toggleMobileOpportunities()">
                        <div class="opp-title-group">
                            <span class="material-symbols-outlined opp-icon">redeem</span>
                            <span class="opp-title-text">Oportunidades Extra</span>
                        </div>
                        <span id="mobileOppToggleIcon" class="material-symbols-outlined opp-toggle-icon">expand_less</span>
                    </div>
                    <div id="mobileOpportunitiesListContent" class="opp-body"></div>
                    <div class="opp-footer">
                        <span id="mobileTotalOpportunitiesCount">0</span> oportunidades adicionales incluidas
                    </div>
                </div>
            </div>
        </div>

        <div class="raffle-sheet-footer">
            <div class="sheet-total-row">
                <span>Total a Pagar:</span>
                <span id="mobileTotalLabel">$0.00</span>
            </div>
            <button id="btnApartarMobile" class="btn-primary" onclick="openCheckoutModal()">
                Apartar Boletos
            </button>
            <button onclick="clearSelection(); closeRaffleSheet();" class="btn-clear">
                Limpiar selección
            </button>
        </div>
    </div>

    <div class="fab-random-container">
        <button onclick="openQuickMenu()" class="btn-fab-random" title="Selección Rápida">
            <i class="fas fa-bolt"></i>
        </button>
    </div>

    <div id="quickMenuOverlay" class="quick-menu-overlay" onclick="closeQuickMenu(event)">
        <div class="quick-menu-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin:0; font-weight: 700; color: #374151; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-bolt" style="color: #4f46e5;"></i> Menú Rápido
                </h3>
                <button onclick="closeQuickMenu()" style="background:none; border:none; color:#9ca3af; cursor:pointer;">
                    <i class="fas fa-times" style="font-size: 1.2rem;"></i>
                </button>
            </div>

            <div class="quick-grid">
                <button class="btn-quick-option" onclick="addRandomTickets(1)">
                    <span class="quick-num">+1</span>
                    <span class="quick-label">Al Azar</span>
                </button>
                <button class="btn-quick-option" onclick="addRandomTickets(3)">
                    <span class="quick-num">+3</span>
                    <span class="quick-label">Al Azar</span>
                </button>
                <button class="btn-quick-option" onclick="addRandomTickets(5)">
                    <span class="quick-num">+5</span>
                    <span class="quick-label">Al Azar</span>
                </button>
            </div>

            <div class="divider-text"><span>O PRUEBA TU SUERTE</span></div>

            <button onclick="openRouletteSetup()" class="btn-roulette-main">
                <div style="display:flex; align-items:center; gap:12px;">
                    <i class="fas fa-dice" style="font-size: 1.5rem;"></i>
                    <div style="text-align:left;">
                        <div style="font-weight:700; font-size: 0.95rem;">Ruleta de la Suerte</div>
                        <div style="font-size: 0.75rem; opacity: 0.9;">Gira para ganar boletos</div>
                    </div>
                </div>
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

    <div id="rouletteSetupModal" class="gallery-modal-overlay">
        <div class="roulette-modal-content">
            <button onclick="closeRouletteAll()" style="position:absolute; top:15px; right:15px; background:none; border:none; color:#9ca3af; cursor:pointer;">
                <i class="fas fa-times" style="font-size: 1.2rem;"></i>
            </button>

            <div style="width:60px; height:60px; background:#f3e8ff; color:#9333ea; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                <i class="fas fa-dice" style="font-size: 1.8rem;"></i>
            </div>

            <h3 style="margin:0 0 5px; color:#1f2937;">¿Cuántos boletos?</h3>
            <p style="margin:0; color:#6b7280; font-size:0.9rem;">La ruleta elegirá tus números.</p>

            <div class="qty-control">
                <button class="btn-qty" onclick="adjustRouletteQty(-1)">-</button>
                <input type="number" id="rouletteQtyInput" class="input-qty" value="5" min="1" max="50" readonly>
                <button class="btn-qty" onclick="adjustRouletteQty(1)">+</button>
            </div>

            <button onclick="runRouletteAnimation()" class="btn-primary" style="display:flex; align-items:center; justify-content:center; gap:8px;">
                Girar Ruleta <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <div id="rouletteRunModal" class="gallery-modal-overlay">
        <div class="roulette-modal-content">
            <h3 style="margin:0; color:#1f2937;">¡Girando!</h3>

            <div class="roulette-wheel-container">
                <i class="fas fa-caret-down roulette-pointer"></i>
                <div id="visualWheel" class="roulette-wheel"></div>
                <div class="roulette-center-star">
                    <i class="fas fa-star" style="color:#eab308;"></i>
                </div>
            </div>

            <p id="rouletteStatusText" style="color:#4f46e5; font-weight:600;">Buscando tu suerte...</p>
        </div>
    </div>

    <div id="rouletteResultModal" class="gallery-modal-overlay">
        <div class="roulette-modal-content">
            <h3 style="margin:0 0 10px; color:#1f2937;">¡Aquí están!</h3>
            <p style="margin:0 0 1rem; color:#6b7280; font-size:0.9rem;">Tus números de la suerte:</p>

            <div id="rouletteResultsBox" class="results-grid">
            </div>

            <div style="display:flex; gap:10px;">
                <button onclick="runRouletteAnimation()" class="btn-primary" style="background:white; color:#6b7280; border:1px solid #d1d5db;">
                    Girar de nuevo
                </button>
                <button onclick="confirmRouletteAdd()" class="btn-primary" style="background:#16a34a;">
                    <i class="fas fa-check"></i> Agregar
                </button>
            </div>
        </div>
    </div>

    <div id="ticketToast" class="ticket-toast">
        <i class="fas fa-check-circle"></i>
        <span id="ticketToastMsg">0 boletos agregados</span>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // ============================================================================
        // 1. CONFIGURACIÓN Y CONSTANTES
        // ============================================================================

        // Datos inyectados desde PHP
        const ticketPrice = <?php echo $rifa['precio_boleto']; ?>;
        const images = <?php echo json_encode($rifa['imagenes']); ?>;
        const API_URL = "https://rifas.rancholastrojes.com.mx/api/reservar.php";
        const opportunitiesMap = <?php echo json_encode($mapa_oportunidades); ?>;
        
        // Configuración WhatsApp
        const waConfig = <?php echo json_encode($wa_config); ?>;
        
        // Metadatos Rifa
        const rifaData = {
            titulo: <?php echo json_encode($rifa['titulo']); ?>
        };

        // ============================================================================
        // 2. VARIABLES GLOBALES (ESTADO)
        // ============================================================================

        // Estado General
        let currentImageIndex = 0;
        let currentFilterMode = 'all'; // 'all' | 'available'
        let selectedTickets = [];
        let lastReservationData = null; // Datos temporales para WhatsApp

        // Estado de Ruleta
        let tempRouletteTickets = [];

        // Estado de Notificaciones (Toast)
        let toastCount = 0;
        let toastTimeout = null;

        // Estado de Zoom (Pinch-to-zoom)
        let zoomState = {
            isPinching: false,
            startDistance: 0,
            currentScale: 1
        };

        // ============================================================================
        // 3. FUNCIONES PURAS / UTILITARIAS
        // ============================================================================

        /**
         * Calcula la distancia entre dos puntos táctiles (para Zoom)
         */
        function getDistance(touches) {
            const dx = touches[0].clientX - touches[1].clientX;
            const dy = touches[0].clientY - touches[1].clientY;
            return Math.sqrt(dx * dx + dy * dy);
        }

        // ============================================================================
        // 4. FUNCIONES DE RENDERIZADO Y ACTUALIZACIÓN DE UI
        // ============================================================================

        // --- Helpers de UI ---

        function checkScrollLock() {
            const activeElements = document.querySelectorAll(
                '#raffleBottomSheet.active, ' +
                '#quickMenuOverlay[style*="display: flex"], ' +
                '#checkoutModal[style*="display: flex"], ' +
                '#rouletteSetupModal.active, ' +
                '#rouletteRunModal.active, ' +
                '#rouletteResultModal.active, ' +
                '#galleryModal.active'
            );

            if (activeElements.length > 0) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        function showTicketToast(addedQuantity) {
            const toast = document.getElementById('ticketToast');
            const msg = document.getElementById('ticketToastMsg');

            if (toastTimeout) clearTimeout(toastTimeout);

            if (toast.classList.contains('active')) {
                toastCount += addedQuantity;
            } else {
                toastCount = addedQuantity;
                toast.classList.add('active');
            }

            const plural = toastCount > 1 ? 's' : '';
            const pluralAgregado = toastCount > 1 ? 's' : '';

            msg.innerText = `¡${toastCount} boleto${plural} agregado${pluralAgregado}!`;

            toastTimeout = setTimeout(() => {
                toast.classList.remove('active');
                setTimeout(() => {
                    toastCount = 0;
                }, 300);
            }, 2500);
        }

        // --- Actualización Principal (Sidebar y Bottom Sheet) ---

        function updateSidebar() {
            // Referencias Desktop
            const list = document.getElementById('selectedList');
            const btn = document.getElementById('btnApartar');
            const opContainer = document.getElementById('opportunitiesContainer');
            const opList = document.getElementById('opportunitiesListContent');
            const totalOpCountLabel = document.getElementById('totalOpportunitiesCount');
            const totalLabel = document.getElementById('totalLabel');
            const helpers = document.querySelectorAll('.selection-helper');

            // Referencias Mobile
            const fab = document.getElementById('raffleFloatingSummary');
            const fabCount = document.getElementById('raffleFabCount');
            const mobileList = document.getElementById('mobileSelectedList');
            const mobileOppContainer = document.getElementById('mobileOpportunitiesContainer');
            const mobileOppList = document.getElementById('mobileOpportunitiesListContent');
            const mobileTotalOpCount = document.getElementById('mobileTotalOpportunitiesCount');
            const mobileTotalLabel = document.getElementById('mobileTotalLabel');
            const btnMobile = document.getElementById('btnApartarMobile');

            // --- Estado Vacío ---
            if (selectedTickets.length === 0) {
                // Desktop
                list.innerHTML = `
                    <div class="empty-state-box">
                        <span class="material-symbols-outlined empty-icon">confirmation_number</span>
                        <p class="empty-text">No has seleccionado ningún boleto aún.</p>
                    </div>
                `;
                btn.disabled = true;
                totalLabel.innerText = "$0.00";
                opContainer.classList.remove('visible');

                helpers.forEach(h => h.style.display = 'none');

                // Mobile
                fab.classList.remove('visible');
                closeRaffleSheet();
                return;
            }

            // --- Estado Con Datos ---
            helpers.forEach(h => h.style.display = 'block');

            // Cálculos
            selectedTickets.sort((a, b) => parseInt(a) - parseInt(b));
            const total = selectedTickets.length * ticketPrice;
            const formattedTotal = "$" + total.toLocaleString('en-US', {
                minimumFractionDigits: 2
            });

            // Generación HTML Boletos
            const ticketsHTML = selectedTickets.map(num =>
                `<span class="selected-ticket-tag" onclick="removeTicket('${num}')" title="Borrar">${num}</span>`
            ).join('');

            // Generación HTML Oportunidades
            let opportunitiesHtml = '';
            let totalExtras = 0;
            let hasOpportunities = false;

            selectedTickets.forEach(num => {
                const numInt = parseInt(num);
                if (opportunitiesMap[numInt] && opportunitiesMap[numInt].length > 0) {
                    hasOpportunities = true;
                    const extraNumbers = opportunitiesMap[numInt];
                    totalExtras += extraNumbers.length;
                    const layoutClass = extraNumbers.length > 7 ? 'layout-column' : '';
                    const badges = extraNumbers.map(e => `<span class="opp-badge">${e}</span>`).join('');

                    opportunitiesHtml += `
                        <div class="opp-row ${layoutClass}">
                            <div class="opp-row-header">
                                <span class="opp-main-ticket">${num}</span>
                                <span class="opp-divider">|</span>
                                <span class="opp-label">Incluye:</span>
                            </div>
                            <div class="opp-badges-container">
                                ${badges}
                            </div>
                        </div>
                    `;
                }
            });

            // Update Desktop
            list.innerHTML = ticketsHTML;
            totalLabel.innerText = formattedTotal;
            btn.disabled = false;

            if (hasOpportunities) {
                opList.innerHTML = opportunitiesHtml;
                totalOpCountLabel.innerText = totalExtras;
                opContainer.classList.add('visible');
            } else {
                opContainer.classList.remove('visible');
            }

            // Update Mobile
            fabCount.innerText = selectedTickets.length;
            if (!document.getElementById('raffleBottomSheet').classList.contains('active')) {
                fab.classList.add('visible');
            }

            mobileList.innerHTML = ticketsHTML;
            mobileTotalLabel.innerText = formattedTotal;
            btnMobile.disabled = false;

            if (hasOpportunities) {
                mobileOppList.innerHTML = opportunitiesHtml;
                mobileTotalOpCount.innerText = totalExtras;
                mobileOppContainer.classList.add('visible');
            } else {
                mobileOppContainer.classList.remove('visible');
            }
        }

        // --- Vistas y Filtros ---

        function switchView(viewName) {
            // UI Tabs
            document.querySelectorAll('.view-tab').forEach(t => t.classList.remove('active'));
            document.getElementById('tab-' + viewName).classList.add('active');

            // Contenedores
            const grid = document.getElementById('gridContainer');
            const list = document.getElementById('participantsContainer');
            const search = document.querySelector('.ticket-search');
            const legend = document.getElementById('ticketsLegend');
            const fabContainer = document.querySelector('.fab-random-container');

            if (viewName === 'tickets') {
                grid.style.display = 'grid';
                if (legend) legend.style.display = 'flex';
                if (fabContainer) fabContainer.style.display = 'block';

                list.style.display = 'none';
                list.classList.remove('active');

                if (search) search.style.visibility = 'visible';
            } else {
                grid.style.display = 'none';
                if (legend) legend.style.display = 'none';
                if (fabContainer) fabContainer.style.display = 'none';

                list.style.display = 'block';
                list.classList.add('active');
            }
        }

        function applyFilters() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const btns = document.querySelectorAll('.ticket-btn');

            btns.forEach(btn => {
                const num = btn.getAttribute('data-number');
                const isSoldOrPending = btn.classList.contains('sold') || btn.classList.contains('pending');

                const matchSearch = num.includes(query);
                const matchAvailability = (currentFilterMode === 'available') ? !isSoldOrPending : true;

                if (matchSearch && matchAvailability) {
                    btn.style.display = 'block';
                } else {
                    btn.style.display = 'none';
                }
            });
        }

        // --- Temporizador ---

        function initTimer() {
            const timerBox = document.querySelector('.raffle-timer-box');
            if (!timerBox) return;

            const endDate = new Date(timerBox.dataset.date).getTime();
            const timerInterval = setInterval(function() {
                const now = new Date().getTime();
                const distance = endDate - now;

                if (distance < 0) {
                    clearInterval(timerInterval);
                    timerBox.innerHTML = "<div class='timer-finished-msg'>¡Sorteo Iniciado!</div>";
                    return;
                }

                document.getElementById("days").innerText = String(Math.floor(distance / (1000 * 60 * 60 * 24))).padStart(2, '0');
                document.getElementById("hours").innerText = String(Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
                document.getElementById("minutes").innerText = String(Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
                document.getElementById("seconds").innerText = String(Math.floor((distance % (1000 * 60)) / 1000)).padStart(2, '0');
            }, 1000);
        }

        // --- Galería ---

        function selectImage(index) {
            currentImageIndex = index;
            const mainImg = document.getElementById('mainImage');
            mainImg.src = images[index];

            document.querySelectorAll('.thumbnail').forEach((t, i) => {
                t.classList.toggle('active', i === index);
            });
        }

        function openGalleryModal(index) {
            if (typeof index === 'undefined') index = currentImageIndex;
            currentImageIndex = index;

            const modal = document.getElementById('galleryModal');
            const modalImg = document.getElementById('galleryModalImage');

            modalImg.src = images[currentImageIndex];
            modal.classList.add('active');
            checkScrollLock();
        }

        function closeGalleryModal(e) {
            if (e && e.target !== e.currentTarget && !e.target.classList.contains('gallery-close-btn')) {
                return;
            }
            document.getElementById('galleryModal').classList.remove('active');
            document.body.style.overflow = '';

            const modalImg = document.getElementById('galleryModalImage');
            if (modalImg) {
                modalImg.style.transform = 'scale(1)';
                zoomState.currentScale = 1;
            }
            checkScrollLock();
        }

        function changeGalleryImage(direction, e) {
            if (e) e.stopPropagation();
            if (images.length <= 1) return;

            currentImageIndex += direction;

            if (currentImageIndex >= images.length) {
                currentImageIndex = 0;
            } else if (currentImageIndex < 0) {
                currentImageIndex = images.length - 1;
            }

            const modalImg = document.getElementById('galleryModalImage');
            if (modalImg) {
                modalImg.src = images[currentImageIndex];
                modalImg.style.transform = 'scale(1)';
                zoomState.currentScale = 1;
            }

            selectImage(currentImageIndex);
        }

        // --- Oportunidades (Acordeón) ---

        function toggleOpportunities() {
            document.getElementById('opportunitiesListContent').classList.toggle('collapsed');
            document.getElementById('oppToggleIcon').classList.toggle('rotated');
        }

        function toggleMobileOpportunities() {
            document.getElementById('mobileOpportunitiesListContent').classList.toggle('collapsed');
            document.getElementById('mobileOppToggleIcon').classList.toggle('rotated');
        }

        // --- Modales y Sheets (Abrir/Cerrar) ---

        function openRaffleSheet() {
            document.getElementById('raffleSheetOverlay').classList.add('active');
            document.getElementById('raffleBottomSheet').classList.add('active');
            document.getElementById('raffleFloatingSummary').classList.remove('visible');
            checkScrollLock();
        }

        function closeRaffleSheet() {
            document.getElementById('raffleSheetOverlay').classList.remove('active');
            document.getElementById('raffleBottomSheet').classList.remove('active');
            document.body.style.overflow = '';
            if (selectedTickets.length > 0) {
                document.getElementById('raffleFloatingSummary').classList.add('visible');
            }
            checkScrollLock();
        }

        function openCheckoutModal() {
            document.getElementById('modalTicketNumbers').innerText = selectedTickets.join(', ');
            document.getElementById('modalTotal').innerText = document.getElementById('totalLabel').innerText;
            document.getElementById('checkoutModal').style.display = 'flex';
            checkScrollLock();
        }

        function closeCheckoutModal() {
            document.getElementById('checkoutModal').style.display = 'none';
            checkScrollLock();
        }

        function openQuickMenu() {
            document.getElementById('quickMenuOverlay').style.display = 'flex';
            checkScrollLock();
        }

        function closeQuickMenu(e) {
            if (e && e.target !== e.currentTarget) return;
            document.getElementById('quickMenuOverlay').style.display = 'none';
            checkScrollLock();
        }

        function openRouletteSetup() {
            document.getElementById('quickMenuOverlay').style.display = 'none';
            document.getElementById('rouletteSetupModal').classList.add('active');
            checkScrollLock();
        }

        function closeRouletteAll() {
            document.getElementById('rouletteSetupModal').classList.remove('active');
            document.getElementById('rouletteRunModal').classList.remove('active');
            document.getElementById('rouletteResultModal').classList.remove('active');
            checkScrollLock();
        }

        function showRouletteResults() {
            const resModal = document.getElementById('rouletteResultModal');
            const container = document.getElementById('rouletteResultsBox');

            container.innerHTML = tempRouletteTickets.map(num => `
                <div class="result-ball">${num}</div>
            `).join('');

            resModal.classList.add('active');
        }

        function renderResultUI(type, reserved, failed) {
            const container = document.getElementById('checkoutResultContainer');

            const config = {
                success: {
                    class: 'feedback-success',
                    icon: 'check_circle',
                    title: '¡Apartado Exitoso!',
                    subtitle: `Has apartado ${reserved.length} boletos correctamente.`,
                    btnText: 'Continuar',
                    btnAction: 'sendWhatsAppAndReload()'
                },
                partial: {
                    class: 'feedback-partial',
                    icon: 'warning',
                    title: 'Apartado Parcial',
                    subtitle: 'Algunos boletos no pudieron ser apartados.',
                    btnText: 'Entendido',
                    btnAction: 'sendWhatsAppAndReload()'
                },
                error: {
                    class: 'feedback-error',
                    icon: 'error',
                    title: 'Apartado Fallido',
                    subtitle: 'Boletos ya no disponibles.',
                    btnText: 'Ver Disponibles',
                    btnAction: 'window.location.reload()'
                }
            };

            const cfg = config[type];

            let reservedHTML = '';
            if (reserved.length > 0) {
                const list = reserved.map(num => `
                    <div class="ticket-badge success">
                        <i class="fas fa-check"></i> ${num}
                    </div>
                `).join('');

                reservedHTML = `
                    <div class="feedback-section-title" style="color: #166534;">
                        <i class="fas fa-check-circle"></i> Apartados (${reserved.length})
                    </div>
                    <div class="feedback-grid">
                        ${list}
                    </div>
                `;
            }

            let failedHTML = '';
            if (failed.length > 0) {
                const list = failed.map(num => `
                    <div class="ticket-badge failed" title="No disponible">
                        <i class="fas fa-times"></i> ${num}
                    </div>
                `).join('');

                failedHTML = `
                    <div class="feedback-section-title" style="color: #991b1b; margin-top: 1rem;">
                        <i class="fas fa-times-circle"></i> No Disponibles (${failed.length})
                    </div>
                    <div class="feedback-grid">
                        ${list}
                    </div>
                    <div class="feedback-alert-box">
                        <i class="fas fa-info-circle" style="font-size: 1.2rem;"></i>
                        <span>Estos boletos ya fueron ganados por alguien más justo antes de tu clic.</span>
                    </div>
                `;
            }

            container.innerHTML = `
                <div class="modal-feedback-container ${cfg.class}">
                    <div class="feedback-header">
                        <div class="feedback-icon-circle">
                            <span class="material-symbols-outlined" style="font-size: 40px;">${cfg.icon}</span>
                        </div>
                        <h3 class="feedback-title">${cfg.title}</h3>
                        <p class="feedback-subtitle">${cfg.subtitle}</p>
                    </div>

                    <div class="feedback-body custom-scrollbar">
                        ${reservedHTML}
                        ${failedHTML}
                    </div>

                    <div class="feedback-actions">
                        <button onclick="${cfg.btnAction}" class="btn-primary">
                            ${cfg.btnText}
                        </button>
                    </div>
                </div>
            `;
        }

        // ============================================================================
        // 5. LÓGICA DE NEGOCIO
        // ============================================================================

        // --- Filtros y Selección ---

        function setFilter(mode) {
            currentFilterMode = mode;

            document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
            if (mode === 'all') document.getElementById('btn-all').classList.add('active');
            else document.getElementById('btn-available').classList.add('active');

            applyFilters();
        }

        function toggleTicket(btn) {
            if (btn.classList.contains('sold') || btn.classList.contains('pending')) return;

            const number = btn.getAttribute('data-number');
            if (selectedTickets.includes(number)) {
                selectedTickets = selectedTickets.filter(n => n !== number);
                btn.classList.remove('selected');
            } else {
                if (selectedTickets.length >= 20) {
                    alert("Máximo 20 boletos por reserva.");
                    return;
                }
                selectedTickets.push(number);
                btn.classList.add('selected');
                showTicketToast(1);
            }
            updateSidebar();
        }

        function removeTicket(number) {
            selectedTickets = selectedTickets.filter(n => n !== number);

            const btn = document.querySelector(`.ticket-btn[data-number="${number}"]`);
            if (btn) btn.classList.remove('selected');

            updateSidebar();
        }

        function clearSelection() {
            selectedTickets = [];
            document.querySelectorAll('.ticket-btn.selected').forEach(b => b.classList.remove('selected'));
            updateSidebar();
        }

        // --- Lógica Ruleta y Aleatoria ---

        function addRandomTickets(count) {
            document.getElementById('quickMenuOverlay').style.display = 'none';
            checkScrollLock();

            const availableBtns = Array.from(document.querySelectorAll('.ticket-btn:not(.sold):not(.pending):not(.selected)'));

            if (availableBtns.length < count) {
                alert("No hay suficientes boletos disponibles para esta cantidad.");
                return;
            }

            availableBtns.sort(() => Math.random() - 0.5);
            const toSelect = availableBtns.slice(0, count);

            toSelect.forEach(btn => {
                const number = btn.getAttribute('data-number');
                if (!selectedTickets.includes(number)) {
                    selectedTickets.push(number);
                    btn.classList.add('selected');
                }
            });

            updateSidebar();
            showTicketToast(toSelect.length);
        }

        function adjustRouletteQty(delta) {
            const input = document.getElementById('rouletteQtyInput');
            let val = parseInt(input.value) + delta;
            if (val < 1) val = 1;
            if (val > 50) val = 50;
            input.value = val;
        }

        function runRouletteAnimation() {
            document.getElementById('rouletteSetupModal').classList.remove('active');
            document.getElementById('rouletteResultModal').classList.remove('active');

            const runModal = document.getElementById('rouletteRunModal');
            runModal.classList.add('active');

            const count = parseInt(document.getElementById('rouletteQtyInput').value);

            const wheel = document.getElementById('visualWheel');
            wheel.style.transition = 'none';
            wheel.style.transform = 'rotate(0deg)';

            void wheel.offsetWidth; // Force Reflow

            const randomDeg = 1800 + Math.floor(Math.random() * 360);

            wheel.style.transition = 'transform 3s cubic-bezier(0.15, 0.9, 0.3, 1)';
            wheel.style.transform = `rotate(${randomDeg}deg)`;

            const availableBtns = Array.from(document.querySelectorAll('.ticket-btn:not(.sold):not(.pending):not(.selected)'));
            if (availableBtns.length < count) {
                alert("No hay suficientes boletos disponibles.");
                runModal.classList.remove('active');
                return;
            }
            availableBtns.sort(() => Math.random() - 0.5);
            tempRouletteTickets = availableBtns.slice(0, count).map(btn => btn.getAttribute('data-number'));

            setTimeout(() => {
                runModal.classList.remove('active');
                showRouletteResults();
            }, 3000);
        }

        function confirmRouletteAdd() {
            let addedCount = 0;

            tempRouletteTickets.forEach(num => {
                if (!selectedTickets.includes(num)) {
                    selectedTickets.push(num);
                    const btn = document.querySelector(`.ticket-btn[data-number="${num}"]`);
                    if (btn) btn.classList.add('selected');
                    addedCount++;
                }
            });

            updateSidebar();
            closeRouletteAll();

            if (addedCount > 0) {
                showTicketToast(addedCount);
            }
        }

        // --- Comunicación Externa (WhatsApp) ---

        function sendWhatsAppAndReload() {
            if (!waConfig.activo || !lastReservationData || !lastReservationData.success) {
                window.location.reload();
                return;
            }

            let msg = waConfig.texto;
            const boletosList = lastReservationData.boletos.join(', ');

            let oportunidadesStr = "";
            let tieneOportunidades = false;
            lastReservationData.boletos.forEach(num => {
                const numInt = parseInt(num);
                if (opportunitiesMap[numInt] && opportunitiesMap[numInt].length > 0) {
                    tieneOportunidades = true;
                    oportunidadesStr += `\n${num}: ${opportunitiesMap[numInt].join(', ')}`;
                }
            });
            if (!tieneOportunidades) oportunidadesStr = "N/A";

            let mensajeCondicional = "";
            if (waConfig.sistema_apartado) {
                mensajeCondicional += `antes de ${waConfig.tiempo_limite} horas`;
            }

            msg = msg.replace(/{titulo_rifa}/g, rifaData.titulo);
            msg = msg.replace(/{cliente_nombre}/g, lastReservationData.nombre);
            msg = msg.replace(/{numeros_boletos}/g, boletosList);
            msg = msg.replace(/{oportunidades_extra}/g, oportunidadesStr);
            msg = msg.replace(/{total_calculado}/g, "$" + lastReservationData.total.toLocaleString('en-US', {
                minimumFractionDigits: 2
            }));
            msg = msg.replace(/{mensaje_condicional}/g, mensajeCondicional);

            msg = msg.replace(/{banco_nombre}/g, waConfig.banco);
            msg = msg.replace(/{beneficiario}/g, waConfig.beneficiario);
            msg = msg.replace(/{banco_cuenta}/g, waConfig.cuenta);

            const numeroDestino = waConfig.numero.replace(/\D/g, '');
            const url = `https://wa.me/${numeroDestino}?text=${encodeURIComponent(msg)}`;

            window.open(url, '_blank');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        // ============================================================================
        // 6. EVENT LISTENERS
        // ============================================================================

        // --- Navegación Teclado (Galería) ---
        document.addEventListener('keydown', function(e) {
            if (!document.getElementById('galleryModal').classList.contains('active')) return;

            if (e.key === 'Escape') closeGalleryModal({
                target: document.getElementById('galleryModal')
            });
            if (e.key === 'ArrowLeft') changeGalleryImage(-1);
            if (e.key === 'ArrowRight') changeGalleryImage(1);
        });

        // --- Envío de Formulario ---
        const reserveForm = document.getElementById('reserveForm');
        if (reserveForm) {
            reserveForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const btn = this.querySelector('button[type="submit"]');
                const originalBtnText = btn.innerText;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

                const nombre = document.getElementById('nombreCliente').value;
                const telefono = document.getElementById('telCliente').value;
                const estado = document.getElementById('estadoCliente').value;
                const rifaId = document.getElementById('rifaId').value;

                try {
                    const response = await fetch(API_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            rifa_id: rifaId,
                            boletos: selectedTickets,
                            nombre: nombre,
                            telefono: telefono,
                            estado: estado
                        })
                    });

                    const textResponse = await response.text();
                    let result;
                    try {
                        result = JSON.parse(textResponse);
                    } catch (err) {
                        console.error("Respuesta inválida:", textResponse);
                        throw new Error("Error de comunicación con el servidor.");
                    }

                    document.getElementById('checkoutFormContainer').style.display = 'none';
                    const resultContainer = document.getElementById('checkoutResultContainer');
                    resultContainer.style.display = 'block';

                    if (result.success && (!result.errores || result.errores.length === 0)) {
                        lastReservationData = {
                            success: true,
                            boletos: result.reservados,
                            nombre: nombre,
                            total: result.reservados.length * ticketPrice
                        };
                        renderResultUI('success', result.reservados, []);
                    } else if (result.success && result.errores && result.errores.length > 0) {
                        lastReservationData = {
                            success: true,
                            boletos: result.reservados,
                            nombre: nombre,
                            total: result.reservados.length * ticketPrice
                        };
                        const failed = selectedTickets.filter(x => !result.reservados.includes(x));
                        renderResultUI('partial', result.reservados, failed);
                    } else {
                        lastReservationData = {
                            success: false
                        };
                        renderResultUI('error', [], selectedTickets);
                    }

                } catch (error) {
                    console.error(error);
                    alert("Error de conexión. Por favor verifica tu internet e intenta de nuevo.");
                    btn.disabled = false;
                    btn.innerText = originalBtnText;
                }
            });
        }

        // --- Touch Events (Zoom) ---
        const modalContent = document.querySelector('.gallery-modal-content');
        const modalImg = document.getElementById('galleryModalImage');
        const modalContainer = document.getElementById('galleryModal');

        if (modalContent) {
            modalContent.addEventListener('touchstart', function(e) {
                if (e.touches.length === 2) {
                    zoomState.isPinching = true;
                    zoomState.startDistance = getDistance(e.touches);

                    if (modalContainer) modalContainer.classList.add('zooming');

                    const rect = modalImg.getBoundingClientRect();
                    const touch1 = e.touches[0];
                    const touch2 = e.touches[1];
                    const centerX = (touch1.clientX + touch2.clientX) / 2;
                    const centerY = (touch1.clientY + touch2.clientY) / 2;
                    const originX = centerX - rect.left;
                    const originY = centerY - rect.top;

                    modalImg.style.transformOrigin = `${originX}px ${originY}px`;
                    modalImg.style.transition = 'none';
                }
            });

            modalContent.addEventListener('touchmove', function(e) {
                if (zoomState.isPinching && e.touches.length === 2) {
                    if (e.cancelable) e.preventDefault();

                    const newDistance = getDistance(e.touches);
                    const scale = newDistance / zoomState.startDistance;
                    zoomState.currentScale = Math.max(1, scale);

                    modalImg.style.transform = `scale(${zoomState.currentScale})`;
                }
            });

            modalContent.addEventListener('touchend', function(e) {
                if (zoomState.isPinching && e.touches.length < 2) {
                    zoomState.isPinching = false;

                    if (modalContainer) modalContainer.classList.remove('zooming');

                    modalImg.style.transition = 'transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1)';
                    modalImg.style.transform = 'scale(1)';
                    zoomState.currentScale = 1;

                    setTimeout(() => {
                        modalImg.style.transformOrigin = 'center center';
                    }, 300);
                }
            });
        }

        // ============================================================================
        // 7. INICIALIZACIÓN
        // ============================================================================

        initTimer();

        document.addEventListener('DOMContentLoaded', () => {
            // Inicializar helpers de UI
            const helpers = document.querySelectorAll('.selection-helper');
            helpers.forEach(h => h.style.display = 'none');
        });
    </script>
</body>

</html>