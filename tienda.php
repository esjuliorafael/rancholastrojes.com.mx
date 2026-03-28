<?php
include_once 'config/database.php';
include_once 'models/Configuracion.php';
include_once 'models/Producto.php';

$database = new Database();
$db = $database->getConnection();
$config = new Configuracion($db);
$logo_actual = $config->obtenerPorClave('sistema_logo');

$productoModel = new Producto($db);
$productosDb = $productoModel->leerTodos();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tienda - Rancho Las Trojes</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Lora:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/forms.css">
    <link id="darkModeStylesheet" rel="stylesheet" href="assets/css/dark-mode.css" disabled>

    <style>
        /* --- ESTILOS PRINCIPALES DE LA TIENDA --- */
        .shop-section {
            background-color: var(--white);
            padding: 3rem 0;
            margin-top: 2rem;
        }

        /* --- TOOLBAR SUPERIOR --- */
        .shop-toolbar {
            margin-bottom: 2rem;
        }

        .toolbar-top {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Buscador */
        .search-container {
            flex: 1;
            position: relative;
            min-width: 280px;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1px solid var(--divider);
            border-radius: 50px;
            font-size: 1em;
            background: var(--off-white-light);
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--brown);
            background: var(--white);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .search-icon {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-color);
        }

        /* Tabs Centrales (Filtro Tipo) */
        .type-tabs {
            display: flex;
            background: var(--off-white-light);
            padding: 0.25rem;
            border-radius: 50px;
            border: 1px solid var(--divider);
        }

        .type-tab {
            padding: 0.5rem 1.5rem;
            border: none;
            background: none;
            border-radius: 40px;
            color: var(--text-color);
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .type-tab.active {
            background-color: var(--white);
            color: var(--brown);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        /* Botón Filtros (Trigger) */
        .btn-filter-trigger {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: 1px solid var(--divider);
            border-radius: 50px;
            background: var(--white);
            color: var(--black-blue);
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-filter-trigger:hover {
            border-color: var(--brown);
            color: var(--brown);
        }

        .filter-count-badge {
            background: var(--brown);
            color: white;
            font-size: 0.7rem;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .filter-count-badge.hidden {
            display: none;
        }

        /* --- CHIPS DE FILTROS ACTIVOS --- */
        .active-filters-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
            min-height: 0;
        }

        .filter-chip {
            background: var(--off-white-light);
            border: 1px solid var(--divider);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            color: var(--black-blue);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: fadeIn 0.3s ease;
        }

        .filter-chip i {
            cursor: pointer;
            color: var(--text-color);
            transition: color 0.2s;
        }

        .filter-chip i:hover {
            color: #ef4444;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- PANEL OFF-CANVAS / BOTTOM SHEET --- */
        .filter-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            backdrop-filter: blur(2px);
        }

        .filter-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .filter-panel {
            position: fixed;
            background: var(--white);
            z-index: 2001;
            display: flex;
            flex-direction: column;
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.2);
        }

        /* Panel Lateral (Desktop) */
        @media (min-width: 769px) {
            .filter-panel {
                top: 0;
                right: 0;
                width: 400px;
                height: 100vh;
                transform: translateX(100%);
            }
            .filter-panel.active {
                transform: translateX(0);
            }
        }

        /* Bottom Sheet (Móvil) */
        @media (max-width: 768px) {
            .filter-panel {
                bottom: 0;
                left: 0;
                width: 100%;
                max-height: 85vh;
                border-radius: 20px 20px 0 0;
                transform: translateY(100%);
            }
            .filter-panel.active {
                transform: translateY(0);
            }
            .toolbar-top { gap: 0.8rem; }
            .search-container { order: 1; width: 100%; }
            .type-tabs { order: 2; flex: 1; justify-content: space-between; }
            .btn-filter-trigger { order: 3; }
            .type-tab { padding: 0.6rem 1rem; font-size: 0.85rem; }
        }

        /* Contenido del Panel */
        .panel-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--divider);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-header h3 {
            font-size: 1.75em;
            font-weight: 600;
            color: var(--black-blue);
        }

        .btn-close-panel {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: var(--text-color);
        }

        .panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
        }

        .filter-group {
            margin-bottom: 2rem;
        }

        .filter-group-title {
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-color);
            margin-bottom: 1rem;
            display: block;
        }

        /* Radio Buttons estilo tarjeta */
        .radio-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        .radio-option {
            position: relative;
        }

        .radio-option input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .radio-label {
            display: block;
            text-align: center;
            padding: 0.75rem;
            border: 1px solid var(--divider);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1em;
            color: var(--black-blue);
        }

        .radio-option input:checked + .radio-label {
            border-color: var(--brown);
            background: rgba(139, 94, 60, 0.05);
            color: var(--brown);
            font-weight: 600;
        }

        .panel-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--divider);
            display: flex;
            gap: 1rem;
        }

        .btn-clear {
            flex: 1;
            background: var(--off-white-light);
            color: var(--text-color);
            border: 1px solid var(--divider);
            padding: 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
        }

        .btn-apply {
            flex: 2;
            background: var(--brown);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
        }

        /* GRID PRODUCTOS */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
        }
        
        @media (max-width: 1024px) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }

        /* Card Producto */
        .product-card {
            background: var(--white);
            border: 1px solid var(--divider);
            border-radius: 1rem;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border-color: var(--brown);
        }

        .card-image-wrapper {
            position: relative;
            padding-top: 100%;
            overflow: hidden;
            background: var(--off-white-light);
        }

        .card-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .product-card:hover .card-image {
            transform: scale(1.05);
        }

        .card-badges {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            z-index: 2;
        }

        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }

        .badge-ave { background: rgba(255, 255, 255, 0.9); color: var(--black-blue); }
        .badge-articulo { background: rgba(255, 255, 255, 0.9); color: var(--brown); }
        .badge-status { color: white; }
        .status-reservado { background: #f59e0b; }
        .status-vendido { background: #ef4444; }

        .card-content {
            padding: 1.25rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        @media (max-width: 768px) {
            .card-content {
                padding: 0.5rem;
            }
        }

        .card-title {
            font-size: 1.25em;
            font-weight: 600;
            color: var(--black-blue);
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .card-title {
                font-size: 1em;
            }
        }

        .card-meta {
            font-size: 0.75em;
            color: var(--text-color);
            margin-bottom: 1rem;
            flex: 1;
        }

        .meta-tag {
            background: var(--off-white-light);
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
        }

        .card-footer-price {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--divider);
            padding-top: 1rem;
            margin-top: auto;
        }

        .price {
            font-size: 1.25em;
            font-weight: 600;
            color: var(--brown);
        }

        @media (max-width: 768px) {
            .price {
                font-size: 1em;
            }
        }

        .btn-add-mini {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--off-white-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brown);
            transition: 0.2s;
        }

        .product-card:hover .btn-add-mini {
            background: var(--brown);
            color: white;
        }

        .empty-results {
            grid-column: 1/-1;
            text-align: center;
            padding: 4rem;
            color: var(--text-color);
        }

        /* Ocultar secciones del panel según contexto */
        .hidden-section {
            display: none !important;
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
            <p class="page-header-subtitle fade-up-animation">Inicio / Tienda</p>
        </div>
    </section>

    <section class="shop-section container-wide">
        <div class="container">

            <div class="shop-toolbar fade-up-animation">
                <div class="toolbar-top">
                    
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="Buscar producto...">
                    </div>

                    <div class="type-tabs">
                        <button class="type-tab active" data-type="all">Todo</button>
                        <button class="type-tab" data-type="ave">Aves</button>
                        <button class="type-tab" data-type="articulo">Artículos</button>
                    </div>

                    <button class="btn-filter-trigger" id="openFilterPanel">
                        <i class="fas fa-sliders-h"></i> Filtrar
                        <span id="filterCountBadge" class="filter-count-badge hidden">0</span>
                    </button>
                </div>

                <div id="activeFilters" class="active-filters-container">
                    </div>
            </div>

            <div id="productsGrid" class="products-grid">
                <div class="empty-results">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p style="margin-top:1rem">Cargando catálogo...</p>
                </div>
            </div>

        </div>
    </section>

    <div class="filter-overlay" id="filterOverlay"></div>
    <div class="filter-panel" id="filterPanel">
        <div class="panel-header">
            <h3 class="animated-text">
                <span class="word">Filtros</span>
                <span class="word lora-italic">y Orden</span>
            </h3>
            <button class="btn-close-panel" id="closeFilterPanel"><i class="fas fa-times"></i></button>
        </div>

        <div class="panel-body">

            <div class="filter-group">
                <span class="filter-group-title">Ordenar Por</span>
                <select id="sortSelect" class="form-control select">
                    <option value="reciente">Más Recientes</option>
                    <option value="precio_asc">Precio: Menor a Mayor</option>
                    <option value="precio_desc">Precio: Mayor a Menor</option>
                </select>
            </div>

            <div id="aveFiltersGroup">
                <div class="filter-group">
                    <span class="filter-group-title">Edad / Etapa</span>
                    <div class="radio-grid">
                        <label class="radio-option">
                            <input type="radio" name="edad" value="" checked>
                            <span class="radio-label">Todas</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="edad" value="gallo">
                            <span class="radio-label">Gallo</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="edad" value="gallina">
                            <span class="radio-label">Gallina</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="edad" value="pollo">
                            <span class="radio-label">Pollo</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="edad" value="polla">
                            <span class="radio-label">Polla</span>
                        </label>
                    </div>
                </div>

                <div class="filter-group">
                    <span class="filter-group-title">Propósito</span>
                    <div class="radio-grid">
                        <label class="radio-option">
                            <input type="radio" name="proposito" value="" checked>
                            <span class="radio-label">Todos</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="proposito" value="combate">
                            <span class="radio-label">Combate</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="proposito" value="cria">
                            <span class="radio-label">Cría</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="filter-group">
                <span class="filter-group-title">Disponibilidad</span>
                <div class="radio-grid">
                    <label class="radio-option">
                        <input type="radio" name="estado" value="disponible" checked>
                        <span class="radio-label">Disponibles</span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="estado" value="">
                        <span class="radio-label">Ver Todo</span>
                    </label>
                </div>
            </div>

        </div>

        <div class="panel-footer">
            <button class="btn-clear" id="clearFiltersBtn">Limpiar</button>
            <button class="btn-apply" id="applyFiltersBtn">Aplicar Filtros</button>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- DATOS INYECTADOS DESDE PHP ---
            const allProducts = <?php echo json_encode($productosDb); ?>;

            // --- ESTADO DE LA APLICACIÓN ---
            let currentType = 'all'; // all, ave, articulo
            let searchText = '';

            // Filtros Panel
            let sortBy = 'reciente';
            let filterEdad = '';
            let filterProposito = '';
            let filterEstado = 'disponible'; // Por defecto solo disponibles

            // --- REFERENCIAS DOM ---
            const grid = document.getElementById('productsGrid');
            const searchInput = document.getElementById('searchInput');
            const tabs = document.querySelectorAll('.type-tab');
            const activeFiltersContainer = document.getElementById('activeFilters');

            // Panel Refs
            const overlay = document.getElementById('filterOverlay');
            const panel = document.getElementById('filterPanel');
            const btnOpen = document.getElementById('openFilterPanel');
            const btnClose = document.getElementById('closeFilterPanel');
            const btnApply = document.getElementById('applyFiltersBtn');
            const btnClear = document.getElementById('clearFiltersBtn');
            const filterBadge = document.getElementById('filterCountBadge');
            const aveFiltersGroup = document.getElementById('aveFiltersGroup');

            // --- 1. INICIALIZACIÓN ---
            renderProducts();

            // --- 2. EVENTOS PRINCIPALES ---

            // Cambio de Tab
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    currentType = tab.dataset.type;

                    // Lógica Inteligente del Panel
                    if (currentType === 'articulo') {
                        aveFiltersGroup.classList.add('hidden-section');
                        // Resetear filtros de ave silenciosamente
                        setRadioValue('edad', '');
                        setRadioValue('proposito', '');
                    } else {
                        aveFiltersGroup.classList.remove('hidden-section');
                    }

                    renderProducts();
                    updateChips();
                });
            });

            // Buscador (Debounce básico)
            let debounceTimer;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    searchText = e.target.value.toLowerCase();
                    renderProducts();
                }, 300);
            });

            // --- 3. LÓGICA DEL PANEL DE FILTROS ---

            function openPanel() {
                overlay.classList.add('active');
                panel.classList.add('active');
                document.body.style.overflow = 'hidden'; // Evitar scroll
            }

            function closePanel() {
                overlay.classList.remove('active');
                panel.classList.remove('active');
                document.body.style.overflow = '';
            }

            btnOpen.addEventListener('click', openPanel);
            btnClose.addEventListener('click', closePanel);
            overlay.addEventListener('click', closePanel);

            // Aplicar Filtros
            btnApply.addEventListener('click', () => {
                // Leer valores del panel
                sortBy = document.getElementById('sortSelect').value;
                filterEdad = getRadioValue('edad');
                filterProposito = getRadioValue('proposito');
                filterEstado = getRadioValue('estado');

                renderProducts();
                updateChips();
                closePanel();
            });

            // Limpiar Filtros
            btnClear.addEventListener('click', () => {
                document.getElementById('sortSelect').value = 'reciente';
                setRadioValue('edad', '');
                setRadioValue('proposito', '');
                setRadioValue('estado', 'disponible'); // Reset a default seguro

                // Trigger apply
                btnApply.click();
            });

            // --- 4. RENDERIZADO ---

            function renderProducts() {
                // Filtrar
                let filtered = allProducts.filter(p => {
                    // 1. Filtro Tipo
                    if (currentType !== 'all' && p.tipo !== currentType) return false;

                    // 2. Filtro Búsqueda
                    if (searchText) {
                        const nombreMatch = p.nombre.toLowerCase().includes(searchText);
                        let anilloMatch = false;
                        if (p.tipo === 'ave' && p.anillo) {
                            anilloMatch = p.anillo.toLowerCase().includes(searchText);
                        }
                        if (!nombreMatch && !anilloMatch) return false;
                    }

                    // 3. Filtros Panel (Edad, Propósito, Estado)
                    if (p.tipo === 'ave') {
                        if (filterEdad && (!p.edad || p.edad.toLowerCase() !== filterEdad.toLowerCase())) return false;
                        if (filterProposito && (!p.proposito || p.proposito.toLowerCase() !== filterProposito.toLowerCase())) return false;
                        
                        // Failsafe: Si viene vacío, asumimos disponible
                        const estadoReal = p.estado_venta || 'disponible';
                        if (filterEstado && estadoReal.toLowerCase() !== filterEstado.toLowerCase()) return false;
                    } else {
                        // Filtro Estado para Artículos (stock)
                        if (filterEstado === 'disponible' && p.stock <= 0) return false;
                    }

                    return true;
                });

                // Ordenar
                filtered.sort((a, b) => {
                    if (sortBy === 'precio_asc') return a.precio - b.precio;
                    if (sortBy === 'precio_desc') return b.precio - a.precio;
                    // Reciente (ID más alto)
                    return b.id - a.id;
                });

                // HTML
                if (filtered.length === 0) {
                    grid.innerHTML = `<div class="empty-results"><i class="fas fa-search"></i><p>No hay resultados.</p></div>`;
                } else {
                    grid.innerHTML = filtered.map(p => createCard(p)).join('');
                }
            }

            function createCard(p) {
                const img = p.portada || 'assets/images/placeholder.jpg';
                const isAve = p.tipo === 'ave';

                let badges = '';
                if (isAve) {
                    badges += `<span class="badge badge-ave">Ave</span>`;
                    if (p.estado_venta && p.estado_venta !== 'disponible') {
                        badges += `<span class="badge badge-status status-${p.estado_venta}">${p.estado_venta}</span>`;
                    }
                } else {
                    badges += `<span class="badge badge-articulo">Artículo</span>`;
                }

                let meta = '';
                if (isAve) {
                    const edadText = p.edad || 'N/A';
                    const propositoText = p.proposito || 'N/A';
                    meta = `<span class="meta-tag">${edadText} / ${propositoText}</span>`;
                } else {
                    meta = `<span class="meta-tag">Stock: ${p.stock}</span>`;
                }

                return `
                    <a href="producto.php?id=${p.id}" class="product-card fade-up-animation">
                        <div class="card-image-wrapper">
                            <img src="${img}" class="card-image" loading="lazy">
                            <div class="card-badges">${badges}</div>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title">${p.nombre}</h3>
                            <div class="card-meta">${meta}</div>
                            <div class="card-footer-price">
                                <span class="price">$${formatPrice(p.precio)}</span>
                                <div class="btn-add-mini"><i class="fas fa-arrow-right"></i></div>
                            </div>
                        </div>
                    </a>
                `;
            }

            // --- 5. CHIPS Y UI HELPERS ---

            function updateChips() {
                activeFiltersContainer.innerHTML = '';
                let count = 0;

                // Helper para crear chip
                const addChip = (text, callback) => {
                    const chip = document.createElement('div');
                    chip.className = 'filter-chip';
                    chip.innerHTML = `<span>${text}</span> <i class="fas fa-times"></i>`;
                    chip.querySelector('i').addEventListener('click', callback);
                    activeFiltersContainer.appendChild(chip);
                    count++;
                };

                if (filterEdad) addChip(`Edad: ${filterEdad}`, () => {
                    setRadioValue('edad', '');
                    btnApply.click();
                });
                
                if (filterProposito) addChip(`Propósito: ${filterProposito}`, () => {
                    setRadioValue('proposito', '');
                    btnApply.click();
                });

                if (filterEstado === '') addChip(`Ver Agotados`, () => {
                    setRadioValue('estado', 'disponible');
                    btnApply.click();
                });

                if (sortBy !== 'reciente') {
                    const sortText = sortBy === 'precio_asc' ? 'Precio: Menor' : 'Precio: Mayor';
                    addChip(sortText, () => {
                        document.getElementById('sortSelect').value = 'reciente';
                        btnApply.click();
                    });
                }

                // Actualizar Badge
                if (count > 0) {
                    filterBadge.textContent = count;
                    filterBadge.classList.remove('hidden');
                } else {
                    filterBadge.classList.add('hidden');
                }
            }

            // Helpers Inputs
            function getRadioValue(name) {
                const checked = document.querySelector(`input[name="${name}"]:checked`);
                return checked ? checked.value : '';
            }

            function setRadioValue(name, val) {
                const radios = document.querySelectorAll(`input[name="${name}"]`);
                radios.forEach(r => {
                    r.checked = (r.value === val);
                });
            }

            function formatPrice(p) {
                return new Intl.NumberFormat('es-MX', {
                    minimumFractionDigits: 2
                }).format(p);
            }
        });
    </script>
</body>
</html>