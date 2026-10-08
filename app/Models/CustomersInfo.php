<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomersInfo extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_unique_id',
        'customer_name',
        'contact_person',
        'parents_name',
        'spouse_name',
        'address',
        'email',
        'mobile',
        'alternative_mobile',
        'identification_no',
        'profession',
        'photo_url',
        'disable_count',
        'ppp_user_id',
        'connection_date',
        'package_id',
        'status',
        'reseller_id',
        // 'auto_disabled',
        // 'expired_date',
        // 'auto_disabled_month',
        // 'extra_date',
    ];

    /**
     * Keep Bangladesh customer mobile numbers in local format (01XXXXXXXXX).
     * Entry screens should never require a country code, and this mutator also
     * protects database writes coming from imports, controllers, and other forms.
     */
    public static function normalizeBangladeshMobile($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if (str_starts_with($digits, '00880')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '8801') && strlen($digits) >= 13) {
            return '0'.substr($digits, 3);
        }

        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            return $digits;
        }

        return $digits !== '' ? $digits : null;
    }

    public function setMobileAttribute($value): void
    {
        $this->attributes['mobile'] = self::normalizeBangladeshMobile($value);
    }

    public function setAlternativeMobileAttribute($value): void
    {
        $this->attributes['alternative_mobile'] = self::normalizeBangladeshMobile($value);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($customer) {
            if ($customer->pppUser()->exists()) {
                throw new \Exception('Cannot delete customer because they are associated with an active PPPoE user. Please remove the PPPoE user first.');
            }
        });
    }
    public function setConnectionDateAttribute($value): void
    {
        $this->attributes['connection_date'] = filled($value) ? $value : null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeUnderDisableLimit($query, $limit)
    {
        return $query->where('disable_count', '<', $limit);
    }

    public function scopeHasPPPUser($query)
    {
        return $query->whereNotNull('ppp_user_id');
    }

    public function pppUser()
    {
        return $this->belongsTo(PPPSecrets::class, 'ppp_user_id', 'id');
    }

    public function package()
    {
        return $this->belongsTo(PackageList::class, 'package_id', 'id');
    }

    public function customerAddress()
    {
        return $this->hasMany(CustomersAddress::class, 'customer_address_unique_id', 'customer_unique_id');
    }

    public function paymentSummary()
    {
        return $this->hasMany(PaymentSummary::class, 'customer_payment_unique_id', 'customer_unique_id');
    }

    public function collectionSummary()
    {
        return $this->hasMany(CollectionSummary::class, 'customer_collection_unique_id', 'customer_unique_id');
    }

    public function billing()
    {
        return $this->belongsTo(BillingInfo::class, 'customer_unique_id', 'customer_bill_unique_id');
    }

    public function official()
    {
        return $this->belongsTo(OfficialInfo::class, 'customer_unique_id', 'customer_office_unique_id');
    }

    public function scopeSearch($query, $search)
    {
        $term = '%'.$search.'%';

        return $query->where(function ($query) use ($term) {
            $query->where('customer_unique_id', 'like', $term)
                ->orWhere('customer_name', 'like', $term)
                ->orWhere('contact_person', 'like', $term)
                ->orWhere('parents_name', 'like', $term)
                ->orWhere('spouse_name', 'like', $term)
                ->orWhere('address', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('mobile', 'like', $term)
                ->orWhere('alternative_mobile', 'like', $term)
                ->orWhere('identification_no', 'like', $term)
                ->orWhere('profession', 'like', $term)
                ->orWhereHas('pppUser', function ($q) use ($term) {
                    $q->where('username', 'like', $term);
                });
        });
    }

    public function kycRequests()
    {
        return $this->hasMany(KycRequest::class, 'customer_unique_id', 'customer_unique_id');
    }

    public function reseller()
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }
}
