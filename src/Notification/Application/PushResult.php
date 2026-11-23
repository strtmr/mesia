<?php

declare(strict_types=1);

namespace App\Notification\Application;

enum PushResult
{
    case Sent;
    /** Abo existiert beim Push-Dienst nicht mehr (App gelöscht, Erlaubnis entzogen): Abo entfernen */
    case Gone;
    /** Vorübergehender Fehler: beim nächsten Lauf erneut versuchen */
    case Failed;
}
