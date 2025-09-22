<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockProcessLog extends Model
{
    protected $table = 'stock_process_log';
    public $timestamps = false;

    // Allow mass assignment for these fields.
    protected $fillable = ['stock_symbol', 'process_type', 'execution_time','e_value','date_created'];
}
