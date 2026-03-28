<style>
    .accordion {
        margin-top: 2rem;
    }

    .accordion-item {
        border-radius: 8px;
        margin-bottom: 10px;
        overflow: hidden;
    }

    .accordion-header {
        background: var(--off-white-light);
        padding: 1rem;
        cursor: pointer;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .accordion-content {
        display: none;
        padding: 1rem;
        background: var(--white);
        color: var(--text-color);
        line-height: 1.6;
    }

    .accordion-header.active + .accordion-content {
        display: block;
    }
</style>

<div class="accordion">
    <div class="accordion-item fade-up-animation">
        <div class="accordion-header">
            ¿Cómo funcionan los envíos de aves?
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="accordion-content">
            Realizamos envíos aéreos a los aeropuertos principales. El costo depende de la zona (Normal o Extendida).
        </div>
    </div>

    <div class="accordion-item fade-up-animation">
        <div class="accordion-header">
            ¿Qué métodos de pago aceptan?
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="accordion-content">
            Aceptamos transferencia bancaria y depósitos en OXXO. Al finalizar tu pedido te enviaremos los datos por WhatsApp.
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.accordion-header').forEach(header => {
        header.addEventListener('click', () => {
            header.classList.toggle('active');
            const icon = header.querySelector('i');
            icon.classList.toggle('fa-chevron-up');
            icon.classList.toggle('fa-chevron-down');
        });
    });
</script>