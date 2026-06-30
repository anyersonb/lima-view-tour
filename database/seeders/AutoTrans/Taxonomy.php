<?php
namespace Database\Seeders\AutoTrans;
use Illuminate\Support\Facades\DB;
class Taxonomy {
    public function run(): void {
        // Regions — only PT needed (has_en already true)
        $r = array_filter(['eyebrow_pt' => 'EXPLORE A CAPITAL'], fn($v) => $v !== null && $v !== '');
        if ($r) DB::table('regions')->where('id', 1)->update($r);

        $r = array_filter(['eyebrow_pt' => 'AVENTURA NO DESERTO'], fn($v) => $v !== null && $v !== '');
        if ($r) DB::table('regions')->where('id', 2)->update($r);

        $r = array_filter(['eyebrow_pt' => 'CIDADELA SAGRADA'], fn($v) => $v !== null && $v !== '');
        if ($r) DB::table('regions')->where('id', 3)->update($r);

        // Categories — only PT needed (has_en already true)
        $r = array_filter(['name_pt' => 'Passeios Culturais'], fn($v) => $v !== null && $v !== '');
        if ($r) DB::table('categories')->where('id', 1)->update($r);

        $r = array_filter(['name_pt' => 'Passeios de Aventura'], fn($v) => $v !== null && $v !== '');
        if ($r) DB::table('categories')->where('id', 2)->update($r);

        $r = array_filter(['name_pt' => 'Experiências Culinárias'], fn($v) => $v !== null && $v !== '');
        if ($r) DB::table('categories')->where('id', 3)->update($r);

        $r = array_filter(['name_pt' => 'Outros'], fn($v) => $v !== null && $v !== '');
        if ($r) DB::table('categories')->where('id', 4)->update($r);
    }
}
