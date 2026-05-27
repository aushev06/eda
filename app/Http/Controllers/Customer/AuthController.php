<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Auth\StartCustomerLogin;
use App\Actions\Auth\TooManyOtpRequests;
use App\Actions\Auth\VerifyCustomerOtp;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function login(): Response
    {
        return Inertia::render('account/login', [
            'phone' => session('login.phone'),
        ]);
    }

    public function start(Request $request, StartCustomerLogin $action): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:5', 'max:32'],
        ]);

        try {
            $result = $action->handle($data['phone']);
        } catch (TooManyOtpRequests $e) {
            throw ValidationException::withMessages(['phone' => $e->getMessage()]);
        }

        $flash = [
            'login.phone' => $result['phone'],
        ];

        // Dev convenience: surface the OTP in the flash so the verify page
        // can show it. Strip this once a real SMS gateway is wired up.
        if (app()->environment(['local', 'testing'])) {
            $flash['dev_otp'] = $result['code'];
        }

        return redirect()
            ->route('customer.verify')
            ->with($flash);
    }

    public function verify(): Response
    {
        return Inertia::render('account/verify', [
            'phone' => session('login.phone'),
            'dev_otp' => session('dev_otp'),
        ]);
    }

    public function confirm(Request $request, VerifyCustomerOtp $action): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:5', 'max:32'],
            'code' => ['required', 'string', 'digits:4'],
        ]);

        $action->handle($data['phone'], $data['code']);

        $request->session()->regenerate();

        return redirect()->route('account.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        auth()->guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
