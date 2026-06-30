<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour9Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'LAGOA HUMANTAY',
            'description_en' => 'Embark on an unforgettable journey to one of Peru\'s most breathtaking natural destinations: Laguna Humantay. Our tour will take you through the majestic landscapes of the Peruvian Andes, where you\'ll enjoy panoramic views, explore nature in its purest state, and immerse yourself in the local culture. From your hotel pickup in Cusco to your return at the end of the day, we guarantee a day full of adventure and discovery. Join us and discover the beauty of Laguna Humantay!

Embark on an unforgettable journey to one of Peru\'s most breathtaking natural destinations: Laguna Humantay. Our tour will take you through the majestic landscapes of the Peruvian Andes, where you\'ll enjoy panoramic views, explore nature in its purest state, and immerse yourself in the local culture. From your hotel pickup in Cusco to your return at the end of the day, we guarantee a day full of adventure and discovery. Join us and discover the beauty of Laguna Humantay!',
            'description_pt' => 'Embarque em uma experiência inesquecível rumo a um dos destinos naturais mais impressionantes do Peru: a Lagoa Humantay. Nosso tour irá levá-lo pelos majestosos paisagens dos Andes peruanos, onde você poderá desfrutar de vistas panorâmicas, explorar a natureza em seu estado mais puro e se imergir na cultura local. Desde a sua busca no hotel em Cusco até o seu retorno ao final do dia, garantimos uma jornada repleta de aventura e descobertas. Junte-se a nós e descubra a beleza da Lagoa Humantay!

