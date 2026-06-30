<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Populates EN and PT translations for all home page Settings.
 *
 * Run once:  php artisan db:seed --class=TranslateHomeContentSeeder
 * After run: php artisan config:clear && php artisan cache:clear
 *
 * Idempotent: each key is written only when its current value is empty/null.
 * ES originals are NEVER touched.
 * Proper nouns preserved: Machu Picchu, Huacachina, Paracas, Lima, Cusco,
 *   Ica, Nazca, Lima View Tours, Sacsayhuamán, Barranco, Ballestas.
 */
class TranslateHomeContentSeeder extends Seeder
{
    // -----------------------------------------------------------------------
    // SIMPLE TEXT KEYS
    // Format: 'base_key' => ['en' => '...', 'pt' => '...']
    // -----------------------------------------------------------------------
    private array $simpleTexts = [

        // Hero title (multiline — blade uses nl2br(e()))
        'home_hero_title' => [
            'en' => "Discover\nwhat\ntransforms you.",
            'pt' => "Descubra\no que\ntransforma você.",
        ],

        // Section: Featured / Más Comprados
        'home_sec_featured_eyebrow' => [
            'en' => 'OUR TOURS',
            'pt' => 'NOSSOS TOURS',
        ],
        'home_sec_featured_title' => [
            'en' => 'Best Sellers',
            'pt' => 'Mais Vendidos',
        ],

        // Section: Cities (Lima, Ica, Cusco)
        'home_sec_cities_title' => [
            'en' => 'Tours in Lima, Ica and Cusco',
            'pt' => 'Tours em Lima, Ica e Cusco',
        ],

        // Section: Destinos
        'home_sec_destinos_eyebrow' => [
            'en' => 'MOST VISITED',
            'pt' => 'MAIS VISITADOS',
        ],
        'home_sec_destinos_title' => [
            'en' => 'Discover the Most<br>Visited Cities in Peru',
            'pt' => 'Descubra as Cidades<br>mais Visitadas do Peru',
        ],
        'home_destinos_footer' => [
            'en' => 'Authentic experiences, unforgettable memories. Travel with',
            'pt' => 'Experiências autênticas, memórias inesquecíveis. Viaje com',
        ],

        // Section: Why choose us
        'home_sec_why_eyebrow' => [
            'en' => 'TRAVEL WITH CONFIDENCE',
            'pt' => 'VIAJE COM CONFIANÇA',
        ],
        'home_sec_why_title' => [
            'en' => 'Why choose<br>Lima View Tours?',
            'pt' => 'Por que escolher<br>a Lima View Tours?',
        ],
        'home_sec_why_subtitle' => [
            'en' => 'More than a tour, we offer you<br>unforgettable experiences.',
            'pt' => 'Mais do que um tour, oferecemos<br>experiências inesquecíveis.',
        ],
        'home_trust_banner' => [
            'en' => 'Easy, secure booking and<br><strong class="font-bold text-orange-500">100% guaranteed</strong>',
            'pt' => 'Reserva fácil, segura e<br><strong class="font-bold text-orange-500">100% garantida</strong>',
        ],

        // Section: Tour types
        'home_sec_types_eyebrow' => [
            'en' => 'STAYS',
            'pt' => 'ESTÂNCIAS',
        ],
        'home_sec_types_title' => [
            'en' => 'What type of tour <br class="sm:hidden">are you looking for?',
            'pt' => 'Que tipo de tour <br class="sm:hidden">você está buscando?',
        ],

        // Swipe hint (carousel)
        'home_swipe_hint' => [
            'en' => 'Swipe to see more tours',
            'pt' => 'Deslize para ver mais tours',
        ],
        'home_swipe_sub' => [
            'en' => 'Discover our best experiences',
            'pt' => 'Descubra nossas melhores experiências',
        ],

        // Section: Experiences
        'home_sec_exp_eyebrow' => [
            'en' => 'EXPERIENCES',
            'pt' => 'EXPERIÊNCIAS',
        ],
        'home_sec_exp_title' => [
            'en' => 'Discover <br class="sm:hidden">unique experiences',
            'pt' => 'Descubra <br class="sm:hidden">experiências únicas',
        ],

        // Section: Reviews
        'home_sec_reviews_eyebrow' => [
            'en' => 'REAL REVIEWS',
            'pt' => 'AVALIAÇÕES REAIS',
        ],
        'home_sec_reviews_title' => [
            'en' => 'What our travelers say',
            'pt' => 'O que nossos viajantes dizem',
        ],
        'home_sec_reviews_subtitle' => [
            'en' => 'Thousands of travelers have experienced Peru with us.<br class="hidden sm:block">Here are some of their stories.',
            'pt' => 'Milhares de viajantes viveram o Peru conosco.<br class="hidden sm:block">Estas são algumas de suas experiências.',
        ],
        'home_reviews_footer' => [
            'en' => 'Thousands of verified reviews from travelers around the world back our experiences.',
            'pt' => 'Milhares de avaliações verificadas de viajantes do mundo inteiro respaldam nossas experiências.',
        ],

        // Section: FAQ
        'home_sec_faq_title' => [
            'en' => 'Frequently asked questions',
            'pt' => 'Perguntas frequentes',
        ],
        'home_sec_faq_subtitle' => [
            'en' => 'We answer the most common questions<br>before you live your experience in Peru.',
            'pt' => 'Respondemos as dúvidas mais comuns<br>antes de você viver sua experiência no Peru.',
        ],

        // Section: Recommended
        'home_sec_reco_eyebrow' => [
            'en' => 'VERIFIED AND RECOMMENDED BY',
            'pt' => 'VERIFICADO E RECOMENDADO POR',
        ],
        'home_reco_intro' => [
            'en' => 'We are recommended by',
            'pt' => 'Somos recomendados por',
        ],
        'home_reco_headline' => [
            'en' => 'Best Tours in Lima',
            'pt' => 'Melhores Tours em Lima',
        ],
        'home_reco_body' => [
            'en' => 'One of the best companies delivering verified tourism experiences.',
            'pt' => 'Uma das melhores empresas que oferecem experiências turísticas verificadas.',
        ],
        'home_reco_award_title' => [
            'en' => 'WE WON THE<br>VERIFICATION AWARD',
            'pt' => 'GANHAMOS O PRÊMIO<br>DE VERIFICAÇÃO',
        ],
        'home_reco_award_desc' => [
            'en' => 'for the best tours and companies delivering high-quality experiences in Lima.',
            'pt' => 'para os melhores tours e empresas que oferecem experiências de alta qualidade em Lima.',
        ],
        'home_sec_why_reco_title' => [
            'en' => 'Why are we recommended?',
            'pt' => 'Por que somos recomendados?',
        ],
        'home_sec_verified_in' => [
            'en' => 'Recommended and verified on',
            'pt' => 'Recomendado e verificado em',
        ],
    ];

