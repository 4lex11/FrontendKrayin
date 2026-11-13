<?php

namespace Webkul\Churn\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    public $timestamps = false;

    protected $table = 'churn_reviews';

    protected $guarded = ['id'];
}
