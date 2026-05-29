/**
 * Owl Carousel initializer — Lima View Tours
 *
 * Loop dinámico: se activa solo si el número de items supera
 * la cantidad visible en el breakpoint más grande configurado.
 */

function resolveLoop($el, maxItems) {
    const count = $el.find('.item').length;
    return count > maxItems;
}

function initOwl() {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.owlCarousel === 'undefined') {
        return setTimeout(initOwl, 80);
    }

    const $ = jQuery;

    // Tours wide (4 columnas en ≥1280) — máximo visible: 4
    $('[data-owl-tours-wide]').each(function () {
        const $el = $(this);
        const shouldLoop = resolveLoop($el, 4);
        $el.owlCarousel({
            loop: shouldLoop,
            margin: 20,
            nav: true,
            dots: true,
            autoplay: shouldLoop,
            autoplayHoverPause: true,
            autoplayTimeout: 5500,
            smartSpeed: 700,
            navText: [
                '<span aria-label="Anterior">‹</span>',
                '<span aria-label="Siguiente">›</span>',
            ],
            responsive: {
                0:    { items: 1, margin: 0 },
                640:  { items: 2 },
                1024: { items: 3 },
                1280: { items: 4 },
            },
        });
    });

    // Tours estándar (4 columnas en ≥1536) — máximo visible: 4
    $('[data-owl-tours]').each(function () {
        const $el = $(this);
        const shouldLoop = resolveLoop($el, 4);
        $el.owlCarousel({
            loop: shouldLoop,
            margin: 24,
            nav: true,
            dots: true,
            autoplay: shouldLoop,
            autoplayHoverPause: true,
            autoplayTimeout: 5500,
            smartSpeed: 700,
            navText: [
                '<span aria-label="Anterior">‹</span>',
                '<span aria-label="Siguiente">›</span>',
            ],
            responsive: {
                0: { items: 1, margin: 0 },
                640: { items: 2 },
                1024: { items: 3 },
                1536: { items: 4 },
            },
        });
    });

    // Experiencias (4 columnas en ≥1536) — máximo visible: 4
    $('[data-owl-experiences]').each(function () {
        const $el = $(this);
        const shouldLoop = resolveLoop($el, 4);
        $el.owlCarousel({
            loop: shouldLoop,
            margin: 24,
            nav: false,
            dots: true,
            autoplay: shouldLoop,
            autoplayTimeout: 6000,
            smartSpeed: 700,
            responsive: {
                0: { items: 1, margin: 0 },
                640: { items: 2 },
                1024: { items: 3 },
                1536: { items: 4 },
            },
        });
    });

    // Testimonios (4 columnas en ≥1280) — máximo visible: 4
    $('[data-owl-testimonials]').each(function () {
        const $el = $(this);
        const shouldLoop = resolveLoop($el, 4);
        $el.owlCarousel({
            loop: shouldLoop,
            margin: 20,
            nav: true,
            dots: true,
            autoplay: shouldLoop,
            autoplayTimeout: 6500,
            smartSpeed: 700,
            navText: [
                '<span aria-label="Anterior">‹</span>',
                '<span aria-label="Siguiente">›</span>',
            ],
            responsive: {
                0: { items: 1, margin: 0 },
                640: { items: 2 },
                1024: { items: 3 },
                1280: { items: 4 },
            },
        });
    });

    // Ofertas (3 columnas en ≥1024) — máximo visible: 3
    $('[data-owl-offers]').each(function () {
        const $el = $(this);
        const shouldLoop = resolveLoop($el, 3);
        $el.owlCarousel({
            loop: shouldLoop,
            margin: 24,
            nav: true,
            dots: true,
            autoplay: shouldLoop,
            autoplayTimeout: 6500,
            smartSpeed: 700,
            navText: [
                '<span aria-label="Anterior">‹</span>',
                '<span aria-label="Siguiente">›</span>',
            ],
            responsive: {
                0: { items: 1, margin: 0 },
                768: { items: 2 },
                1024: { items: 3 },
            },
        });
    });

    // Relacionados (4 columnas en ≥1536) — máximo visible: 4
    $('[data-owl-related]').each(function () {
        const $el = $(this);
        const shouldLoop = resolveLoop($el, 4);
        $el.owlCarousel({
            loop: shouldLoop,
            margin: 20,
            nav: false,
            dots: true,
            autoplay: false,
            smartSpeed: 600,
            responsive: {
                0: { items: 1, margin: 0 },
                640: { items: 2 },
                1024: { items: 3 },
                1536: { items: 4 },
            },
        });
    });

    // Galería (siempre 1 item, loop seguro con ≥2 items)
    $('[data-owl-gallery]').each(function () {
        const $el = $(this);
        const shouldLoop = resolveLoop($el, 1);
        $el.owlCarousel({
            loop: shouldLoop,
            margin: 12,
            nav: true,
            dots: false,
            items: 1,
            navText: [
                '<span aria-label="Anterior">‹</span>',
                '<span aria-label="Siguiente">›</span>',
            ],
        });
    });
}

document.addEventListener('DOMContentLoaded', initOwl);
