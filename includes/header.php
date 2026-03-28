<header>
    <div class="container-wide">
        <div class="header-wrapper">
            
            <div class="logo-container">
                <div class="logo-circle"></div>
                <div class="logo">
                    <img src="<?php echo isset($logo_actual) ? $logo_actual : 'assets/images/logo.png'; ?>" alt="Rancho Las Trojes Logo">
                </div>
            </div>

            <div class="header-group">
                <div class="header-actions">
                    <a href="#" id="themeToggle" class="btn-icon" aria-label="Cambiar tema">
                        <i class="fa-solid fa-moon"></i>
                    </a>

                    <?php if (
                        basename($_SERVER['PHP_SELF']) == 'index.php' ||
                        basename($_SERVER['PHP_SELF']) == 'tienda.php' ||
                        basename($_SERVER['PHP_SELF']) == 'producto.php' ||
                        basename($_SERVER['PHP_SELF']) == 'checkout.php'
                    ): ?>
                        <div class="cart-dropdown-wrapper" style="position: relative;">
                            
                            <a href="javascript:void(0);" id="cartToggleBtn" class="btn-icon" style="position: relative;">
                                <i class="fa-solid fa-shopping-cart"></i>
                                <span id="cart-count" style="position: absolute; top: 0; right: 0; background: var(--brown); color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; display: none;">0</span>
                            </a>

                            <div id="miniCartOverlay" class="mini-cart-overlay"></div>

                            <div id="miniCart" class="mini-cart-container">
                                <div class="mini-cart-header">
                                    <h3>Tu Carrito</h3>
                                    <button id="closeMiniCart"><i class="fas fa-times"></i></button>
                                </div>
                                
                                <div id="miniCartItems" class="mini-cart-items">
                                    <div class="empty-msg">Cargando...</div>
                                </div>

                                <div class="mini-cart-footer">
                                    <div class="mini-subtotal">
                                        <span>Subtotal:</span>
                                        <strong id="miniCartTotal">$0.00</strong>
                                    </div>
                                    <a href="checkout.php" class="btn-checkout-mini">Proceder al Pago</a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    <a href="contacto.php" class="btn-text">
                        <i class="fa-solid fa-phone"></i><span>Contáctanos</span>
                    </a>
                </div>

                <div class="nav_hamburger" id="hamburgerButton">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </div>
            </div>

        </div>
    </div>
</header>

<div class="nav-menu-overlay" id="navMenuOverlay">
    <div class="nav-menu-content">
        <ul class="nav-menu-list">
            <li class="nav-menu-item"><a href="index.php" class="nav-menu-link">Inicio</a></li>
            <li class="nav-menu-item"><a href="galeria.php" class="nav-menu-link">Galería</a></li>
            <li class="nav-menu-item"><a href="tienda.php" class="nav-menu-link">Tienda</a></li>
            <li class="nav-menu-item"><a href="contacto.php" class="nav-menu-link">Contacto</a></li>
        </ul>
    </div>
</div>

<div id="mobileFloatingCart" class="mobile-floating-cart">
    <div class="floating-cart-icon">
        <i class="fa-solid fa-shopping-cart"></i>
        <span id="mobile-cart-count">0</span>
    </div>
    <span class="floating-text">Ver Carrito</span>
</div>