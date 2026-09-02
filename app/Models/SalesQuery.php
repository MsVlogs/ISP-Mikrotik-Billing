<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesQuery extends Model
{
    protected $fillable = [
        "prospect_name", "email", "mobile1", "mobile2", "nid", "connection_type",
        "package_id", "expected_date", "lead_source", "referred_by", "priority",
        "follow_up_at", "floor_flat", "house", "road", "area_id", "area_text",
        "district", "thana", "assigned_to", "remarks", "status",
    ];

    protected $casts = ["expected_date" => "date", "follow_up_at" => "datetime"];

    public function package()
    {
        return $this->belongsTo(PackageList::class, "package_id");
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, "assigned_to");
    }
}
