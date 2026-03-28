<?php
include_once 'config/database.php';
include_once 'models/Configuracion.php';

$database = new Database();
$db = $database->getConnection();
$config = new Configuracion($db);
$logo_actual = $config->obtenerPorClave('sistema_logo');

$queryGallos = "SELECT * FROM productos 
                WHERE tipo = 'ave' AND activo = 1 
                ORDER BY fecha_creacion DESC 
                LIMIT 5";
$stmtGallos = $db->prepare($queryGallos);
$stmtGallos->execute();
$gallos = $stmtGallos->fetchAll(PDO::FETCH_ASSOC);

$galloDestacado = null;
$gallosGrid = [];

if (!empty($gallos)) {
    $galloDestacado = $gallos[0]; // El más reciente es el principal
    // Si hay más de 1, los siguientes (hasta 4) van al grid
    if (count($gallos) > 1) {
        $gallosGrid = array_slice($gallos, 1);
    }
}

// ============================================================================
// LÓGICA DE RIFAS (CONEXIÓN SECUNDARIA)
// ============================================================================

class DatabaseRifasIndex {
    private $host = "localhost";
    private $db_name = "granlivo_rifas_las_trojes_db";
    private $username = "granlivo_admin";
    private $password = "j10u22l12i9O16*";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch (PDOException $exception) {
            // En producción silenciamos error, en dev podríamos hacer echo
            return null;
        }
        return $this->conn;
    }
}

$rifasActivas = [];
$dbRifasObj = new DatabaseRifasIndex();
$dbRifas = $dbRifasObj->getConnection();

if ($dbRifas) {
    // CORRECCIÓN AQUÍ: Cambiado 'activo' por 'activa' según tu DB
    $queryRifas = "SELECT * FROM rifas WHERE estado = 'activa' ORDER BY id DESC LIMIT 3";
    
    $stmtR = $dbRifas->prepare($queryRifas);
    $stmtR->execute();
    $rifasRaw = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rifasRaw as $r) {
        // Calcular progreso (Boletos vendidos o pendientes de pago)
        $qVentas = "SELECT COUNT(*) FROM ventas WHERE rifa_id = :id AND estado_pago IN ('pagado', 'pendiente')";
        $stmtV = $dbRifas->prepare($qVentas);
        $stmtV->bindParam(':id', $r['id']);
        $stmtV->execute();
        $vendidos = $stmtV->fetchColumn();

        $meta = intval($r['cantidad_boletos']);
        $porcentaje = ($meta > 0) ? ($vendidos / $meta) * 100 : 0;
        
        // Formatear fecha
        $fechaObj = new DateTime($r['fecha_sorteo']);
        $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        $mesNombre = $meses[$fechaObj->format('n') - 1];
        
        // Validar imagen: Si está vacía, no intentar cargar ruta rota
        $rutaImagen = !empty($r['imagen']) 
            ? "https://rifas.rancholastrojes.com.mx/" . $r['imagen']
            : "assets/images/placeholder.jpg";

        $rifasActivas[] = [
            'id' => $r['id'],
            'titulo' => $r['titulo'],
            'descripcion' => $r['descripcion'],
            'precio' => $r['precio_boleto'],
            'imagen' => $rutaImagen,
            'porcentaje' => $porcentaje,
            'dia' => $fechaObj->format('d'),
            'mes' => $mesNombre
        ];
    }
}

