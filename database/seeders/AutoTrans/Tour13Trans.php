<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour13Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'TOUR PRIVADO DE 2 DIAS PARA MACHU PICCHU (TUDO INCLUÍDO)',
            'description_en' => 'Discover the majesty of Machu Picchu on a 2-day tour from Cusco. On this journey, you\'ll enjoy a scenic train ride to Aguas Calientes, the base town for exploring Machu Picchu. Learn about the history and culture of this ancient Inca city on a guided tour, and enjoy free time to explore at your own pace. An itinerary full of adventures and unforgettable experiences in one of the most iconic places in the world.

Discover the majesty of Machu Picchu on a 2-day tour from Cusco. On this journey, you\'ll enjoy a scenic train ride to Aguas Calientes, the base town for exploring Machu Picchu. Learn about the history and culture of this ancient Inca city on a guided tour, and enjoy free time to explore at your own pace. An itinerary full of adventures and unforgettable experiences in one of the most iconic places in the world.',
            'description_pt' => 'Descubra a majestade de Machu Picchu em um tour de 2 dias saindo de Cusco. Nessa viagem, você desfrutará de um passeio panorâmico de trem até Aguas Calientes, a cidade base para explorar Machu Picchu. Conheça a história e a cultura dessa antiga cidade inca em um passeio guiado e aproveite o tempo livre para explorar por conta própria. Um roteiro repleto de aventuras e experiências inesquecíveis em um dos lugares mais icônicos do mundo.

Descubra a majestade de Machu Picchu em um tour de 2 dias saindo de Cusco. Nessa viagem, você desfrutará de um passeio panorâmico de trem até Aguas Calientes, a cidade base para explorar Machu Picchu. Conheça a história e a cultura dessa antiga cidade inca em um passeio guiado e aproveite o tempo livre para explorar por conta própria. Um roteiro repleto de aventuras e experiências inesquecíveis em um dos lugares mais icônicos do mundo.',
            'recommendations_en' => 'Bring original passport
Waterproof jacket / rain poncho
Warm jacket
Camera
Take altitude remedies
Bring original ID documents',
            'recommendations_pt' => 'Levar passaporte original
