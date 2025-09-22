<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockInstitutionalPortfolio extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_institutional_portfolio';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'change',
        'cik',
        'cusip',
        'name',
        'noVoting',
        'percentage',
        'putCall',
        'share',
        'sharedVoting',
        'soleVoting',
        'symbol',
        'value',
        'report_date',
        'from',
        'to'
    ];

    /**
     * Insert or update stock institutional portfolio at stock_institutional_portfolio table.
     *
     * @param array $data
     * @return bool
     */
    public static function updateStockInstitutionalPortfolio(string $cik, array $data): bool
    {
        
        return (bool) self::updateOrCreate(
            [
                'cusip' => $data['cusip'],
                'symbol' => $data['symbol']
            ], // search criteria
            [
                'change' => $data['change'],
                'cik' => $cik,
                'name' => $data['name'],
                'noVoting' => $data['noVoting'],
                'percentage' => $data['percentage'],
                'putCall' => $data['putCall'],
                'share' => $data['share'],
                'sharedVoting' => $data['sharedVoting'],
                'soleVoting' => $data['soleVoting'],
                'value' => $data['value'],
                'report_date'=>$data['report_date'],
                'from' => $data['from'],
                'to' => $data['to']
            ]
        );
    }

    
    public static function getCIKNumbers()
    {
        return $cik_array = [
            "1588456",
            "1067983",
            "1061768",
            "1027796",
            "1017918",
            "921669",
            "1061165",
            "200217",
            "1099281",
            "1079114",
            "1039565",
            "915191",
            "898382",
            "807985",
            "1164833",
            "814375",
            "883965",
            "732905",
            "313028",
            "1549575",
            "936753",
            "1015079",
            "1035674",
            "1029160",
            "1056831",
            "1336528",
            "1166559",
            "96223",
            "1720792",
            "905567",
            "1036325",
            "1325447",
            "1106500",
            "1112520",
            "850529",
            "918537",
            "1027451",
            "938582",
            "1656456",
            "1553733",
            "1358331",
            "860585",
            "807249",
            "1096343",
            "868491",
            "947996",
            "1133219",
            "872323",
            "775688",
            "747546",
            "783412",
            "37565",
            "1614370",
            "1031661",
            "1142062",
            "1700530",
            "1510387",
            "52848",
            "37566",
            "1697748"
        ];
        
    }
}