$slides = [
    [
        'titulo' => 'Genética de Campeones',
        'subtitulo' => 'Crianza selectiva con los más altos estándares de sanidad.',
        'imagen' => 'assets/uploads/sliders/hero_home_1.jpg', 
        'fallback' => 'https://images.unsplash.com/photo-1548505299-923315a6e29c?q=80&w=2072&auto=format&fit=crop'
    ],
    [
        'titulo' => 'Pasión por la Tradición',
        'subtitulo' => 'Más de 20 años conservando las mejores líneas de sangre.',
        'imagen' => 'assets/uploads/sliders/hero_home_2.jpg',
        'fallback' => 'https://images.unsplash.com/photo-1639749563032-4752b5757753?q=80&w=2070&auto=format&fit=crop'
    ]
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rancho Las Trojes - Inicio</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Lora:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="assets/css/styles.css">

    <style>
        /* ==========================================================================
           1. LAYOUT PRINCIPAL & SCROLL ENGINE
           ========================================================================== */
        .home-scroll-track {
            position: relative;
            width: 100%;
            height: 250vh;
        }

        .home-sticky-viewport {
            position: sticky;
            top: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }

        /* ==========================================================================
           2. CAPA 1: TEXTO DE FONDO (BIENVENIDA)
           ========================================================================== */
        .layer-background-text {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0 20px;
            text-align: center;
            pointer-events: none;
        }

        .welcome-pre {
            margin-bottom: 1.5rem;
            font-size: 1em;
            font-weight: 700;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: var(--brown);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.8s ease;
        }

        .welcome-title-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            line-height: 1;
        }

        .welcome-title {
            margin: 0;
            font-family: 'Lora', serif;
            font-size: clamp(2.5em, 8vw, 5em);
            font-weight: 700;
            line-height: 1.1;
            color: var(--black-blue);
        }

        @media (max-width: 768px) {
            .welcome-title {
                font-size: 3.5em;
            }
        }

        .char-reveal {
            display: inline-block;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .char-reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .welcome-line {
            width: 80px;
            height: 3px;
            margin: 1.5rem auto;
            background-color: var(--brown);
            opacity: 0;
            transition: opacity 0.8s ease;
        }

        .welcome-desc {
            max-width: 600px;
            font-size: 1.1em;
            color: #64748b;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.8s ease;
        }

        /* ==========================================================================
           3. CAPA 2: HERO SLIDER
           ========================================================================== */
        .layer-foreground-slider {
            position: relative;
            z-index: 40;
            display: flex;
            justify-content: center;
            width: 100%;
            padding: 10rem 2.5rem 2rem 2.5rem;
            box-sizing: border-box;
            transform-origin: top center;
        }

        @media (max-width: 1024px) {
            .layer-foreground-slider {
                padding: 10rem 1rem 2rem 1rem;
            }
        }

        .hero-slider-container {
            position: relative;
            width: 100%;
            height: calc(100vh - 192px);
            overflow: hidden;
            border-radius: 1.25rem;
            background-color: #1e293b;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        /* --- Slides --- */
        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s ease;
        }

        .slide.active {
            opacity: 1;
        }

        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .slide-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.6), transparent);
        }

        .slide-content {
            position: absolute;
            bottom: 2rem;
            left: 2rem;
            color: white;
        }

        @media (max-width: 512px) {
            .slide-content {
                bottom: 6rem;
                left: 1.5rem;
            }
        }

        .slide-title {
            margin-bottom: 0.5rem;
            font-family: 'Lora', serif;
            font-size: 2.5em;
            color: var(--white);
        }

        .slide-text {
            font-size: 1em;
            color: var(--white);
            max-width: 600px;
        }

        /* --- Controls --- */
        .slider-nav {
            position: absolute;
            right: 2rem;
            bottom: 2rem;
            z-index: 50;
            display: flex;
            gap: 1rem;
        }

        .nav-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            color: white;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            cursor: pointer;
            transition: 0.3s;
        }

        .nav-btn:hover {
            color: black;
            background: white;
        }

        .scroll-hint {
            position: absolute;
            bottom: 1rem;
            left: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 0.75em;
            color: white;
            transform: translateX(-50%);
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-10px); }
        }

        /* ==========================================================================
           4. MÓDULO: CATÁLOGO (GENÉRICO)
           ========================================================================== */
        .catalog-section {
            position: relative;
            z-index: 30;
            padding: 4rem 2rem;
            background-color: #f8fafc;
        }

        .rifas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .rifa-card {
            border: 1px solid #e2e8f0;
            border-radius: 1.2rem;
            overflow: hidden;
            color: inherit;
            text-decoration: none;
            background: white;
            transition: 0.3s;
        }

        .rifa-card:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            transform: translateY(-8px);
        }

        .card-img-wrap {
            height: 220px;
            overflow: hidden;
        }

        .card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* ==========================================================================
           5. MÓDULO: ÚLTIMA COLECCIÓN (GALLOS)
           ========================================================================== */
        .latest-collection {
            background-color: var(--white);
            padding: 3rem 0;
            margin-top: 2rem;
        }

        @media (max-width: 512px) {
            .latest-collection {
                padding: 1rem 0;
            }
        }

        /* --- Header --- */
        .collection-header {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        @media (min-width: 768px) {
            .collection-header {
                flex-direction: row;
                align-items: flex-end;
                justify-content: space-between;
            }
        }

        .collection-tag {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.75em;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .collection-title {
            margin: 0;
            font-family: 'Lora', serif;
            font-size: 2.5em;
            line-height: 1.1;
            color: var(--text-color);
        }

        @media (min-width: 768px) {
            .collection-title {
                font-size: 3.5em;
            }
        }

        .collection-desc {
            max-width: 450px;
            margin: 0;
            font-size: 1em;
            line-height: 1.6;
            color: var(--text-gray);
        }

        /* --- Grid Layout --- */
        .collection-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        @media (min-width: 1024px) {
            .collection-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 512px) {
            .collection-grid {
                gap: 1rem;
            }
        }

        .sub-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        @media (max-width: 512px) {
            .sub-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }

        /* ==========================================================================
           6. COMPONENTE: TARJETA EDITORIAL
           ========================================================================== */
        .editorial-card {
            position: relative;
            overflow: hidden;
            border-radius: 1.25rem;
            background-color: #f0f0f0;
            box-shadow: var(--shadow-card);
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .editorial-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transform: translateY(-5px);
        }

        /* Aspect Ratios */
        .featured-aspect {
            height: 100%;
            aspect-ratio: 3/4;
        }

        .standard-aspect {
            aspect-ratio: 3/4;
        }
        
        @media (max-width: 512px) {
            .featured-aspect {
              aspect-ratio: 5/8;  
            }
            .standard-aspect {
              aspect-ratio: 5/8;  
            }
        }
        
        /* Imágenes */
        .editorial-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .editorial-card:hover img {
            transform: scale(1.05);
        }

        /* Overlay */
        .card-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 1.5rem;
            color: white;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.2) 50%, rgba(0, 0, 0, 0) 100%);
        }

        @media (max-width: 512px) {
            .featured-card .card-overlay {
                padding: 1rem;
            }
            .standard-card .card-overlay {
                padding: 0.5rem
            }
        }

        /* Badges */
        .card-top-badges {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            left: 1.5rem;
            display: flex;
            justify-content: space-between;
        }

        @media (max-width: 512px) {
            .card-top-badges {
                top: 0.5rem;
                right: 0.5rem;
                left: 0.5rem
            }
        }

        .badge-pill {
            padding: 4px 10px;
            border-radius: 0.75rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: var(--white);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(2px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Contenido Texto */
        .card-title {
            margin: 0 0 0.5rem 0;
            font-family: 'Lora', serif;
            font-size: 1.5em;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .featured-card .card-title {
            font-size: 2.25em;
        }

        .standard-card .card-title {
            font-size: 1.25em;
        }

        @media (max-width: 512px) {
            .featured-card .card-title {
                font-size: 1.75em;
            }
            .standard-card .card-title {
                font-size: 1em;
                margin: 0 0 0.25rem 0;
            }
        }

        .card-details {
            display: -webkit-box;
            margin-bottom: 1.5rem;
            overflow: hidden;
            font-size: 1em;
            color: rgba(255, 255, 255, 0.8);
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        @media (max-width: 512px) {
            .featured-card .card-details {
                margin-bottom: 1rem;
            }
        }

        .standard-card .card-details {
            display: none;
        }

        /* Actions Footer */
        .card-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .card-actions div {
            line-height: 1.2;
        }

        @media (max-width: 512px) {
            .card-actions {
                padding-top: 1rem;
            }
        }

        .price-text {
            font-size: 1.25em;
            font-weight: 700;
        }

        .btn-icon-glass {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            font-size: 1em;
            color: var(--white);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(2px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-icon-glass:hover {
            color: var(--white);
            background: rgba(0, 0, 0, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(4px);
        }

        .featured-card .btn-responsive-details {
            width: auto;
            padding: 0 1.5rem;
            font-weight: 600;
            font-size: 1em;
            text-decoration: none;
        }

        .featured-card .btn-responsive-details i {
            display: none;
        }

        @media (max-width: 512px) {
            .featured-card .btn-responsive-details {
                width: 48px;
                padding: 0;
            }
            .featured-card .btn-responsive-details span {
                display: none;
            }
            .featured-card .btn-responsive-details i {
                display: inline-block;
            }
        }

        @media (max-width: 512px) {
            .standard-card .card-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                padding-top: 0.25rem;
            }

            .standard-card .card-actions > div {
                width: 100%;
            }

            .standard-card .btn-icon-glass {
                width: auto;
                flex: 1;
                height: 40px;
            }
            
            .standard-card .price-text {
                font-size: 1em;
            }
        }

        /* ==========================================================================
           7. UTILITIES & REFACTORED STYLES (NO INLINE STYLES)
           ========================================================================== */
        
        .price-label {
            font-size: 0.75em; 
            opacity: 0.7; 
            text-transform: uppercase;
        }

        .action-buttons-group {
            display: flex; 
            gap: 0.75rem;
        }

        .standard-card .action-buttons-group {
            gap: 0.5rem;
        }

        .empty-collection-msg {
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background: #f8fafc; 
            border-radius: 1rem; 
            color: #94a3b8;
            padding: 2rem;
            text-align: center;
        }

        .char-space {
            display: inline-block;
            width: 0.3em;
        }

        /* ==========================================================================
           8. SECCIÓN: RIFAS ACTIVAS
           ========================================================================== */
        .raffles-section {
            padding: 3rem 0;
            margin-top: 2rem;
            background-color: var(--brown-dark);
        }

        @media (max-width: 512px) {
            .raffles-section {
                padding: 1rem 0;
            }
        }

        .raffles-header {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            margin-bottom: 4rem;
        }

        @media (min-width: 768px) {
            .raffles-header {
                flex-direction: row;
                align-items: flex-end;
                justify-content: space-between;
            }
        }

        .gold-subtitle {
            color: var(--brown-light);
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.75em;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .white-title {
            margin: 0;
            font-family: 'Lora', serif;
            font-size: 2.5em;
            line-height: 1.1;
            color: var(--white);
        }

        @media (min-width: 768px) {
            .white-title {
                font-size: 3.5em;
            }
        }

        .raffles-intro-text {
            max-width: 450px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* --- Grid de Rifas --- */
        .raffles-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        @media (min-width: 768px) {
            .raffles-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .raffles-grid { grid-template-columns: repeat(3, 1fr); }
        }

        /* --- Tarjeta de Rifa --- */
        .raffle-card {
            position: relative;
            height: 500px;
            border-radius: 1.25rem;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .raffle-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            border-color: var(--brown-light);
        }

        .raffle-bg-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .raffle-card:hover .raffle-bg-img {
            transform: scale(1.1);
        }

        .raffle-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(29, 22, 16, 0.96) 0%, rgba(29, 22, 16, 0.4) 60%, transparent 100%);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Calendar Box */
        .calendar-box {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(2px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.75rem;
            padding: 10px 15px;
            text-align: center;
            width: fit-content;
        }

        .cal-month {
            display: block;
            font-size: 0.75em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--brown-light);
        }

        .cal-day {
            display: block;
            font-size: 1.5em;
            font-weight: 700;
            line-height: 1;
            color: white;
        }

        /* Content */
        .raffle-info-block h3 {
            font-family: 'Lora', serif;
            font-size: 1.75em;
            margin: 0 0 0.5rem 0;
            color: white;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .raffle-short-desc {
            color: rgba(255, 255, 255, 0.6);
            font-size: 1em;
            margin-bottom: 1.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Progress Bar */
        .raffle-progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.75em;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--brown-light);
        }

        .raffle-track {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .raffle-fill {
            height: 100%;
            background: #c4a47c;
        }

        /* Footer Actions */
        .raffle-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .raffle-footer div {
            line-height: 1.2;
        }

        .raffle-price-label {
            font-size: 0.75em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            display: block;
        }

        .raffle-price-val {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }

        .btn-gold-outline {
            background: transparent;
            border: 1px solid var(--brown-light);
            color: var(--brown-light);
            padding: 0 1.5rem;
            border-radius: 0.75rem;
            font-size: 1em;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s;
            height: 48px;
        }

        .raffle-card:hover .btn-gold-outline {
            background: var(--brown-light);
            color: var(--brown-dark);
        }
    </style>
</head>

<body>

    <?php include 'includes/header.php'; ?>

    <div id="scroll-track" class="home-scroll-track">
        <div class="home-sticky-viewport">

            <div class="layer-background-text">
                <span id="welcome-pre" class="welcome-pre">BIENVENIDO A</span>
                <div class="welcome-title-container">
                    <h1 class="welcome-title">Rancho</h1>
                    <h1 class="welcome-title">Las Trojes</h1>
                </div>
                <div id="welcome-line" class="welcome-line"></div>
                <p id="welcome-desc" class="welcome-desc">
                    Donde la tradición se encuentra con la excelencia genética.
                </p>
            </div>

            <div id="slider-wrapper" class="layer-foreground-slider">
                <div id="hero-slider" class="hero-slider-container">

                    <div id="slides-container">
                        <?php foreach ($slides as $index => $slide): ?>
                            <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo $slide['imagen']; ?>" 
                                     alt="<?php echo htmlspecialchars($slide['titulo']); ?>"
                                     onerror="this.src='<?php echo $slide['fallback']; ?>'; this.onerror=null;">
                                
                                <div class="slide-overlay"></div>
                                
                                <div class="slide-content">
                                    <h2 class="slide-title"><?php echo htmlspecialchars($slide['titulo']); ?></h2>
                                    <p class="slide-text">
                                        <?php echo htmlspecialchars($slide['subtitulo']); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="slider-ui" class="slider-nav">
                        <button class="nav-btn" onclick="changeSlide(-1)"><i class="fas fa-arrow-left"></i></button>
                        <button class="nav-btn" onclick="changeSlide(1)"><i class="fas fa-arrow-right"></i></button>
                    </div>

                    <div id="scroll-hint" class="scroll-hint">
                        <span>SCROLL</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <?php if ($galloDestacado): ?>
        <section class="latest-collection container-wide">
            <div class="container">

                <div class="collection-header">
                    <div>
                        <span class="collection-tag">Temporada 2026</span>
                        <h2 class="collection-title">Últimos Gallos</h2>
                    </div>
                    <p class="collection-desc">
                        Ejemplares con casta seleccionada para alta competencia y pie de cría.
                    </p>
                </div>

                <div class="collection-grid">

                    <div class="editorial-card featured-card featured-aspect" onclick="window.location.href='producto.php?id=<?php echo $galloDestacado['id']; ?>'">
                        <?php
                        $imgDestacada = !empty($galloDestacado['portada']) ? $galloDestacado['portada'] : 'assets/images/placeholder.jpg';
                        ?>
                        <img src="<?php echo $imgDestacada; ?>"
                            alt="<?php echo htmlspecialchars($galloDestacado['nombre']); ?>"
                            loading="lazy">

                        <div class="card-overlay">
                            <div class="card-top-badges">
                                <span class="badge-pill"><?php echo htmlspecialchars($galloDestacado['proposito'] ?? 'Ave'); ?></span>

                                <?php if (!empty($galloDestacado['anillo'])): ?>
                                    <span class="badge-pill">Anillo: <?php echo htmlspecialchars($galloDestacado['anillo']); ?></span>
                                <?php endif; ?>
                            </div>

                            <h3 class="card-title"><?php echo htmlspecialchars($galloDestacado['nombre']); ?></h3>

                            <p class="card-details">
                                <?php echo mb_strimwidth(strip_tags($galloDestacado['descripcion']), 0, 120, "..."); ?>
                            </p>

                            <div class="card-actions">
                                <div>
                                    <span class="price-label">Precio</span>
                                    <div class="price-text">$<?php echo number_format($galloDestacado['precio'], 2); ?></div>
                                </div>
                                <div class="action-buttons-group">
                                    <button class="btn-icon-glass"
                                        onclick="event.stopPropagation(); addIndexProductToCart(
                                            <?php echo $galloDestacado['id']; ?>, 
                                            '<?php echo addslashes($galloDestacado['nombre']); ?>', 
                                            <?php echo $galloDestacado['precio']; ?>, 
                                            '<?php echo $imgDestacada; ?>', 
                                            '<?php echo $galloDestacado['tipo']; ?>', 
                                            <?php echo $galloDestacado['stock']; ?>
                                        )"
                                        title="Agregar al carrito">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>

                                    <a href="producto.php?id=<?php echo $galloDestacado['id']; ?>" 
                                    class="btn-icon-glass btn-responsive-details">
                                        <span>Ver Detalles</span>
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($gallosGrid)): ?>
                        <div class="sub-grid">
                            <?php foreach ($gallosGrid as $gallo):
                                $imgGrid = !empty($gallo['portada']) ? $gallo['portada'] : 'assets/images/placeholder.jpg';
                            ?>
                                <div class="editorial-card standard-card standard-aspect" onclick="window.location.href='producto.php?id=<?php echo $gallo['id']; ?>'">
                                    <img src="<?php echo $imgGrid; ?>"
                                        alt="<?php echo htmlspecialchars($gallo['nombre']); ?>"
                                        loading="lazy">

                                    <div class="card-overlay">
                                        <div class="card-top-badges">
                                            <span class="badge-pill"><?php echo htmlspecialchars($gallo['proposito'] ?? 'Ave'); ?></span>
                                        </div>

                                        <h3 class="card-title"><?php echo htmlspecialchars($gallo['nombre']); ?></h3>

                                        <div class="card-actions">
                                            <div class="price-text">$<?php echo number_format($gallo['precio'], 2); ?></div>
                                            <div class="action-buttons-group">
                                                <button class="btn-icon-glass"
                                                    onclick="event.stopPropagation(); addIndexProductToCart(
                                                        <?php echo $gallo['id']; ?>, 
                                                        '<?php echo addslashes($gallo['nombre']); ?>', 
                                                        <?php echo $gallo['precio']; ?>, 
                                                        '<?php echo $imgGrid; ?>', 
                                                        '<?php echo $gallo['tipo']; ?>', 
                                                        <?php echo $gallo['stock']; ?>
                                                    )">
                                                    <i class="fas fa-cart-plus"></i>
                                                </button>
                                                
                                                <a href="producto.php?id=<?php echo $gallo['id']; ?>" class="btn-icon-glass">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-collection-msg">
                            <p>Próximamente más ejemplares disponibles.</p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($rifasActivas)): ?>
    <section class="raffles-section container-wide">
        <div class="container">
            
            <div class="raffles-header">
                <div>
                    <span class="gold-subtitle">Tu oportunidad de ganar es hoy</span>
                    <h2 class="white-title">Próximas Rifas</h2>
                </div>
                <div class="raffles-intro-text">
                    <p>No necesitas gastar una fortuna. Participa en nuestra rifa y, por el costo de un simple boleto, podrías ser el ganador.</p>
                </div>
            </div>

            <div class="raffles-grid">
                <?php foreach ($rifasActivas as $rifa): ?>
                    <div class="raffle-card" onclick="window.location.href='ver_rifa.php?id=<?php echo $rifa['id']; ?>'">
                        <img src="<?php echo $rifa['imagen']; ?>" 
                             alt="<?php echo htmlspecialchars($rifa['titulo']); ?>" 
                             class="raffle-bg-img"
                             loading="lazy">
                        
                        <div class="raffle-overlay">
                            <div class="calendar-box">
                                <span class="cal-month"><?php echo $rifa['mes']; ?></span>
                                <span class="cal-day"><?php echo $rifa['dia']; ?></span>
                            </div>

                            <div class="raffle-info-block">
                                <h3><?php echo htmlspecialchars($rifa['titulo']); ?></h3>
                                <p class="raffle-short-desc">
                                    <?php echo mb_strimwidth(strip_tags($rifa['descripcion']), 0, 90, "..."); ?>
                                </p>

                                <div class="raffle-progress-label">
                                    <span>VENDIDO</span>
                                    <span><?php echo number_format($rifa['porcentaje'], 0); ?>%</span>
                                </div>
                                <div class="raffle-track">
                                    <div class="raffle-fill" style="width: <?php echo $rifa['porcentaje']; ?>%"></div>
                                </div>

                                <div class="raffle-footer">
                                    <div>
                                        <span class="raffle-price-label">Precio Boleto</span>
                                        <div class="raffle-price-val">$<?php echo number_format($rifa['precio'], 0); ?></div>
                                    </div>
                                    <button class="btn-gold-outline">Apartar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </section>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <script>
        // 1. REVEAL TEXT SETUP
        const titleElements = document.querySelectorAll('.welcome-title');

        titleElements.forEach(el => {
            const text = el.innerText;
            el.innerHTML = text.split('').map(char => {
                // Se reemplazó el estilo inline por la clase .char-space
                if (char === ' ') return `<span class="char-space">&nbsp;</span>`;
                return `<span class="char-reveal">${char}</span>`;
            }).join('');
        });

        // Ahora seleccionamos TODOS los caracteres generados para la secuencia
        const chars = document.querySelectorAll('.char-reveal');
        const welcomePre = document.getElementById('welcome-pre');
        const welcomeLine = document.getElementById('welcome-line');
        const welcomeDesc = document.getElementById('welcome-desc');
        const slider = document.getElementById('hero-slider');
        const sliderUI = document.getElementById('slider-ui');
        const scrollHint = document.getElementById('scroll-hint');

        // 2. SCROLL ENGINE (160px TOP / 32px BOTTOM)
        function updateScroll() {
            const scrollTop = window.scrollY;
            const vh = window.innerHeight;

            // Tus medidas exactas
            const marginTop = 160;
            const marginBottom = 32;
            const totalReserved = marginTop + marginBottom;

            // Altura inicial = Pantalla completa - 192px
            const initialHeight = vh - totalReserved;
            const targetHeight = 384; // Altura final de la tira al scrollear

            const range = vh * 0.7; // Sensibilidad del scroll
            let progress = Math.min(Math.max(scrollTop / range, 0), 1);

            // Redimensionar Slider
            // NOTA: Estos estilos dinámicos (height, opacity) se mantienen vía JS 
            // porque dependen del cálculo numérico del scroll.
            const currentHeight = initialHeight - ((initialHeight - targetHeight) * progress);
            slider.style.height = `${currentHeight}px`;

            // Desvanecer Slider
            slider.style.opacity = 1 - progress;
            if (sliderUI) sliderUI.style.opacity = 1 - (progress * 2);
            if (scrollHint) scrollHint.style.opacity = 1 - (progress * 3);

            // Aparecer texto de fondo
            if (progress > 0.3) {
                const textProgress = (progress - 0.3) / 0.7;
                welcomePre.style.opacity = 1;
                welcomePre.style.transform = 'translateY(0)';
                chars.forEach((char, i) => {
                    if (textProgress > (i / chars.length)) char.classList.add('visible');
                    else char.classList.remove('visible');
                });
                if (textProgress > 0.8) {
                    welcomeLine.style.opacity = 1;
                    welcomeDesc.style.opacity = 1;
                    welcomeDesc.style.transform = 'translateY(0)';
                }
            } else {
                welcomePre.style.opacity = 0;
                chars.forEach(c => c.classList.remove('visible'));
                welcomeLine.style.opacity = 0;
                welcomeDesc.style.opacity = 0;
            }
        }

        // 3. INITIALIZE
        window.addEventListener('scroll', () => {
            window.requestAnimationFrame(updateScroll);
        });

        window.addEventListener('resize', updateScroll);

        // Slider Carousel Logic
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');

        function showSlide(index) {
            slides.forEach(s => s.classList.remove('active'));
            slides[index].classList.add('active');
        }

        function changeSlide(dir) {
            currentSlide = (currentSlide + dir + slides.length) % slides.length;
            showSlide(currentSlide);
        }
        setInterval(() => changeSlide(1), 5000);

        function addIndexProductToCart(id, nombre, precio, imagen, tipo, stockMax) {
            // 1. Verificación de Seguridad: Asegurar que main.js cargó
            if (typeof cart === 'undefined') {
                console.error("Error crítico: El sistema de carrito (main.js) no está cargado.");
                alert("Error interno. Por favor recarga la página.");
                return;
            }

            // 2. Normalización de datos
            const prodId = parseInt(id);
            const prodPrice = parseFloat(precio);
            const prodStock = parseInt(stockMax);

            // 3. Buscar existencia previa
            const existingIndex = cart.findIndex(item => item.id === prodId);
            let currentQtyInCart = existingIndex > -1 ? cart[existingIndex].cantidad : 0;

            // 4. Validaciones de Negocio (Idénticas a producto.php)
            
            // A) Validación para AVES (Stock único)
            if (tipo === 'ave' && currentQtyInCart >= 1) {
                // Usamos el Toast de UI si existe, o alert nativo
                if (typeof TrojesUI !== 'undefined' && TrojesUI.toast) {
                    TrojesUI.toast('error', 'Esta ave ya está en tu carrito (Pieza única).');
                } else {
                    alert("Esta ave ya está en tu carrito (Stock único).");
                }
                return;
            }

            // B) Validación de STOCK General (Para artículos o futuros productos)
            if (tipo !== 'ave' && (currentQtyInCart + 1) > prodStock) {
                alert(`Stock máximo alcanzado. Disponibles: ${prodStock}`);
                return;
            }

            // 5. Actualización del Estado (Array Global)
            if (existingIndex > -1) {
                cart[existingIndex].cantidad++;
            } else {
                cart.push({
                    id: prodId,
                    tipo: tipo,
                    nombre: nombre,
                    precio: prodPrice,
                    imagen: imagen, // Guardamos la imagen para futuros usos en checkout
                    cantidad: 1
                });
            }

            // 6. Persistencia y Sincronización (El núcleo del ecosistema)
            saveCart();      // Guarda en localStorage 'rlt_cart'
            updateCartUI();  // Actualiza badges (Header y FAB)

            // 7. Feedback Visual (UX)
            // Abrimos el mini-cart para confirmar la acción, igual que en las grandes tiendas
            if (typeof renderMiniCartContents === 'function') {
                renderMiniCartContents();
                openMiniCart();
            } else {
                // Fallback por si acaso
                alert("✅ Producto agregado correctamente");
            }
        }

        // Run once on load
        updateScroll();
    </script>
</body>

</html>