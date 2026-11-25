<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMinimum extends Model
{
    protected $fillable = ['warehouse_id', 'part_id', 'min_qty'];

    protected function casts(): array
    {
        return ['min_qty' => 'float'];
    }
}
