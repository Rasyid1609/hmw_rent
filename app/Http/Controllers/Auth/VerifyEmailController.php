<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Old verification links return to the catalog without changing the account.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->intended(route('front.products.index', absolute: false));
    }
}
