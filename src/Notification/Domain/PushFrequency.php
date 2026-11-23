<?php

declare(strict_types=1);

namespace App\Notification\Domain;

/**
 * Wie oft ein Gerät höchstens benachrichtigt wird.
 */
enum PushFrequency: string
{
    /** Beim nächsten Cron-Lauf (jede Minute) */
    case Immediate = 'immediate';
    /** Höchstens alle 15 Minuten eine Sammel-Nachricht */
    case QuarterHour = 'quarter_hour';

    public function minimumInterval(): ?\DateInterval
    {
        return match ($this) {
            self::Immediate => null,
            self::QuarterHour => new \DateInterval('PT15M'),
        };
    }
}
