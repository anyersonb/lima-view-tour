<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour6Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'CITY TOUR CUSCO',
            'description_en' => 'Discover the majesty of Cusco on our full-day city tour. Starting from your hotel, you\'ll explore the historic Plaza de Armas and the magnificent Cathedral. Next, visit the Inca Temple of Qoricancha, where Inca and Spanish architecture blend in perfect harmony. We\'ll continue to the ruins of Sacsayhuaman, with panoramic views of the city, and explore the archaeological sites of Quenqo, Tambomachay, and Pucapucara, each with its own unique history and beauty. We conclude the tour by dropping you back at your hotel, ready to keep exploring this city steeped in history and culture. Join us and enjoy this unforgettable experience in Cusco!

Discover the majesty of Cusco on our full-day city tour. Starting from your hotel, you\'ll explore the historic Plaza de Armas and the magnificent Cathedral. Next, visit the Inca Temple of Qoricancha, where Inca and Spanish architecture blend in perfect harmony. We\'ll continue to the ruins of Sacsayhuaman, with panoramic views of the city, and explore the archaeological sites of Quenqo, Tambomachay, and Pucapucara, each with its own unique history and beauty. We conclude the tour by dropping you back at your hotel, ready to keep exploring this city steeped in history and culture. Join us and enjoy this unforgettable experience in Cusco!',
            'description_pt' => 'Descubra a majestade de Cusco em nosso city tour de dia completo. Partindo do seu hotel, você explorará a histórica Plaza de Armas e a impressionante Catedral. Em seguida, visitará o Templo Inca de Qoricancha, onde a arquitetura inca e espanhola se fundem em perfeita harmonia. Continuaremos até as ruínas de Sacsayhuaman, com vistas panorâmicas da cidade, e exploraremos os sítios arqueológicos de Quenqo, Tambomachay e Pucapucara, cada um com sua própria história e beleza únicas. Encerramos o passeio levando você de volta ao seu hotel, pronto para continuar explorando essa cidade repleta de história e cultura. Junte-se a nós e aproveite essa experiência inesquecível em Cusco!

Descubra a majestade de Cusco em nosso city tour de dia completo. Partindo do seu hotel, você explorará a histórica Plaza de Armas e a impressionante Catedral. Em seguida, visitará o Templo Inca de Qoricancha, onde a arquitetura inca e espanhola se fundem em perfeita harmonia. Continuaremos até as ruínas de Sacsayhuaman, com vistas panorâmicas da cidade, e exploraremos os sítios arqueológicos de Quenqo, Tambomachay e Pucapucara, cada um com sua própria história e beleza únicas. Encerramos o passeio levando você de volta ao seu hotel, pronto para continuar explorando essa cidade repleta de história e cultura. Junte-se a nós e aproveite essa experiência inesquecível em Cusco!',
            'recommendations_en' => "Bring your original passport\r\n Waterproof jacket / rain poncho\r\n Warm jacket\r\n Camera\r\n Drink herbal teas for altitude sickness",
            'recommendations_pt' => "Levar passaporte original\r\n Jaqueta impermeável / poncho para chuva\r\n Jaqueta grossa\r\n Câmera fotográfica\r\n Tomar infusões para a altitude",
            'notes_en' => 'Our hotel pickup and drop-off service covers the area around the Plaza de Armas in Cusco. If your hotel or accommodation is outside this area, we offer the option to pick you up at the Plaza de Armas in Cusco (exactly at the water fountain).',
            'notes_pt' => 'Nosso serviço de busca e retorno ao hotel cobre a área da Plaza de Armas de Cusco. Caso seu hotel ou acomodação esteja fora dessa área, oferecemos a opção de buscá-lo na Plaza de Armas de Cusco (exatamente na fonte de água).',
            'itinerary_en' => json_encode([
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19210.jpg',
                    'description' => 'Hotel pickup: The tour guide or driver will meet you in the hotel lobby.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => 'Plaza de Armas in Cusco: We\'ll explore the Plaza de Armas, the historic and cultural heart of the city. Visit the Cathedral and other colonial buildings.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Qoricancha (): We\'ll discover the famous Inca temple of Qoricancha. Learn about the fusion of Inca and Spanish architecture.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/image.jpg',
                    'description' => 'Sacsayhuaman (): We\'ll visit the impressive ruins of Sacsayhuaman. Learn about Inca architecture and enjoy panoramic views of Cusco.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => 'Quenqo (): Explore the archaeological complex of Quenqo. Discover the ceremonial structures carved directly into the rock.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19215.jpg',
                    'description' => 'Tambomachay (): Visit the archaeological remains of Tambomachay, known for its water fountains. Enjoy the tranquility of the surroundings.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19219.jpg',
                    'description' => 'Pucapucara (): Finally, visit Puca Pucara, a military site with impressive views. Discover its defensive structures and appreciate the Incas\' strategic planning. Remember to bring comfortable clothing, appropriate footwear and a camera to capture the beauty of these historic sites. Enjoy your city tour in Cusco!',
                ],
                [
                    'time' => null,
                    'image' => 'tours/image-1.jpg',
                    'description' => 'Return to hotel (): We\'ll conclude the tour by dropping you off at your hotel or at the Plaza de Armas in Cusco.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19210.jpg',
                    'description' => 'Busca no hotel: O guia turístico ou motorista irá buscá-lo no lobby do hotel.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => 'Plaza de Armas de Cusco: Exploraremos a Plaza de Armas, o coração histórico e cultural da cidade. Visite a Catedral e outros edifícios coloniais.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Qoricancha (): Descobriremos o famoso templo inca de Qoricancha. Aprenda sobre a fusão da arquitetura inca e espanhola.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/image.jpg',
                    'description' => 'Sacsayhuaman (): Visitaremos as impressionantes ruínas de Sacsayhuaman. Aprenda sobre a arquitetura inca e desfrute das vistas panorâmicas de Cusco.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => 'Quenqo (): Explore o complexo arqueológico de Quenqo. Descubra as estruturas cerimoniais esculpidas diretamente na rocha.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19215.jpg',
                    'description' => 'Tambomachay (): Conheça os vestígios arqueológicos de Tambomachay, famoso por suas fontes de água. Aproveite a tranquilidade do lugar.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19219.jpg',
                    'description' => 'Pucapucara (): Por fim, visite Puca Pucara, um sítio militar com vistas impressionantes. Descubra as estruturas defensivas e aprecie o planejamento estratégico dos incas. Lembre-se de levar roupas confortáveis, calçado adequado e uma câmera para registrar a beleza desses sítios históricos. Aproveite seu city tour em Cusco!',
                ],
                [
                    'time' => null,
                    'image' => 'tours/image-1.jpg',
                    'description' => 'Retorno ao hotel (): Finalizaremos o passeio levando você ao seu hotel ou à Plaza de Armas de Cusco.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                'Includes entrance ticket',
                'Professional guide',
                'Tourist transport with hotel pickup and drop-off',
                'Pickup at your hotel or Airbnb',
                'Includes tickets to each archaeological site',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                'Inclui ingresso de entrada',
                'Guia profissional',
                'Transporte turístico com busca e retorno ao hotel',
                'Busca no seu hotel ou Airbnb',
                'Inclui ingressos para cada sítio arqueológico',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 6)->update($data);
        }
    }
}
