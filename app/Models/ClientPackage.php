<?php

/**
 * @Quill Information Technology
 */

namespace App\Models;

use App\Models\Base\BaseModel;

class ClientPackage extends BaseModel
{
    protected $guarded = ['id'];

    protected $logName = "ClientPackage";

    // file image push
    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id', 'id');
    }
    // date format
}
