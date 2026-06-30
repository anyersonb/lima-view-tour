<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour7Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'VALE SAGRADO DOS INCAS',
            'description_en' => 'Experience the magic of the Sacred Valley of the Incas and the authenticity of Chinchero on a full-day tour from Cusco. Immerse yourself in the rich culture and fascinating history of the Incas as you explore traditional markets, impressive ruins, and local weaving workshops. A journey that will transport you back to the time of the Incas and allow you to appreciate the natural beauty and local craftsmanship of this truly unique region.' . "\n\n" . 'Experience the magic of the Sacred Valley of the Incas and the authenticity of Chinchero on a full-day tour from Cusco. Immerse yourself in the rich culture and fascinating history of the Incas as you explore traditional markets, impressive ruins, and local weaving workshops. A journey that will transport you back to the time of the Incas and allow you to appreciate the natural beauty and local craftsmanship of this truly unique region.',
            'description_pt' => 'Experimente a magia do Vale Sagrado dos Incas e a autenticidade de Chinchero em um tour de dia completo saindo de Cusco. Mergulhe na rica cultura e na fascinante história dos Incas enquanto explora mercados tradicionais, ruínas impressionantes e ateliês de tecelagem locais. Uma viagem que vai transportá-lo de volta à época dos Incas e permitirá que você aprecie a beleza natural e o artesanato local desta região única no mundo.' . "\n\n" . 'Experimente a magia do Vale Sagrado dos Incas e a autenticidade de Chinchero em um tour de dia completo saindo de Cusco. Mergulhe na rica cultura e na fascinante história dos Incas enquanto explora mercados tradicionais, ruínas impressionantes e ateliês de tecelagem locais. Uma viagem que vai transportá-lo de volta à época dos Incas e permitirá que você aprecie a beleza natural e o artesanato local desta região única no mundo.',
            'recommendations_en' => "Bring your original passport\r\n Waterproof jacket / rain poncho\r\n Warm jacket\r\n Camera\r\n Herbal teas for altitude sickness",
            'recommendations_pt' => "Levar passaporte original\r\n Jaqueta impermeável / poncho para chuva\r\n Jaqueta grossa\r\n Câmera fotográfica\r\n Levar chás de ervas para a altitude",
            'notes_en' => 'Our hotel pick-up and drop-off service covers the area around the Plaza de Armas in Cusco. If your hotel or accommodation is outside this area, we offer the option to pick you up at the Plaza de Armas in Cusco (exactly at the water fountain).',
            'notes_pt' => 'Nosso serviço de busca e retorno ao hotel cobre a área da Plaza de Armas de Cusco. Se o seu hotel ou acomodação estiver fora dessa área, oferecemos a possibilidade de buscá-lo na Plaza de Armas de Cusco (exatamente na fonte de água).',
            'itinerary_en' => json_encode([
                [
                    'time' => '08:00 am',
                    'image' => 'tours/Rectangle-19210.jpg',
                    'description' => 'Pick-up at your hotel in Cusco by our tour guide and driver.',
                ],
                [
                    'time' => '08:30 am',
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => 'Departure towards the Sacred Valley, a fertile valley steeped in history.',
                ],
                [
                    'time' => '10:00 am',
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Transfer to the Pisac Ruins, an Inca archaeological complex perched high on the mountainside. Our guide will take you on a tour of the agricultural terraces, temples, and impressive irrigation system.',
                ],
                [
                    'time' => '12:00 pm',
                    'image' => 'tours/image.jpg',
                    'description' => 'Buffet lunch at a local restaurant (included in the price), where you can enjoy the delicious flavors of Peruvian cuisine, combining traditional recipes with fresh local ingredients.',
                ],
                [
                    'time' => '01:30 pm',
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => 'Journey to Ollantaytambo, a living Inca town that preserves its original urban layout. Here we will visit the impressive Ollantaytambo Fortress, an archaeological complex that showcases Inca urban planning and offers panoramic views of the valley.',
                ],
                [
                    'time' => '03:30 pm',
                    'image' => 'tours/Rectangle-19215.jpg',
                    'description' => 'We continue our journey to Chinchero, a village renowned for its alpaca and llama textile production. Here we will visit a weaving center where we\'ll learn about the textile-making process and have the opportunity to watch artisans at work.',
                ],
                [
                    'time' => '04:30 pm',
                    'image' => 'tours/Rectangle-19219.jpg',
                    'description' => 'Return to Cusco, enjoying the landscapes of the Sacred Valley along the way back.',
                ],
                [
                    'time' => '05:30 pm',
                    'image' => 'tours/image-1.jpg',
                    'description' => 'Arrival in Cusco and transfer back to your hotel, concluding our day full of discoveries in the Sacred Valley, Ollantaytambo, and Chinchero.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                [
                    'time' => '08:00 am',
                    'image' => 'tours/Rectangle-19210.jpg',
                    'description' => 'Busca no hotel em Cusco pelo nosso guia turístico e motorista.',
                ],
                [
                    'time' => '08:30 am',
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => 'Partida em direção ao Vale Sagrado, um vale fértil repleto de história.',
                ],
                [
                    'time' => '10:00 am',
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Traslado às Ruínas de Pisac, um complexo arqueológico inca situado no alto da montanha. Nosso guia conduzirá um passeio pelas terraços agrícolas, templos e o impressionante sistema de irrigação.',
                ],
                [
                    'time' => '12:00 pm',
                    'image' => 'tours/image.jpg',
                    'description' => 'Almoço buffet em restaurante local (incluído no preço), onde você poderá desfrutar da deliciosa gastronomia peruana, com sabores tradicionais e ingredientes frescos da região.',
                ],
                [
                    'time' => '01:30 pm',
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => 'Viagem até Ollantaytambo, uma cidade inca viva que preserva seu traçado urbano original. Aqui visitaremos a impressionante Fortaleza de Ollantaytambo, um complexo arqueológico que demonstra o planejamento urbano inca e oferece vistas panorâmicas do vale.',
                ],
                [
                    'time' => '03:30 pm',
                    'image' => 'tours/Rectangle-19215.jpg',
                    'description' => 'Continuamos nossa viagem até Chinchero, um vilarejo conhecido pela produção de tecidos de alpaca e lhama. Aqui visitaremos um centro têxtil onde aprenderemos sobre o processo de produção dos tecidos e teremos a oportunidade de ver os artesãos em ação.',
                ],
                [
                    'time' => '04:30 pm',
                    'image' => 'tours/Rectangle-19219.jpg',
                    'description' => 'Retorno a Cusco, aproveitando as paisagens do Vale Sagrado no caminho de volta.',
                ],
                [
                    'time' => '05:30 pm',
                    'image' => 'tours/image-1.jpg',
                    'description' => 'Chegada a Cusco e traslado de volta ao hotel, encerrando assim nosso dia repleto de descobertas no Vale Sagrado, Ollantaytambo e Chinchero.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                'Entrance tickets included',
                'Professional guide',
                'Tourist transport with pick-up and drop-off',
                'Pick-up at your hotel or Airbnb',
                'Tickets to each archaeological site included',
                'Buffet lunch included',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                'Ingressos incluídos',
                'Guia profissional',
                'Transporte turístico com busca e retorno',
                'Busca no hotel ou Airbnb',
                'Ingressos para cada centro arqueológico incluídos',
                'Almoço buffet incluído',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 7)->update($data);
        }
    }
}
