<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Notification\Domain\Model\PushSubscription;

interface PushSender
{
    /** Ist Web Push eingerichtet (VAPID-Schlüssel vorhanden)? */
    public function isConfigured(): bool;

    public function publicKey(): string;

    public function send(PushSubscription $subscription, PushMessage $message): PushResult;
}
