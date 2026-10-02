<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ContractorService extends Pivot
{
    protected $table = 'ContractorServices';

    public $timestamps = false;

    public $incrementing = false;
}
