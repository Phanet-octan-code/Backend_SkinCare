@extends('layouts.app')

@section('title', 'Customers & VIP Loyalty CRM')

@section('breadcrumb')
    <li class="breadcrumb-item active text-dark" aria-current="page">Customers</li>
@endsection

@section('content')
<x-page-header 
    title="Customers & VIP Loyalty Members" 
    description="Customer CRM profiles, VIP tier privileges, contact details, and lifetime purchase history."
    :breadcrumbs="['Customers' => null]">
    <x-slot:actions>
        <x-action-button roles="admin,manager,cashier" icon="bi-person-plus" modalTarget="#addCustomerModal">
            New Customer / VIP
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- Search & CRM Filters -->
<x-search-filter :action="route('customers.index')" :resetUrl="route('customers.index')" placeholder="Search name, phone, or member #...">
    <div class="col-6 col-md-3">
        <select name="tier" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All Membership Tiers</option>
            <option value="Platinum" {{ request('tier') === 'Platinum' ? 'selected' : '' }}>Platinum VIP (15% off)</option>
            <option value="Gold" {{ request('tier') === 'Gold' ? 'selected' : '' }}>Gold VIP (10% off)</option>
            <option value="Silver" {{ request('tier') === 'Silver' ? 'selected' : '' }}>Silver Member (5% off)</option>
            <option value="Bronze" {{ request('tier') === 'Bronze' ? 'selected' : '' }}>Bronze Standard</option>
        </select>
    </div>

    <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>

    <div class="col-12 col-md-3">
        <select name="sort_by" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="created_at" {{ request('sort_by') === 'created_at' ? 'selected' : '' }}>Sort: Recently Added</option>
            <option value="sales_sum_final_total" {{ request('sort_by') === 'sales_sum_final_total' ? 'selected' : '' }}>Sort: Highest Lifetime Spend</option>
            <option value="sales_count" {{ request('sort_by') === 'sales_count' ? 'selected' : '' }}>Sort: Most Orders</option>
            <option value="name" {{ request('sort_by') === 'name' ? 'selected' : '' }}>Sort: Member Name</option>
        </select>
    </div>
</x-search-filter>

