<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour8Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'MONTANHA ARCO-ÍRIS DE 7 CORES',
            'description_en' => 'Embark on an unforgettable journey to the Rainbow Mountain, a natural wonder that will take your breath away. Departing early from Cusco, we set off to witness this one-of-a-kind geological spectacle. Also known as Vinicunca, this mountain owes its vibrant colors to the minerals that have accumulated in its layers over millions of years. Upon reaching the base, we begin our hike through dreamlike landscapes, with the snow-capped peak of Wiñay Ritty accompanying us throughout the adventure. As you reach the summit — over 5,000 meters above sea level — you\'ll be amazed by the sweeping panoramic views of the Andes and the vast multicolored mountain stretching out before your eyes. A sight that seems ripped from a dream, leaving you in absolute awe. After exploring and capturing the beauty of this unique place in the world, we begin our descent, carrying with us an indelible memory of this natural marvel. Join us for this one-of-a-kind experience!

Embark on an unforgettable journey to the Rainbow Mountain, a natural wonder that will take your breath away. Departing early from Cusco, we set off to witness this one-of-a-kind geological spectacle. Also known as Vinicunca, this mountain owes its vibrant colors to the minerals that have accumulated in its layers over millions of years. Upon reaching the base, we begin our hike through dreamlike landscapes, with the snow-capped peak of Wiñay Ritty accompanying us throughout the adventure. As you reach the summit — over 5,000 meters above sea level — you\'ll be amazed by the sweeping panoramic views of the Andes and the vast multicolored mountain stretching out before your eyes. A sight that seems ripped from a dream, leaving you in absolute awe. After exploring and capturing the beauty of this unique place in the world, we begin our descent, carrying with us an indelible memory of this natural marvel. Join us for this one-of-a-kind experience!',
            'description_pt' => 'Embarque em uma jornada inesquecível rumo à Montanha Arco-Íris, uma maravilha natural que irá tirar o seu fôlego. Saindo cedo de Cusco, nos dirigimos para testemunhar esse espetáculo geológico único. Conhecida também como Vinicunca, essa montanha deve suas cores vibrantes à presença de minerais que se depositaram em suas camadas ao longo de milhões de anos. Ao chegarmos à base, iniciamos nossa caminhada por paisagens de sonho, com o pico nevado de Wiñay Ritty nos acompanhando durante toda a aventura. Ao alcançar o cume — a mais de 5.000 metros acima do nível do mar — você ficará maravilhado com a vista panorâmica dos Andes e a imensidão da montanha multicolorida se estendendo diante dos seus olhos. Um espetáculo que parece saído de um sonho e que irá encantá-lo completamente. Após explorar e registrar a beleza desse lugar único no mundo, iniciamos a descida, levando conosco uma lembrança inesquecível dessa maravilha natural. Junte-se a nós nessa experiência única!

