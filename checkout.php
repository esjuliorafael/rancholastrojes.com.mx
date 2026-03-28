<?php
include_once 'config/database.php';
include_once 'models/Configuracion.php';

$database = new Database();
$db = $database->getConnection();

// 1. Traer el logo
$configObj = new Configuracion($db);
$logo_actual = $configObj->obtenerPorClave('sistema_logo');

// 2. Traer TODA la configuración como un arreglo clave => valor
$queryConfig = "SELECT clave, valor FROM configuracion";
$stmtConfig = $db->prepare($queryConfig);
$stmtConfig->execute();
$configuracionDb = [];
while ($row = $stmtConfig->fetch(PDO::FETCH_ASSOC)) {
    $configuracionDb[$row['clave']] = $row['valor'];
}

// 3. Traer las zonas de envío
$queryZonas = "SELECT id, estado, tipo_zona FROM zonas_envio ORDER BY estado ASC";
$stmtZonas = $db->prepare($queryZonas);
$stmtZonas->execute();
$zonasDb = $stmtZonas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - Rancho Las Trojes</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Lora:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/forms.css">
    <link id="darkModeStylesheet" rel="stylesheet" href="assets/css/dark-mode.css" disabled>

    <style>
        /* Estilos específicos del Layout de Checkout */
        .checkout-section {
            background: var(--white);
            padding: 3rem 0;
            margin-top: 10rem;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 3rem;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Títulos */
        .checkout-form-col h2,
        .order-summary-col h2 {
            font-size: 2.5em;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--black-blue);
        }

        @media (max-width: 512px) {
            .checkout-form-col h2,
            .order-summary-col h2 {
                font-size: 1.75em;
            }
        }

        /* Lista Carrito en Resumen */
        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid var(--divider);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-info h4 {
            margin: 0 0 0.25rem 0;
            color: var(--black-blue);
            font-size: 1rem;
        }

        .item-info span {
            font-size: 0.85rem;
            color: var(--text-color);
        }

        .item-price {
            font-weight: 600;
            color: var(--brown);
        }

        .btn-remove {
            color: #ef4444;
            background: none;
            border: none;
            cursor: pointer;
            margin-left: 1rem;
            font-size: 1rem;
            transition: transform 0.2s;
        }

        .btn-remove:hover {
            transform: scale(1.1);
        }

        /* Alertas Informativas */
        .alert-info {
            background: rgba(33, 150, 243, 0.1);
            color: #0d47a1;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            line-height: 1.5;
            border-left: 4px solid #2196f3;
        }

        /* Caja de Resumen de Costos */
        .order-summary {
            background: var(--off-white-light);
            padding: 1.5rem;
            border-radius: 1rem;
            margin-top: 2rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            color: var(--text-color);
        }

        .summary-row.total {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--black-blue);
            border-top: 1px solid var(--divider);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        /* Botón Checkout */
        .btn-checkout {
            width: 100%;
            background: var(--brown);
            color: var(--white);
            padding: 1rem;
            border: none;
            border-radius: 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-checkout:hover {
            background: var(--black-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-checkout:disabled {
            background: var(--divider);
            cursor: not-allowed;
            transform: none;
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <section class="checkout-section container-wide">
        <div class="container">
            <div class="checkout-grid">

                <div class="checkout-form-col">
                    <h2 class="animated-text">
                        <span class="word">Datos</span>
                        <span class="word lora-italic">de</span>
                        <span class="word lora-italic">Envío</span>
                    </h2>

                    <form id="checkoutForm" class="fade-up-animation" onsubmit="event.preventDefault(); processOrder();">
                        <div class="form-group">
                            <label class="form-label">Nombre Completo</label>
                            <input type="text" id="nombreCliente" class="form-control" required placeholder="Tu nombre completo">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono (WhatsApp)</label>
                            <input type="tel" id="telCliente" class="form-control" required placeholder="Para enviarte el seguimiento">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Estado de Destino</label>
                            <select id="estadoSelect" class="form-control select" required onchange="calculateShipping()">
                                <option value="">Selecciona tu estado...</option>
                            </select>
                        </div>

                        <div id="addressSection" class="hidden">
                            <div class="alert-info">
                                <i class="fas fa-box"></i> Tienes artículos físicos en tu carrito. Necesitamos tu dirección completa.
                            </div>
                            <div class="form-group">
                                <label class="form-label">Dirección Completa</label>
                                <input type="text" id="direccionInput" class="form-control" placeholder="Calle, Número, Col, CP, Ciudad">
                            </div>
                        </div>

                        <div id="airportSection" class="hidden">
                            <div class="alert-info">
                                <i class="fas fa-feather"></i> Tienes aves en tu carrito. El envío se realiza al aeropuerto o terminal más cercano a tu estado. Nos pondremos en contacto para coordinar la entrega.
                            </div>
                        </div>

                        <button type="submit" class="btn-checkout" id="btnPlaceOrder">
                            <i class="fab fa-whatsapp"></i> Finalizar Pedido
                        </button>
                    </form>
                </div>

                <div class="order-summary-col">
                    <h2 class="animated-text">
                        <span class="word">Resumen</span>
                        <span class="word lora-italic">del</span>
                        <span class="word lora-italic">Pedido</span>
                    </h2>

                    <div id="cartItemsContainer" class="fade-up-animation">
                        </div>

                    <div class="order-summary fade-up-animation">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span id="summarySubtotal">$0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Envío Estimado</span>
                            <span id="summaryShipping">Calculando...</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span id="summaryTotal">$0.00</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    
    <script>
        // --- DATOS INYECTADOS DESDE PHP ---
        const appConfig = <?php echo json_encode($configuracionDb); ?>;
        const shippingZones = <?php echo json_encode($zonasDb); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            // 1. Llenar selector dinámico de la BD
            const stateSelect = document.getElementById('estadoSelect');
            if (stateSelect && shippingZones.length > 0) {
                shippingZones.forEach(zona => {
                    const option = document.createElement('option');
                    option.value = zona.estado;
                    option.dataset.zoneType = zona.tipo_zona;
                    option.textContent = zona.estado;
                    stateSelect.appendChild(option);
                });
            }
            // 2. Pintar carrito
            renderCheckout();
        });

        function renderCheckout() {
            const container = document.getElementById('cartItemsContainer');
            if (!container) return;

            if (cart.length === 0) {
                container.innerHTML = '<div class="alert-info">Tu carrito está vacío.</div>';
                document.getElementById('btnPlaceOrder').disabled = true;
                document.getElementById('summarySubtotal').textContent = "$0.00";
                document.getElementById('summaryShipping').textContent = "$0.00";
                document.getElementById('summaryTotal').textContent = "$0.00";
                return;
            }

            container.innerHTML = cart.map((item, index) => `
                <div class="cart-item">
                    <div class="item-info">
                        <h4>${item.nombre}</h4>
                        <span>${item.tipo === 'ave' ? 'Ave única' : 'Cantidad: ' + item.cantidad}</span>
                    </div>
                    <div style="display: flex; align-items: center;">
                        <div class="item-price">$${formatPrice(item.precio * item.cantidad)}</div>
                        <button type="button" class="btn-remove" onclick="removeCheckoutItem(${index})" title="Quitar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');

            calculateShipping();
        }

        function removeCheckoutItem(index) {
            cart.splice(index, 1);
            localStorage.setItem('rlt_cart', JSON.stringify(cart));
            updateCartUI(); // Función de main.js
            renderMiniCartContents(); // Función de main.js
            renderCheckout();
        }

        function calculateShipping() {
            let countAves = 0;
            let hasArticulos = false;
            let subtotal = 0;

            cart.forEach(item => {
                subtotal += (item.precio * item.cantidad);
                if (item.tipo === 'ave') {
                    countAves += item.cantidad;
                } else {
                    hasArticulos = true;
                }
            });

            // Actualizar UI
            const addressSection = document.getElementById('addressSection');
            const airportSection = document.getElementById('airportSection');
            const direccionInput = document.getElementById('direccionInput');

            if (hasArticulos) {
                addressSection.classList.remove('hidden');
                direccionInput.required = true;
            } else {
                addressSection.classList.add('hidden');
                direccionInput.required = false;
            }

            if (countAves > 0) {
                airportSection.classList.remove('hidden');
            } else {
                airportSection.classList.add('hidden');
            }

            // Cálculos matemáticos
            const stateSelect = document.getElementById('estadoSelect');
            const selectedOption = stateSelect.options[stateSelect.selectedIndex];
            const zoneType = selectedOption && selectedOption.value !== "" ? selectedOption.dataset.zoneType : null;

            let shippingAves = 0;
            let shippingArticulos = 0;

            if (zoneType) {
                // Regla 1: Cada ave multiplica el costo de su zona
                if (countAves > 0) {
                    if (parseInt(appConfig.envio_gratis_aves) === 1) {
                        shippingAves = 0;
                    } else {
                        const costPerAve = zoneType === 'extendida' 
                            ? parseFloat(appConfig.envio_costo_extendida) 
                            : parseFloat(appConfig.envio_costo_normal);
                        shippingAves = costPerAve * countAves; 
                    }
                }

                // Regla 2: Los artículos cobran una única tarifa base
                if (hasArticulos) {
                    if (parseInt(appConfig.envio_gratis_articulos) === 1) {
                        shippingArticulos = 0;
                    } else {
                        shippingArticulos = parseFloat(appConfig.envio_costo_base_articulos);
                    }
                }
            }

            const totalShipping = zoneType ? (shippingAves + shippingArticulos) : 0;
            const total = subtotal + totalShipping;

            // Escribir al DOM
            document.getElementById('summarySubtotal').textContent = '$' + formatPrice(subtotal);
            
            if (zoneType) {
                document.getElementById('summaryShipping').textContent = totalShipping === 0 ? 'Gratis' : '$' + formatPrice(totalShipping);
            } else {
                document.getElementById('summaryShipping').textContent = 'Selecciona estado...';
            }
            
            document.getElementById('summaryTotal').textContent = '$' + formatPrice(total);
            document.getElementById('btnPlaceOrder').disabled = !zoneType;
        }

        async function processOrder() {
            if (cart.length === 0) return;

            const btn = document.getElementById('btnPlaceOrder');
            const formContainer = document.querySelector('.checkout-form-col');
            const form = document.getElementById('checkoutForm');

            // Validación nativa
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Bloquear UI
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando Orden...';

            const nombre = document.getElementById('nombreCliente').value;
            const tel = document.getElementById('telCliente').value;
            const estado = document.getElementById('estadoSelect').value;
            const direccionInput = document.getElementById('direccionInput');

            const hasArt = cart.some(i => i.tipo === 'articulo');
            const direccionFinal = hasArt ? (direccionInput ? direccionInput.value : '') : `El envío se realizará al aeropuerto o terminal más cercana al estado de ${estado}.`;

            const orderData = {
                cliente: { nombre, telefono: tel, direccion: direccionFinal, estado },
                carrito: cart
            };

            try {
                const response = await fetch('api/checkout.php?accion=crear_orden', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(orderData)
                });
                const res = await response.json();

                if (res.success) {
                    // Limpieza Local
                    localStorage.removeItem('rlt_cart');
                    cart = [];
                    if (typeof updateCartUI === 'function') updateCartUI();
                    if (typeof renderMiniCartContents === 'function') renderMiniCartContents();

                    window.scrollTo({ top: 0, behavior: 'smooth' });

                    // Inyección de Vista de Éxito
                    formContainer.innerHTML = `
                        <div class="checkout-feedback feedback-success">
                            <i class="fas fa-check-circle feedback-icon" style="color: #10b981; font-size: 3rem; margin-bottom: 1rem;"></i>
                            <h2 class="feedback-title" style="font-size: 2rem; color: #1f2937;">¡Gracias, ${nombre.split(' ')[0]}!</h2>
                            <p class="feedback-text" style="color: #4b5563; margin-bottom: 1.5rem;">
                                Tu orden <strong style="color: #8b5e3c;">#${res.orden_id}</strong> ha sido registrada correctamente.<br>
                                Estamos abriendo WhatsApp para confirmar tu pago y envío.
                            </p>
                            <a href="${res.whatsapp_link}" target="_blank" class="btn-checkout" style="text-decoration: none;">
                                <i class="fab fa-whatsapp"></i> Enviar Mensaje Ahora
                            </a>
                            <div style="margin-top: 1rem; color: #9ca3af; font-size: 0.9rem; text-align: center;">
                                <i class="fas fa-circle-notch fa-spin"></i> Redirigiendo en <strong id="countdown">3</strong> segundos...
                            </div>
                        </div>
                    `;

                    let seconds = 3;
                    const countSpan = document.getElementById('countdown');
                    const timer = setInterval(() => {
                        seconds--;
                        if(countSpan) countSpan.textContent = seconds;
                        if (seconds <= 0) {
                            clearInterval(timer);
                            window.open(res.whatsapp_link, '_blank');
                            setTimeout(() => { window.location.href = 'index.php'; }, 1000);
                        }
                    }, 1000);

                } else {
                    // Feedback de Error
                    let errorBox = document.getElementById('checkoutErrorBox');
                    if (!errorBox) {
                        errorBox = document.createElement('div');
                        errorBox.id = 'checkoutErrorBox';
                        errorBox.className = 'alert-info';
                        errorBox.style.background = '#fee2e2';
                        errorBox.style.color = '#991b1b';
                        errorBox.style.borderLeft = '4px solid #ef4444';
                        errorBox.style.marginTop = '1rem';
                        form.insertBefore(errorBox, btn);
                    }
                    errorBox.innerHTML = `<div style="display:flex; gap:10px; align-items:center;"><i class="fas fa-exclamation-triangle" style="font-size:1.5rem;"></i><div><strong>No pudimos procesar tu pedido</strong><br>${res.message}</div></div>`;
                    
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fab fa-whatsapp"></i> Intentar Nuevamente';
                    errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } catch (e) {
                console.error(e);
                alert("Error de conexión. Verifica tu internet.");
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-whatsapp"></i> Finalizar Pedido';
            }
        }

        function formatPrice(p) {
            return new Intl.NumberFormat('es-MX', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(p);
        }
    </script>
</body>
</html>