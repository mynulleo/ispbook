<?php

/**
 * @Quill Information Technology
 */

namespace App\Models;

use App\Models\Base\BaseModel;

class FundAccountBalance extends BaseModel
{
    protected $table = 'fund_account_balance';
    protected $primaryKey = 'account_id';
    public $incrementing = false;
    protected $guarded = [];
    protected $logName = "FundAccountBalance";

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }
}
