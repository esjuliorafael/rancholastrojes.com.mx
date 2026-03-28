<?php
include_once 'config/database.php';
include_once 'models/Producto.php';
include_once 'models/Configuracion.php';

$database = new Database();
$db = $database->getConnection();

$productoModel = new Producto($db);
$config = new Configuracion($db);

$logo_actual = $config->obtenerPorClave('sistema_logo');

$id_producto = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$producto = $productoModel->leerUno($id_producto);

if (!$producto || !$producto['activo']) {
    header("Location: tienda.php");
    exit;
}

// Obtener productos relacionados
$relacionados = $productoModel->leerRelacionados($producto['tipo'], $producto['id']);

$galeria = isset($producto['galeria']) ? $producto['galeria'] : [];

// Preparar array de imágenes para JS (Portada + Galería)
$imagenes_js = [];
if (!empty($producto['portada'])) {
    $imagenes_js[] = $producto['portada'];
}
foreach ($galeria as $img) {
    $imagenes_js[] = $img['ruta_archivo'];
}

// Datos para JS en el botón de compra
$js_id = $producto['id'];
$js_tipo = $producto['tipo'];
$js_nombre = htmlspecialchars($producto['nombre']);
$js_precio = $producto['precio'];
$js_stock = $producto['stock'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($producto['nombre']); ?> - Rancho Las Trojes</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Lora:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <link id="darkModeStylesheet" rel="stylesheet" href="assets/css/dark-mode.css" disabled>

    <style>
        /* ==========================================================================
           1. LAYOUT PRINCIPAL
           ========================================================================== */
        .product-detail-section {
            /* Box Model */
            padding: 3rem 0;
            margin-top: 2rem;
            /* Visuals */
            background-color: var(--white);
        }

        @media (max-width: 512px) {
            .product-detail-section {
                padding: 1rem 0;
            }
        }

        .product-container {
            /* Box Model */
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 4rem;
        }

        @media (max-width: 1024px) {
            .product-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        /* ==========================================================================
           2. MÓDULO: GALERÍA DE IMÁGENES
           ========================================================================== */
        .product-gallery {
            /* Box Model */
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .main-image-container {
            /* Positioning */
            position: relative;
            /* Box Model */
            overflow: hidden;
            aspect-ratio: 1/1;
            border: 1px solid var(--divider);
            border-radius: 1rem;
            /* Visuals */
            cursor: pointer;
            background: var(--off-white-light);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.1);
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
            transition: transform 0.3s, opacity 0.2s ease-in-out;
        }

        .zoom-hint {
            /* Positioning */
            position: absolute;
            bottom: 10px;
            right: 10px;
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
            overflow: hidden;
            aspect-ratio: 1/1;
            border: 2px solid transparent;
            border-radius: 1rem;
            /* Visuals */
            cursor: pointer;
            opacity: 0.7;
            transition: all 0.2s;
        }

        .thumbnail.active,
        .thumbnail:hover {
            opacity: 1;
            border-color: var(--brown);
        }

        .thumbnail img {
            /* Box Model */
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ==========================================================================
           3. MÓDULO: INFORMACIÓN PRINCIPAL
           ========================================================================== */
        .product-info h1 {
            /* Box Model */
            margin-bottom: 0.5rem;
            /* Typography */
            font-family: 'Lora', serif;
            font-size: 3em;
            font-weight: 600;
            color: var(--black-blue);
        }

        @media (max-width: 512px) {
            .product-info h1 {
                font-size: 2em;
            }
        }

        .product-price {
            /* Box Model */
            margin-bottom: 1.5rem;
            /* Typography */
            font-size: 2em;
            font-weight: 600;
            color: var(--brown);
        }

        @media (max-width: 512px) {
            .product-price {
                font-size: 1.5em;
            }
        }

        .product-description {
            /* Box Model */
            margin-bottom: 2rem;
            /* Typography */
            line-height: 1.8;
            color: var(--text-color);
        }

        /* --- Badges / Etiquetas --- */
        .status-badge {
            /* Box Model */
            display: inline-block;
            padding: 0.25rem 0.75rem;
            margin-bottom: 1rem;
            border-radius: 1rem;
            /* Typography */
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-disponible {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-reservado {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .status-vendido {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        /* ==========================================================================
           4. MÓDULO: CONTROLES DE COMPRA
           ========================================================================== */
        /* --- Selector de Cantidad --- */
        .quantity-control {
            /* Box Model */
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .qty-label {
            /* Typography */
            font-weight: 600;
            color: var(--text-color);
        }

        .qty-wrapper {
            /* Box Model */
            display: flex;
            align-items: center;
            height: 45px;
            overflow: hidden;
            border: 1px solid var(--divider);
            border-radius: 0.75rem;
        }

        .qty-btn {
            /* Box Model */
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 100%;
            border: none;
            /* Typography */
            font-size: 1rem;
            color: var(--black-blue);
            /* Visuals */
            background: var(--off-white-light);
            cursor: pointer;
            transition: 0.2s;
        }

        .qty-btn:hover {
            background: #e2e8f0;
            color: var(--brown);
        }

        .qty-input {
            /* Box Model */
            width: 50px;
            border: none;
            outline: none;
            /* Typography */
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            color: var(--black-blue);
            /* Visuals */
            background: transparent;
            -moz-appearance: textfield;
        }

        .qty-input::-webkit-outer-spin-button,
        .qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* --- Botones de Acción --- */
        .action-buttons {
            /* Box Model */
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        @media (max-width: 1024px) {
            .action-buttons {
                flex-direction: column;
            }
        }

        .btn-cart {
            /* Box Model */
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 0.75rem;
            /* Typography */
            font-size: 1em;
            font-weight: 600;
            color: var(--white);
            /* Visuals */
            background: var(--black-blue);
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cart:hover {
            background: #2c3e50;
            transform: translateY(-2px);
        }

        .btn-buy {
            /* Box Model */
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 0.75rem;
            /* Typography */
            font-size: 1rem;
            font-weight: 700;
            color: var(--white);
            /* Visuals */
            background: var(--brown);
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(139, 94, 60, 0.3);
            transition: all 0.3s;
        }

        .btn-buy:hover {
            background: #6d4a2f;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 94, 60, 0.4);
        }

        /* ==========================================================================
           5. MÓDULO: DETALLES Y FAQ
           ========================================================================== */
        .details-grid {
            /* Box Model */
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            padding-top: 3rem;
            margin-bottom: 4rem;
            border-top: 1px solid var(--divider);
            align-items: start;
        }

        @media (max-width: 1024px) {
            .details-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        /* --- Meta Data --- */
        .product-meta {
            /* Box Model */
            height: fit-content;
            padding: 2rem;
            border-radius: 1rem;
            /* Visuals */
            background: var(--off-white-light);
        }

        .meta-title {
            /* Box Model */
            margin-bottom: 1.5rem;
            /* Typography */
            font-family: 'Lora', serif;
            font-size: 1.5em;
            color: var(--black-blue);
        }

        .meta-item {
            /* Box Model */
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--divider);
        }

        .meta-item:last-child {
            border-bottom: none;
        }

        .meta-label {
            /* Typography */
            font-weight: 600;
            color: var(--text-color);
        }

        .meta-value {
            /* Typography */
            font-weight: 500;
            color: var(--black-blue);
        }

        /* --- FAQ Container --- */
        .faq-container {
            /* Box Model */
            padding: 0.5rem 0;
        }

        .faq-title {
            /* Box Model */
            margin-bottom: 1rem;
            /* Typography */
            font-size: 2.5em;
            font-weight: 600;
            color: var(--black-blue);
        }

        @media (max-width: 512px) {
            .faq-title {
                font-size: 1.75em;
            }
        }

        /* ==========================================================================
           6. MÓDULO: PRODUCTOS RELACIONADOS
           ========================================================================== */
        .related-products-section {
            /* Box Model */
            padding-top: 2rem;
            border-top: 1px solid var(--divider);
        }

        .related-title {
            /* Box Model */
            margin-bottom: 1rem;
            /* Typography */
            font-size: 2.5em;
            font-weight: 600;
            color: var(--black-blue);
        }

        @media (max-width: 512px) {
            .related-title {
                font-size: 1.75em;
            }
        }

        .related-grid {
            /* Box Model */
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        @media (max-width: 1024px) {
            .related-grid {
                grid-template-columns: 1fr;
            }
        }

        .related-card {
            /* Box Model */
            display: block;
            border-radius: 1rem;
            overflow: hidden;
            /* Typography */
            text-decoration: none;
            /* Visuals */
            background: var(--white);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        }

        .related-img {
            /* Box Model */
            width: 100%;
            height: 256px;
            object-fit: cover;
            /* Visuals */
            background: #f0f0f0;
        }

        .related-info {
            /* Box Model */
            padding: 1rem;
            /* Typography */
            text-align: center;
        }

        .related-name {
            /* Box Model */
            margin-bottom: 0.5rem;
            /* Typography */
            font-size: 1.25em;
            font-weight: 600;
            color: var(--black-blue);
        }

        .related-price {
            /* Typography */
            font-size: 1.25em;
            font-weight: 600;
            color: var(--brown);
        }

        .related-badge {
            /* Box Model */
            margin-left: 5px;
            padding: 2px 6px;
            border-radius: 4px;
            /* Typography */
            vertical-align: middle;
            font-size: 0.7rem;
        }

        /* ==========================================================================
           7. MÓDULO: MODAL GALERÍA (FULLSCREEN)
           ========================================================================== */
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
            background-color: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(20px);
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

        /* --- Botones de Control (Close/Nav) --- */
        .gallery-close-btn,
        .gallery-nav-btn {
            /* Positioning */
            position: fixed;
            z-index: 2001;
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
            cursor: pointer;
            background: rgba(0, 0, 0, 0.7);
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

        .gallery-nav-btn.prev { left: 2rem; }
        .gallery-nav-btn.next { right: 2rem; }

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

        /* Estado Zooming: Ocultar controles */
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

            .gallery-nav-btn.prev { left: 1rem; }
            .gallery-nav-btn.next { right: 1rem; }
        }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <section class="page-header-start container-wide">
        <img src="assets/images/42c08f60-d5b7-4aec-87cd-632c3a0ed6a6.jpeg" alt="Fondo Tienda">
        <div class="page-header-overlay">
            <h1 class="page-header-title animated-text">
                <span class="word">Tienda</span>
            </h1>
            <p class="page-header-subtitle fade-up-animation">Inicio / Tienda / <?php echo htmlspecialchars($producto['nombre']); ?></p>
        </div>
    </section>

    <section class="product-detail-section container-wide">
        <div class="container">

            <div class="product-container">

                <div class="product-gallery fade-up-animation">
                    <div class="main-image-container" onclick="openGalleryModal(currentImageIndex)">
                        <img src="<?php echo !empty($producto['portada']) ? $producto['portada'] : 'assets/images/placeholder.jpg'; ?>" class="main-image" id="mainImage" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                        <div class="zoom-hint"><i class="fas fa-search-plus"></i> Ampliar</div>
                    </div>
                    <div class="thumbnails-grid">
                        <?php foreach ($imagenes_js as $index => $img): ?>
                            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" onclick="selectImage(<?php echo $index; ?>)">
                                <img src="<?php echo $img; ?>" alt="Thumb">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="product-info">
                    <?php if ($producto['tipo'] === 'ave'): ?>
                        <span class="status-badge status-<?php echo $producto['estado_venta']; ?>">
                            <?php echo ucfirst($producto['estado_venta']); ?>
                        </span>
                    <?php endif; ?>

                    <h1><?php echo htmlspecialchars($producto['nombre']); ?></h1>
                    <div class="product-price">$<?php echo number_format($producto['precio'], 2); ?> MXN</div>

                    <div class="product-description">
                        <?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?>
                    </div>

                    <?php
                    $puede_comprar = ($producto['tipo'] === 'ave')
                        ? ($producto['estado_venta'] === 'disponible')
                        : ($producto['stock'] > 0);
                    ?>

                    <?php if ($puede_comprar): ?>

                        <?php if ($producto['tipo'] === 'articulo'): ?>
                            <div class="quantity-control">
                                <span class="qty-label">Cantidad:</span>
                                <div class="qty-wrapper">
                                    <button type="button" class="qty-btn" onclick="updateQty(-1)"><i class="fas fa-minus"></i></button>
                                    <input type="number" id="qtyInput" class="qty-input" value="1" min="1" max="<?php echo $producto['stock']; ?>" readonly>
                                    <button type="button" class="qty-btn" onclick="updateQty(1)"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="action-buttons">
                            <button class="btn-cart" onclick="addCurrentProductToCart()">
                                <i class="fas fa-cart-plus"></i> Añadir al Carrito
                            </button>
                            <button class="btn-buy" onclick="buyCurrentProductNow()">
                                <i class="fas fa-bolt"></i> Comprar Ahora
                            </button>
                        </div>

                    <?php else: ?>
                        <button class="btn-cart" style="background: var(--text-color); cursor: not-allowed; width:100%;" disabled>
                            No Disponible
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="details-grid">
                <div class="product-meta fade-up-animation">
                    <h3 class="meta-title">Detalles</h3>
                    <div class="meta-item">
                        <span class="meta-label">Categoría:</span>
                        <span class="meta-value"><?php echo ucfirst($producto['tipo']); ?></span>
                    </div>

                    <?php if ($producto['tipo'] === 'ave'): ?>
                        <div class="meta-item">
                            <span class="meta-label">Anillo:</span>
                            <span class="meta-value"><?php echo $producto['anillo'] ?: 'N/A'; ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Edad / Etapa:</span>
                            <span class="meta-value"><?php echo $producto['edad'] ?: 'N/A'; ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Propósito:</span>
                            <span class="meta-value"><?php echo $producto['proposito'] ?: 'N/A'; ?></span>
                        </div>
                    <?php else: ?>
                        <div class="meta-item">
                            <span class="meta-label">Stock Disponible:</span>
                            <span class="meta-value"><?php echo $producto['stock']; ?> unidades</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="faq-container">
                    <h3 class="faq-title animated-text">
                        <span class="word">Preguntas</span>
                        <span class="word lora-italic">Frecuentes</span>
                    </h3>
                    <?php include 'includes/faq.php'; ?>
                </div>
            </div>

            <?php if (!empty($relacionados)): ?>
                <div class="related-products-section">
                    <h3 class="related-title animated-text">
                        <?php if ($producto['tipo'] === 'ave'): ?>
                            <span class="word">Otras</span>
                            <span class="word lora-italic">Aves Disponibles</span>
                        <?php else: ?>
                            <span class="word">Artículos</span>
                            <span class="word lora-italic">Relacionados</span>
                        <?php endif; ?>
                    </h3>
                    <div class="related-grid">
                        <?php foreach ($relacionados as $rel): ?>
                            <a href="producto.php?id=<?php echo $rel['id']; ?>" class="related-card fade-up-animation">
                                <img src="<?php echo !empty($rel['portada']) ? $rel['portada'] : 'assets/images/placeholder.jpg'; ?>"
                                    alt="<?php echo htmlspecialchars($rel['nombre']); ?>"
                                    class="related-img">
                                <div class="related-info">
                                    <div class="related-name">
                                        <?php echo htmlspecialchars($rel['nombre']); ?>
                                        <?php if ($rel['tipo'] == 'ave' && $rel['estado_venta'] != 'disponible'): ?>
                                            <span class="related-badge status-<?php echo $rel['estado_venta']; ?>">
                                                <?php echo ucfirst($rel['estado_venta']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="related-price">$<?php echo number_format($rel['precio'], 2); ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <div id="galleryModal" class="gallery-modal-overlay" onclick="closeGalleryModal(event)">
        <span class="gallery-close-btn" onclick="closeGalleryModal(event)"><i class="fas fa-times"></i></span>

        <?php if (count($imagenes_js) > 1): ?>
            <button class="gallery-nav-btn prev" onclick="changeGalleryImage(-1, event)">
                <i class="fas fa-chevron-left"></i>
            </button>
        <?php endif; ?>

        <div class="gallery-modal-content">
            <img id="galleryModalImage" class="gallery-modal-img" src="" alt="Vista detallada">
        </div>

        <?php if (count($imagenes_js) > 1): ?>
            <button class="gallery-nav-btn next" onclick="changeGalleryImage(1, event)">
                <i class="fas fa-chevron-right"></i>
            </button>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // ============================================================================
        // 1. CONFIGURACIÓN Y CONSTANTES
        // ============================================================================

        // Datos inyectados desde PHP
        const images = <?php echo json_encode($imagenes_js); ?>;
        const maxStock = <?php echo $js_stock; ?>;
        const isAve = '<?php echo $js_tipo; ?>' === 'ave';
        const currentProductId = <?php echo $js_id; ?>;

        // Referencias DOM (Elementos Estáticos)
        const dom = {
            mainImg: document.getElementById('mainImage'),
            modal: document.getElementById('galleryModal'),
            modalImg: document.getElementById('galleryModalImage'),
            modalContent: document.querySelector('.gallery-modal-content'),
            qtyInput: document.getElementById('qtyInput')
        };

        // ============================================================================
        // 2. VARIABLES GLOBALES (ESTADO)
        // ============================================================================

        // Estado de Galería
        let currentImageIndex = 0;

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
         * Calcula la distancia euclidiana entre dos puntos táctiles
         */
        function getDistance(touches) {
            const dx = touches[0].clientX - touches[1].clientX;
            const dy = touches[0].clientY - touches[1].clientY;
            return Math.sqrt(dx * dx + dy * dy);
        }

        // ============================================================================
        // 4. FUNCIONES DE RENDERIZADO Y ACTUALIZACIÓN DE UI
        // ============================================================================

        // --- Galería ---

        function selectImage(index) {
            currentImageIndex = index;
            const mainImg = document.getElementById('mainImage'); // Referencia directa para compatibilidad
            if (mainImg) mainImg.src = images[index];

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
            document.body.style.overflow = 'hidden'; // Bloquear scroll del body
        }

        function closeGalleryModal(e) {
            // Cerrar solo si es click en overlay o botón explícito
            if (e && e.target !== e.currentTarget && !e.target.classList.contains('gallery-close-btn')) {
                return;
            }
            document.getElementById('galleryModal').classList.remove('active');
            document.body.style.overflow = '';

            // Resetear zoom al cerrar
            const modalImg = document.getElementById('galleryModalImage');
            if (modalImg) {
                modalImg.style.transform = 'scale(1)';
                zoomState.currentScale = 1;
            }
        }

        function changeGalleryImage(direction, e) {
            if (e) e.stopPropagation();
            if (images.length <= 1) return;

            currentImageIndex += direction;

            // Loop infinito
            if (currentImageIndex >= images.length) {
                currentImageIndex = 0;
            } else if (currentImageIndex < 0) {
                currentImageIndex = images.length - 1;
            }

            const modalImg = document.getElementById('galleryModalImage');
            if (modalImg) {
                modalImg.src = images[currentImageIndex];
                // Resetear zoom al cambiar imagen
                modalImg.style.transform = 'scale(1)';
                zoomState.currentScale = 1;
            }

            selectImage(currentImageIndex);
        }

        // --- Controles de UI (Input Cantidad) ---

        function updateQty(change) {
            if (isAve) return; // Las aves siempre son 1

            const input = document.getElementById('qtyInput');
            let newVal = parseInt(input.value) + change;

            if (newVal < 1) newVal = 1;
            if (newVal > maxStock) {
                newVal = maxStock;
                alert("Máximo stock disponible alcanzado");
            }
            input.value = newVal;
        }

        // ============================================================================
        // 5. LÓGICA DE NEGOCIO (CARRITO Y COMPRA)
        // ============================================================================

        function addCurrentProductToCart() {
            if (typeof cart === 'undefined') {
                console.error("Error: main.js no cargado o variable 'cart' no accesible");
                return;
            }

            const input = document.getElementById('qtyInput');
            const qtyToAdd = isAve ? 1 : parseInt(input ? input.value : 1);

            // 1. Buscar si ya existe en el carrito
            const existingItemIndex = cart.findIndex(i => i.id === currentProductId);
            let currentInCart = existingItemIndex > -1 ? cart[existingItemIndex].cantidad : 0;

            // 2. Validar stock total (Lo que ya tengo + lo que quiero agregar)
            if (isAve && currentInCart >= 1) {
                alert("Esta ave ya está en tu carrito (Stock único).");
                return;
            }
            if (!isAve && (currentInCart + qtyToAdd) > maxStock) {
                alert(`No puedes agregar esa cantidad. Stock disponible: ${maxStock}. Ya tienes ${currentInCart} en el carrito.`);
                return;
            }

            // 3. Modificar el carrito
            if (existingItemIndex > -1) {
                cart[existingItemIndex].cantidad += qtyToAdd;
                alert("Cantidad actualizada en el carrito.");
            } else {
                cart.push({
                    id: currentProductId,
                    tipo: '<?php echo $js_tipo; ?>',
                    nombre: '<?php echo $js_nombre; ?>',
                    precio: <?php echo $js_precio; ?>,
                    cantidad: qtyToAdd
                });
                alert("Agregado al carrito.");
            }

            saveCart(); // Función global de main.js
            updateCartUI(); // Función global de main.js
        }

        function buyCurrentProductNow() {
            if (typeof cart === 'undefined') {
                console.error("Error: main.js no cargado");
                return;
            }

            const input = document.getElementById('qtyInput');
            const qtyToAdd = isAve ? 1 : parseInt(input ? input.value : 1);

            const existingItemIndex = cart.findIndex(i => i.id === currentProductId);
            let currentInCart = existingItemIndex > -1 ? cart[existingItemIndex].cantidad : 0;

            // Validaciones directas para compra rápida
            if (isAve && currentInCart >= 1) {
                window.location.href = 'checkout.php';
                return;
            }
            if (!isAve && (currentInCart + qtyToAdd) > maxStock) {
                alert(`No puedes comprar esa cantidad. Stock disponible: ${maxStock}. Ya tienes ${currentInCart} en el carrito.`);
                return;
            }

            // Actualizar carrito y redirigir
            if (existingItemIndex > -1) {
                cart[existingItemIndex].cantidad += qtyToAdd;
            } else {
                cart.push({
                    id: currentProductId,
                    tipo: '<?php echo $js_tipo; ?>',
                    nombre: '<?php echo $js_nombre; ?>',
                    precio: <?php echo $js_precio; ?>,
                    cantidad: qtyToAdd
                });
            }

            saveCart();
            updateCartUI();
            window.location.href = 'checkout.php';
        }

        // ============================================================================
        // 6. EVENT LISTENERS
        // ============================================================================

        // --- Navegación Teclado (Accesibilidad Galería) ---
        document.addEventListener('keydown', function(e) {
            if (!document.getElementById('galleryModal').classList.contains('active')) return;

            if (e.key === 'Escape') closeGalleryModal({
                target: document.getElementById('galleryModal')
            });
            if (e.key === 'ArrowLeft') changeGalleryImage(-1);
            if (e.key === 'ArrowRight') changeGalleryImage(1);
        });

        // --- Gestos Táctiles (Zoom tipo Instagram) ---
        // Usamos las referencias DOM definidas arriba si existen, o buscamos dinámicamente

        const modalContent = document.querySelector('.gallery-modal-content');

        if (modalContent) {
            // 1. Iniciar el gesto (Touch Start)
            modalContent.addEventListener('touchstart', function(e) {
                if (e.touches.length === 2) {
                    zoomState.isPinching = true;
                    zoomState.startDistance = getDistance(e.touches);

                    // Ocultar botones de UI para limpieza visual
                    const modalContainer = document.getElementById('galleryModal');
                    if (modalContainer) modalContainer.classList.add('zooming');

                    // Calcular pivote del zoom
                    const modalImg = document.getElementById('galleryModalImage');
                    const rect = modalImg.getBoundingClientRect();
                    const touch1 = e.touches[0];
                    const touch2 = e.touches[1];

                    const centerX = (touch1.clientX + touch2.clientX) / 2;
                    const centerY = (touch1.clientY + touch2.clientY) / 2;

                    const originX = centerX - rect.left;
                    const originY = centerY - rect.top;

                    modalImg.style.transformOrigin = `${originX}px ${originY}px`;
                    modalImg.style.transition = 'none'; // Eliminar delay para respuesta inmediata
                }
            });

            // 2. Mover los dedos (Touch Move)
            modalContent.addEventListener('touchmove', function(e) {
                if (zoomState.isPinching && e.touches.length === 2) {
                    if (e.cancelable) e.preventDefault(); // Evitar scroll nativo

                    const newDistance = getDistance(e.touches);
                    const scale = newDistance / zoomState.startDistance;

                    zoomState.currentScale = Math.max(1, scale); // No permitir zoom out menor a 1

                    const modalImg = document.getElementById('galleryModalImage');
                    modalImg.style.transform = `scale(${zoomState.currentScale})`;
                }
            });

            // 3. Soltar (Touch End)
            modalContent.addEventListener('touchend', function(e) {
                // Si dejamos de tener 2 dedos en pantalla
                if (zoomState.isPinching && e.touches.length < 2) {
                    zoomState.isPinching = false;

                    // Restaurar UI
                    const modalContainer = document.getElementById('galleryModal');
                    if (modalContainer) modalContainer.classList.remove('zooming');

                    // Animación de rebote al estado original
                    const modalImg = document.getElementById('galleryModalImage');
                    modalImg.style.transition = 'transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1)';
                    modalImg.style.transform = 'scale(1)';
                    zoomState.currentScale = 1;

                    // Resetear origen después de la animación
                    setTimeout(() => {
                        modalImg.style.transformOrigin = 'center center';
                    }, 300);
                }
            });
        }
    </script>
</body>
</html>