{{--
    Item 6 (docs/payment-links/QA.md, hallazgo ALTO): navigator.clipboard NO
    existe fuera de un contexto seguro (https o localhost) — en local
    (http://lima-tour.test) Filament's ->copyable() rompe en silencio
    (Alpine Expression Error) y no hay ningún respaldo.

    Delegado en `document` (una sola vez, para todo el panel) en vez de un
    listener por elemento: cualquier elemento con `data-copy-text="..."`
    (celda de la tabla "Enlace", acción de fila "Copiar enlace", botón junto
    al campo "Enlace para el cliente" del formulario) queda copiable sin
    tocar Livewire — el copy corre de forma SÍNCRONA dentro del propio
    evento de clic, que es un requisito de navigator.clipboard/execCommand
    (fuera de un gesto de usuario síncrono, el navegador los rechaza).
    Mismo patrón de fallback ya usado en resources/views/checkout.blade.php
    (botón "Copiar referencia").
--}}
<script>
(function () {
    'use strict';

    function showToast(message) {
        var toast = document.getElementById('lvt-copy-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'lvt-copy-toast';
            toast.setAttribute('role', 'status');
            toast.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;'
                + 'background:#15474B;color:#fff;padding:10px 16px;border-radius:8px;'
                + 'font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.2);opacity:0;'
                + 'transition:opacity .2s;pointer-events:none;';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.style.opacity = '1';
        window.clearTimeout(toast.__lvtHideTimeout);
        toast.__lvtHideTimeout = window.setTimeout(function () {
            toast.style.opacity = '0';
        }, 2000);
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-copy-text]');
        if (!trigger) {
            return;
        }

        var text = trigger.getAttribute('data-copy-text');
        if (!text) {
            return;
        }

        // FIX-3 (docs/payment-links/FIX-2.md): si el disparador quedó
        // dentro de un <a href> (link de fila por defecto de Filament u
        // otro uso futuro), copiar NO debe dejar que el navegador también
        // navegue. La columna "Enlace" de payment-links ya lleva
        // ->disabledClick() para no generar ese <a>, pero este guard queda
        // como respaldo para cualquier otro data-copy-text que sí esté
        // envuelto en un link.
        var anchor = event.target.closest('a[href]');
        if (anchor) {
            event.preventDefault();
            event.stopPropagation();
        }

        try {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }

            showToast(trigger.getAttribute('data-copy-message') || 'Enlace copiado');
            trigger.dispatchEvent(new CustomEvent('lvt-copied', { bubbles: true }));
        } catch (e) {
            // El texto sigue seleccionable a mano (readOnly + onclick="this.select()"
            // en el campo del formulario) si todo lo demás falla.
        }
    });
})();
</script>
