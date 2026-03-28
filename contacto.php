<?php
include_once 'config/database.php';
include_once 'models/Configuracion.php';

$database = new Database();
$db = $database->getConnection();
$config = new Configuracion($db);
$logo_actual = $config->obtenerPorClave('sistema_logo');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - Rancho Las Trojes</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Lora:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link id="darkModeStylesheet" rel="stylesheet" href="assets/css/dark-mode.css" disabled>

    <style>
        /* Estilos Específicos de la Página de Contacto */
        .page-contact-start {
            background-color: var(--white);
            padding: 3rem 0;
            margin-top: 2rem;
        }

        .contact-content {
            display: flex;
            gap: 3rem;
        }

        @media (max-width: 1024px) {
            .contact-content {
                flex-direction: column;
            }
        }

        .contact-info,
        .contact-form {
            flex: 1;
        }

        .contact-info h2 {
            font-size: 2.5em;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--black-blue);
        }

        @media (max-width: 512px) {
            .contact-info h2 {
                font-size: 1.75em;
            }
        }

        .contact-info p {
            margin-bottom: 2rem;
        }

        .contact-info-row {
            display: flex;
            background-color: var(--off-white-light);
            border-radius: 1.25rem;
            padding: 2rem 1rem;
        }

        .contact-info-item {
            flex: 1;
            margin: 0;
        }

        .contact-info-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .icon-box {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--white);
            border-radius: 50%;
        }

        .icon-box i {
            color: var(--brown);
            font-size: 1.2rem;
        }

        .contact-info-title h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--black-blue);
        }

        .contact-info-body p {
            margin: 0;
            font-size: 1rem;
            color: var(--text-color);
        }

        .contact-info-body a {
            color: var(--brown);
            text-decoration: none;
            font-weight: 500;
        }

        .contact-info-body a:hover {
            color: var(--black-blue);
            text-decoration: underline;
        }

        .contact-divider {
            width: 1px;
            background-color: var(--divider);
            margin: 0 1rem;
        }

        .contact-form {
            background-color: var(--off-white-light);
            border-radius: 1.25rem;
            padding: 2.5rem;
        }

        @media (max-width: 512px) {
            .contact-form {
                padding: 2.5rem 1rem;
            }
        }

        .contact-form h2 {
            font-size: 2.5em;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--black-blue);
        }

        @media (max-width: 512px) {
            .contact-form h2 {
                font-size: 1.75em;
            }
        }

        .contact-form p {
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 1rem;
            font-weight: 500;
            color: var(--black-blue);
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            background-color: var(--white);
            border: none;
            border-radius: 0.5rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--brown);
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .btn-submit {
            background: var(--brown);
            color: var(--white);
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1em;
            font-weight: 600;
            transition: background-color 0.3s;
            width: 100%;
        }

        .btn-submit:hover {
            background-color: var(--black-blue);
        }

        .contact-selection {
            display: flex;
            gap: 2rem;
            margin-bottom: 1rem;
        }

        .contact-option {
            flex: 1;
            text-align: center;
            padding: 1rem;
            border: 1px solid var(--divider);
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .contact-option.selected {
            border-color: var(--brown);
            background-color: var(--white);
        }

        .contact-option:hover {
            border-color: var(--brown);
            background-color: var(--white);
        }

        .contact-option input {
            display: none;
        }

        .contact-option img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 0.5rem;
        }

        .contact-option h4 {
            margin: 0.5rem 0;
            color: var(--black-blue);
        }

        .contact-option p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--text-color);
        }

        .form-row {
            display: flex;
            gap: 2rem;
            margin-bottom: 1rem;
        }

        .form-col {
            flex: 1;
        }

        @media (max-width: 768px) {
            .contact-info-row {
                flex-direction: column;
            }
            .contact-divider {
                width: 100%;
                height: 1px;
                margin: 1rem 0;
            }
            .contact-selection {
                flex-direction: column;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <section class="page-header-start container-wide ">
        <img src="assets/images/42c08f60-d5b7-4aec-87cd-632c3a0ed6a6.jpeg" alt="Fondo Contacto">
        <div class="page-header-overlay">
            <h1 class="page-header-title animated-text">
                <span class="word">Contacto</span>
            </h1>
            <p class="page-header-subtitle fade-up-animation">Inicio / Contacto</p>
        </div>
    </section>

    <section class="page-contact-start container-wide">
        <div class="container">
            <div class="contact-content">
                <div class="contact-info">
                    <h2 class="animated-text">
                        <span class="word">Estamos</span>
                        <span class="word lora-italic">para</span>
                        <span class="word lora-italic">servirte</span>
                    </h2>
                    <p class="fade-up-animation">Si tienes dudas o quieres más información sobre nuestros gallos y servicios, mándanos un WhatsApp o llámanos. ¡Con gusto te atenderemos!</p>
                    <div class="contact-info-row fade-up-animation">
                        <div class="contact-info-item">
                            <div class="contact-info-header">
                                <div class="icon-box">
                                    <i class="fa-brands fa-whatsapp"></i>
                                </div>
                                <div class="contact-info-title">
                                    <h3>Ricardo Torres</h3>
                                </div>
                            </div>
                            <div class="contact-info-body">
                                <p>Gallos para combate</p>
                                <p><a href="tel:+524432020019">+(52) 443 202 0019</a></p>
                            </div>
                        </div>
                        <div class="contact-divider"></div>
                        <div class="contact-info-item">
                            <div class="contact-info-header">
                                <div class="icon-box">
                                    <i class="fa-brands fa-whatsapp"></i>
                                </div>
                                <div class="contact-info-title">
                                    <h3>Juan Luis Peña</h3>
                                </div>
                            </div>
                            <div class="contact-info-body">
                                <p>Gallos para cría</p>
                                <p><a href="tel:+524433953204">+(52) 443 395 3204</a></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="contact-form fade-up-animation">
                    <h2 class="animated-text">
                        <span class="word">Envíanos</span>
                        <span class="word lora-italic">un</span>
                        <span class="word lora-italic">mensaje</span>
                    </h2>
                    <p>Completa el formulario y te responderemos lo antes posible.</p>
                    <form id="whatsappForm">
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="name">Nombre completo</label>
                                    <input type="text" id="name" class="form-control" placeholder="Tu nombre completo" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="phone">Teléfono (opcional)</label>
                                    <input type="tel" id="phone" class="form-control" placeholder="Tu número de teléfono">
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="location">¿De dónde nos escribes?</label>
                                    <input type="text" id="location" class="form-control" placeholder="Tu ciudad o estado" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="tipo-consulta">Tipo de consulta</label>
                                    <select id="tipo-consulta" class="form-control" required>
                                        <option value="">Selecciona una opción</option>
                                        <option value="venta">Gallos para combate</option>
                                        <option value="cria">Gallos para cría</option>
                                        <option value="envios">Información de envíos</option>
                                        <option value="precios">Precios</option>
                                        <option value="visita">Visitar el rancho</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Selecciona con quién deseas comunicarte</label>
                            <div class="contact-selection">
                                <label class="contact-option selected" id="ricardo-option">
                                    <input type="radio" name="contact" value="ricardo" checked>
                                    <img src="https://placehold.co/60x60?text=RT" alt="Ricardo Torres">
                                    <h4>Ricardo Torres</h4>
                                    <p>Gallos para combate</p>
                                </label>
                                <label class="contact-option" id="juan-option">
                                    <input type="radio" name="contact" value="juan">
                                    <img src="https://placehold.co/60x60?text=JLP" alt="Juan Luis Peña">
                                    <h4>Juan Luis Peña</h4>
                                    <p>Gallos para cría</p>
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="message">Mensaje</label>
                            <textarea id="message" class="form-control" placeholder="Escribe tu mensaje aquí..." required></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Enviar por WhatsApp</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Lógica para selección visual de Contacto (Ricardo/Juan)
            const ricardoOption = document.getElementById('ricardo-option');
            const juanOption = document.getElementById('juan-option');

            function selectContact(element) {
                document.querySelectorAll('.contact-option').forEach(option => {
                    option.classList.remove('selected');
                });
                element.classList.add('selected');
                const radio = element.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            }

            if (ricardoOption) {
                ricardoOption.addEventListener('click', function() { selectContact(this); });
            }
            if (juanOption) {
                juanOption.addEventListener('click', function() { selectContact(this); });
            }

            // 2. Lógica de Envío a WhatsApp
            const whatsappForm = document.getElementById('whatsappForm');
            if (whatsappForm) {
                whatsappForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const nameInput = document.getElementById('name');
                    const phoneInput = document.getElementById('phone');
                    const locationInput = document.getElementById('location');
                    const tipoConsultaInput = document.getElementById('tipo-consulta');
                    const messageInput = document.getElementById('message');

                    if (!nameInput || !locationInput || !tipoConsultaInput || !messageInput) return;

                    const name = nameInput.value;
                    const phone = phoneInput.value;
                    const location = locationInput.value;
                    const tipoConsulta = tipoConsultaInput.value;
                    const contactRadio = document.querySelector('input[name="contact"]:checked');
                    const contact = contactRadio ? contactRadio.value : 'ricardo';

                    const message = messageInput.value;
                    const ricardoNumber = "524432020019";
                    const juanNumber = "524433953204";

                    let phoneNumber = ricardoNumber;
                    let contactName = "Ricardo Torres";
                    if (contact === 'juan') {
                        phoneNumber = juanNumber;
                        contactName = "Juan Luis Peña";
                    }

                    const tipoConsultaMap = {
                        'venta': 'Gallos para combate',
                        'cria': 'Gallos para cría',
                        'envios': 'Información de envíos',
                        'precios': 'Precios',
                        'visita': 'Visitar el rancho',
                        'otro': 'Otra consulta'
                    };

                    let whatsappMessage = `Hola ${contactName}, soy ${name}`;
                    if (location) {
                        whatsappMessage += ` de ${location}`;
                    }
                    const consultaLabel = tipoConsultaMap[tipoConsulta] || 'una consulta';
                    whatsappMessage += `. Necesito información sobre ${consultaLabel}.%0A%0A${encodeURIComponent(message)}`;
                    if (phone) {
                        whatsappMessage += `%0A%0AMi teléfono es: ${phone}`;
                    }

                    const whatsappURL = `https://wa.me/${phoneNumber}?text=${whatsappMessage}`;
                    window.open(whatsappURL, '_blank');
                });
            }
        });
    </script>
</body>
</html>