<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketTemplate extends Model
{
    protected $fillable = [
        "type", "name", "sort_order", "subject_template", "internal_note_template",
        "description_template", "customer_message", "staff_message", "bell_notification",
        "staff_sms", "customer_sms", "customer_whatsapp", "owner_telegram", "custom_override", "active",
    ];

    protected $casts = [
        "bell_notification" => "boolean", "staff_sms" => "boolean", "customer_sms" => "boolean",
        "customer_whatsapp" => "boolean", "owner_telegram" => "boolean", "custom_override" => "boolean", "active" => "boolean",
    ];
}
