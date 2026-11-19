<?php

namespace Tests\Support;

use App\Models\DeviceSession;
use App\Services\Network\GatewayException;
use App\Services\Network\NetworkGateway;

/** Test double for the OS enforcement boundary. Records calls; can be told to fail. */
class FakeGateway implements NetworkGateway
{
    public array $authorized = [];
    public array $deauthorized = [];
    public bool $failAuthorize = false;
    public bool $failDeauthorize = false;

    public function authorize(DeviceSession $session, array $limits): void
    {
        if ($this->failAuthorize) {
            throw new GatewayException('simulated gateway outage');
        }
        $this->authorized[] = ['mac' => $session->mac, 'session_id' => $session->id, 'limits' => $limits];
    }

    public function deauthorize(DeviceSession $session, string $reason): void
    {
        if ($this->failDeauthorize) {
            throw new GatewayException('simulated gateway outage');
        }
        $this->deauthorized[] = ['mac' => $session->mac, 'session_id' => $session->id, 'reason' => $reason];
    }
}
