<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour16Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'Pacote Cusco 4 Dias com Machu Picchu, Humantay e Montanha das 7 Cores',
            'description_en' => 'Discover the best of Cusco on a 4-day, 3-night trip that combines history, Andean landscapes and an unforgettable visit to Machu Picchu. This package includes a City Tour in Cusco, the Rainbow Mountain, Laguna Humantay, hotel, transfers and entrance fees — ideal for those seeking a complete, well-organized and comfortable experience.' . "\n\n" . 'This 4-day Cusco package has been designed for travelers who want to explore the region\'s most outstanding destinations in one single trip. Along the way you will visit the imperial city, discover Machu Picchu, admire the natural beauty of Laguna Humantay and explore the stunning Rainbow Mountain. It is an excellent choice for those looking for a well-organized, comfortable journey covering the main highlights of Cusco in just a few days.',
            'description_pt' => 'Descubra o melhor de Cusco em uma viagem de 4 dias e 3 noites que combina história, paisagens andinas e uma visita inesquecível a Machu Picchu. Este pacote inclui City Tour em Cusco, Montanha das 7 Cores, Laguna Humantay, hotel, traslados e ingressos — ideal para quem busca uma experiência completa, organizada e confortável.' . "\n\n" . 'Este pacote de 4 dias em Cusco foi desenvolvido para viajantes que desejam conhecer os destinos mais incríveis da região em uma única experiência. Durante o roteiro você poderá explorar a cidade imperial, visitar Machu Picchu, admirar a beleza natural da Laguna Humantay e descobrir a impressionante Montanha das 7 Cores. É uma ótima opção para quem busca uma viagem bem organizada, confortável e com os principais atrativos de Cusco em poucos dias.',
            'recommendations_en' => "Bring original passport\r\n Waterproof jacket / rain poncho\r\n Warm jacket\r\n Camera\r\n Drink herbal infusions for altitude\r\n Bring original ID documents",
            'recommendations_pt' => "Trazer passaporte original\r\n Jaqueta impermeável / poncho de chuva\r\n Jaqueta grossa\r\n Câmera fotográfica\r\n Tomar infusões para a altitude\r\n Trazer documentos de identidade originais",
            'notes_en' => 'Our pick-up and drop-off service covers the Plaza de Armas area of Cusco. If your hotel or accommodation is outside this area, we can pick you up at the Plaza de Armas in Cusco (exactly at the water fountain).',
            'notes_pt' => 'Nosso serviço de coleta e retorno ao hotel abrange a área da Plaza de Armas de Cusco. Se o seu hotel ou acomodação estiver fora dessa área, oferecemos a possibilidade de buscá-lo na Plaza de Armas de Cusco (exatamente na fonte d\'água).',
            'itinerary_en' => json_encode([
                [
                    'time' => 'DAY 1: AIRPORT PICK-UP AND AFTERNOON CITY TOUR',
                    'image' => null,
                    'description' => 'Airport reception and hotel transfer. Free time for acclimatization. In the afternoon, City Tour of Cusco visiting Qoricancha, Sacsayhuamán, Qenqo, Puka Pukara, Tambomachay and a panoramic view of the Cathedral. Overnight in Cusco. (3-STAR HOTEL INCLUDED)',
                ],
                [
                    'time' => 'DAY 2: RAINBOW MOUNTAIN',
                    'image' => null,
                    'description' => 'Early pick-up, breakfast on the way and excursion to Vinicunca, one of the most breathtaking landscapes around Cusco. Time for photos, buffet lunch and afternoon return. Overnight in Cusco. (3-STAR HOTEL INCLUDED)',
                ],
                [
                    'time' => 'DAY 3: MACHU PICCHU FULL-DAY TOUR',
                    'image' => null,
                    'description' => 'Departure from Cusco to the train station in Ollantaytambo, then continue by train to Aguas Calientes. From there, take the bus up to Machu Picchu and enjoy a guided tour of the citadel lasting approximately 2 to 3 hours. Afterwards, free time for photos and to soak in the surroundings before the bus and train return to Cusco. At Lima View Tours, this tour includes train, bus, Machu Picchu entrance and professional guide. Overnight in Cusco. (3-STAR HOTEL INCLUDED)',
                ],
                [
                    'time' => 'DAY 4: LAGUNA HUMANTAY AND AIRPORT TRANSFER',
                    'image' => null,
                    'description' => 'On the final day we will visit the spectacular Laguna Humantay, famous for its turquoise waters and mountain scenery. After the excursion and time to enjoy the setting, we will head back and transfer to the airport.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                [
                    'time' => 'DIA 1: CHEGADA AO AEROPORTO E CITY TOUR À TARDE',
                    'image' => null,
                    'description' => 'Recepção no aeroporto e traslado ao hotel. Tempo livre para aclimatação. À tarde, City Tour por Cusco com visita a Qoricancha, Sacsayhuamán, Qenqo, Puka Pukara, Tambomachay e vista panorâmica da Catedral. Pernoite em Cusco. (HOTEL 3 ESTRELAS INCLUÍDO)',
                ],
                [
                    'time' => 'DIA 2: MONTANHA DAS 7 CORES',
                    'image' => null,
                    'description' => 'Coleta cedo, café da manhã durante o percurso e excursão a Vinicunca, uma das paisagens mais impressionantes de Cusco. Tempo para fotos, almoço buffet e retorno à tarde. Pernoite em Cusco. (HOTEL 3 ESTRELAS INCLUÍDO)',
                ],
                [
                    'time' => 'DIA 3: MACHU PICCHU TOUR DE DIA COMPLETO',
                    'image' => null,
                    'description' => 'Partida de Cusco em direção à estação de trem em Ollantaytambo para continuar a viagem a Aguas Calientes. Em seguida, sobe-se de ônibus até Machu Picchu, onde será realizada uma visita guiada pela cidadela com duração aproximada de 2 a 3 horas. Depois haverá tempo livre para fotos e para curtir o entorno antes do retorno de ônibus e trem para Cusco. Na Lima View Tours, este tour inclui trem, ônibus, ingresso a Machu Picchu e guia profissional. Pernoite em Cusco. (HOTEL 3 ESTRELAS INCLUÍDO)',
                ],
                [
                    'time' => 'DIA 4: LAGUNA HUMANTAY E TRASLADO AO AEROPORTO',
                    'image' => null,
                    'description' => 'No último dia visitaremos a espetacular Laguna Humantay, famosa pelas suas águas turquesas e paisagens de montanha. Após a excursão e o tempo para curtir o local, será realizado o retorno e o traslado para o aeroporto.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                "Day 1 – Arrival in Cusco + City Tour- Airport transfer\r\n- City Tour of Cusco\r\n- Tourist transport\r\n- Professional guide\r\n- Entrance fees to archaeological sites\r\n- One night in a 3-star hotel.",
                "Day 2 – Rainbow Mountain- Hotel pick-up\r\n- Tourist transport\r\n- Entrance fee to the mountain\r\n- Professional guide\r\n- Buffet breakfast\r\n- Buffet lunch\r\n- One night in a 3-star hotel.",
                "Day 3 – Machu Picchu- Hotel transfer\r\n- Train and bus ride (one way)\r\n- Return from Machu Picchu\r\n- Machu Picchu entrance ticket\r\n- Professional guide\r\n- Return to Cusco\r\n- One night in a 3-star hotel.",
                "Day 4 – Laguna Humantay + departure- Hotel pick-up\r\n- Tourist transport\r\n- Laguna entrance fee\r\n- Professional guide\r\n- Buffet breakfast\r\n- Buffet lunch\r\n- Final transfer to the airport",
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                "Dia 1 – Chegada a Cusco + City Tour- Traslado do aeroporto\r\n- City Tour em Cusco\r\n- Transporte turístico\r\n- Guia profissional\r\n- Ingressos para os centros arqueológicos\r\n- Pernoite em hotel 3 estrelas.",
                "Dia 2 – Montanha das 7 Cores- Coleta no hotel\r\n- Transporte turístico\r\n- Ingresso para a montanha\r\n- Guia profissional\r\n- Café da manhã buffet\r\n- Almoço buffet\r\n- Pernoite em hotel 3 estrelas.",
                "Dia 3 – Machu Picchu- Traslado do hotel\r\n- Viagem de trem e ônibus (ida)\r\n- Volta de Machu Picchu\r\n- Ingresso para Machu Picchu\r\n- Guia profissional\r\n- Retorno a Cusco\r\n- Pernoite em hotel 3 estrelas.",
                "Dia 4 – Laguna Humantay + saída- Coleta no hotel\r\n- Transporte turístico\r\n- Ingresso para a laguna\r\n- Guia profissional\r\n- Café da manhã buffet\r\n- Almoço buffet\r\n- Traslado final para o aeroporto",
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 16)->update($data);
        }
    }
}
