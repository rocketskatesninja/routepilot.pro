<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor for staff: secret generation, QR provisioning, code
 * verification (with a ±1 step window for clock drift), and recovery codes.
 * Thin wrapper over pragmarx/google2fa + bacon/bacon-qr-code.
 */
class TwoFactorAuth
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /** Inline SVG QR for the authenticator-app otpauth provisioning URI. */
    public function qrSvg(User $user, string $secret): string
    {
        $uri = $this->google2fa->getQRCodeUrl(
            (string) config('app.name'),
            (string) $user->getAttribute('email'),
            $secret,
        );

        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($uri);
    }

    /** Verify a 6-digit TOTP code against the secret (±1 30s step). */
    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if ($code === '') {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $code, 1);
    }

    /**
     * A fresh set of single-use recovery codes.
     *
     * @return list<string>
     */
    public function newRecoveryCodes(int $count = 8): array
    {
        return array_map(
            static fn (): string => Str::upper(Str::random(5).'-'.Str::random(5)),
            range(1, $count),
        );
    }
}
