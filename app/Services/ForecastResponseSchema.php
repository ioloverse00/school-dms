<?php
namespace App\Services;

class ForecastResponseSchema
{
    public static function make(int $horizon, int $typeCount): array
    {
        $text = ['type'=>'STRING'];
        // Describe lengths here; enforce every bound in ForecastOutputValidator.
        $count = ['type'=>'INTEGER'];
        $list = fn($item,$min=0,$max=20)=>['type'=>'ARRAY','items'=>$item,
            'description'=>$min===$max ? 'Exactly '.$min.' entries.' : 'At most '.$max.' entries.'];
        $object = fn($properties)=>['type'=>'OBJECT','properties'=>$properties,'required'=>array_keys($properties)];
        $notes = $list($text);
        return $object([
            'forecast_months'=>['type'=>'INTEGER','description'=>'Must equal '.$horizon.'.'],
            'months'=>$list($text,$horizon,$horizon),
            'overall_forecast'=>$list($count,$horizon,$horizon),
            'per_document_type'=>$list($object(['document_type_id'=>['type'=>'INTEGER'],'name'=>$text,'forecast'=>$list($count,$horizon,$horizon)]),$typeCount,$typeCount),
            'volume_forecast'=>$object(['period'=>$text,'total_requests'=>$count,'notes'=>$notes]),
            'peak_period_analysis'=>$object(['upcoming_peaks_30_60_days'=>$notes,'historical_peaks'=>$notes,'why_peaks_happen'=>$notes]),
            'top_documents'=>$list($object(['document_type_id'=>['type'=>'INTEGER'],'name'=>$text,'forecast_share'=>['type'=>'NUMBER'],'notes'=>$text]),0,50),
            'processing_workload'=>$object(['notes'=>$notes]),
            'staffing_recommendations'=>$object(['normal_period'=>$notes,'peak_period'=>$notes]),
            'insights'=>$notes,'assumptions'=>$notes,
        ]);
    }
}
