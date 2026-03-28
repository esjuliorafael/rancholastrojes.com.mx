/* =============================================
   MAIN JS - Rancho Las Trojes
   Carrito & Lógica de Checkout + Mini Cart + FAB
============================================= */

let cart = JSON.parse(localStorage.getItem('rlt_cart')) || [];

/* --- ACCIONES DE CARRITO --- */

function addToCart(id, type, name, price) {
    const existingIndex = cart.findIndex(i => i.id === id);

    if (existingIndex > -1) {
        if (type === 'ave') {
            alert("Esta ave ya está en tu carrito (Stock único).");
            return;
        } else {
            cart[existingIndex].cantidad++;
        }
    } else {
        cart.push({ id, tipo: type, nombre: name, precio: parseFloat(price), cantidad: 1 });
    }
    
    saveCart();
    updateCartUI();
    
    // Abrir Mini Cart automáticamente
    renderMiniCartContents();
    openMiniCart();
}

function buyNow(id, type, name, price) {
    const existingIndex = cart.findIndex(i => i.id === id);

    if (existingIndex > -1) {
        if (type === 'ave') {
            // Ya está
        }
    } else {
        cart.push({ id, tipo: type, nombre: name, precio: parseFloat(price), cantidad: 1 });
    }
    
    saveCart();
    updateCartUI();
    window.location.href = 'checkout.php';
}

function removeFromCart(index) {
    if(confirm("¿Eliminar este producto?")) {
        cart.splice(index, 1);
        saveCart();
        
        if (document.getElementById('cartItemsContainer')) {
            renderCheckout();
        } 
        
        updateCartUI();
        renderMiniCartContents();
    }
}

function saveCart() { localStorage.setItem('rlt_cart', JSON.stringify(cart)); }

function updateCartUI() {
    const totalItems = cart.reduce((sum, item) => sum + item.cantidad, 0);

    // 1. Badge del Header (Escritorio)
    const countBadge = document.getElementById('cart-count');
    if (countBadge) {
        countBadge.textContent = totalItems;
        countBadge.style.display = totalItems > 0 ? 'flex' : 'none';
    }

    // 2. Lógica del Botón Flotante Móvil (FAB)
    const mobileFab = document.getElementById('mobileFloatingCart');
    const mobileBadge = document.getElementById('mobile-cart-count');
    
    if (mobileFab && mobileBadge) {
        mobileBadge.textContent = totalItems;
        
        // Solo mostrar si hay items Y NO está oculto por el sheet
        if (totalItems > 0) {
            mobileFab.classList.add('visible'); 
        } else {
            mobileFab.classList.remove('visible');
        }
    }
}

/* --- MINI CART LOGIC (Dropdown / Bottom Sheet) --- */

function renderMiniCartContents() {
    const container = document.getElementById('miniCartItems');
    const totalEl = document.getElementById('miniCartTotal');
    
    if (!container) return;

    if (cart.length === 0) {
        container.innerHTML = '<div class="empty-msg"><i class="fas fa-shopping-basket" style="font-size: 2rem; color: #ddd; margin-bottom: 10px;"></i><br>Tu carrito está vacío</div>';
        if(totalEl) totalEl.textContent = '$0.00';
        return;
    }

    let html = '';
    let total = 0;

    cart.forEach((item, index) => {
        const itemTotal = item.precio * item.cantidad;
        total += itemTotal;

        html += `
            <div class="mini-cart-item">
                <div class="mini-item-info">
                    <h4>${item.nombre}</h4>
                    <div class="mini-item-details">
                        ${item.tipo === 'ave' ? 'Ave única' : 'Cant: ' + item.cantidad} 
                        <span class="mini-item-price">$${itemTotal.toFixed(2)}</span>
                    </div>
                </div>
                <button class="mini-remove-btn" onclick="removeFromCart(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
    });

    container.innerHTML = html;
    if(totalEl) totalEl.textContent = `$${total.toFixed(2)}`;
}

/**
 * ABRIR MINI CART
 * Agregamos lógica para ocultar el FAB cuando el sheet sube.
 */
function openMiniCart() {
    const miniCart = document.getElementById('miniCart');
    const overlay = document.getElementById('miniCartOverlay');
    const mobileFab = document.getElementById('mobileFloatingCart'); // Referencia al FAB

    if(miniCart) miniCart.classList.add('active');
    if(overlay) overlay.classList.add('active');

    // NUEVO: Ocultar el botón flotante para que no estorbe
    if(mobileFab) mobileFab.classList.add('hidden-by-sheet');
}

/**
 * CERRAR MINI CART
 * Agregamos lógica para volver a mostrar el FAB cuando el sheet baja.
 */
function closeMiniCartFn() {
    const miniCart = document.getElementById('miniCart');
    const overlay = document.getElementById('miniCartOverlay');
    const mobileFab = document.getElementById('mobileFloatingCart'); // Referencia al FAB

    if(miniCart) miniCart.classList.remove('active');
    if(overlay) overlay.classList.remove('active');

    // NUEVO: Volver a mostrar el botón flotante
    if(mobileFab) mobileFab.classList.remove('hidden-by-sheet');
}

// Inicialización de eventos
document.addEventListener('DOMContentLoaded', function() {
    updateCartUI();

    // Referencias
    const toggleBtn = document.getElementById('cartToggleBtn');
    const closeBtn = document.getElementById('closeMiniCart');
    const overlay = document.getElementById('miniCartOverlay');
    const miniCart = document.getElementById('miniCart');
    const mobileFab = document.getElementById('mobileFloatingCart');

    // Toggle al dar clic en el icono del header
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (miniCart && miniCart.classList.contains('active')) {
                closeMiniCartFn();
            } else {
                renderMiniCartContents();
                openMiniCart();
            }
        });
    }

    // Toggle al dar clic en el Botón Flotante Móvil
    if (mobileFab) {
        mobileFab.addEventListener('click', function() {
            renderMiniCartContents();
            openMiniCart();
        });
    }

    if (closeBtn) closeBtn.addEventListener('click', closeMiniCartFn);
    if (overlay) overlay.addEventListener('click', closeMiniCartFn);

    // Cerrar al dar clic fuera (Solo Desktop)
    document.addEventListener('click', function(e) {
        if (window.innerWidth > 768 && miniCart && miniCart.classList.contains('active')) {
            if (!miniCart.contains(e.target) && !toggleBtn.contains(e.target)) {
                closeMiniCartFn();
            }
        }
    });
});