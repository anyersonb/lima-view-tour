<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour1Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'TOUR DE DIA COMPLETO AO OÁSIS DE HUACACHINA + ILHAS BALLESTAS EM PARACAS',
            'description_en' => 'We will take you to Huacachina to explore its stunning sand dunes, enjoy a thrilling buggy ride down its steep slopes, and experience the excitement of sandboarding. With its unique combination of adrenaline and natural beauty, Huacachina offers an unforgettable experience in the heart of the desert.
 We will also visit Paracas, one of the most outstanding destinations in southern Lima, renowned for its breathtaking Ballestas Islands. During the boat tour, you will have the chance to observe up close the charming sea lions, incredible birds, and small penguins that inhabit the area. Join us on this unforgettable adventure!

We will take you to Huacachina to explore its stunning sand dunes, enjoy a thrilling buggy ride down its steep slopes, and experience the excitement of sandboarding. With its unique combination of adrenaline and natural beauty, Huacachina offers an unforgettable experience in the heart of the desert.
 We will also visit Paracas, one of the most outstanding destinations in southern Lima, renowned for its breathtaking Ballestas Islands. During the boat tour, you will have the chance to observe up close the charming sea lions, incredible birds, and small penguins that inhabit the area. Join us on this unforgettable adventure!',
            'description_pt' => 'Levaremos você a Huacachina para explorar suas impressionantes dunas de areia, aproveitar um emocionante passeio de buggy por suas encostas íngremes e vivenciar a emoção do sandboarding. Com sua combinação única de adrenalina e beleza natural, Huacachina oferece uma experiência inesquecível no coração do deserto.
 Além disso, visitaremos Paracas, um dos destinos mais destacados do sul de Lima, famoso pelas suas impressionantes Ilhas Ballestas. Durante o passeio de barco, você terá a oportunidade de observar de perto os encantadores leões-marinhos, incríveis aves e pequenos pinguins que habitam a região. Junte-se a nós nessa aventura inesquecível!

Levaremos você a Huacachina para explorar suas impressionantes dunas de areia, aproveitar um emocionante passeio de buggy por suas encostas íngremes e vivenciar a emoção do sandboarding. Com sua combinação única de adrenalina e beleza natural, Huacachina oferece uma experiência inesquecível no coração do deserto.
 Além disso, visitaremos Paracas, um dos destinos mais destacados do sul de Lima, famoso pelas suas impressionantes Ilhas Ballestas. Durante o passeio de barco, você terá a oportunidade de observar de perto os encantadores leões-marinhos, incríveis aves e pequenos pinguins que habitam a região. Junte-se a nós nessa aventura inesquecível!',
            'recommendations_en' => 'Sunscreen
 Comfortable clothing and footwear for walking
 A hat
 Sunglasses',
            'recommendations_pt' => 'Protetor solar
 Roupas e calçados confortáveis para caminhar
 Um chapéu
 Óculos de sol',
            'notes_en' => 'We offer complimentary hotel pickup and drop-off from hotels located in the Miraflores district, ensuring your comfort from start to finish. If your hotel is outside this district, an additional charge of $10 per person will apply for the transportation service.

Please make sure to provide us with your hotel address at the time of booking so we can coordinate the transfer details and ensure a seamless experience.',
            'notes_pt' => 'Oferecemos traslado de ida e volta incluído a partir de hotéis localizados no distrito de Miraflores, garantindo seu conforto do início ao fim. Caso seu hotel esteja fora deste distrito, será cobrado um valor adicional de $10 por pessoa pelo serviço de transporte.

