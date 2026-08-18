import './main.css';

// Lightweight interactivity for the customer web + admin panel.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-confirm]').forEach((el) => {
        el.addEventListener('submit', (e) => {
            if (!window.confirm(el.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
});
