<?php

namespace Webkul\Churn\Models;

use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    protected $table = 'churn_predictions';

    protected $guarded = ['id'];

    protected $casts = ['response' => 'array', 'probability' => 'float', 'limited_history' => 'boolean', 'automatic_action_allowed' => 'boolean'];
}
