<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour10Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'MARAS MORAY E MINAS DE SAL + QUADRICICLOS',
            'description_en' => 'Discover the cultural and natural richness of the Sacred Valley of the Incas on our tour to Maras, Moray, and the Maras Salt Mines. We\'ll start by exploring Moray, a unique archaeological complex consisting of circular terraces that the Incas used to experiment with different crops. Then, we\'ll head to the Maras Salt Mines, where you\'ll marvel at the thousands of salt terraces that have been in use since pre-Inca times. Finally, we\'ll visit the charming village of Maras and its colonial church. Immerse yourself in the history and natural beauty of this region on our complete and exciting tour.' . "\n\n" . 'Discover the cultural and natural richness of the Sacred Valley of the Incas on our tour to Maras, Moray, and the Maras Salt Mines. We\'ll start by exploring Moray, a unique archaeological complex consisting of circular terraces that the Incas used to experiment with different crops. Then, we\'ll head to the Maras Salt Mines, where you\'ll marvel at the thousands of salt terraces that have been in use since pre-Inca times. Finally, we\'ll visit the charming village of Maras and its colonial church. Immerse yourself in the history and natural beauty of this region on our complete and exciting tour.',
            'description_pt' => 'Descubra a riqueza cultural e natural do Vale Sagrado dos Incas em nosso tour a Maras, Moray e às Salinas de Maras. Começaremos explorando Moray, um complexo arqueológico único composto por terraços circulares que os incas utilizavam para experimentar diferentes cultivos. Em seguida, nos dirigiremos às Salinas de Maras, onde você ficará maravilhado com os milhares de terraços de sal utilizados desde a época pré-inca. Por fim, visitaremos o pitoresco povoado de Maras e sua igreja colonial. Mergulhe na história e na beleza natural desta região com nosso tour completo e emocionante.' . "\n\n" . 'Descubra a riqueza cultural e natural do Vale Sagrado dos Incas em nosso tour a Maras, Moray e às Salinas de Maras. Começaremos explorando Moray, um complexo arqueológico único composto por terraços circulares que os incas utilizavam para experimentar diferentes cultivos. Em seguida, nos dirigiremos às Salinas de Maras, onde você ficará maravilhado com os milhares de terraços de sal utilizados desde a época pré-inca. Por fim, visitaremos o pitoresco povoado de Maras e sua igreja colonial. Mergulhe na história e na beleza natural desta região com nosso tour completo e emocionante.',
            'recommendations_en' => "Bring your original passport\r\n Waterproof jacket / rain poncho\r\n Warm jacket\r\n Camera\r\n Drink herbal teas for altitude",
            'recommendations_pt' => "Levar passaporte original\r\n Jaqueta impermeável / poncho para chuva\r\n Jaqueta quente\r\n Câmera fotográfica\r\n Tomar chás para a altitude",
            'notes_en' => 'Our pickup and drop-off service covers the Cusco main square (Plaza de Armas) area. If your hotel or accommodation is outside this area, we offer the option to pick you up at the Plaza de Armas de Cusco (exactly at the water fountain).',
            'notes_pt' => 'Nosso serviço de traslado de ida e volta ao seu hotel cobre a área da Praça de Armas de Cusco. Se o seu hotel ou acomodação estiver fora dessa área, oferecemos a opção de buscá-lo na Plaza de Armas de Cusco (exatamente na fonte de água).',
            'itinerary_en' => json_encode([
                'Join us on an exciting ATV adventure through the Sacred Valley of the Incas. Our tour starts early, at 7:00 AM, with pickup at your hotel in Cusco. From there, we\'ll travel one hour to the ATV base in Cruzpata.',
                'Once in Cruzpata, you\'ll receive a detailed briefing and riding instructions from our expert guides. You\'ll then have a 15-minute practice session to get familiar with the ATV before setting off on our exciting route.',
                'We\'ll begin our ATV ride to the archaeological site of Moray, where you\'ll have 20 minutes of free time to explore this fascinating site. Afterward, we\'ll continue our ATV journey for 2 hours and 20 minutes, enjoying the stunning views of the landscape.',
                'Upon returning to our base in Cruzpata, we\'ll board our vehicle to head to the Maras Salt Mines, also known as the Minas de Sal. Here, you\'ll have the chance to marvel at the salt terraces and learn about this ancient extraction method.',
                'Finally, we\'ll return to the city of Cusco, where we\'ll drop you off at your hotel around 1:30 PM. Enjoy a day full of adventure and breathtaking scenery on our ATV tour through the Sacred Valley.',
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                'Junte-se a nós em uma emocionante aventura de quadriciclo pelo Vale Sagrado dos Incas. Nosso tour começa cedo, às 7h00, com a busca no seu hotel em Cusco. De lá, seguiremos uma hora de viagem até a base dos quadriciclos em Cruzpata.',
                'Uma vez em Cruzpata, você receberá um briefing detalhado e instruções de pilotagem dos nossos guias especializados. Em seguida, terá 15 minutos de prática para se familiarizar com o quadriciclo antes de iniciarmos o emocionante percurso.',
                'Iniciaremos nosso passeio de quadriciclo até o centro arqueológico de Moray, onde terá 20 minutos livres para explorar esse fascinante local. Depois, continuaremos nosso percurso de quadriciclo por 2 horas e 20 minutos, aproveitando as impressionantes vistas da paisagem.',
                'Ao retornar à nossa base em Cruzpata, embarcaremos em nosso veículo para ir às Salinas de Maras, também conhecidas como Minas de Sal. Aqui, você poderá se maravilhar com os terraços de sal e aprender sobre esse antigo método de extração.',
                'Por fim, retornaremos à cidade de Cusco, onde o deixaremos no seu hotel por volta de 13h30. Aproveite um dia repleto de aventura e paisagens deslumbrantes em nosso tour de quadriciclo pelo Vale Sagrado.',
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                'Entrance tickets included',
                'Professional guide',
                'Tourist transport, pickup and drop-off',
                'Pickup at your hotel or Airbnb',
                'Entrance fees to each archaeological site included',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                'Inclui ingressos de entrada',
                'Guia profissional',
                'Transporte turístico, busca e retorno',
                'Busca no seu hotel ou Airbnb',
                'Inclui entradas para cada centro arqueológico',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 10)->update($data);
        }
    }
}
