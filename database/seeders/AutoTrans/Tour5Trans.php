<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour5Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'TOUR DE DIA COMPLETO AO OÁSIS DE HUACACHINA COM BUGGY PRIVADO (CANAM) + ILHAS BALLESTAS EM PARACAS',
            'description_en' => 'We will take you to Huacachina on a private tour to explore its stunning sand dunes, enjoy a thrilling buggy ride (canam) along its steep slopes, and experience the excitement of sandboarding. With its unique combination of adrenaline and natural beauty, Huacachina offers an unforgettable experience in the heart of the desert.
 In addition, we will visit Paracas, one of the most outstanding destinations in southern Lima, famous for its spectacular Ballestas Islands. During the boat tour, you will have the chance to get up close to the charming sea lions, amazing birds, and small penguins that inhabit the area. Join us on this unforgettable adventure!

We will take you to Huacachina on a private tour to explore its stunning sand dunes, enjoy a thrilling buggy ride (canam) along its steep slopes, and experience the excitement of sandboarding. With its unique combination of adrenaline and natural beauty, Huacachina offers an unforgettable experience in the heart of the desert.
 In addition, we will visit Paracas, one of the most outstanding destinations in southern Lima, famous for its spectacular Ballestas Islands. During the boat tour, you will have the chance to get up close to the charming sea lions, amazing birds, and small penguins that inhabit the area. Join us on this unforgettable adventure!',
            'description_pt' => 'Vamos levá-lo a Huacachina em um tour privado para explorar suas impressionantes dunas de areia, desfrutar de um emocionante passeio de buggy (canam) pelas suas íngremes encostas e vivenciar a emoção do sandboarding. Com sua combinação única de adrenalina e beleza natural, Huacachina oferece uma experiência inesquecível no meio do deserto.
 Além disso, visitaremos Paracas, um dos destinos mais destacados do sul de Lima, famoso pelas suas impressionantes Ilhas Ballestas. Durante o passeio de barco, você terá a oportunidade de observar de perto os encantadores leões-marinhos, incríveis aves e pequenos pinguins que habitam a região. Junte-se a nós nessa aventura inesquecível!

