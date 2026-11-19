<?php

namespace App\Services\Network;

use App\Models\DeviceSession;

/**
 * Default binding until the Python network service exists (Phase 6).
 * FAILS CLOSED: nobody gets internet access and nothing pretends enforcement happened.
 */
class UnavailableGateway implements NetworkGateway
{
    public function authorize(DeviceSession $session, array $limits): void
    {
        throw new GatewayException('The network service is not configured; access cannot be granted.');
    }

    public function deauthorize(DeviceSession $session, string $reason): void
    {
        throw new GatewayException('The network service is not configured; access cannot be removed remotely.');
    }
}
