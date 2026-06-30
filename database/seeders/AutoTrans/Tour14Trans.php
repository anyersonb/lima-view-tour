<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Tour14Trans {
    public function run(): void {
        $data = [
            'title_pt' => 'TOUR ÀS LINHAS DE NAZCA + TOUR AO OASIS DE HUACACHINA COM BUGGY E SANDBOARDING',
        ];
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');
        if ($data) {
            DB::table('tours')->where('id', 14)->update($data);
        }
    }
}