    // -----------------------------------------------------------------------
    // REPEATER KEYS  (arrays stored as JSON in settings)
    // Each entry: the full array with _es/_en/_pt per field.
    // Non-translatable fields (img, iconPath, deco, id, price, etc.) are kept
    // exactly as the blade default so the UI renders identically.
    // -----------------------------------------------------------------------

    /** home_destinos — 3 destination banners */
    private function destinosArray(): array
    {
        return [
            [
                'title_es' => 'Tours en Cusco',
                'title_en' => 'Tours in Cusco',
                'title_pt' => 'Tours em Cusco',
                'badge_es' => 'CULTURA E HISTORIA',
                'badge_en' => 'CULTURE & HISTORY',
                'badge_pt' => 'CULTURA E HISTÓRIA',
                'desc_es'  => 'Descubre el corazón del Imperio Inca. Historia, arquitectura y tradiciones que te transportarán en el tiempo.',
                'desc_en'  => 'Discover the heart of the Inca Empire. History, architecture and traditions that will transport you through time.',
                'desc_pt'  => 'Descubra o coração do Império Inca. História, arquitetura e tradições que vão transportá-lo no tempo.',
                'img'      => 'Rectangle 19218.jpg',
                'iconPath' => 'M3 21h18M5 21V10.5L12 6l7 4.5V21M9 21v-6h6v6',
                'deco'     => '<path d="M40 80 Q40 25, 50 15 Q60 25, 60 80 M47 55 L47 80 M53 55 L53 80 M50 15 L50 8 M46 22 L54 22"/>',
            ],
            [
                'title_es' => 'Tours en Lima',
                'title_en' => 'Tours in Lima',
                'title_pt' => 'Tours em Lima',
                'badge_es' => 'COSTA Y MODERNIDAD',
                'badge_en' => 'COAST & MODERNITY',
                'badge_pt' => 'COSTA E MODERNIDADE',
                'desc_es'  => 'Vive la energía de la capital y disfruta de su cultura, gastronomía y atractivos únicos.',
                'desc_en'  => 'Experience the energy of the capital and enjoy its culture, gastronomy and unique attractions.',
                'desc_pt'  => 'Viva a energia da capital e desfrute de sua cultura, gastronomia e atrações únicas.',
                'img'      => 'Rectangle 19216.jpg',
                'iconPath' => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
                'deco'     => '<path d="M40 80 L40 30 L50 20 L60 30 L60 80 M48 50 L52 50 M48 65 L52 65 M50 20 L50 13 L55 17"/>',
            ],
            [
                'title_es' => 'Tours en Ica',
                'title_en' => 'Tours in Ica',
                'title_pt' => 'Tours em Ica',
                'badge_es' => 'NATURALEZA Y AVENTURA',
                'badge_en' => 'NATURE & ADVENTURE',
                'badge_pt' => 'NATUREZA E AVENTURA',
                'desc_es'  => 'Aventura, viñedos y oasis en el desierto de Ica. Naturaleza que sorprende en cada momento.',
                'desc_en'  => 'Adventure, vineyards and oasis in the Ica desert. Nature that surprises at every moment.',
                'desc_pt'  => 'Aventura, vinhedos e oásis no deserto de Ica. Natureza que surpreende a cada momento.',
                'img'      => 'Rectangle 19219.jpg',
                'iconPath' => 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
                'deco'     => '<path d="M25 80 L40 50 L48 70 L60 35 L75 80 M55 70 Q60 60, 65 70"/>',
            ],
        ];
    }