Por favor, certifique-se de nos fornecer o endereço do seu hotel no momento da reserva para que possamos coordenar os detalhes do traslado e garantir uma experiência sem contratempos.',
            'itinerary_en' => json_encode([
                ['time' => '4:00 am', 'image' => 'tours/classic-hotel-reception-design.jpg', 'description' => 'Hotel pickup'],
                ['time' => '6:30 am', 'image' => 'tours/Rectangle-19211.jpg', 'description' => 'We will make a brief stop to have breakfast'],
                ['time' => '9:00 am', 'image' => 'tours/Rectangle-19212.jpg', 'description' => 'Ballestas Islands — arrival at the tourist pier where we will begin our excursion to the Ballestas Islands. During this trip we will take a boat ride, where you will be able to admire El Candelabro, a large-scale geoglyph very similar to the Nazca Lines. These islands are made up of natural rock formations that shelter an important marine fauna, home to large colonies of guano birds (Guanay cormorant, Peruvian booby, Pelican, Cormorant, Inca terns, etc.). We will also be able to spot Sea Lions, Humboldt Penguins and, with a bit of luck, Dolphins.'],
                ['time' => '1:00 pm', 'image' => 'tours/image.jpg', 'description' => 'Lunch — we arrive in Ica for lunch (free time). We can enjoy some typical local dishes such as "La Ruta del Pisco", duck rice, sopa seca, ceviche, and other specialties.'],
                ['time' => '2:00 pm', 'image' => 'tours/Rectangle-19214.jpg', 'description' => 'Winery visit and tasting (Bodega Nieto) — we will tour the winery to learn about the production of fine pisco, followed by a tasting of wines, pisco creams, macerados, and pisco, all already included in our service. Those who wish may purchase additional items.'],
                ['time' => '3:00 pm', 'image' => 'tours/Rectangle-19215.jpg', 'description' => 'Visit to the Huacachina Oasis and Dunes — we will discover the thrill of Huacachina! A one-of-a-kind experience at the largest oasis in South America.'],
                ['time' => 'Buggies in the desert', 'image' => 'tours/Rectangle-19219.jpg', 'description' => 'We will experience the excitement of riding the dunes in our off-road vehicles, driven by expert drivers who will take you across the sand dunes at full speed to enjoy breathtaking panoramic views.'],
                ['time' => 'Sandboarding', 'image' => 'tours/image-1.jpg', 'description' => 'We will conquer the dunes with our sandboarding equipment and glide down the slopes on a board specially designed for sand — an exhilarating experience you simply cannot miss.'],
                ['time' => '6:00 pm', 'image' => 'tours/Rectangle-19217.jpg', 'description' => 'Return to Lima'],
                ['time' => '10:00 pm', 'image' => 'tours/classic-hotel-reception-design.jpg', 'description' => 'Arrival at your hotel'],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                ['time' => '4:00 am', 'image' => 'tours/classic-hotel-reception-design.jpg', 'description' => 'Busca no hotel'],
                ['time' => '6:30 am', 'image' => 'tours/Rectangle-19211.jpg', 'description' => 'Faremos uma breve parada para tomar café da manhã'],
                ['time' => '9:00 am', 'image' => 'tours/Rectangle-19212.jpg', 'description' => 'Ilhas Ballestas — chegada ao píer turístico, onde iniciaremos nossa excursão às Ilhas Ballestas. Neste percurso faremos um passeio de lancha, onde poderão admirar El Candelabro, um geoglifo de grandes dimensões muito semelhante às Linhas de Nazca. Essas ilhas são formadas naturalmente por formações rochosas que abrigam uma importante fauna marinha, com grandes colônias de aves guaneiras (Guanay, Piquero, Pelicano, Cormorão, Gaivota-de-inca, etc.). Também poderemos avistar leões-marinhos, pinguins de Humboldt e, com um pouco de sorte, golfinhos.'],
                ['time' => '1:00 pm', 'image' => 'tours/image.jpg', 'description' => 'Almoço — chegamos a Ica para almoçar (tempo livre). Poderemos desfrutar de alguns pratos típicos como "La Ruta del Pisco", arroz com pato, sopa seca, ceviche e outras especialidades.'],
                ['time' => '2:00 pm', 'image' => 'tours/Rectangle-19214.jpg', 'description' => 'Visita à vitivinícola e degustação (Bodega Nieto) — faremos um tour pela adega para conhecer a elaboração de um bom pisco, seguido de degustação de vinhos, cremes de pisco, macerados e pisco, tudo já incluído no serviço. Quem desejar poderá comprar.'],
                ['time' => '3:00 pm', 'image' => 'tours/Rectangle-19215.jpg', 'description' => 'Visita ao Oásis Huacachina e Dunas — vamos descobrir a emoção de Huacachina! Uma experiência única no maior oásis da América do Sul.'],
                ['time' => 'Buggies no deserto', 'image' => 'tours/Rectangle-19219.jpg', 'description' => 'Viveremos a emoção de percorrer as dunas em nossos veículos off-road, conduzidos por motoristas experientes que levarão você pelas dunas de areia em alta velocidade para desfrutar de vistas panorâmicas deslumbrantes.'],
                ['time' => 'Sandboarding', 'image' => 'tours/image-1.jpg', 'description' => 'Dominaremos as dunas com nosso equipamento de sandboarding e desceremos pelas encostas em uma prancha especialmente projetada para a areia — sem dúvida uma experiência emocionante que você não pode perder.'],
                ['time' => '6:00 pm', 'image' => 'tours/Rectangle-19217.jpg', 'description' => 'Retorno a Lima'],
                ['time' => '10:00 pm', 'image' => 'tours/classic-hotel-reception-design.jpg', 'description' => 'Chegada ao seu hotel'],
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                'Hotel pickup and drop-off.',
                'Transportation to Paracas-Ica in a tourist vehicle.',
                'Huacachina Oasis tour in Ica.',
                'Ballestas Islands tour in Paracas.',
                'Winery visit with wine and pisco tasting.',
                'Entrance tickets.',
                'Bilingual tour guide (English and Spanish).',
                'Permanent assistance.',
                'Local guide at the Ballestas Islands.',
                'Buggy ride.',
                'Sandboarding adventure.',
                'Boat ride in Paracas.',
                'Life jacket.',
                'Marine wildlife spotting.',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                'Busca e retorno ao hotel.',
                'Traslado para Paracas-Ica em veículo turístico.',
                'Tour ao Oásis Huacachina em Ica.',
                'Tour pelas Ilhas Ballestas em Paracas.',
                'Visita à adega de vinhos e piscos com degustação.',
                'Ingressos de entrada.',
                'Guia de turismo em inglês e espanhol.',
                'Assistência permanente.',
                'Guia local nas Ilhas Ballestas.',
                'Passeio de buggy.',
                'Aventura de sandboarding.',
                'Passeio de lancha em Paracas.',
                'Colete salva-vidas.',
                'Avistamento de fauna marinha.',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 1)->update($data);
        }
    }
}
