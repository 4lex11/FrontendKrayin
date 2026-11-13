<?php

namespace Webkul\Churn\Models;

use Illuminate\Database\Eloquent\Model;

class DatasetCustomer extends Model
{
    protected $table = 'churn_dataset_customers';

    protected $guarded = ['id'];
}
