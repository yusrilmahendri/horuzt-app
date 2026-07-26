<?php

namespace Tests\Feature;

use Tests\TestCase;

class WeddingGuestInvitationAuthTest extends TestCase
{
    public function test_unauthenticated_user_cannot_delete_guest_link(): void
    {
        $this->deleteJson('/api/v1/wedding-guests/1')
            ->assertForbidden();
    }
}