    /** home_why_items — 5 reasons to choose us */
    private function whyItemsArray(): array
    {
        return [
            [
                'title_es' => 'Reserva segura',
                'title_en' => 'Secure booking',
                'title_pt' => 'Reserva segura',
                'desc_es'  => 'Tus datos protegidos y transacciones 100% seguras.',
                'desc_en'  => 'Your data protected and 100% secure transactions.',
                'desc_pt'  => 'Seus dados protegidos e transações 100% seguras.',
                'iconPath' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
            ],
            [
                'title_es' => 'Guías expertos',
                'title_en' => 'Expert guides',
                'title_pt' => 'Guias especializados',
                'desc_es'  => 'Profesionales locales con amplia experiencia.',
                'desc_en'  => 'Local professionals with extensive experience.',
                'desc_pt'  => 'Profissionais locais com ampla experiência.',
                'iconPath' => 'M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z',
            ],
            [
                'title_es' => 'Mejores experiencias',
                'title_en' => 'Best experiences',
                'title_pt' => 'Melhores experiências',
                'desc_es'  => 'Tours seleccionados y diseñados para ti.',
                'desc_en'  => 'Tours selected and designed for you.',
                'desc_pt'  => 'Tours selecionados e criados para você.',
                'iconPath' => 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0',
            ],
            [
                'title_es' => 'Soporte 24/7',
                'title_en' => 'Support 24/7',
                'title_pt' => 'Suporte 24/7',
                'desc_es'  => 'Estamos para ayudarte antes, durante y después de tu viaje.',
                'desc_en'  => 'We are here to help you before, during and after your trip.',
                'desc_pt'  => 'Estamos aqui para ajudá-lo antes, durante e após sua viagem.',
                'iconPath' => 'M2.25 6.75a4.5 4.5 0 004.5 4.5v8.25a3 3 0 003 3h6a3 3 0 003-3v-8.25a4.5 4.5 0 004.5-4.5h-21z',
            ],
            [
                'title_es' => 'Turismo responsable',
                'title_en' => 'Responsible tourism',
                'title_pt' => 'Turismo responsável',
                'desc_es'  => 'Cuidamos nuestro Perú y apoyamos a las comunidades locales.',
                'desc_en'  => 'We care for our Peru and support local communities.',
                'desc_pt'  => 'Cuidamos do nosso Peru e apoiamos as comunidades locais.',
                'iconPath' => 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418',
            ],
        ];
    }

