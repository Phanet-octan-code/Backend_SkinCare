<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAndCustomerModulesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $hr;
    protected User $cashier;
    protected Position $dermatologistPosition;
    protected Position $cashierPosition;
    protected Employee $employee1;
    protected Employee $employee2;
    protected Customer $vipCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::where('role', 'admin')->first();
        $this->hr = User::where('role', 'hr')->first();
        $this->cashier = User::where('role', 'cashier')->first();

        $this->dermatologistPosition = Position::firstOrCreate(
            ['name' => 'Lead Dermatologist'],
            ['base_salary' => 4500.00]
        );

        $this->cashierPosition = Position::firstOrCreate(
            ['name' => 'POS Cashier & Receptionist'],
            ['base_salary' => 1800.00]
        );

        $this->employee1 = Employee::firstOrCreate(
            ['email' => 'staff.test1@skincare.com'],
            [
                'name' => 'Julian Vance',
                'position_id' => $this->dermatologistPosition->id,
                'phone' => '+1-555-4401',
                'start_date' => Carbon::now()->subMonths(6)->toDateString(),
                'status' => 'active',
            ]
        );

        $this->employee2 = Employee::firstOrCreate(
            ['email' => 'staff.test2@skincare.com'],
            [
                'name' => 'Serena Joy',
                'position_id' => $this->cashierPosition->id,
                'phone' => '+1-555-4402',
                'start_date' => Carbon::now()->subMonths(2)->toDateString(),
                'status' => 'active',
            ]
        );

        $this->vipCustomer = Customer::firstOrCreate(
            ['email' => 'vip.client@test.com'],
            [
                'name' => 'Elena Rostova',
                'phone' => '+1-555-9988',
                'address' => '450 Park Avenue, NY',
                'membership_number' => 'MEM-PLT-9999',
                'membership_level' => 'Platinum',
                'status' => 'active',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | POSITIONS MODULE TESTS
    |--------------------------------------------------------------------------
    */

    public function test_positions_index_renders_with_employee_counts_for_hr(): void
    {
        $response = $this->actingAs($this->hr)->get(route('positions.index'));

        $response->assertStatus(200);
        $response->assertSee('Positions & Base Compensation');
        $response->assertSee($this->dermatologistPosition->name);
        $response->assertSee('$' . number_format($this->dermatologistPosition->base_salary, 2));
    }

    public function test_position_creation_and_salary_validation(): void
    {
        // Salary cannot be negative
        $failResponse = $this->actingAs($this->hr)->post(route('positions.store'), [
            'name' => 'Assistant Chemist',
            'base_salary' => -500,
        ]);
        $failResponse->assertSessionHasErrors('base_salary');

        // Successful creation
        $response = $this->actingAs($this->hr)->post(route('positions.store'), [
            'name' => 'Formulation Chemist',
            'base_salary' => 3800.50,
        ]);

        $response->assertRedirect(route('positions.index'));
        $this->assertDatabaseHas('positions', [
            'name' => 'Formulation Chemist',
            'base_salary' => 3800.50,
        ]);
    }

    public function test_position_update_with_unique_name(): void
    {
        $response = $this->actingAs($this->hr)->put(route('positions.update', $this->dermatologistPosition), [
            'name' => 'Senior Lead Dermatologist',
            'base_salary' => 5200.00,
        ]);

        $response->assertRedirect(route('positions.index'));
        $this->assertDatabaseHas('positions', [
            'id' => $this->dermatologistPosition->id,
            'name' => 'Senior Lead Dermatologist',
            'base_salary' => 5200.00,
        ]);
    }

    public function test_position_unsafe_deletion_prevented_when_staff_assigned(): void
    {
        // $this->employee1 is assigned to $this->dermatologistPosition
        $response = $this->actingAs($this->hr)->delete(route('positions.destroy', $this->dermatologistPosition));

        $response->assertRedirect(route('positions.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('positions', ['id' => $this->dermatologistPosition->id]);
    }

    public function test_position_deletion_allowed_when_no_staff_assigned(): void
    {
        $emptyPosition = Position::create([
            'name' => 'Temporary Intern Tier',
            'base_salary' => 1200.00,
        ]);

        $response = $this->actingAs($this->hr)->delete(route('positions.destroy', $emptyPosition));

        $response->assertRedirect(route('positions.index'));
        $this->assertDatabaseMissing('positions', ['id' => $emptyPosition->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEES MODULE TESTS
    |--------------------------------------------------------------------------
    */

    public function test_employees_index_search_filter_sort_paginate(): void
    {
        $response = $this->actingAs($this->hr)->get(route('employees.index', [
            'search' => 'Julian',
            'position_id' => $this->dermatologistPosition->id,
            'status' => 'active',
            'sort_by' => 'name',
            'sort_dir' => 'asc',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Julian Vance');
    }

    public function test_employee_store_validation_and_creation(): void
    {
        $response = $this->actingAs($this->hr)->post(route('employees.store'), [
            'name' => 'Camille Dubois',
            'position_id' => $this->cashierPosition->id,
            'phone' => '+1-555-7788',
            'email' => 'camille.d@skincare.com',
            'start_date' => '2026-01-15',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', [
            'name' => 'Camille Dubois',
            'email' => 'camille.d@skincare.com',
            'position_id' => $this->cashierPosition->id,
        ]);
    }

    public function test_employee_show_profile_and_attendance_history(): void
    {
        // Create an attendance record for employee1
        $attDate = Carbon::now()->subDays(5)->toDateString();
        Attendance::where('employee_id', $this->employee1->id)->whereDate('date', $attDate)->delete();

        Attendance::create([
            'employee_id' => $this->employee1->id,
            'date' => $attDate,
            'check_in' => '08:50:00',
            'check_out' => '17:30:00',
            'status' => 'present',
            'notes' => 'Full smooth clinic shift',
        ]);

        $response = $this->actingAs($this->hr)->get(route('employees.show', $this->employee1));

        $response->assertStatus(200);
        $response->assertSee($this->employee1->name);
        $response->assertSee($this->dermatologistPosition->name);
        $response->assertSee('Full smooth clinic shift');
    }

    public function test_employee_update_and_toggle_status(): void
    {
        $response = $this->actingAs($this->hr)->put(route('employees.update', $this->employee1), [
            'name' => 'Julian Vance Jr.',
            'position_id' => $this->dermatologistPosition->id,
            'phone' => '+1-555-9999',
            'email' => $this->employee1->email,
            'start_date' => $this->employee1->start_date->toDateString(),
            'status' => 'active',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employees', [
            'id' => $this->employee1->id,
            'name' => 'Julian Vance Jr.',
            'phone' => '+1-555-9999',
        ]);

        // Toggle status
        $toggleResp = $this->actingAs($this->hr)->patch(route('employees.toggle-status', $this->employee1));
        $toggleResp->assertSessionHas('status');
        $this->assertDatabaseHas('employees', [
            'id' => $this->employee1->id,
            'status' => 'inactive',
        ]);
    }

    public function test_employee_safe_deactivation_on_delete_with_history(): void
    {
        // Ensure employee has attendance
        Attendance::firstOrCreate(
            ['employee_id' => $this->employee2->id, 'date' => '2026-08-10'],
            ['check_in' => '09:00:00', 'check_out' => '17:00:00', 'status' => 'present']
        );

        $response = $this->actingAs($this->hr)->delete(route('employees.destroy', $this->employee2));

        $response->assertRedirect(route('employees.index'));
        // Profile was preserved as inactive, not hard deleted!
        $this->assertDatabaseHas('employees', [
            'id' => $this->employee2->id,
            'status' => 'inactive',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE MODULE TESTS
    |--------------------------------------------------------------------------
    */

    public function test_attendance_check_in_and_duplicate_prevention(): void
    {
        $targetDate = '2026-11-01';

        // Clean any existing record
        Attendance::where('employee_id', $this->employee1->id)->whereDate('date', $targetDate)->delete();

        // 1. First record logs successfully
        $response = $this->actingAs($this->hr)->post(route('attendances.store'), [
            'employee_id' => $this->employee1->id,
            'date' => $targetDate,
            'check_in' => '08:50:00',
            'check_out' => '17:30:00',
            'status' => 'present',
            'notes' => 'On time morning arrival',
        ]);

        $response->assertRedirect();
        $this->assertTrue(
            Attendance::where('employee_id', $this->employee1->id)
                ->whereDate('date', $targetDate)
                ->where('status', 'present')
                ->exists()
        );

        // 2. Duplicate record on same employee and date is blocked
        $dupResponse = $this->actingAs($this->hr)->post(route('attendances.store'), [
            'employee_id' => $this->employee1->id,
            'date' => $targetDate,
            'check_in' => '09:00:00',
            'status' => 'present',
        ]);

        $dupResponse->assertSessionHasErrors('employee_id');
    }

    public function test_attendance_check_out_time_validation(): void
    {
        $targetDate = '2026-11-02';
        Attendance::where('employee_id', $this->employee1->id)->whereDate('date', $targetDate)->delete();

        // Invalid: Check-out before check-in
        $response = $this->actingAs($this->hr)->post(route('attendances.store'), [
            'employee_id' => $this->employee1->id,
            'date' => $targetDate,
            'check_in' => '17:00:00',
            'check_out' => '09:00:00', // Earlier!
            'status' => 'present',
        ]);

        $response->assertSessionHasErrors('check_out');
    }

    public function test_attendance_configurable_late_threshold_classification(): void
    {
        $targetDate = '2026-11-03';
        Attendance::where('employee_id', $this->employee1->id)->whereDate('date', $targetDate)->delete();

        // Late threshold is 09:15:00. Check-in at 09:25:00 with status=present should auto-mark as late!
        $response = $this->actingAs($this->hr)->post(route('attendances.store'), [
            'employee_id' => $this->employee1->id,
            'date' => $targetDate,
            'check_in' => '09:25:00',
            'check_out' => '18:00:00',
            'status' => 'present',
            'notes' => 'Traffic delay',
        ]);

        $response->assertRedirect();
        $this->assertTrue(
            Attendance::where('employee_id', $this->employee1->id)
                ->whereDate('date', $targetDate)
                ->where('status', 'late')
                ->exists()
        );
    }

    public function test_attendance_permission_and_absent_statuses(): void
    {
        $datePermission = '2026-11-04';
        $dateAbsent = '2026-11-05';
        Attendance::where('employee_id', $this->employee1->id)->whereIn('date', [$datePermission, $dateAbsent])->delete();

        // Permission status with notes
        $permResp = $this->actingAs($this->hr)->post(route('attendances.store'), [
            'employee_id' => $this->employee1->id,
            'date' => $datePermission,
            'status' => 'permission',
            'notes' => 'Medical appointment authorization',
        ]);
        $permResp->assertRedirect();
        $this->assertTrue(
            Attendance::where('employee_id', $this->employee1->id)
                ->whereDate('date', $datePermission)
                ->where('status', 'permission')
                ->exists()
        );

        // Absent status without times
        $absResp = $this->actingAs($this->hr)->post(route('attendances.store'), [
            'employee_id' => $this->employee1->id,
            'date' => $dateAbsent,
            'status' => 'absent',
            'notes' => 'Unscheduled absence',
        ]);
        $absResp->assertRedirect();
        $this->assertTrue(
            Attendance::where('employee_id', $this->employee1->id)
                ->whereDate('date', $dateAbsent)
                ->where('status', 'absent')
                ->exists()
        );
    }

    public function test_attendance_quick_checkout_and_working_hours_calculation(): void
    {
        $targetDate = '2026-11-06';
        Attendance::where('employee_id', $this->employee1->id)->whereDate('date', $targetDate)->delete();

        $attendance = Attendance::create([
            'employee_id' => $this->employee1->id,
            'date' => $targetDate,
            'check_in' => '09:00:00',
            'check_out' => null,
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->hr)->patch(route('attendances.checkout', $attendance), [
            'check_out' => '17:30:00',
        ]);

        $response->assertRedirect();
        $attendance->refresh();
        $this->assertEquals('17:30:00', $attendance->check_out);
        $this->assertEquals(8.5, $attendance->working_hours);
    }

    public function test_attendance_monthly_report_renders_and_aggregates(): void
    {
        $response = $this->actingAs($this->hr)->get(route('attendances.report', [
            'month' => date('Y-m'),
        ]));

        $response->assertStatus(200);
        $response->assertSee('Monthly Staff Attendance Report');
        $response->assertSee('Total Working Hours');
        $response->assertSee('Punctuality Rate');
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMERS / MEMBERS MODULE TESTS
    |--------------------------------------------------------------------------
    */

    public function test_customers_index_search_and_tier_filter(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('customers.index', [
            'search' => 'Elena',
            'tier' => 'Platinum',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Elena Rostova');
        $response->assertSee('MEM-PLT-9999');
    }

    public function test_customer_creation_auto_generates_membership_number(): void
    {
        $response = $this->actingAs($this->cashier)->post(route('customers.store'), [
            'name' => 'Giselle Bundchen',
            'phone' => '+1-555-8811',
            'email' => 'giselle@client.com',
            'membership_level' => 'Gold',
        ]);

        $response->assertRedirect(route('customers.index'));
        $customer = Customer::where('email', 'giselle@client.com')->first();
        $this->assertNotNull($customer);
        $this->assertStringStartsWith('MEM-GLD-', $customer->membership_number);
        $this->assertEquals(10.0, $customer->membership_discount_percentage);
    }

    public function test_customer_creation_json_response_for_pos(): void
    {
        $response = $this->actingAs($this->cashier)
            ->postJson(route('customers.store'), [
                'name' => 'Margot Robbie',
                'phone' => '+1-555-1234',
                'membership_level' => 'Silver',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'customer' => [
                'name' => 'Margot Robbie',
                'membership_level' => 'Silver',
                'discount_rate' => 5.0,
            ],
        ]);
    }

    public function test_customer_show_displays_profile_and_sales_history(): void
    {
        $product = Product::first();

        // Create completed sale linked to Elena
        $sale = Sale::create([
            'user_id' => $this->cashier->id,
            'customer_id' => $this->vipCustomer->id,
            'invoice_number' => 'INV-TEST-0099',
            'subtotal' => 100.00,
            'discount' => 15.00,
            'tax' => 0.00,
            'final_total' => 85.00,
            'payment_type' => 'card',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 50.00,
            'subtotal' => 100.00,
            'discount' => 15.00,
            'final_total' => 85.00,
        ]);

        $response = $this->actingAs($this->cashier)->get(route('customers.show', $this->vipCustomer));

        $response->assertStatus(200);
        $response->assertSee('Elena Rostova');
        $response->assertSee('INV-TEST-0099');
        $response->assertSee('$85.00');
        $response->assertSee('Lifetime Spend');
    }

    public function test_customer_guest_sales_support_nullable_customer_id(): void
    {
        $product = Product::first();

        $guestSale = Sale::create([
            'user_id' => $this->cashier->id,
            'customer_id' => null, // Guest checkout
            'invoice_number' => 'INV-GUEST-0001',
            'subtotal' => 45.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'final_total' => 45.00,
            'payment_type' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        $this->assertNull($guestSale->customer_id);
        $this->assertDatabaseHas('sales', [
            'invoice_number' => 'INV-GUEST-0001',
            'customer_id' => null,
        ]);
    }

    public function test_customer_safe_deactivation_on_delete_when_sales_exist(): void
    {
        // Elena has sales from test above
        Sale::create([
            'user_id' => $this->cashier->id,
            'customer_id' => $this->vipCustomer->id,
            'invoice_number' => 'INV-AUDIT-SAFE',
            'subtotal' => 50.00,
            'discount' => 7.50,
            'tax' => 0.00,
            'final_total' => 42.50,
            'payment_type' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('customers.destroy', $this->vipCustomer));

        $response->assertRedirect(route('customers.index'));
        // Record is preserved as inactive to safeguard financial auditing
        $this->assertDatabaseHas('customers', [
            'id' => $this->vipCustomer->id,
            'status' => 'inactive',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | AUTHORIZATION / RBAC TESTS
    |--------------------------------------------------------------------------
    */

    public function test_cashier_forbidden_from_staff_and_attendance_modules(): void
    {
        // Employees index
        $empResp = $this->actingAs($this->cashier)->get(route('employees.index'));
        $empResp->assertStatus(403);

        // Positions index
        $posResp = $this->actingAs($this->cashier)->get(route('positions.index'));
        $posResp->assertStatus(403);

        // Attendance index
        $attResp = $this->actingAs($this->cashier)->get(route('attendances.index'));
        $attResp->assertStatus(403);

        // Attendance report
        $repResp = $this->actingAs($this->cashier)->get(route('attendances.report'));
        $repResp->assertStatus(403);
    }

    public function test_cashier_authorized_for_customer_crm_access(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('customers.index'));
        $response->assertStatus(200);
    }
}