Vamos levá-lo a Huacachina em um tour privado para explorar suas impressionantes dunas de areia, desfrutar de um emocionante passeio de buggy (canam) pelas suas íngremes encostas e vivenciar a emoção do sandboarding. Com sua combinação única de adrenalina e beleza natural, Huacachina oferece uma experiência inesquecível no meio do deserto.
 Além disso, visitaremos Paracas, um dos destinos mais destacados do sul de Lima, famoso pelas suas impressionantes Ilhas Ballestas. Durante o passeio de barco, você terá a oportunidade de observar de perto os encantadores leões-marinhos, incríveis aves e pequenos pinguins que habitam a região. Junte-se a nós nessa aventura inesquecível!',
            'recommendations_en' => "Sunscreen\r\n Comfortable clothes and footwear for walking\r\n A hat\r\n Sunglasses",
            'recommendations_pt' => "Protetor solar\r\n Roupas e calçados confortáveis para caminhar\r\n Um chapéu\r\n Óculos de sol",
            'notes_en' => 'Our included transfer service covers the areas of Miraflores, Barranco, and San Isidro. If your hotel or accommodation is outside these districts, you can also head to our central meeting point (LARCOMAR shopping center, in front of the JW Marriott hotel).',
            'notes_pt' => 'Nosso serviço de traslado incluso abrange as áreas de Miraflores, Barranco e San Isidro. Se o seu hotel ou acomodação estiver fora desses distritos, você também pode se dirigir ao nosso ponto de encontro central (shopping LARCOMAR, em frente ao hotel JW Marriott).',
            'itinerary_en' => json_encode([
                [
                    'time' => '4:00 am',
                    'image' => 'tours/Rectangle-19210.jpg',
                    'description' => 'We will pick you up at your hotel.',
                ],
                [
                    'time' => '7:00 am',
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => 'We will make a brief stop for breakfast.',
                ],
                [
                    'time' => '9:00 am: Ballestas Islands',
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Arrival at the tourist pier where we will begin our excursion to the Ballestas Islands. During this tour we will take a boat ride where you will be able to appreciate',
                ],
                [
                    'time' => 'El Candelabro',
                    'image' => 'tours/image.jpg',
                    'description' => 'a large geoglyph very similar to the Nazca Lines. This island is naturally made of rocky formations that host important marine wildlife, including large colonies of guano birds (Guanay cormorant, Peruvian booby, Peruvian pelican, cormorant, Inca tern, etc.). We will also be able to spot sea lions, Humboldt penguins, and with a bit of luck, dolphins.',
                ],
                [
                    'time' => '1:00 pm: lunch',
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => 'We arrive in Ica for lunch (free time). We can enjoy some typical dishes such as "La Ruta del Pisco," duck rice, sopa seca, ceviche, and other dishes.',
                ],
                [
                    'time' => '2:00 pm: visit to the winery and tasting (Bodega Nietto)',
                    'image' => 'tours/Rectangle-19215.jpg',
                    'description' => 'We will take a tour of the winery to learn about the making of a great pisco, then we will taste wines, pisco creams, macerations, and pisco — all already included in our service. Those who wish may purchase.',
                ],
                [
                    'time' => '3:00 pm: visit to the Huacachina Oasis and dunes',
                    'image' => 'tours/Rectangle-19219.jpg',
                    'description' => 'We will discover the thrill of Huacachina! A unique experience at the largest oasis in South America.',
                ],
                [
                    'time' => 'Buggies in the desert',
                    'image' => 'tours/image-1.jpg',
                    'description' => 'We will experience the thrill of riding through the dunes in our private off-road vehicle (canam), driven by expert drivers who will take you across the sand dunes at full speed to enjoy breathtaking panoramic views — a 1-hour ride.',
                ],
                [
                    'time' => 'Sandboarding',
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => 'We will conquer the dunes with our sandboarding gear, sliding down the slopes on a board specially designed for sand. It is undoubtedly an exciting experience you cannot miss. After these adventure sports we will head to Chincha.',
                ],
                [
                    'time' => '7:00 pm: arrival in Chincha / Bodega Racimo de Uva',
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Chincha will be our last stop to taste some pisco macerations and pure piscos. You will also be able to purchase mamajuanas and try some typical Chincha desserts such as tejas and chocotejas.',
                ],
                [
                    'time' => '7:00 pm: return to Lima',
                    'image' => 'tours/image.jpg',
                    'description' => null,
                ],
                [
                    'time' => '10:00 pm: arrival at your hotel.',
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => null,
                ],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                [
                    'time' => '4:00 am',
                    'image' => 'tours/Rectangle-19210.jpg',
                    'description' => 'Vamos buscá-lo no seu hotel.',
                ],
                [
                    'time' => '7:00 am',
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => 'Faremos uma breve parada para o café da manhã.',
                ],
                [
                    'time' => '9:00 am: Ilhas Ballestas',
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Chegada ao cais turístico, onde iniciaremos nossa excursão às Ilhas Ballestas. Neste percurso faremos um passeio de barco, onde você poderá apreciar',
                ],
                [
                    'time' => 'El Candelabro',
                    'image' => 'tours/image.jpg',
                    'description' => 'um grande geoglifo muito semelhante às Linhas de Nazca. Esta ilha é formada naturalmente por formações rochosas que abrigam uma importante fauna marinha, onde vivem grandes colônias de aves guaneiras (cormorão de Guanay, atobá peruano, pelicano peruano, cormorão, trinta-réis inca, etc.). Também poderemos avistar lobos-marinhos, pinguins de Humboldt e, com um pouco de sorte, golfinhos.',
                ],
                [
                    'time' => '1:00 pm: almoço',
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => 'Chegamos a Ica para almoçar (tempo livre). Podemos desfrutar de alguns pratos típicos como "La Ruta del Pisco", arroz com pato, sopa seca, ceviche e outros pratos.',
                ],
                [
                    'time' => '2:00 pm: visita à vinícola e degustação (Bodega Nietto)',
                    'image' => 'tours/Rectangle-19215.jpg',
                    'description' => 'Faremos um passeio pela bodega para conhecer a elaboração de um bom pisco e, em seguida, degustaremos vinhos, cremes de pisco, macerados e pisco — tudo já incluído no nosso serviço. Quem desejar poderá comprar.',
                ],
                [
                    'time' => '3:00 pm: visita ao Oásis de Huacachina e dunas',
                    'image' => 'tours/Rectangle-19219.jpg',
                    'description' => 'Descobriremos a emoção de Huacachina! Uma experiência única no maior oásis da América do Sul.',
                ],
                [
                    'time' => 'Buggies no deserto',
                    'image' => 'tours/image-1.jpg',
                    'description' => 'Viveremos a emoção de percorrer as dunas em nosso veículo off-road privado (canam), conduzido por motoristas experientes que o levarão pelas dunas de areia em alta velocidade para desfrutar de vistas panorâmicas impressionantes — 1 hora de percurso.',
                ],
                [
                    'time' => 'Sandboarding',
                    'image' => 'tours/Rectangle-19211.jpg',
                    'description' => 'Vamos dominar as dunas com nosso equipamento de sandboarding e deslizar pelas encostas em uma prancha especialmente projetada para a areia. É sem dúvida uma experiência emocionante que você não pode perder. Após esses esportes de aventura, partiremos para Chincha.',
                ],
                [
                    'time' => '7:00 pm: chegada a Chincha / Bodega Racimo de Uva',
                    'image' => 'tours/Rectangle-19212.jpg',
                    'description' => 'Chincha será nossa última parada para degustar alguns macerados de pisco e piscos puros. Você também poderá comprar mamajuanas e experimentar algumas sobremesas típicas de Chincha como tejas e chocotejas.',
                ],
                [
                    'time' => '7:00 pm: retorno a Lima',
                    'image' => 'tours/image.jpg',
                    'description' => null,
                ],
                [
                    'time' => '10:00 pm: chegada ao seu hotel.',
                    'image' => 'tours/Rectangle-19214.jpg',
                    'description' => null,
                ],
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                'Includes private buggy (canam)',
                'Hotel pick-up and drop-off.',
                'Transfer to Paracas-Ica by tourist vehicle.',
                'Huacachina Oasis tour in Ica.',
                'Ballestas Islands tour in Paracas.',
                'Visit to winery with wine and pisco tasting.',
                'Entrance tickets.',
                'Bilingual tour guide (English and Spanish).',
                'Permanent assistance',
                'Local guide at the Ballestas Islands',
                'Buggy ride',
                'Sandboarding adventure',
                'Boat ride in Paracas',
                'Life jacket',
                'Marine wildlife spotting',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                'Inclui buggy privado (canam)',
                'Traslado de e para o hotel.',
                'Transfer para Paracas-Ica em veículo turístico.',
                'Tour ao Oásis de Huacachina em Ica.',
                'Tour às Ilhas Ballestas em Paracas.',
                'Visita a vinícola com degustação de vinhos e piscos.',
                'Ingressos de entrada.',
                'Guia de turismo bilíngue (inglês e espanhol).',
                'Assistência permanente',
                'Guia local nas Ilhas Ballestas',
                'Passeio de buggy',
                'Aventura de sandboarding',
                'Passeio de barco em Paracas',
                'Colete salva-vidas',
                'Observação de fauna marinha',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 5)->update($data);
        }
    }
}
