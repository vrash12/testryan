<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class JobService extends Pivot
{
    protected $table = 'Job_Services';

    protected $primaryKey = 'job_service_id';

    public $timestamps = false;

    public $incrementing = true;
}
