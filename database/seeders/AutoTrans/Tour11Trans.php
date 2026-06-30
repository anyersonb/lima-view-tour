<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour11Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'MARAS MORAY + MINAS DE SAL',
            'description_en' => 'Discover the cultural and natural richness of the Sacred Valley of the Incas on our tour to Maras, Moray and the Maras Salt Mines. We\'ll start by exploring Moray, a unique archaeological complex consisting of circular terraces that the Incas used to experiment with different crops. Then, we\'ll head to the Maras Salt Mines, where you\'ll marvel at the thousands of salt terraces that have been in use since pre-Inca times. Finally, we\'ll visit the charming village of Maras and its colonial church. Immerse yourself in the history and natural beauty of this region on our complete and exciting tour.

Discover the cultural and natural richness of the Sacred Valley of the Incas on our tour to Maras, Moray and the Maras Salt Mines. We\'ll start by exploring Moray, a unique archaeological complex consisting of circular terraces that the Incas used to experiment with different crops. Then, we\'ll head to the Maras Salt Mines, where you\'ll marvel at the thousands of salt terraces that have been in use since pre-Inca times. Finally, we\'ll visit the charming village of Maras and its colonial church. Immerse yourself in the history and natural beauty of this region on our complete and exciting tour.',
            'description_pt' => 'Descubra a riqueza cultural e natural do Vale Sagrado dos Incas em nosso tour para Maras, Moray e as Salinas de Maras. Começaremos explorando Moray, um complexo arqueológico único composto por terraços circulares que os Incas utilizavam para experimentar diferentes cultivos. Em seguida, nos dirigiremos às Salinas de Maras, onde você se maravilhará com os milhares de terraços de sal em uso desde a época pré-inca. Por fim, visitaremos o charmoso vilarejo de Maras e sua igreja colonial. Mergulhe na história e na beleza natural desta região com nosso tour completo e emocionante.

Descubra a riqueza cultural e natural do Vale Sagrado dos Incas em nosso tour para Maras, Moray e as Salinas de Maras. Começaremos explorando Moray, um complexo arqueológico único composto por terraços circulares que os Incas utilizavam para experimentar diferentes cultivos. Em seguida, nos dirigiremos às Salinas de Maras, onde você se maravilhará com os milhares de terraços de sal em uso desde a época pré-inca. Por fim, visitaremos o charmoso vilarejo de Maras e sua igreja colonial. Mergulhe na história e na beleza natural desta região com nosso tour completo e emocionante.',
            'recommendations_en' => "Bring your original passport\r\n Waterproof jacket / rain poncho\r\n Warm jacket\r\n Camera\r\n Drink herbal teas for altitude",
            'recommendations_pt' => "Levar passaporte original\r\n Jaqueta impermeável / poncho de chuva\r\n Jaqueta grossa\r\n Câmera fotográfica\r\n Tomar infusões para a altitude",
            'notes_en' => 'Our pickup and return service to your hotel covers the area of the Plaza de Armas in Cusco. If your hotel or accommodation is outside this area, we offer the option to pick you up at the Plaza de Armas in Cusco (exactly at the water fountain).',
            'notes_pt' => 'Nosso serviço de busca e retorno ao seu hotel cobre a área da Plaza de Armas do Cusco. Se o seu hotel ou acomodação estiver fora desta área, oferecemos a possibilidade de buscá-lo na Plaza de Armas do Cusco (exatamente na fonte de água).',
            'itinerary_en' => json_encode([
                [
                    'time' => null,
                    'image' => null,
                    'description' => 'Our transport service will pick you up at your hotel between 8:00 and 8:30 AM and take you to the tour starting point. We\'ll begin by visiting Moray, located 38 km from the city of Cusco. Moray is recognized as an Inca agricultural laboratory made up of four enormous circles — concentric circular terraces. The deepest reaches a depression of 80 meters, with the lowest terrace having a diameter of 36 meters. Additionally, within these terraces, temperature variations range from 0.5° to 1.5°C per terrace, creating up to 20 different microclimates.',
                ],
                [
                    'time' => null,
                    'image' => null,
                    'description' => 'We will then visit the Maras Salt Mines, made up of around 5,000 small salt pools, each producing approximately 50 to 80 kg of salt per season. These pools are fed by an underground saltwater spring. This entirely natural process takes nearly two months for one growing season. Maras salt is highly sought after, and the highest-quality variety is the pink mineral salt.',
                ],
                [
                    'time' => null,
                    'image' => null,
                    'description' => 'We will return to Cusco, where our tour will conclude at around 2:00 PM, near the Plaza de Armas in Cusco, marking the end of our excursion to the Maras Salt Mines.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                [
                    'time' => null,
                    'image' => null,
                    'description' => 'Nosso serviço de transporte passará em seu hotel entre as 8h00 e as 8h30 para levá-lo ao ponto de início do tour. Começaremos visitando Moray, localizado a 38 km da cidade do Cusco. Moray é reconhecido como um laboratório agrícola inca composto por quatro enormes círculos, que são terraços circulares concêntricos. O mais profundo atinge uma depressão de 80 metros, com o terraço mais baixo tendo um diâmetro de 36 metros. Além disso, dentro desses terraços, há variações de temperatura que oscilam entre 0,5° e 1,5°C por terraço, criando até 20 microclimas diferentes.',
                ],
                [
                    'time' => null,
                    'image' => null,
                    'description' => 'Em seguida, visitaremos as Salinas de Maras, compostas por cerca de 5.000 pequenas pozas de sal, cada uma produzindo aproximadamente de 50 a 80 kg de sal por temporada. Essas pozas são alimentadas por uma nascente de água salgada subterrânea. Esse processo, totalmente natural, leva quase dois meses para uma temporada de cultivo. O sal de Maras é muito procurado, e a variedade de maior qualidade é o sal mineral rosado.',
                ],
                [
                    'time' => null,
                    'image' => null,
                    'description' => 'Retornaremos ao Cusco, onde nosso tour será concluído por volta das 14h00, perto da Plaza de Armas do Cusco, encerrando nossa excursão às Minas de Sal de Maras.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                'Entrance ticket included',
                'Professional guide',
                'Tourist transport, pickup and return',
                'Pickup at your hotel or Airbnb',
                'Entrance tickets to each archaeological site',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                'Ingresso incluso',
                'Guia profissional',
                'Transporte turístico, busca e retorno',
                'Busca em seu hotel ou Airbnb',
                'Ingressos para cada centro arqueológico',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 11)->update($data);
        }
    }
}
