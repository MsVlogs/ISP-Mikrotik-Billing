<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycRequest extends Model
{
    protected $fillable = [
        "customer_unique_id", "customer_name", "phone", "nid", "email",
        "document_type", "document_reference", "notes", "status", "reviewed_by", "reviewed_at",
    ];

    protected $casts = ["reviewed_at" => "datetime"];

    public function customer()
    {
        return $this->belongsTo(CustomersInfo::class, "customer_unique_id", "customer_unique_id");
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, "reviewed_by");
    }
}
