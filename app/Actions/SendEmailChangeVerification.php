<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\EmailChangeVerificationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Email a signed confirmation link to the NEW address a user wants to switch
 * to. The switch only lands when they open the link (see ConfirmEmailChange),
 * proving they control that inbox. The link embeds the target email and is
 * tamper-proofed by the signature, so no pending state needs storing.
 */
class SendEmailChangeVerification
{
    public function handle(User $user, string $newEmail): void
    {
        $url = URL::temporarySignedRoute(
            'email-change.confirm',
            now()->addHour(),
            ['user' => $user->id, 'email' => $newEmail],
        );

        Mail::to($newEmail)->send(new EmailChangeVerificationMail($user->first_name, $newEmail, $url));
    }
}