    /** home_tour_type_tabs — 4 tab panels */
    private function tourTypeTabsArray(): array
    {
        return [
            [
                'id'         => 'cult',
                'label_es'   => 'TOURS CULTURALES',
                'label_en'   => 'CULTURAL TOURS',
                'label_pt'   => 'TOURS CULTURAIS',
                'title_es'   => 'Tours Culturales',
                'title_en'   => 'Cultural Tours',
                'title_pt'   => 'Tours Culturais',
                'img'        => 'Rectangle 19215.jpg',
                'eyebrow_es' => 'CUPOS LIMITADOS',
                'eyebrow_en' => 'LIMITED SPOTS',
                'eyebrow_pt' => 'VAGAS LIMITADAS',
                'desc_es'    => 'Descubre el legado milenario de los incas con visitas guiadas a Machu Picchu, Sacsayhuamán, museos en Lima y centros históricos.',
                'desc_en'    => 'Discover the millenary legacy of the Incas with guided visits to Machu Picchu, Sacsayhuamán, museums in Lima and historic centers.',
                'desc_pt'    => 'Descubra o legado milenar dos incas com visitas guiadas a Machu Picchu, Sacsayhuamán, museus em Lima e centros históricos.',
                'price'      => 200,
                'iconPath'   => 'M3 21h18M5 21V10.5L12 6l7 4.5V21M9 21v-6h6v6',
            ],
            [
                'id'         => 'adv',
                'label_es'   => 'TOURS DE AVENTURA',
                'label_en'   => 'ADVENTURE TOURS',
                'label_pt'   => 'TOURS DE AVENTURA',
                'title_es'   => 'Tours de Aventura',
                'title_en'   => 'Adventure Tours',
                'title_pt'   => 'Tours de Aventura',
                'img'        => 'Rectangle 19219.jpg',
                'eyebrow_es' => 'CUPOS LIMITADOS',
                'eyebrow_en' => 'LIMITED SPOTS',
                'eyebrow_pt' => 'VAGAS LIMITADAS',
                'desc_es'    => 'Sandboarding en Huacachina, trekking a Machu Picchu, tirolesa en el Valle Sagrado y kayak en las Islas Ballestas.',
                'desc_en'    => 'Sandboarding in Huacachina, trekking to Machu Picchu, zip line in the Sacred Valley and kayaking at the Ballestas Islands.',
                'desc_pt'    => 'Sandboarding em Huacachina, trekking a Machu Picchu, tirolesa no Vale Sagrado e caiaque nas Ilhas Ballestas.',
                'price'      => 250,
                'iconPath'   => 'M2.25 17.25L8 11l4 4 4-7 5.75 9.25',
            ],
            [
                'id'         => 'cul',
                'label_es'   => 'EXPERIENCIAS CULINARIAS',
                'label_en'   => 'CULINARY EXPERIENCES',
                'label_pt'   => 'EXPERIÊNCIAS CULINÁRIAS',
                'title_es'   => 'Experiencias Culinarias',
                'title_en'   => 'Culinary Experiences',
                'title_pt'   => 'Experiências Culinárias',
                'img'        => 'Rectangle 19211.jpg',
                'eyebrow_es' => 'TOUR DEGUSTACIÓN',
                'eyebrow_en' => 'TASTING TOUR',
                'eyebrow_pt' => 'TOUR DEGUSTAÇÃO',
                'desc_es'    => 'Recorrido por la gastronomía peruana premiada mundialmente: ceviche en Barranco, pisco sour en bares históricos.',
                'desc_en'    => "A tour of Peru's world-award-winning gastronomy: ceviche in Barranco, pisco sour at historic bars.",
                'desc_pt'    => 'Um roteiro pela gastronomia peruana premiada mundialmente: ceviche em Barranco, pisco sour em bares históricos.',
                'price'      => 180,
                'iconPath'   => 'M3 11h18M7 11V5m10 6V5M5 11v10m14-10v10M9 18h6',
            ],
            [
                'id'         => 'oth',
                'label_es'   => 'OTROS',
                'label_en'   => 'OTHERS',
                'label_pt'   => 'OUTROS',
                'title_es'   => 'Otras experiencias',
                'title_en'   => 'Other experiences',
                'title_pt'   => 'Outras experiências',
                'img'        => 'Rectangle 19216.jpg',
                'eyebrow_es' => 'NUEVO',
                'eyebrow_en' => 'NEW',
                'eyebrow_pt' => 'NOVO',
                'desc_es'    => 'Tours fotográficos, observación de aves, retiros wellness en el Valle Sagrado y experiencias místicas.',
                'desc_en'    => 'Photography tours, birdwatching, wellness retreats in the Sacred Valley and mystical experiences.',
                'desc_pt'    => 'Tours fotográficos, observação de aves, retiros wellness no Vale Sagrado e experiências místicas.',
                'price'      => 220,
                'iconPath'   => 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z',
            ],
        ];
    }

