<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resolve and ensure a valid account context for the authenticated user.
     * If user.account_id is missing, fall back to the first available account
     * and persist it on the user to stabilize subsequent requests.
     */
    protected function resolveAccountIdForUser($user): int
    {
        $accountId = (int) ($user?->account_id ?? 0);

        if ($accountId > 0) {
            return $accountId;
        }

        $fallbackAccountId = (int) (Account::query()->value('id') ?? 0);

        if ($fallbackAccountId > 0 && $user) {
            $user->account_id = $fallbackAccountId;
            $user->save();

            return $fallbackAccountId;
        }

        abort(403, 'Account context is missing.');
    }

    /**
     * Resolve the current user's account ID from the request.
     * Aborts with 403 if no account context can be established.
     */
    protected function getAccountId(Request $request): int
    {
        return $this->resolveAccountIdForUser($request->user());
    }

    /**
     * Resolve account ID from the authenticated user directly (no Request needed).
     * Aborts with 403 if no account context can be established.
     */
    protected function currentAccountId(): int
    {
        return $this->resolveAccountIdForUser(auth()->user());
    }
}
