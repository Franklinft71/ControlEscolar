/**
 * ControlEscolar - App.js
 * Script para manejar micro-animaciones, efectos visuales y funciones globales.
 */

document.addEventListener('DOMContentLoaded', () => {
    
    /* ====================================================
       1. Animación de Entrada (Fade-In) del Contenido
       ==================================================== */
    const mainContent = document.querySelector('.flex-grow-1');
    if (mainContent) {
        // Establecemos el estado inicial invisible y un poco más abajo
        mainContent.style.opacity = '0';
        mainContent.style.transform = 'translateY(15px)';
        mainContent.style.transition = 'opacity 0.6s ease-out, transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1)';
        
        // Disparamos la animación en el siguiente frame
        requestAnimationFrame(() => {
            mainContent.style.opacity = '1';
            mainContent.style.transform = 'translateY(0)';
        });
    }

    /* ====================================================
       2. Animación Escalonada (Stagger) para las Tarjetas
       ==================================================== */
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(25px)';
        
        // Animamos cada tarjeta con un ligero retraso multiplicando el índice
        setTimeout(() => {
            card.style.transition = 'all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 + (index * 80));
    });

    /* ====================================================
       3. Micro-interacción: Escáner RFID Virtual
       ==================================================== */
    const rfidInput = document.getElementById('rfidInput');
    const scannerBox = document.querySelector('.scanner-box');
    
    if (rfidInput && scannerBox) {
        // Al teclear, simulamos que el hardware está leyendo con una luz azul
        rfidInput.addEventListener('input', () => {
            if (!scannerBox.classList.contains('scanning')) {
                scannerBox.classList.add('scanning');
            }
            
            // Remover la luz después de medio segundo de inactividad
            clearTimeout(window.scanTimeout);
            window.scanTimeout = setTimeout(() => {
                scannerBox.classList.remove('scanning');
            }, 400);
        });
    }

    /* ====================================================
       4. Mejoras Globales de Alertas (SweetAlert Premium)
       ==================================================== */
    if (typeof Swal !== 'undefined') {
        
        // Agregar soporte para notificaciones "Toast" en nuestra App Global
        // Son como notificaciones push que salen en la esquina superior derecha
        if (typeof App !== 'undefined') {
            App.toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                background: '#ffffff',
                iconColor: 'var(--bs-primary)',
                customClass: {
                    popup: 'shadow-lg rounded-3 border-0 mt-3 me-3'
                },
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
        }
    }
    
    /* ====================================================
       5. Tooltips de Bootstrap (Iniciación global)
       ==================================================== */
    // Buscar todos los elementos que tengan data-bs-toggle="tooltip" y activarlos
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
    if (typeof bootstrap !== 'undefined' && tooltipTriggerList.length > 0) {
        [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
    }
});