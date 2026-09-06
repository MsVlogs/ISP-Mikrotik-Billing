<?php

use App\Models\SupportTicketTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            ['complain', 'Connection Problem', 10],
            ['complain', 'Internet Speed', 20],
            ['complain', 'Outdoor Box Down', 30],
            ['complain', 'ONU Loss Light', 40],
            ['complain', 'Laser High', 50],
            ['complain', 'Fiber Cut Down', 60],
            ['complain', 'Disconnect', 70],
            ['complain', 'Router Problem', 80],
            ['task', 'Bill Collection', 90],
            ['task', 'Router Configure', 100],
            ['task', 'ONU Change', 110],
            ['sales', 'New Connection', 120],
            ['sales', 'Connection Shift', 130],
            ['sales', 'Sales Query', 140],
        ];

        foreach ($templates as [$type, $name, $sortOrder]) {
            SupportTicketTemplate::firstOrCreate(
                ['type' => $type, 'name' => $name],
                [
                    'sort_order' => $sortOrder,
                    'subject_template' => $name,
                    'internal_note_template' => null,
                    'description_template' => null,
                    'customer_message' => null,
                    'staff_message' => null,
                    'bell_notification' => true,
                    'staff_sms' => true,
                    'customer_sms' => true,
                    'customer_whatsapp' => false,
                    'owner_telegram' => true,
                    'custom_override' => true,
                    'active' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        SupportTicketTemplate::whereIn('name', [
            'Connection Problem', 'Internet Speed', 'Outdoor Box Down', 'ONU Loss Light',
            'Laser High', 'Fiber Cut Down', 'Disconnect', 'Router Problem', 'Bill Collection',
            'Router Configure', 'ONU Change', 'New Connection', 'Connection Shift', 'Sales Query',
        ])->delete();
    }
};
