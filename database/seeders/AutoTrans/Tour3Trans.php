<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour3Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'Full Day Lima: Centro Histórico, Pachacamac, Miraflores e Parque das Águas',
            'description_en' => 'Discover the cultural and archaeological richness of Lima on this full-day tour. Visit the Sanctuary of Pachacamac, explore the charming districts of Barranco and Miraflores, and marvel at the history of the historic center. End the day with a spectacular light show at the Parque de las Aguas. An experience that combines history, art, and gastronomy all in one day.' . "\n\n" . 'Discover the cultural and archaeological richness of Lima on this full-day tour. Visit the Sanctuary of Pachacamac, explore the charming districts of Barranco and Miraflores, and marvel at the history of the historic center. End the day with a spectacular light show at the Parque de las Aguas. An experience that combines history, art, and gastronomy all in one day.',
            'description_pt' => 'Descubra a riqueza cultural e arqueológica de Lima neste tour de dia completo. Visite o Santuário de Pachacamac, explore os encantadores bairros de Barranco e Miraflores e maravilhe-se com a história do centro histórico. Finalize com um espetáculo de luzes no Parque das Águas. Uma experiência que combina história, arte e gastronomia em um único dia.' . "\n\n" . 'Descubra a riqueza cultural e arqueológica de Lima neste tour de dia completo. Visite o Santuário de Pachacamac, explore os encantadores bairros de Barranco e Miraflores e maravilhe-se com a história do centro histórico. Finalize com um espetáculo de luzes no Parque das Águas. Uma experiência que combina história, arte e gastronomia em um único dia.',
            'recommendations_en' => 'Comfortable clothing and shoes.' . "\r\n" . ' Sunglasses' . "\r\n" . ' Sunscreen' . "\r\n" . ' A hat' . "\r\n" . ' Water',
            'recommendations_pt' => 'Roupas e sapatos confortáveis.' . "\r\n" . ' Óculos de sol' . "\r\n" . ' Protetor solar' . "\r\n" . ' Um chapéu' . "\r\n" . ' Água',
            'notes_en' => 'Our hotel pickup and drop-off service covers the Miraflores, Barranco, and San Isidro districts. If your hotel or accommodation is outside these districts, we offer the option to pick you up at Parque del Amor (MIRAFLORES).',
            'notes_pt' => 'Nosso serviço de busca e retorno ao hotel cobre as áreas de Miraflores, Barranco e San Isidro. Se o seu hotel ou acomodação estiver fora desses distritos, oferecemos a possibilidade de buscá-lo no Parque del Amor (MIRAFLORES).',
            'itinerary_en' => json_encode([
                [
                    'time' => 'Hotel Departure',
                    'image' => 'tours/5.jpg',
                    'description' => 'We will meet you in your hotel lobby to begin our tour.',
                ],
                [
                    'time' => 'Pachacamac Sanctuary',
                    'image' => 'tours/pachacamac-foto-interna-desktop.jpg',
                    'description' => 'We will explore the Sanctuary of Pachacamac, a pre-Inca archaeological site that served as an important religious and ceremonial center. Discover the ruins of temples and pyramids while learning about the history and culture of the region.',
                ],
                [
                    'time' => 'Barranco',
                    'image' => 'tours/el-puente-de-los-suspiros_3.webp',
                    'description' => 'We will visit the charming district of Barranco, known for its colorful streets and bohemian atmosphere. We will see the iconic Bridge of Sighs, a wooden structure offering panoramic views of the Pacific Ocean. We will also visit Barranco\'s main square, its church, and nearby monuments.',
                ],
                [
                    'time' => 'Lunch in Barranco or Miraflores',
                    'image' => 'tours/image.jpg',
                    'description' => 'Enjoy a delicious lunch at a local restaurant in Barranco or the neighboring district of Miraflores, where you can savor the varied and exquisite Peruvian cuisine.',
                ],
                [
                    'time' => 'Miraflores',
                    'image' => 'tours/2017_Lima_-_Escultura_El_beso_en_el_Parque_del_Amor.jpg',
                    'description' => 'We will explore the district of Miraflores, one of Lima\'s most modern and vibrant neighborhoods. We will visit Parque Kennedy, a popular spot among locals and visitors, and enjoy its lively atmosphere. Then, we will head to Huaca Pucllana, an archaeological site in the heart of the city that offers a fascinating glimpse into pre-Inca history.',
                ],
                [
                    'time' => 'Lima City Tour',
                    'image' => 'tours/FULL-DAY-LIMA-ANCESTRAL-5-1-1.jpg',
                    'description' => 'We will tour Lima\'s historic center, a UNESCO World Heritage Site. You will see the Plaza de Armas, the heart of the city, home to Lima Cathedral, the Government Palace, and other historic buildings. We will also visit the Convent of San Francisco, famous for its underground Catacombs.',
                ],
                [
                    'time' => 'Parque de las Aguas',
                    'image' => 'tours/CENTRO-HISTORICO-DE-LIMA-PARQUE-DE-LAS-AGUAS-7.jpg',
                    'description' => 'We will end our tour with a visit to the Parque de las Aguas, a theme park featuring spectacular illuminated fountains. Enjoy a dazzling water and light show in a magical and relaxing setting.' . "\r\n\r\n" . 'This itinerary will take you to discover the best of Lima, from its ancient ruins to its modern districts, offering you a complete and memorable experience in the Peruvian capital.',
                ],
                [
                    'time' => 'Return to Hotel',
                    'image' => 'tours/5.jpg',
                    'description' => 'After enjoying the beauty and history, we will return you to your hotel to conclude our tour.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                [
                    'time' => 'Partida do hotel',
                    'image' => 'tours/5.jpg',
                    'description' => 'Nos encontraremos no lobby do seu hotel para iniciar nosso passeio.',
                ],
                [
                    'time' => 'Santuário de Pachacamac',
                    'image' => 'tours/pachacamac-foto-interna-desktop.jpg',
                    'description' => 'Exploraremos o Santuário de Pachacamac, um sítio arqueológico pré-inca que foi um importante centro religioso e cerimonial. Descubra as ruínas de templos e pirâmides enquanto aprende sobre a história e a cultura da região.',
                ],
                [
                    'time' => 'Barranco',
                    'image' => 'tours/el-puente-de-los-suspiros_3.webp',
                    'description' => 'Visitaremos o encantador bairro de Barranco, conhecido por suas ruas coloridas e atmosfera boêmia. Conheceremos a emblemática Ponte dos Suspiros, uma estrutura de madeira que oferece vistas panorâmicas do Oceano Pacífico. Também visitaremos a praça principal de Barranco, sua igreja e monumentos próximos.',
                ],
                [
                    'time' => 'Almoço em Barranco ou Miraflores',
                    'image' => 'tours/image.jpg',
                    'description' => 'Desfrute de um delicioso almoço em um restaurante local em Barranco ou no bairro vizinho de Miraflores, onde você poderá saborear a variada e requintada gastronomia peruana.',
                ],
                [
                    'time' => 'Miraflores',
                    'image' => 'tours/2017_Lima_-_Escultura_El_beso_en_el_Parque_del_Amor.jpg',
                    'description' => 'Exploraremos o bairro de Miraflores, um dos mais modernos e vibrantes de Lima. Conheceremos o Parque Kennedy, um local popular entre moradores e visitantes, e aproveite sua atmosfera animada. Em seguida, visitaremos a Huaca Pucllana, um sítio arqueológico no meio da cidade que oferece uma visão fascinante da história pré-inca.',
                ],
                [
                    'time' => 'City tour Lima',
                    'image' => 'tours/FULL-DAY-LIMA-ANCESTRAL-5-1-1.jpg',
                    'description' => 'Realizaremos um passeio pelo centro histórico de Lima, declarado Patrimônio da Humanidade pela Unesco. Você conhecerá a Plaza de Armas, o coração da cidade, onde se encontram a Catedral de Lima, o Palácio do Governo e outros edifícios históricos. Também visitaremos o Convento de São Francisco, famoso pelas suas Catacumbas subterrâneas.',
                ],
                [
                    'time' => 'Parque das Águas',
                    'image' => 'tours/CENTRO-HISTORICO-DE-LIMA-PARQUE-DE-LAS-AGUAS-7.jpg',
                    'description' => 'Encerraremos nosso tour com uma visita ao Parque das Águas, um parque temático com espetaculares fontes iluminadas. Desfrute de um show de água e luz em um ambiente mágico e relaxante.' . "\r\n\r\n" . 'Este roteiro levará você a descobrir o melhor de Lima, desde suas ruínas antigas até seus bairros modernos, proporcionando uma experiência completa e inesquecível na capital peruana.',
                ],
                [
                    'time' => 'Retorno ao hotel',
                    'image' => 'tours/5.jpg',
                    'description' => 'Após desfrutar da beleza e da história, retornaremos ao seu hotel para encerrar nosso tour.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                'Entrance tickets to all museums included',
                'Professional guide',
                'Tourist transport, pickup and drop-off',
                'Pickup at your hotel or Airbnb',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                'Ingressos para todos os museus incluídos',
                'Guia profissional',
                'Transporte turístico, busca e retorno',
                'Busca no seu hotel ou Airbnb',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 3)->update($data);
        }
    }
}
