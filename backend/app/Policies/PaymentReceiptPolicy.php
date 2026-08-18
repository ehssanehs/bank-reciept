<?php

namespace App\Policies;

use App\Models\PaymentReceipt;
use App\Models\Permission;
use App\Models\User;

class PaymentReceiptPolicy
{
    public function view(User $user, PaymentReceipt $receipt): bool
    {
        return $user->hasPermissionTo(Permission::VIEW_RECEIPTS);
    }

    public function delete(User $user, PaymentReceipt $receipt): bool
    {
        return $user->hasPermissionTo(Permission::VIEW_RECEIPTS) && $user->hasRole('super_admin', 'admin');
    }
}
