<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour12Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'CONHEÇA MACHU PICCHU SEM ENTRADA',
            'description_en' => 'Discover the majesty of Machu Picchu on a 2-day tour from Cusco. On this journey, you\'ll enjoy a scenic train ride to Aguas Calientes, the base town for exploring Machu Picchu. Learn about the history and culture of this ancient Inca city on a guided visit, and enjoy free time to explore on your own. An itinerary packed with adventures and unforgettable experiences at one of the world\'s most iconic destinations.' . "\n\n" . 'Discover the majesty of Machu Picchu on a 2-day tour from Cusco. On this journey, you\'ll enjoy a scenic train ride to Aguas Calientes, the base town for exploring Machu Picchu. Learn about the history and culture of this ancient Inca city on a guided visit, and enjoy free time to explore on your own. An itinerary packed with adventures and unforgettable experiences at one of the world\'s most iconic destinations.',
            'description_pt' => 'Descubra a majestade de Machu Picchu em um tour de 2 dias saindo de Cusco. Nesta viagem, você vai desfrutar de uma pitoresca viagem de trem até Aguas Calientes, a cidade base para explorar Machu Picchu. Conheça a história e a cultura desta antiga cidade inca em uma visita guiada e aproveite o tempo livre para explorar por conta própria. Um roteiro repleto de aventuras e experiências inesquecíveis em um dos destinos mais emblemáticos do mundo.' . "\n\n" . 'Descubra a majestade de Machu Picchu em um tour de 2 dias saindo de Cusco. Nesta viagem, você vai desfrutar de uma pitoresca viagem de trem até Aguas Calientes, a cidade base para explorar Machu Picchu. Conheça a história e a cultura desta antiga cidade inca em uma visita guiada e aproveite o tempo livre para explorar por conta própria. Um roteiro repleto de aventuras e experiências inesquecíveis em um dos destinos mais emblemáticos do mundo.',
            'recommendations_en' => "Bring your original passport\r\n Waterproof jacket / rain poncho\r\n Warm jacket\r\n Camera\r\n Drink altitude teas\r\n Bring original ID documents",
            'recommendations_pt' => "Levar passaporte original\r\n Jaqueta impermeável / poncho para chuva\r\n Jaqueta quente\r\n Câmera fotográfica\r\n Tomar infusões para a altitude\r\n Levar documentos de identidade originais",
            'notes_en' => 'Our hotel pickup and drop-off service covers the Plaza de Armas area of Cusco. If your hotel or accommodation is outside this area, we offer the option to meet you at the Plaza de Armas de Cusco (right at the water fountain).',
            'notes_pt' => 'Nosso serviço de traslado de ida e volta ao hotel cobre a área da Plaza de Armas de Cusco. Se o seu hotel ou acomodação estiver fora desta área, oferecemos a opção de buscá-lo na Plaza de Armas de Cusco (exatamente na fonte d\'água).',
            'itinerary_en' => json_encode([
                [
                    'time' => '8:00 am hotel pickup',
                    'image' => null,
                    'description' => 'We\'ll begin by picking you up at your hotel lobby to start our tour.',
                ],
                [
                    'time' => '8:30 am journey to Ollantaytambo',
                    'image' => null,
                    'description' => 'After picking you up from the hotel, we\'ll start our transfer from Cusco in a comfortable vehicle to the train station in the town of Ollantaytambo (approximately 2h 30min journey).',
                ],
                [
                    'time' => '11:00 am arrival at Ollantaytambo',
                    'image' => null,
                    'description' => 'From there, we\'ll board a scenic panoramic train to Aguas Calientes, the charming town nestled at the foot of Machu Picchu.',
                ],
                [
                    'time' => '1:00 pm arrival at Aguas Calientes',
                    'image' => null,
                    'description' => 'Upon arriving at Aguas Calientes, our guide will be waiting at the station with a sign bearing your name to give you a warm welcome. After receiving instructions, we\'ll check in to the hotel. You\'ll then have free time to relax and explore this picturesque town, full of colorful streets and charming local shops.',
                ],
                [
                    'time' => '8:30 pm overnight stay in Aguas Calientes',
                    'image' => null,
                    'description' => 'In the evening, you\'ll be free to enjoy a delicious dinner at one of the local restaurants, where you can taste authentic Peruvian cuisine. Afterward, you\'ll spend the night at the hotel (included in the price) and get ready for the exciting day ahead.',
                ],
                [
                    'time' => '7:00 am bus from Aguas Calientes to Machu Picchu',
                    'image' => null,
                    'description' => 'After an early breakfast, we\'ll board the bus that will take us from the town of Aguas Calientes to the entrance of Machu Picchu, where our adventure in this archaeological wonder begins.',
                ],
                [
                    'time' => 'Machu Picchu entry is subject to the admission schedule coordinated with the passenger',
                    'image' => null,
                    'description' => 'Upon entering the Llaqta, we\'ll explore Machu Picchu with our expert guide, who will lead us through the main highlights of the Llaqta de Machu Picchu (approximately 2 to 3 hours) until we\'ve toured the entire citadel, learning about the fascinating history and culture of this ancient Inca city. You\'ll also have free time to explore on your own, take photos, and soak in the mystical atmosphere of this unique architectural site.',
                ],
                [
                    'time' => 'Free afternoon',
                    'image' => null,
                    'description' => 'We\'ll return to Aguas Calientes by bus, where you\'ll have free time to have lunch and pick up some souvenirs from your visit to Machu Picchu.',
                ],
                [
                    'time' => ':00 pm return to Ollantaytambo',
                    'image' => null,
                    'description' => 'In the afternoon, we\'ll board the train back to Ollantaytambo, enjoying the stunning panoramic views on the way back.',
                ],
                [
                    'time' => '9:00 pm arrival at Ollantaytambo',
                    'image' => null,
                    'description' => 'Once in Ollantaytambo, you\'ll be transferred back to Cusco by bus operated by Inca Rail.',
                ],
                [
                    'time' => '11:00 pm arrival at Cusco',
                    'image' => null,
                    'description' => 'Once you arrive at the bus station in Cusco, we\'ll pick you up and transfer you to your hotel, where you can rest and reflect on this unforgettable experience at Machu Picchu.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                [
                    'time' => '8:00 am busca no hotel',
                    'image' => null,
                    'description' => 'Começaremos buscando você no saguão do seu hotel para iniciar nosso roteiro.',
                ],
                [
                    'time' => '8:30 am viagem para Ollantaytambo',
                    'image' => null,
                    'description' => 'Após buscá-lo no hotel, iniciaremos nosso traslado de Cusco em um confortável veículo até a estação de trem na cidade de Ollantaytambo (aproximadamente 2h 30min de viagem).',
                ],
                [
                    'time' => '11:00 am chegada a Ollantaytambo',
                    'image' => null,
                    'description' => 'De lá, embarcaremos em uma pitoresca viagem panorâmica de trem até Aguas Calientes, a encantadora cidade situada aos pés de Machu Picchu.',
                ],
                [
                    'time' => '1:00 pm chegada a Aguas Calientes',
                    'image' => null,
                    'description' => 'Ao chegar a Aguas Calientes, nosso guia estará esperando na estação com uma placa com seu nome para recebê-lo confortavelmente. Após as instruções, faremos o check-in no hotel. Em seguida, você terá tempo livre para relaxar e explorar esta pitoresca cidade, cheia de ruas coloridas e encantadoras lojas locais.',
                ],
                [
                    'time' => '8:30 pm pernoite em Aguas Calientes',
                    'image' => null,
                    'description' => 'À noite, você poderá sair para desfrutar de um delicioso jantar em um dos restaurantes locais, onde poderá experimentar a autêntica culinária peruana. Em seguida, passará a noite no hotel (incluído no preço) e se preparará para o emocionante dia seguinte.',
                ],
                [
                    'time' => '7:00 am ônibus de Aguas Calientes para Machu Picchu',
                    'image' => null,
                    'description' => 'Após um café da manhã cedo, embarcaremos no ônibus que nos levará da cidade de Aguas Calientes até a entrada de Machu Picchu, onde começará nossa aventura nesta maravilha arqueológica.',
                ],
                [
                    'time' => 'O horário de entrada em Machu Picchu está sujeito ao agendamento acordado com o passageiro',
                    'image' => null,
                    'description' => 'Ao entrar na Llaqta, percorreremos Machu Picchu com nosso guia especialista, que nos conduzirá pelos principais pontos de interesse da Llaqta de Machu Picchu (duração aproximada de 2 a 3 horas) até completar o percurso pela cidadela, contando a fascinante história e cultura desta antiga cidade inca. Você também terá tempo livre para explorar por conta própria, tirar fotos e se deixar envolver pela mística atmosfera deste lugar arquitetônico único.',
                ],
                [
                    'time' => 'Tarde livre',
                    'image' => null,
                    'description' => 'Retornaremos a Aguas Calientes de ônibus, onde você terá tempo livre para almoçar e comprar algumas lembranças da sua visita a Machu Picchu.',
                ],
                [
                    'time' => ':00 pm retorno para Ollantaytambo',
                    'image' => null,
                    'description' => 'À tarde, embarcaremos no trem de volta para Ollantaytambo, aproveitando as impressionantes vistas panorâmicas no caminho de regresso.',
                ],
                [
                    'time' => '9:00 pm chegada a Ollantaytambo',
                    'image' => null,
                    'description' => 'Em Ollantaytambo, você será transferido de volta a Cusco em um ônibus operado pela Inca Rail.',
                ],
                [
                    'time' => '11:00 pm chegada a Cusco',
                    'image' => null,
                    'description' => 'Ao chegar à rodoviária em Cusco, buscaremos você e o transferiremos ao seu hotel, onde poderá descansar e refletir sobre esta inesquecível experiência em Machu Picchu.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                'Transfer from your hotel to the train station in Ollantaytambo',
                'Train from Ollantaytambo to Aguas Calientes',
                '1 night in a hotel in Aguas Calientes (based on single room occupancy)',
                'Bus from Aguas Calientes to the entrance of Machu Picchu (round trip)',
                'Machu Picchu entrance ticket',
                'Professional tourism guide at Machu Picchu',
                'Train from Aguas Calientes to Ollantaytambo',
                'Train company bus from Ollantaytambo to Cusco',
                'Transfer from the bus station to your hotel in Cusco',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                'Traslado do seu hotel até a estação de trem em Ollantaytambo',
                'Trem de Ollantaytambo a Aguas Calientes',
                '1 noite em hotel em Aguas Calientes (com base em quarto individual)',
                'Ônibus de Aguas Calientes até a entrada de Machu Picchu (ida e volta)',
                'Ingresso para Machu Picchu',
                'Guia profissional de turismo em Machu Picchu',
                'Trem de Aguas Calientes a Ollantaytambo',
                'Ônibus da empresa de trens de Ollantaytambo a Cusco',
                'Traslado da rodoviária até o seu hotel em Cusco',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 12)->update($data);
        }
    }
}
