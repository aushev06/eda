<?php

namespace App\Actions\Auth;

use App\Models\Customer;
use App\Models\CustomerOtp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VerifyCustomerOtp
{
    public const MAX_ATTEMPTS = 5;

    public function handle(string $phone, string $code): Customer
    {
        $phone = app(StartCustomerLogin::class)->normalize($phone);

        $otp = CustomerOtp::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if (! $otp) {
            $this->fail('Код истёк. Запросите новый.');
        }

        $otp->increment('attempts');

        if ($otp->attempts > self::MAX_ATTEMPTS) {
            $otp->update(['consumed_at' => now()]);
            $this->fail('Слишком много попыток. Запросите новый код.');
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $this->fail('Неверный код.');
        }

        $otp->update(['consumed_at' => now()]);

        $customer = Customer::query()->where('phone', $phone)->firstOrFail();

        Auth::guard('customer')->login($customer);

        return $customer;
    }

    /**
     * @throws ValidationException
     */
    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['code' => $message]);
    }
}
