<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationPromptController extends Controller
{
    /**
     * Keep old verification URLs usable while email verification is disabled.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->intended(route('front.products.index', absolute: false));
    }
}
