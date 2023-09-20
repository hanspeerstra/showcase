<?php

declare(strict_types=1);

namespace App\Auth\TwoFactorAuthentication;

use App\Auth\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Nette\Utils\Random;
use Propaganistas\LaravelPhone\PhoneNumber;

class TwoFactorAuthenticationService
{
    public const TWO_FACTOR_METHOD_EMAIL = 'email';
    public const TWO_FACTOR_METHOD_PHONE = 'phone';

    public const TWO_FACTOR_SETUP_STATUS_PENDING = 'pending';
    public const TWO_FACTOR_SETUP_STATUS_APPROVED = 'approved';

    /** @var bool */
    private $twoFactorEnabledOnEnvironment;

    public function __construct(bool $twoFactorEnabledOnEnvironment)
    {
        $this->twoFactorEnabledOnEnvironment = $twoFactorEnabledOnEnvironment;
    }

    public function requiresTwoFactorVerification(User $user): bool
    {
        if (!$this->twoFactorEnabledOnEnvironment) {
            return false;
        }

        return true;
    }

    public function isTwoFactorSetupCompleted(User $user): bool
    {
        return self::TWO_FACTOR_SETUP_STATUS_APPROVED === $user->two_factor_setup_status;
    }

    public function isPendingSetupCodeVerification(User $user): bool
    {
        return null !== $user->two_factor_setup_verification_code;
    }

    public function isPendingCodeVerification(User $user): bool
    {
        return null !== $user->two_factor_verification_code;
    }

    public function isTwoFactorVerificationCodeExpired(User $user): bool
    {
        return null === $user->two_factor_verification_code_expires_at
            || $user->two_factor_verification_code_expires_at->lessThan(Carbon::now());
    }

    public function generateAndSendTwoFactorVerificationCode(User $user): void
    {
        $verificationCode = $this->generateVerificationCode();

        $user->two_factor_verification_code = $verificationCode;
        $user->two_factor_verification_code_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        Notification::send($user, new TwoFactorVerificationCodeNotification($verificationCode));
    }

    public function verifyTwoFactorCode(User $user, string $twoFactorCode): bool
    {
        if ($this->isValidTwoFactorCode($user, $twoFactorCode)) {
            $this->clearTwoFactorVerificationCode($user);

            return true;
        }

        return false;
    }

    public function verifySetupTwoFactorCode(User $user, string $verificationCode): bool
    {
        if ($this->isValidSetupTwoFactorCode($user, $verificationCode)) {
            $user->two_factor_setup_verification_code = null;
            $user->two_factor_setup_verification_code_expires_at = null;
            $user->two_factor_setup_verified_at = Carbon::now();
            $user->two_factor_setup_status = self::TWO_FACTOR_SETUP_STATUS_APPROVED;
            $user->save();

            return true;
        }

        return false;
    }

    public function storeSetupEmailMethod(User $user): User
    {
        $user->two_factor_method = self::TWO_FACTOR_METHOD_EMAIL;

        return $this->generateAndSendSetupVerificationCode($user);
    }

    public function storeSetupPhoneMethod(User $user, PhoneNumber $phoneNumber): User
    {
        $user->two_factor_method = self::TWO_FACTOR_METHOD_PHONE;
        $user->two_factor_phone_number = $phoneNumber->formatE164();

        return $this->generateAndSendSetupVerificationCode($user);
    }

    public function generateAndSendSetupVerificationCode(User $user): User
    {
        if (!$user->hasTwoFactorMethod()) {
            throw new LogicException('Cannot send setup verification code if method is unknown');
        }

        $verificationCode = $this->generateVerificationCode();

        $user->two_factor_setup_status = self::TWO_FACTOR_SETUP_STATUS_PENDING;
        $user->two_factor_setup_verification_code = $verificationCode;
        $user->two_factor_setup_verification_code_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        Notification::send($user, new TwoFactorSetupVerificationCodeNotification($verificationCode));

        return $user;
    }

    public function isVerified(User $user): bool
    {
        return null === $user->two_factor_verification_code_expires_at;
    }

    public function isValidTwoFactorCode(User $user, string $twoFactorCode): bool
    {
        return $user->two_factor_verification_code === $twoFactorCode
            && null !== $user->two_factor_verification_code_expires_at
            && $user->two_factor_verification_code_expires_at->greaterThanOrEqualTo(Carbon::now());
    }

    public function isValidSetupTwoFactorCode(User $user, string $twoFactorCode): bool
    {
        return $user->two_factor_setup_verification_code === $twoFactorCode
            && null !== $user->two_factor_setup_verification_code_expires_at
            && $user->two_factor_setup_verification_code_expires_at->greaterThanOrEqualTo(Carbon::now());
    }

    public function clearTwoFactorVerificationCode(User $user): User
    {
        $user->two_factor_verification_code = null;
        $user->two_factor_verification_code_expires_at = null;
        $user->save();

        return $user;
    }

    private function generateVerificationCode(): string
    {
        return Random::generate(6, '0-9');
    }
}