    /** home_footer_features — 4 feature badges below tab panels */
    private function footerFeaturesArray(): array
    {
        return [
            [
                'label_es' => 'Reserva fácil',
                'label_en' => 'Easy booking',
                'label_pt' => 'Reserva fácil',
                'iconPath' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
            ],
            [
                'label_es' => 'Soporte 24/7',
                'label_en' => 'Support 24/7',
                'label_pt' => 'Suporte 24/7',
                'iconPath' => 'M2.25 6.75a4.5 4.5 0 004.5 4.5v8.25a3 3 0 003 3h6a3 3 0 003-3v-8.25a4.5 4.5 0 004.5-4.5h-21z',
            ],
            [
                'label_es' => 'Cancelación gratuita',
                'label_en' => 'Free cancellation',
                'label_pt' => 'Cancelamento grátis',
                'iconPath' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
            ],
            [
                'label_es' => 'Mejores experiencias',
                'label_en' => 'Best experiences',
                'label_pt' => 'Melhores experiências',
                'iconPath' => 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497',
            ],
        ];
    }

    /** home_exp_tours — 4 experience cards */
    private function expToursArray(): array
    {
        return [
            [
                'img'      => 'Rectangle 19216.jpg',
                'title_es' => 'Excursión de día completo en Lima',
                'title_en' => 'Full-day excursion in Lima',
                'title_pt' => 'Excursão de dia inteiro em Lima',
                'badge_es' => 'MÁS VENDIDO',
                'badge_en' => 'BEST SELLER',
                'badge_pt' => 'MAIS VENDIDO',
                'badgeBg'  => 'teal-800',
                'slug'     => 'excursion-dia-completo-lima',
            ],
            [
                'img'      => 'Rectangle 19218.jpg',
                'title_es' => 'Misterios del Imperio Inca en Cusco',
                'title_en' => 'Mysteries of the Inca Empire in Cusco',
                'title_pt' => 'Mistérios do Império Inca em Cusco',
                'badge_es' => 'MÁS VENDIDO',
                'badge_en' => 'BEST SELLER',
                'badge_pt' => 'MAIS VENDIDO',
                'badgeBg'  => 'teal-800',
                'slug'     => 'misterios-imperio-inca-cusco',
            ],
            [
                'img'      => 'Rectangle 19219.jpg',
                'title_es' => 'Oasis de Huacachina y Líneas de Nazca',
                'title_en' => 'Huacachina Oasis and Nazca Lines',
                'title_pt' => 'Oásis de Huacachina e Linhas de Nazca',
                'badge_es' => 'DESTACADO',
                'badge_en' => 'FEATURED',
                'badge_pt' => 'DESTAQUE',
                'badgeBg'  => 'orange-500',
                'slug'     => 'oasis-huacachina-lineas-nazca',
            ],
            [
                'img'      => 'Rectangle 19215.jpg',
                'title_es' => 'Tour Cultural Lima Colonial y Moderna',
                'title_en' => 'Colonial and Modern Lima Cultural Tour',
                'title_pt' => 'Tour Cultural Lima Colonial e Moderna',
                'badge_es' => 'MÁS VENDIDO',
                'badge_en' => 'BEST SELLER',
                'badge_pt' => 'MAIS VENDIDO',
                'badgeBg'  => 'teal-800',
                'slug'     => 'tour-cultural-lima-colonial-moderna',
            ],
        ];
    }

