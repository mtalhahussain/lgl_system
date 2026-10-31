<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_has_only_three_roles_and_reconciled_payments(): void
    {
        $this->seed();
        $this->assertSame(23, User::count());
        $this->assertSame(['admin', 'student', 'teacher'], User::distinct()->orderBy('role')->pluck('role')->all());
        $this->assertSame('admin@admin.com', User::where('role', 'admin')->sole()->email);
        $this->assertSame(0, User::where('role', '!=', 'admin')->where('email', 'not like', '%@lgl.com')->count());
        foreach (User::all() as $user) {
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $user->password));
        }
        $this->assertGreaterThan(0, Enrollment::count());
        foreach (Enrollment::all() as $enrollment) {
            app(PaymentService::class)->assertReconciled($enrollment);
            $this->assertSame(1, $enrollment->payments()->count());
            $this->assertSame(3, $enrollment->feeInstallments()->count());
        }
    }
}
