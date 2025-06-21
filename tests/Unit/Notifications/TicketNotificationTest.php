<?php

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Services\EmailLogoService;
use Padmission\Tickets\Services\EmailStyleService;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketUrlService;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    $this->ticket = Ticket::factory()->create(['subject' => 'Test Ticket']);
    $this->user = User::factory()->create();

    // Mock services
    $this->activityService = new TicketActivityService;
    $this->logoService = new EmailLogoService;
    $this->styleService = new EmailStyleService;
    $this->urlService = new TicketUrlService;
});

afterEach(function () {
    Mockery::close();
});

test('generates correct email subject for different types', function () {
    $notification = new TicketNotification(
        $this->ticket,
        'created',
        $this->activityService,
        $this->logoService,
        $this->styleService,
        $this->urlService
    );

    $subject = invade($notification)->getEmailSubject();

    // Should contain the ticket ID and subject
    expect($subject)->toContain((string) $this->ticket->id);
    expect($subject)->toContain('Test Ticket');
});