    /** home_faqs — 6 FAQ items */
    private function faqsArray(): array
    {
        return [
            [
                'q_es'     => '¿Qué tipo de tours ofrecen?',
                'q_en'     => 'What types of tours do you offer?',
                'q_pt'     => 'Que tipos de tours vocês oferecem?',
                'a_es'     => 'Ofrecemos tours culturales, de aventura, gastronómicos y experiencias únicas en Lima, Ica y Cusco.',
                'a_en'     => 'We offer cultural, adventure, gastronomic tours and unique experiences in Lima, Ica and Cusco.',
                'a_pt'     => 'Oferecemos tours culturais, de aventura, gastronômicos e experiências únicas em Lima, Ica e Cusco.',
                'iconPath' => 'M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c-.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z',
            ],
            [
                'q_es'     => '¿Los tours son aptos para todos los viajeros?',
                'q_en'     => 'Are the tours suitable for all travelers?',
                'q_pt'     => 'Os tours são adequados para todos os viajantes?',
                'a_es'     => 'La mayoría de nuestros tours son aptos para todas las edades y niveles físicos. Consúltanos para detalles.',
                'a_en'     => 'Most of our tours are suitable for all ages and fitness levels. Ask us for details.',
                'a_pt'     => 'A maioria de nossos tours é adequada para todas as idades e níveis físicos. Consulte-nos para detalhes.',
                'iconPath' => 'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486',
            ],
            [
                'q_es'     => '¿Incluyen recojo desde el hotel?',
                'q_en'     => 'Do you offer hotel pickup?',
                'q_pt'     => 'Vocês oferecem traslado do hotel?',
                'a_es'     => 'Sí, todos nuestros tours en Lima, Ica y Cusco incluyen recojo y retorno a tu hotel sin costo adicional.',
                'a_en'     => 'Yes, all our tours in Lima, Ica and Cusco include hotel pickup and drop-off at no extra cost.',
                'a_pt'     => 'Sim, todos os nossos tours em Lima, Ica e Cusco incluem busca e retorno ao hotel sem custo adicional.',
                'iconPath' => 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
            ],
            [
                'q_es'     => '¿Cómo puedo reservar un tour?',
                'q_en'     => 'How can I book a tour?',
                'q_pt'     => 'Como posso reservar um tour?',
                'a_es'     => 'Puedes reservar online seleccionando tu tour, fecha y número de pasajeros. Te enviaremos la confirmación al instante.',
                'a_en'     => "You can book online by selecting your tour, date and number of passengers. We'll send you instant confirmation.",
                'a_pt'     => 'Você pode reservar online selecionando seu tour, data e número de passageiros. Enviaremos a confirmação instantaneamente.',
                'iconPath' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
            ],
            [
                'q_es'     => '¿Hay opciones para familias o grupos?',
                'q_en'     => 'Are there options for families or groups?',
                'q_pt'     => 'Há opções para famílias ou grupos?',
                'a_es'     => 'Sí, ofrecemos tarifas especiales y tours privados diseñados para familias y grupos de cualquier tamaño.',
                'a_en'     => 'Yes, we offer special rates and private tours designed for families and groups of any size.',
                'a_pt'     => 'Sim, oferecemos tarifas especiais e tours privados criados para famílias e grupos de qualquer tamanho.',
                'iconPath' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z',
            ],
            [
                'q_es'     => '¿Es seguro viajar con Lima View Tours?',
                'q_en'     => 'Is it safe to travel with Lima View Tours?',
                'q_pt'     => 'É seguro viajar com a Lima View Tours?',
                'a_es'     => 'Contamos con +11 años de experiencia, guías certificados, vehículos asegurados y protocolos de seguridad.',
                'a_en'     => 'We have 11+ years of experience, certified guides, insured vehicles and safety protocols.',
                'a_pt'     => 'Temos +11 anos de experiência, guias certificados, veículos segurados e protocolos de segurança.',
                'iconPath' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
            ],
        ];
    }