<x-card :noPadding="true" title="Customer Directory" badge="{{ $customers->total() }} members">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light text-muted" style="font-size: 0.78rem; text-transform: uppercase;">
                <tr>
                    <th class="ps-4">Member Name</th>
                    <th>Membership #</th>
                    <th>VIP Tier & Discount</th>
                    <th>Contact Details</th>
                    <th>Completed Orders</th>
                    <th>Lifetime Spending</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $customer)
                @php
                    $tierBadgeClass = match(strtolower($customer->membership_level ?? '')) {
                        'platinum' => 'bg-dark text-white border-dark',
                        'gold' => 'bg-warning-subtle text-dark border-warning',
                        'silver' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                        default => 'bg-light text-dark border',
                    };
                @endphp
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-circle">
                                {{ strtoupper(substr($customer->name, 0, 2)) }}
                            </span>
                            <div>
                                <a href="{{ route('customers.show', $customer) }}" class="fw-semibold text-dark text-decoration-none hover-primary">
                                    {{ $customer->name }}
                                </a>
                                <div class="text-muted small text-truncate" style="max-width: 200px;">
                                    <i class="bi bi-geo-alt me-1"></i>{{ $customer->address ?? 'No address recorded' }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="font-monospace text-dark fw-bold">
                        {{ $customer->membership_number ?? 'REGULAR' }}
                    </td>
                    <td>
                        <span class="badge {{ $tierBadgeClass }} px-2 py-1">
                            <i class="bi bi-award me-1"></i>{{ $customer->membership_level }}
                        </span>
                        <div class="text-muted" style="font-size: 0.72rem;">{{ $customer->membership_discount_percentage }}% VIP discount</div>
                    </td>
                    <td>
                        <div><i class="bi bi-telephone text-muted me-1"></i>{{ $customer->phone ?? '—' }}</div>
                        <div class="text-muted small"><i class="bi bi-envelope text-muted me-1"></i>{{ $customer->email ?? '—' }}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            {{ $customer->sales_count }} orders
                        </span>
                    </td>
                    <td>
                        <span class="fw-bold text-success font-monospace">
                            ${{ number_format($customer->sales_sum_final_total ?? 0, 2) }}
                        </span>
                    </td>
                    <td>
                        <x-status-badge :status="$customer->status" />
                    </td>
                    <td class="text-end pe-4">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light border px-2 py-1" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.85rem;">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('customers.show', $customer) }}">
                                        <i class="bi bi-receipt text-muted"></i> Purchase History & VIP
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2 text-success" href="{{ route('pos.index', ['customer_id' => $customer->id]) }}">
                                        <i class="bi bi-cart-plus"></i> New Sale in POS
                                    </a>
                                </li>
                                <li>
                                    <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-bs-toggle="modal" data-bs-target="#editCustomerModal{{ $customer->id }}">
                                        <i class="bi bi-pencil text-muted"></i> Edit Client
                                    </button>
                                </li>
                                <li>
                                    <form method="POST" action="{{ route('customers.toggle-status', $customer) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                                            <i class="bi {{ $customer->status === 'active' ? 'bi-pause-circle text-warning' : 'bi-play-circle text-success' }}"></i>
                                            {{ $customer->status === 'active' ? 'Deactivate Member' : 'Activate Member' }}
                                        </button>
                                    </form>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteCustomerModal{{ $customer->id }}">
                                        <i class="bi bi-trash3"></i> Delete Client
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Edit Customer Modal -->
                        <x-modal id="editCustomerModal{{ $customer->id }}" title="Edit Customer: {{ $customer->name }}" size="md">
                            <form method="POST" action="{{ route('customers.update', $customer) }}">
                                @csrf
                                @method('PUT')

                                <x-form-input name="name" label="Customer Name" :value="$customer->name" required />

                                <div class="row g-2">
                                    <div class="col-6">
                                        <x-form-input name="phone" label="Phone" :value="$customer->phone" />
                                    </div>
                                    <div class="col-6">
                                        <x-form-input type="email" name="email" label="Email" :value="$customer->email" />
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-6">
                                        <x-form-select name="membership_level" label="VIP Loyalty Tier" required>
                                            <option value="Bronze" {{ $customer->membership_level === 'Bronze' ? 'selected' : '' }}>Bronze (0% discount)</option>
                                            <option value="Silver" {{ $customer->membership_level === 'Silver' ? 'selected' : '' }}>Silver (5% discount)</option>
                                            <option value="Gold" {{ $customer->membership_level === 'Gold' ? 'selected' : '' }}>Gold (10% discount)</option>
                                            <option value="Platinum" {{ $customer->membership_level === 'Platinum' ? 'selected' : '' }}>Platinum (15% discount)</option>
                                        </x-form-select>
                                    </div>
                                    <div class="col-6">
                                        <x-form-select name="status" label="Status" required>
                                            <option value="active" {{ $customer->status === 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ $customer->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </x-form-select>
                                    </div>
                                </div>

                                <x-form-input name="membership_number" label="Membership Number" :value="$customer->membership_number" />

                                <x-form-textarea name="address" label="Shipping / Delivery Address" :value="$customer->address" rows="2" />

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-skincare-primary">Save Profile</button>
                                </div>
                            </form>
                        </x-modal>

                        <!-- Delete Customer Modal -->
                        <x-modal id="deleteCustomerModal{{ $customer->id }}" title="Delete Customer Profile" size="md">
                            @if($customer->sales_count > 0)
                                <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-info-circle-fill fs-5"></i>
                                    <div>
                                        <strong>Historical Sales Preserved:</strong> This client has <strong>{{ $customer->sales_count }} recorded purchase transaction(s)</strong>.
                                    </div>
                                </div>
                                <p class="text-muted small">
                                    To protect financial audit integrity, invoicing history, and tax reports, confirming below will safely deactivate <strong>"{{ $customer->name }}"</strong> instead of deleting sales data.
                                </p>
                            @else
                                <p class="text-dark mb-3">
                                    Are you sure you want to permanently delete customer <strong>"{{ $customer->name }}"</strong>?
                                </p>
                            @endif

                            <form method="POST" action="{{ route('customers.destroy', $customer) }}">
                                @csrf
                                @method('DELETE')
                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-danger">Confirm</button>
                                </div>
                            </form>
                        </x-modal>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <x-empty-state icon="bi-person-heart" title="No customers found" description="No member accounts match the current filter." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($customers->hasPages())
    <x-slot:footer>
        {{ $customers->links() }}
    </x-slot:footer>
    @endif
</x-card>

<!-- Add Customer Modal -->
<x-modal id="addCustomerModal" title="Enroll New Skincare Client" size="md">
    <form method="POST" action="{{ route('customers.store') }}">
        @csrf

        <x-form-input name="name" label="Customer Full Name" required placeholder="e.g. Scarlett Johansen" />

        <div class="row g-2">
            <div class="col-6">
                <x-form-input name="phone" label="Phone Number" placeholder="+1-555-0211" />
            </div>
            <div class="col-6">
                <x-form-input type="email" name="email" label="Email Address" placeholder="client@example.com" />
            </div>
        </div>

        <div class="row g-2">
            <div class="col-6">
                <x-form-select name="membership_level" label="VIP Loyalty Tier" required>
                    <option value="Bronze" selected>Bronze Standard (0% off)</option>
                    <option value="Silver">Silver Member (5% off)</option>
                    <option value="Gold">Gold VIP (10% off)</option>
                    <option value="Platinum">Platinum VIP (15% off)</option>
                </x-form-select>
            </div>
            <div class="col-6">
                <x-form-input name="membership_number" label="Membership # (Optional)" placeholder="Auto-generated if empty" />
            </div>
        </div>

        <x-form-textarea name="address" label="Address" placeholder="Street, city, postal code..." rows="2" />

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary">Register Customer</button>
        </div>
    </form>
</x-modal>
@endsection
