<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Redirect old resend requests without sending a verification email.
     */
    public function store(Request $request): RedirectResponse
    {
        return redirect()->intended(route('front.products.index', absolute: false));
    }
}