Embarque em uma experiência inesquecível rumo a um dos destinos naturais mais impressionantes do Peru: a Lagoa Humantay. Nosso tour irá levá-lo pelos majestosos paisagens dos Andes peruanos, onde você poderá desfrutar de vistas panorâmicas, explorar a natureza em seu estado mais puro e se imergir na cultura local. Desde a sua busca no hotel em Cusco até o seu retorno ao final do dia, garantimos uma jornada repleta de aventura e descobertas. Junte-se a nós e descubra a beleza da Lagoa Humantay!',
            'recommendations_en' => "Bring your original passport\r\n Waterproof jacket / rain poncho\r\n Warm jacket\r\n Camera\r\n Herbal altitude teas recommended",
            'recommendations_pt' => "Levar passaporte original\r\n Jaqueta impermeável / poncho para chuva\r\n Jaqueta grossa\r\n Câmera fotográfica\r\n Tomar infusões para altitude",
            'notes_en' => 'Our hotel pickup and drop-off service covers the area around the Plaza de Armas in Cusco. If your hotel or accommodation is outside this area, we offer the option to pick you up at the Plaza de Armas in Cusco (exactly at the water fountain).',
            'notes_pt' => 'Nosso serviço de busca e retorno ao hotel cobre a área da Plaza de Armas de Cusco. Se o seu hotel ou acomodação estiver fora desta área, oferecemos a possibilidade de buscá-lo na Plaza de Armas de Cusco (exatamente na fonte de água).',
            'itinerary_en' => json_encode([
                [
                    'time' => '4:30 am - 5:00 am',
                    'image' => 'tours/Rectangle-19210.jpg',
                    'description' => 'Hotel pickup — our transport service will pick you up at your hotel in Cusco. It is important to be ready to leave early, as Laguna Humantay is some distance away and we want to make the most of the day.',
                ],
                [
                    'time' => '5:00 am - 8:30 am',
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => 'Journey to Laguna Humantay — enjoy a scenic drive through the stunning landscapes of the Peruvian Andes on the way to Laguna Humantay. Strategic stops will be made for photos and to take in the views.',
                ],
                [
                    'time' => '8:30 am - 9:00 am',
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Breakfast — we will arrive at a meeting point where a buffet breakfast will be served to fuel up before the hike. (Buffet breakfast included in the price.)',
                ],
                [
                    'time' => '9:00 am - 11:30 am',
                    'image' => 'tours/image.jpg',
                    'description' => 'Hike to Laguna Humantay — we begin the hike toward Laguna Humantay. The trail offers panoramic views and the chance to appreciate the natural beauty of the region. Comfortable clothing and proper footwear are recommended.',
                ],
                [
                    'time' => '11:30 am - 12:30 pm',
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => 'Free time at Laguna Humantay — upon arriving at Laguna Humantay, you will have free time to explore, take photos, and soak in the natural surroundings.',
                ],
                [
                    'time' => '12:30 pm - 1:30 pm',
                    'image' => null,
                    'description' => 'Buffet lunch — we will enjoy a buffet lunch with a variety of options to suit different tastes and dietary needs. (Buffet lunch included in the price.)',
                ],
                [
                    'time' => '1:30 pm - 4:30 pm',
                    'image' => null,
                    'description' => 'Descent and return to Cusco — we begin the descent back to the meeting point and then make our way back to Cusco. On the way back, we\'ll take the time to reflect on the experience and enjoy additional views.',
                ],
                [
                    'time' => '6:00 pm',
                    'image' => null,
                    'description' => 'Arrival in Cusco — we will arrive in Cusco and drop you off near the Plaza de Armas or at your hotel. The rest of the afternoon and evening are free for you to enjoy Cusco at your own pace.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                [
                    'time' => '4:30 am - 5:00 am',
                    'image' => 'tours/Rectangle-19210.jpg',
                    'description' => 'Busca no hotel — nosso serviço de transporte irá buscá-lo no seu hotel em Cusco. É importante estar pronto para sair cedo, pois a Lagoa Humantay fica a certa distância e queremos aproveitar ao máximo o dia.',
                ],
                [
                    'time' => '5:00 am - 8:30 am',
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => 'Viagem à Lagoa Humantay — desfrutaremos de uma viagem panorâmica pelas impressionantes paisagens dos Andes peruanos enquanto nos dirigimos à Lagoa Humantay. Serão realizadas paradas estratégicas para fotografias e para apreciar as vistas.',
                ],
                [
                    'time' => '8:30 am - 9:00 am',
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Café da manhã — chegaremos a um ponto de encontro onde será servido um café da manhã buffet para recarregar as energias antes da caminhada. (Café da manhã buffet incluído no preço.)',
                ],
                [
                    'time' => '9:00 am - 11:30 am',
                    'image' => 'tours/image.jpg',
                    'description' => 'Caminhada até a Lagoa Humantay — iniciamos a trilha em direção à Lagoa Humantay. O percurso oferece vistas panorâmicas e a oportunidade de apreciar a beleza natural da região. Recomenda-se roupas confortáveis e calçado adequado.',
                ],
                [
                    'time' => '11:30 am - 12:30 pm',
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => 'Tempo livre na Lagoa Humantay — ao chegar à Lagoa Humantay, você terá tempo livre para explorar, tirar fotos e desfrutar do entorno natural.',
                ],
                [
                    'time' => '12:30 pm - 1:30 pm',
                    'image' => null,
                    'description' => 'Almoço buffet — desfrutaremos de um almoço buffet com uma variedade de opções para satisfazer diferentes gostos e necessidades alimentares. (Almoço buffet incluído no preço.)',
                ],
                [
                    'time' => '1:30 pm - 4:30 pm',
                    'image' => null,
                    'description' => 'Descida e retorno a Cusco — iniciamos a descida de volta ao ponto de encontro e depois nos dirigimos de volta a Cusco. No caminho de volta, aproveitaremos para refletir sobre a experiência e desfrutar das vistas adicionais.',
                ],
                [
                    'time' => '6:00 pm',
                    'image' => null,
                    'description' => 'Chegada a Cusco — chegaremos a Cusco e o deixaremos próximo à Plaza de Armas ou no seu hotel. O restante da tarde e da noite fica livre para que aproveite Cusco a seu critério.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                'Entrance ticket included',
                'Professional guide',
                'Tourist transport — hotel pickup and drop-off',
                'Pickup at your hotel or Airbnb',
                'Buffet breakfast included',
                'Buffet lunch included',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                'Ingresso de entrada incluído',
                'Guia profissional',
                'Transporte turístico — busca e retorno ao hotel',
                'Busca no seu hotel ou Airbnb',
                'Café da manhã buffet incluído',
                'Almoço buffet incluído',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 9)->update($data);
        }
    }
}
