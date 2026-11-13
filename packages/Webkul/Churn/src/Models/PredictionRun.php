<?php

namespace Webkul\Churn\Models;

use Illuminate\Database\Eloquent\Model;

class PredictionRun extends Model
{
    public $timestamps = false;

    protected $table = 'churn_prediction_runs';

    protected $guarded = ['id'];
}
