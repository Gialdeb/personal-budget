<?php

namespace App\Events\Sharing;

use App\Models\AccountInvitation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountInvitationCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public AccountInvitation $invitation,
        public string $plainToken,
    ) {}
}
