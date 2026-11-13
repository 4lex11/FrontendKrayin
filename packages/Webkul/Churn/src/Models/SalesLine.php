<?php

namespace Webkul\Churn\Models;

use Illuminate\Database\Eloquent\Model;

class SalesLine extends Model
{
    protected $table = 'churn_sales_lines';

    protected $guarded = ['id'];

    protected $casts = ['sales' => 'decimal:6'];
}
