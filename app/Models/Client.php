<?php

/**
 * @Quill Information Technology
 */

namespace App\Models;

use App\Models\Base\BaseModel;

class Client extends BaseModel
{
    protected $guarded = ['id'];

    protected $logName = "Client";

    protected $appends = ['is_invoice_setup'];

    protected $casts = [
        'invoice_setup' => 'array'
    ];

    public function getIsInvoiceSetupAttribute()
    {
        $setup = $this->invoice_setup;

        // যদি string হয় তাহলে decode
        if (is_string($setup)) {
            $setup = json_decode($setup, true) ?? [];
        }

        // যদি null হয়
        if (empty($setup)) {
            return 'No';
        }

        foreach ($setup as $row) {
            if (!empty($row['category_id'])) {
                return 'Yes';
            }
        }

        return 'No';
    }

    // file image push
    public static function generateClientID()
    {
        // Today's prefix
        $prefix = now()->format('ymd');

        // Count today's clients
        $todayCount = Client::whereDate('created_at', today())->count();

        // Next serial
        $serial = str_pad($todayCount + 1, 4, '0', STR_PAD_LEFT);

        // Final Client ID
        return $prefix . $serial;
    }

    public function getRegDateAttribute($value)
    {
        $startDate = null;
        if ($value) {
            $startDate = date('d M, Y', strtotime($value));
        }

        return $startDate;
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    function client_packages()
    {
        return $this->hasMany(ClientPackage::class, 'client_id', 'id');
    }

    // date format
}
