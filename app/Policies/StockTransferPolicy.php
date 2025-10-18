<?php

namespace App\Policies;

use App\Models\User;
use App\Models\StockTransfer;

class StockTransferPolicy
{
    public function create(User $user)
    {
        return $user->role === 'admin' || $user->role === 'manager';
    }

    public function view(User $user, StockTransfer $transfer)
    {
        return $user->id === $transfer->transferred_by
            || $user->role === 'admin'
            || $user->role === 'manager';
    }

    public function cancel(User $user, StockTransfer $transfer)
    {
        $withinTime = $transfer->created_at->diffInHours(now()) <= 48;
        return (
            ($user->id === $transfer->transferred_by
                || $user->role === 'admin'
                || $user->role === 'manager'
            ) && $transfer->status === 'completed' && $withinTime
        );
    }

    public function delete(User $user, StockTransfer $transfer)
    {
        return $user->role === 'admin' && $transfer->status === 'cancelled';
    }

    public function stats(User $user)
    {
        return $user->role === 'admin' || $user->role === 'manager';
    }
}
