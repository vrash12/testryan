<?php

namespace App\Models;

class Template extends LegacyRecord
{
    protected $table = 'Templates';

    protected $primaryKey = 'template_id';

    public $timestamps = true;
}
