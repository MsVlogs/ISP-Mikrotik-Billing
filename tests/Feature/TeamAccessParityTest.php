<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamAccessParityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Role::findOrCreate('Super Admin', 'web');
        $user->assignRole('Super Admin');
        return $user;
    }

    public function test_team_access_parity_routes_are_protected_and_render_for_admin(): void
    {
        $routes = [
            'xlink.team-access','staff-team','attendance','attendance-report','attendance-settings',
            'attendance-leave','attendance-payroll','staff-conveyance','user-activity','team-access.demo',
        ];

        foreach ($routes as $name) {
            $this->get(route($name))->assertRedirect();
        }

        $admin = $this->admin();
        $this->actingAs($admin);

        foreach ($routes as $name) {
            $response = $this->get(route($name));
            if ($name === 'user-activity') {
                $response->assertRedirect(route('admin.activity-logs'));
            } else {
                $response->assertSuccessful();
            }
        }
    }

    public function test_attendance_and_leave_workflows_store_data(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->post(route('team-attendance.checkin'), ['lat' => '23.8', 'lng' => '90.4'])
            ->assertRedirect();
        $this->post(route('attendance-leave.submit'), [
            'leave_type' => 'Casual', 'from_date' => today()->addDay()->toDateString(),
            'to_date' => today()->addDays(2)->toDateString(), 'reason' => 'Personal',
        ])->assertRedirect();

        $this->assertDatabaseCount('team_attendance_records', 1);
        $this->assertNotNull(\App\Models\TeamAttendanceRecord::query()->where('user_id', $admin->id)->whereDate('attendance_date', today())->first());
        $this->assertDatabaseHas('team_leave_requests', ['user_id' => $admin->id, 'status' => 'pending']);
    }
}
