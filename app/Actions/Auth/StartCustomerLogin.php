<?php

namespace App\Actions\Auth;

use App\Models\Customer;
use App\Models\CustomerOtp;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class StartCustomerLogin
{
    public const OTP_TTL_MINUTES = 5;

    public const RESEND_THROTTLE_SECONDS = 30;

    /**
     * Generate a fresh 4-digit OTP for the given phone, store it hashed,
     * invalidate prior unused codes, and return the code so callers can
     * deliver it (currently: log + flash for dev).
     *
     * @return array{phone: string, code: string, expires_at: CarbonInterface}
     */
    public function handle(string $phone, ?string $name = null): array
    {
        $phone = $this->normalize($phone);

        $recent = CustomerOtp::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('created_at', '>', now()->subSeconds(self::RESEND_THROTTLE_SECONDS))
            ->latest('created_at')
            ->first();

        if ($recent) {
            $wait = self::RESEND_THROTTLE_SECONDS - now()->diffInSeconds($recent->created_at, true);
            throw new TooManyOtpRequests("Подождите {$wait} сек перед новой попыткой.");
        }

        // Ensure customer exists so we can attach orders later. Phone is the unique key.
        Customer::firstOrCreate(
            ['phone' => $phone],
            ['name' => $name ?: 'Гость'],
        );

        // Invalidate prior unused codes for this phone.
        CustomerOtp::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = (string) random_int(1000, 9999);
        $expiresAt = now()->addMinutes(self::OTP_TTL_MINUTES);

        CustomerOtp::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => $expiresAt,
            'created_at' => now(),
        ]);

        // Dev delivery: log so it's visible in laravel.log / pail.
        // TODO: replace with real SMS gateway in production.
        Log::info("[OTP] phone={$phone} code={$code} expires_in=".self::OTP_TTL_MINUTES.'m');

        return ['phone' => $phone, 'code' => $code, 'expires_at' => $expiresAt];
    }

    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        // Russia: 8XXXXXXXXXX → +7XXXXXXXXXX; 7XXXXXXXXXX → +7XXXXXXXXXX.
        if (str_starts_with($digits, '8') && strlen($digits) === 11) {
            $digits = '7'.substr($digits, 1);
        }

        return '+'.$digits;
    }
}
