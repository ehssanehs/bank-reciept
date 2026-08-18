<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\Permission;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::VIEW_PAYMENTS);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasPermissionTo(Permission::VIEW_PAYMENTS);
    }

    public function approve(User $user, Payment $payment): bool
    {
        return $user->hasPermissionTo(Permission::APPROVE_PAYMENTS);
    }

    public function reject(User $user, Payment $payment): bool
    {
        return $user->hasPermissionTo(Permission::REJECT_PAYMENTS);
    }
}
