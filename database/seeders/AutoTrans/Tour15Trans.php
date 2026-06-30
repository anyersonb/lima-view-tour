<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour15Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'Excursão ao Santuário de Pachacámac + Museu com Traslado ao Hotel',
            'description_en' => "Explore the Archaeological Sanctuary of Pachacámac with hotel pickup included. Features tourist transport, professional guide, and entry to the archaeological complex. Discover temples, pyramids, and the impressive Temple of the Sun on this unmissable cultural tour in Lima.\n\nDiscover one of the most important ceremonial centers of ancient Peru in an experience full of history, culture, and breathtaking views of the ocean.\n\n\r\nThe Archaeological Sanctuary of Pachacámac was a major pre-Inca and Inca oracle, visited by pilgrims from across the country for over 1,000 years.\n\n\r\nThis tour is ideal for those who want to explore Peru\'s ancient past without leaving Lima.",
            'description_pt' => "Explore o Santuário Arqueológico de Pachacámac com traslado desde o seu hotel. Inclui transporte turístico, guia profissional e entrada ao complexo arqueológico. Descubra templos, pirâmides e o impressionante Templo do Sol neste tour cultural imperdível em Lima.\n\nDescubra um dos centros cerimoniais mais importantes do Peru antigo em uma experiência repleta de história, cultura e vistas deslumbrantes do oceano.\n\n\r\nO Santuário Arqueológico de Pachacámac foi um importante oráculo pré-inca e inca, visitado por peregrinos de diversas regiões do país há mais de 1.000 anos.\n\n\r\nEste tour é ideal para quem deseja conhecer o passado milenar do Peru sem sair de Lima.",
            'recommendations_en' => '• Sunglasses and hat• Comfortable clothing• Water• Camera',
            'recommendations_pt' => '• Óculos de sol e chapéu• Roupas confortáveis• Água• Câmera fotográfica',
            'notes_en' => 'Our hotel pickup and drop-off service covers the Miraflores, Barranco, and San Isidro districts. If your hotel or accommodation is outside these districts, we offer the option of picking you up at Parque del Amor (MIRAFLORES).',
            'notes_pt' => 'Nosso serviço de traslado de ida e volta ao hotel cobre as áreas de Miraflores, Barranco e San Isidro. Se o seu hotel ou acomodação estiver fora desses distritos, oferecemos a possibilidade de buscá-lo no Parque del Amor (MIRAFLORES).',
            'itinerary_en' => json_encode([
                [
                    'time' => '08:30 – 09:10 a.m.',
                    'image' => null,
                    'description' => "Hotel pickup in Miraflores, San Isidro, or Barranco.\r\n(The day before the tour, we will send you the exact pickup time at your hotel for better coordination.)",
                ],
                [
                    'time' => '09:10 – 10:15 a.m.',
                    'image' => null,
                    'description' => 'Transfer to the Archaeological Sanctuary of Pachacámac.',
                ],
                [
                    'time' => '10:15 – 10:35 a.m.',
                    'image' => null,
                    'description' => 'Guided tour of the Pachacamac Site Museum.',
                ],
                [
                    'time' => '10:35 a.m. – 12:15 p.m.',
                    'image' => null,
                    'description' => "Guided tour of the archaeological complex:\r\n• Temple of the Sun\r\n• Painted Temple\r\n• Acllahuasi\r\n• Plaza of the Pilgrims\r\n• Administrative and ceremonial sectors",
                ],
                [
                    'time' => '12:15 p.m.',
                    'image' => null,
                    'description' => 'Return to your hotel.',
                ],
                [
                    'time' => '01:30 p.m. approx.',
                    'image' => null,
                    'description' => 'Arrival at the hotel and end of service.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'itinerary_pt' => json_encode([
                [
                    'time' => '08:30 – 09:10 a.m.',
                    'image' => null,
                    'description' => "Coleta no hotel em Miraflores, San Isidro ou Barranco.\r\n(No dia anterior ao tour, enviaremos o horário exato de coleta no seu hotel para uma melhor coordenação.)",
                ],
                [
                    'time' => '09:10 – 10:15 a.m.',
                    'image' => null,
                    'description' => 'Transfer ao Santuário Arqueológico de Pachacámac.',
                ],
                [
                    'time' => '10:15 – 10:35 a.m.',
                    'image' => null,
                    'description' => 'Visita guiada ao Museu de Sítio de Pachacamac.',
                ],
                [
                    'time' => '10:35 a.m. – 12:15 p.m.',
                    'image' => null,
                    'description' => "Visita guiada pelo complexo arqueológico:\r\n• Templo do Sol\r\n• Templo Pintado\r\n• Acllahuasi\r\n• Praça dos Peregrinos\r\n• Setores administrativos e cerimoniais",
                ],
                [
                    'time' => '12:15 p.m.',
                    'image' => null,
                    'description' => 'Retorno ao seu hotel.',
                ],
                [
                    'time' => '01:30 p.m. aprox.',
                    'image' => null,
                    'description' => 'Chegada ao hotel e fim do serviço.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'includes_en' => json_encode([
                '✅ Hotel pickup and drop-off (Miraflores, San Isidro, or Barranco)✅ Comfortable and safe tourist transport✅ Professional guide specializing in history✅ Entry to the Archaeological Sanctuary of Pachacámac✅ Site Museum visit✅ Tour of temples, pyramids, and ceremonial enclosures✅ Panoramic view from the Temple of the Sun',
            ], JSON_UNESCAPED_UNICODE),
            'includes_pt' => json_encode([
                '✅ Traslado de ida e volta ao hotel (Miraflores, San Isidro ou Barranco)✅ Transporte turístico confortável e seguro✅ Guia profissional especializado em história✅ Entrada ao Santuário Arqueológico de Pachacámac✅ Visita ao Museu de Sítio✅ Passeio por templos, pirâmides e recintos cerimoniais✅ Vista panorâmica do Templo do Sol',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 15)->update($data);
        }
    }
}