Jaqueta impermeável / capa de chuva
Jaqueta grossa
Câmera fotográfica
Tomar infusões para a altitude
Levar documentos de identidade originais',
            'notes_en' => 'Our hotel pick-up and drop-off service covers the Plaza de Armas area of Cusco. If your hotel or accommodation is outside this area, we offer the option of picking you up at the Plaza de Armas in Cusco (exactly at the water fountain).',
            'notes_pt' => 'Nosso serviço de traslado de ida e volta ao seu hotel cobre a região da Plaza de Armas de Cusco. Se o seu hotel ou acomodação estiver fora dessa área, oferecemos a opção de buscá-lo na Plaza de Armas de Cusco (exatamente na fonte d\'água).',
            'itinerary_en' => json_encode([
                ['time' => '8:00 am hotel pick-up', 'image' => 'tours/classic-hotel-reception-design.jpg', 'description' => 'We begin by picking you up in the lobby of your hotel to start our tour.'],
                ['time' => '8:30 am journey to Ollantaytambo', 'image' => 'tours/estaciones-perurail.webp', 'description' => 'After picking you up from the hotel, we begin our transfer from Cusco in comfortable transportation to the train station in the town of Ollantaytambo (approximately 2h 30min journey).'],
                ['time' => '11:00 am arrival in Ollantaytambo', 'image' => 'tours/12_coche_observatorio_perurail_titicaca.jpg', 'description' => 'From there, we board a scenic panoramic train to Aguas Calientes, the charming town nestled at the foot of Machu Picchu.'],
                ['time' => '1:00 pm arrival in Aguas Calientes', 'image' => 'tours/pueblo-machu-picchu.jpg', 'description' => 'Upon arriving in Aguas Calientes, our guide will be waiting at the station with a sign bearing your name for a warm welcome. After check-in at the hotel, you\'ll have free time to relax and explore this picturesque town, full of colorful streets and charming local shops.'],
                ['time' => '8:30 pm overnight stay in Aguas Calientes', 'image' => 'tours/salon-principal-scaled.jpg', 'description' => 'In the evening, you can head out to enjoy a delicious dinner at one of the local restaurants, where you can sample authentic Peruvian cuisine (meals not included in the price). Afterward, you\'ll stay overnight at the designated hotel (hotel included in the price) and prepare for the exciting day ahead.'],
                ['time' => '7:00am bus from Aguas Calientes to Machu Picchu', 'image' => 'tours/bus-machu-picchu-400.jpg', 'description' => 'After an early breakfast, we board the bus that will take us from Aguas Calientes to the entrance of Machu Picchu, where our adventure in this archaeological wonder begins.'],
                ['time' => 'Machu Picchu entry time subject to the schedule coordinated with the passenger', 'image' => 'tours/3a62b5d265cef8a3f300145c765404bb.jpg', 'description' => 'Upon entering the Llaqta, we will tour Machu Picchu with our expert guide, who will lead us through the main points of interest of the Llaqta of Machu Picchu (approximately 2 to 3 hours) until we have explored the entire citadel, sharing the fascinating history and culture of this ancient Inca city. You will also have free time to explore on your own, take photos, and soak in the mystical atmosphere of this unique architectural site.'],
                ['time' => 'Free afternoon', 'image' => 'tours/Machu_Picchu_Peru_-_Laslovarga_262-scaled.jpg', 'description' => 'We return to Aguas Calientes by bus, where you\'ll have free time to have lunch and buy some souvenirs from your visit to Machu Picchu.'],
                ['time' => '5:00 pm return to Ollantaytambo', 'image' => 'tours/VIO-viajes-portada.jpg', 'description' => 'In the afternoon, we board the train back to Ollantaytambo, enjoying stunning panoramic views on the return journey.'],
                ['time' => '9:00 pm arrival in Ollantaytambo', 'image' => 'tours/default_header_2.webp', 'description' => 'Once in Ollantaytambo, you will be transferred back to Cusco by bus operated by Inca Rail.'],
                ['time' => '11:00 pm arrival in Cusco', 'image' => 'tours/classic-hotel-reception-design.jpg', 'description' => 'Once you arrive at the bus station in Cusco, we will pick you up and take you back to your hotel, where you can rest and reflect on this unforgettable experience in Machu Picchu.'],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                ['time' => '8:00 am busca no hotel', 'image' => 'tours/classic-hotel-reception-design.jpg', 'description' => 'Iniciamos buscando você no saguão do seu hotel para começar nosso roteiro.'],
                ['time' => '8:30 am viagem a Ollantaytambo', 'image' => 'tours/estaciones-perurail.webp', 'description' => 'Após a busca no hotel, iniciamos nosso traslado desde Cusco em um veículo confortável até a estação de trem da cidade de Ollantaytambo (aproximadamente 2h 30min de viagem).'],
                ['time' => '11:00 am chegada a Ollantaytambo', 'image' => 'tours/12_coche_observatorio_perurail_titicaca.jpg', 'description' => 'De lá, embarcamos em um trem panorâmico e pitoresco com destino a Aguas Calientes, a encantadora cidade situada aos pés de Machu Picchu.'],
                ['time' => '13:00 chegada a Aguas Calientes', 'image' => 'tours/pueblo-machu-picchu.jpg', 'description' => 'Ao chegar em Aguas Calientes, nosso guia estará esperando na estação com uma placa com o seu nome para uma recepção acolhedora. Após o check-in no hotel, você terá tempo livre para relaxar e explorar essa charmosa cidade, cheia de ruas coloridas e lojas locais encantadoras.'],
                ['time' => '20:30 pernoite em Aguas Calientes', 'image' => 'tours/salon-principal-scaled.jpg', 'description' => 'À noite, você pode sair para desfrutar de um delicioso jantar em um dos restaurantes locais, onde poderá provar a autêntica culinária peruana (refeições não incluídas no preço). Depois, você pernoitará no hotel indicado (hotel incluído no preço) e se preparará para o emocionante dia seguinte.'],
                ['time' => '7:00 ônibus de Aguas Calientes para Machu Picchu', 'image' => 'tours/bus-machu-picchu-400.jpg', 'description' => 'Após um café da manhã cedo, embarcamos no ônibus que nos levará de Aguas Calientes até a entrada de Machu Picchu, onde começa nossa aventura nesta maravilha arqueológica.'],
                ['time' => 'O horário de entrada em Machu Picchu está sujeito ao horário combinado com o passageiro', 'image' => 'tours/3a62b5d265cef8a3f300145c765404bb.jpg', 'description' => 'Ao entrar na Llaqta, percorreremos Machu Picchu com nosso guia especializado, que nos conduzirá pelos principais pontos de interesse da Llaqta de Machu Picchu (duração de 2 a 3 horas aproximadamente) até concluirmos o tour pela cidadela, compartilhando a fascinante história e cultura dessa antiga cidade inca. Você também terá tempo livre para explorar por conta própria, tirar fotos e se encantar com a atmosfera mística deste lugar arquitetônico único.'],
                ['time' => 'Tarde livre', 'image' => 'tours/Machu_Picchu_Peru_-_Laslovarga_262-scaled.jpg', 'description' => 'Retornamos a Aguas Calientes de ônibus, onde você terá tempo livre para almoçar e comprar algumas lembranças da sua visita a Machu Picchu.'],
                ['time' => '17:00 retorno a Ollantaytambo', 'image' => 'tours/VIO-viajes-portada.jpg', 'description' => 'À tarde, embarcamos no trem de volta a Ollantaytambo, desfrutando de vistas panorâmicas deslumbrantes no caminho de retorno.'],
                ['time' => '21:00 chegada a Ollantaytambo', 'image' => 'tours/default_header_2.webp', 'description' => 'Uma vez em Ollantaytambo, você será transferido de volta a Cusco em um ônibus operado pela Inca Rail.'],
                ['time' => '23:00 chegada a Cusco', 'image' => 'tours/classic-hotel-reception-design.jpg', 'description' => 'Ao chegar à rodoviária em Cusco, iremos buscá-lo e levá-lo de volta ao seu hotel, onde você poderá descansar e refletir sobre essa experiência inesquecível em Machu Picchu.'],
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                'Transfer from your hotel to the train station in Ollantaytambo',
                'Train from Ollantaytambo to Aguas Calientes',
                '1 night in a hotel in Aguas Calientes (based on a single room)',
                'Bus from Aguas Calientes to the entrance of Machu Picchu (round trip)',
                'Entrance ticket to Machu Picchu',
                'Professional tour guide at Machu Picchu',
                'Train from Aguas Calientes to Ollantaytambo',
                'Bus from the train company from Ollantaytambo to Cusco',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                'Traslado do seu hotel até a estação de trem em Ollantaytambo',
                'Trem de Ollantaytambo a Aguas Calientes',
                '1 noite em hotel em Aguas Calientes (em base a quarto individual)',
                'Ônibus de Aguas Calientes até a entrada de Machu Picchu (ida e volta)',
                'Ingresso de entrada em Machu Picchu',
                'Guia profissional de turismo em Machu Picchu',
                'Trem de Aguas Calientes a Ollantaytambo',
                'Ônibus da empresa de trens de Ollantaytambo a Cusco',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 13)->update($data);
        }
    }
}
