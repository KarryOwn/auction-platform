<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine if the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->buyer_id
            || $user->id === $invoice->seller_id
            || $user->isStaff();
    }

    /**
     * Determine if the user can download the invoice PDF.
     */
    public function download(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