    /** home_reco_items — 4 "why recommended" cards */
    private function recoItemsArray(): array
    {
        return [
            [
                'title_es' => 'Calidad Verificada',
                'title_en' => 'Verified Quality',
                'title_pt' => 'Qualidade Verificada',
                'desc_es'  => 'Operadores evaluados bajo estrictos estándares de calidad.',
                'desc_en'  => 'Operators evaluated under strict quality standards.',
                'desc_pt'  => 'Operadores avaliados sob rigorosos padrões de qualidade.',
                'icon'     => 'shield',
            ],
            [
                'title_es' => 'Experiencias Auténticas',
                'title_en' => 'Authentic Experiences',
                'title_pt' => 'Experiências Autênticas',
                'desc_es'  => 'Tours diseñados para brindar experiencias únicas y memorables.',
                'desc_en'  => 'Tours designed to deliver unique and memorable experiences.',
                'desc_pt'  => 'Tours criados para oferecer experiências únicas e memoráveis.',
                'icon'     => 'star',
            ],
            [
                'title_es' => 'Soporte Confiable',
                'title_en' => 'Reliable Support',
                'title_pt' => 'Suporte Confiável',
                'desc_es'  => 'Acompañamiento antes, durante y después de tu viaje.',
                'desc_en'  => 'Support before, during and after your trip.',
                'desc_pt'  => 'Acompanhamento antes, durante e após sua viagem.',
                'icon'     => 'headset',
            ],
            [
                'title_es' => 'Altos Estándares de Calidad',
                'title_en' => 'High Quality Standards',
                'title_pt' => 'Altos Padrões de Qualidade',
                'desc_es'  => 'Comprometidos con la excelencia y la satisfacción del viajero.',
                'desc_en'  => 'Committed to excellence and traveler satisfaction.',
                'desc_pt'  => 'Comprometidos com a excelência e a satisfação do viajante.',
                'icon'     => 'medal',
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // RUN
    // -----------------------------------------------------------------------
    public function run(): void
    {
        // 1) Simple texts: set _en and _pt only when empty
        foreach ($this->simpleTexts as $baseKey => $translations) {
            foreach (['en', 'pt'] as $lang) {
                $fullKey = "{$baseKey}_{$lang}";
                $current = Setting::get($fullKey);
                if ($current === null || $current === '' || $current === [] || $current === '[]') {
                    Setting::set($fullKey, $translations[$lang]);
                }
            }
        }

        // 2) Repeaters: set the whole JSON array only when the key is empty.
        //    The blade reads these as json/array type; Setting::set() stores
        //    whatever we pass through json_encode internally when it detects an array.
        $repeaters = [
            'home_destinos'        => $this->destinosArray(),
            'home_why_items'       => $this->whyItemsArray(),
            'home_tour_type_tabs'  => $this->tourTypeTabsArray(),
            'home_footer_features' => $this->footerFeaturesArray(),
            'home_exp_tours'       => $this->expToursArray(),
            'home_faqs'            => $this->faqsArray(),
            'home_reco_items'      => $this->recoItemsArray(),
        ];

        foreach ($repeaters as $key => $data) {
            $current = Setting::get($key);
            if ($current === null || $current === '' || $current === [] || $current === '[]') {
                Setting::set($key, json_encode($data), 'json', 'home');
            }
        }
    }
}
