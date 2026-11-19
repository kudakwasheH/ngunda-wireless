<?php

namespace App\Services\Network;

use App\Models\DeviceSession;

/**
 * Boundary between Laravel (business rules) and the operating system (enforcement).
 * The only production implementation talks to the restricted Python service (nws-netd).
 * Laravel never runs OS commands itself.
 */
interface NetworkGateway
{
    /**
     * Allow this session's device through the firewall with the given limits.
     * MUST throw GatewayException if enforcement could not be put in place.
     *
     * @param  array{download_kbps:?int, upload_kbps:?int, session_seconds:int, quota_bytes:?int}  $limits
     */
    public function authorize(DeviceSession $session, array $limits): void;

    /** Remove access for a device. MUST throw GatewayException on failure. */
    public function deauthorize(DeviceSession $session, string $reason): void;
}
