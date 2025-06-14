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
    $this->activityService = Mockery::mock(TicketActivityService::class);
    $this->logoService = Mockery::mock(EmailLogoService::class);
    $this->styleService = Mockery::mock(EmailStyleService::class);
    $this->urlService = Mockery::mock(TicketUrlService::class);
});

afterEach(function () {
    Mockery::close();
});

test('can instantiate with notification type', function () {
    $notification = new TicketNotification(
        $this->ticket,
        'created',
        $this->activityService,
        $this->logoService,
        $this->styleService,
        $this->urlService
    );

    expect($notification)->toBeInstanceOf(TicketNotification::class);
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

    // Use reflection to access protected method
    $reflection = new ReflectionClass($notification);
    $method = $reflection->getMethod('getEmailSubject');
    $method->setAccessible(true);

    $subject = $method->invoke($notification);

    // Should contain the ticket ID and subject
    expect($subject)->toContain((string) $this->ticket->id);
    expect($subject)->toContain('Test Ticket');
});