Embarque em uma jornada inesquecível rumo à Montanha Arco-Íris, uma maravilha natural que irá tirar o seu fôlego. Saindo cedo de Cusco, nos dirigimos para testemunhar esse espetáculo geológico único. Conhecida também como Vinicunca, essa montanha deve suas cores vibrantes à presença de minerais que se depositaram em suas camadas ao longo de milhões de anos. Ao chegarmos à base, iniciamos nossa caminhada por paisagens de sonho, com o pico nevado de Wiñay Ritty nos acompanhando durante toda a aventura. Ao alcançar o cume — a mais de 5.000 metros acima do nível do mar — você ficará maravilhado com a vista panorâmica dos Andes e a imensidão da montanha multicolorida se estendendo diante dos seus olhos. Um espetáculo que parece saído de um sonho e que irá encantá-lo completamente. Após explorar e registrar a beleza desse lugar único no mundo, iniciamos a descida, levando conosco uma lembrança inesquecível dessa maravilha natural. Junte-se a nós nessa experiência única!',
            'recommendations_en' => "Bring your original passport\r\n Waterproof jacket / rain poncho\r\n Warm jacket\r\n Camera\r\n Drink herbal teas for altitude",
            'recommendations_pt' => "Trazer passaporte original\r\n Jaqueta impermeável / poncho para chuva\r\n Jaqueta quente\r\n Câmera fotográfica\r\n Tomar infusões para a altitude",
            'notes_en' => 'Our hotel pick-up and drop-off service covers the area around the Plaza de Armas in Cusco. If your hotel or accommodation is outside this area, we offer the option to pick you up at the Plaza de Armas in Cusco (exactly at the water fountain).',
            'notes_pt' => 'Nosso serviço de busca e retorno ao hotel cobre a área da Plaza de Armas de Cusco. Se o seu hotel ou acomodação estiver fora dessa área, oferecemos a possibilidade de buscá-lo na Plaza de Armas de Cusco (exatamente na fonte de água).',
            'itinerary_en' => json_encode([
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19210.jpg',
                    'description' => 'We\'ll start early with a hotel pick-up in Cusco between 4:00 and 5:00 am, then head off toward the mountain. During the journey, you\'ll enjoy breathtaking scenery as you venture deeper into the Peruvian Andes.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => 'Around 7:00 am, we\'ll stop for a delicious buffet breakfast at a local restaurant (buffet breakfast included in the price), so you can fuel up for the adventure ahead.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'We\'ll continue our journey to the base of the Rainbow Mountain, arriving at approximately 9:00 am. Here our hike to the summit begins, where you\'ll witness the majestic snow-capped peak of Wiñay Ritty (Forever Young).',
                ],
                [
                    'time' => null,
                    'image' => 'tours/image.jpg',
                    'description' => 'After approximately three hours of hiking, you\'ll reach the summit of the Rainbow Mountain at around 12:00 pm. You\'ll have free time to explore and soak in the stunning panoramic views of the surrounding landscape. This is the perfect spot for photos and to appreciate the natural beauty all around you.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => 'At around 1:00 pm, we\'ll begin our descent back to the base of the mountain, where a delicious buffet lunch will be waiting at a local restaurant at 2:00 pm (buffet lunch included in the price). After enjoying the meal, we\'ll make our way back to Cusco, arriving at approximately 7:00 pm. Once in Cusco, we\'ll take you back to your hotel, where you can rest and reflect on this incredible experience at the Rainbow Mountain. Join us and live this unforgettable adventure in the Peruvian Andes!',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19210.jpg',
                    'description' => 'Começaremos cedo com a busca no seu hotel em Cusco, entre 4h e 5h da manhã, para então partirmos em direção à montanha. Durante o trajeto, você desfrutará de belas paisagens enquanto se aprofunda nos Andes peruanos.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => 'Por volta das 7h, faremos uma parada para um delicioso café da manhã buffet em um restaurante local (café da manhã buffet incluído no preço), para você recarregar as energias antes da aventura que está por vir.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Continuaremos nossa jornada até a base da Montanha de 7 Cores, chegando aproximadamente às 9h. Aqui começa nossa caminhada em direção ao cume, onde você testemunhará a majestade do pico nevado Wiñay Ritty (Para Sempre Jovem).',
                ],
                [
                    'time' => null,
                    'image' => 'tours/image.jpg',
                    'description' => 'Após aproximadamente três horas de caminhada, você chegará ao cume da Montanha de 7 Cores por volta do meio-dia. Haverá tempo livre para explorar e apreciar as deslumbrantes vistas panorâmicas ao redor. Este é o lugar perfeito para tirar fotos e contemplar a beleza natural que o rodeia.',
                ],
                [
                    'time' => null,
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => 'Por volta da 1h da tarde, iniciaremos a descida de volta à base da montanha, onde um delicioso almoço buffet nos aguardará em um restaurante local às 14h (almoço buffet incluído no preço). Após saborear a refeição, partiremos de volta a Cusco, chegando aproximadamente às 19h. Uma vez em Cusco, levaremos você de volta ao seu hotel, onde poderá descansar e relembrar essa incrível experiência na Montanha de 7 Cores. Junte-se a nós e viva essa aventura inesquecível nos Andes peruanos!',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                'Entrance ticket included',
                'Professional guide',
                'Tourist transport with hotel pick-up and drop-off',
                'Pick-up at your hotel or Airbnb',
                'Buffet breakfast included',
                'Buffet lunch included',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                'Ingresso de entrada incluído',
                'Guia profissional',
                'Transporte turístico com busca e retorno ao hotel',
                'Busca no seu hotel ou Airbnb',
                'Café da manhã buffet incluído',
                'Almoço buffet incluído',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 8)->update($data);
        }
    }
}
