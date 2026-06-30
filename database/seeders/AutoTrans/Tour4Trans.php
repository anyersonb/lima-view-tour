<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour4Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'CITY TOUR À TARDE + PARQUE DAS ÁGUAS',
            'description_en' => 'Enjoy a unique experience exploring Lima\'s historic center at night, where you can admire the majesty of the Plaza Mayor, the imposing Lima Cathedral, the Government Palace, and the Convent of San Francisco. Then immerse yourself in a spectacular light and water show at the Parque de las Aguas, where the fountains come to life in a fascinating display of lights projected onto the water. A tour that combines history, culture, and entertainment in one of Latin America\'s most vibrant cities.' . "\n\n" . 'Enjoy a unique experience exploring Lima\'s historic center at night, where you can admire the majesty of the Plaza Mayor, the imposing Lima Cathedral, the Government Palace, and the Convent of San Francisco. Then immerse yourself in a spectacular light and water show at the Parque de las Aguas, where the fountains come to life in a fascinating display of lights projected onto the water. A tour that combines history, culture, and entertainment in one of Latin America\'s most vibrant cities.',
            'description_pt' => 'Desfrute de uma experiência única explorando o centro histórico de Lima à noite, onde poderá admirar a majestade da Plaza Mayor, a imponente Catedral de Lima, o Palácio do Governo e o Convento de San Francisco. Em seguida, mergulhe em um espetáculo de luzes e água no Parque das Águas, onde as fontes ganham vida em um fascinante show de luzes projetadas na água. Um passeio que combina história, cultura e entretenimento em uma das cidades mais vibrantes da América Latina.' . "\n\n" . 'Desfrute de uma experiência única explorando o centro histórico de Lima à noite, onde poderá admirar a majestade da Plaza Mayor, a imponente Catedral de Lima, o Palácio do Governo e o Convento de San Francisco. Em seguida, mergulhe em um espetáculo de luzes e água no Parque das Águas, onde as fontes ganham vida em um fascinante show de luzes projetadas na água. Um passeio que combina história, cultura e entretenimento em uma das cidades mais vibrantes da América Latina.',
            'recommendations_en' => "Comfortable clothing and shoes.\r\n Sunglasses\r\n Sunscreen\r\n A hat\r\n Water",
            'recommendations_pt' => "Roupas e calçados confortáveis.\r\n Óculos de sol\r\n Protetor solar\r\n Um chapéu\r\n Água",
            'notes_en' => 'Our hotel pickup and drop-off service covers the Miraflores, Barranco, and San Isidro areas. If your hotel or accommodation is outside these districts, we offer the option to pick you up at the Parque del Amor (MIRAFLORES).',
            'notes_pt' => 'Nosso serviço de traslado de ida e volta ao hotel cobre as áreas de Miraflores, Barranco e San Isidro. Caso o seu hotel ou acomodação esteja fora desses distritos, oferecemos a opção de buscá-lo no Parque del Amor (MIRAFLORES).',
            'includes_en' => json_encode([
                'Admission tickets to all museums included',
                'Professional guide',
                'Tourist transport, hotel pickup and drop-off',
                'Pickup from your hotel or Airbnb',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                'Ingressos para todos os museus incluídos',
                'Guia profissional',
                'Transporte turístico com traslado de ida e volta ao hotel',
                'Busca no seu hotel ou Airbnb',
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_en' => json_encode([
                [
                    'time' => 'Inicio en el hotel',
                    'image' => 'tours/Rectangle-19210.jpg',
                    'description' => 'We will meet in your hotel lobby to begin our tour of Lima\'s historic center and the Parque de las Aguas.',
                ],
                [
                    'time' => 'Centro Histórico de Lima de noche',
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => null,
                ],
                [
                    'time' => 'Plaza Mayor',
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Our first stop will be the Plaza Mayor, where we can admire the majestic illuminated colonial architecture and enjoy the nighttime atmosphere of this important historic landmark.',
                ],
                [
                    'time' => 'Monumentos Históricos',
                    'image' => 'tours/image.jpg',
                    'description' => 'We will view various monuments and colonial republican-style houses with balconies, etc.',
                ],
                [
                    'time' => 'Catedral de Lima',
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => 'We will visit the Lima Cathedral, one of the city\'s main churches, renowned for its architectural beauty and rich history.',
                ],
                [
                    'time' => 'Palacio de Gobierno',
                    'image' => 'tours/Rectangle-19215.jpg',
                    'description' => 'We will continue our tour to the Government Palace, where we can watch the changing of the guard, a traditional ceremony held every evening.',
                ],
                [
                    'time' => 'Convento de San Francisco',
                    'image' => 'tours/Rectangle-19219.jpg',
                    'description' => 'We will conclude our tour of the historic center at the Convent of San Francisco, where we can explore its catacombs and learn more about Lima\'s colonial history.',
                ],
                [
                    'time' => 'Parque de las Aguas',
                    'image' => 'tours/image-1.jpg',
                    'description' => 'Light and water show — after enjoying the historic center, we will head to the Parque de las Aguas to enjoy its light and water show, where we can see the illuminated fountains synchronized with music in a one-of-a-kind spectacle.',
                ],
                [
                    'time' => 'Regreso al hotel',
                    'image' => null,
                    'description' => 'After enjoying the beauty and history of Lima\'s historic center and the Parque de las Aguas, we will return to your hotel to conclude our tour.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                [
                    'time' => 'Inicio en el hotel',
                    'image' => 'tours/Rectangle-19210.jpg',
                    'description' => 'Nos encontraremos no lobby do seu hotel para iniciar nosso passeio pelo centro histórico de Lima e o Parque das Águas.',
                ],
                [
                    'time' => 'Centro Histórico de Lima de noche',
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => null,
                ],
                [
                    'time' => 'Plaza Mayor',
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Nossa primeira parada será na Plaza Mayor, onde poderemos admirar a majestosa arquitetura colonial iluminada e aproveitar a atmosfera noturna deste importante espaço histórico.',
                ],
                [
                    'time' => 'Monumentos Históricos',
                    'image' => 'tours/image.jpg',
                    'description' => 'Visualizaremos alguns monumentos e casas com varandas coloniais republicanas, etc.',
                ],
                [
                    'time' => 'Catedral de Lima',
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => 'Visitaremos a Catedral de Lima, uma das principais igrejas da cidade, destacada por sua beleza arquitetônica e sua história.',
                ],
                [
                    'time' => 'Palacio de Gobierno',
                    'image' => 'tours/Rectangle-19215.jpg',
                    'description' => 'Continuaremos nosso passeio em direção ao Palácio do Governo, onde poderemos assistir à troca da guarda, uma cerimônia tradicional realizada todas as noites.',
                ],
                [
                    'time' => 'Convento de San Francisco',
                    'image' => 'tours/Rectangle-19219.jpg',
                    'description' => 'Concluiremos nosso passeio pelo centro histórico no Convento de San Francisco, onde poderemos explorar suas catacumbas e conhecer mais sobre a história colonial de Lima.',
                ],
                [
                    'time' => 'Parque de las Aguas',
                    'image' => 'tours/image-1.jpg',
                    'description' => 'Espetáculo de luzes e água — após desfrutar do centro histórico, nos dirigiremos ao Parque das Águas para aproveitar seu espetáculo de luzes e água, onde poderemos ver as fontes iluminadas e sincronizadas com música em um espetáculo único.',
                ],
                [
                    'time' => 'Regreso al hotel',
                    'image' => null,
                    'description' => 'Após desfrutar da beleza e da história do centro histórico de Lima e do Parque das Águas, retornaremos ao seu hotel para concluir nosso tour.',
                ],
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 4)->update($data);
        }
    }
}
