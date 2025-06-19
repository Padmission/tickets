# Padmission Tickets Plugin - Implementation Examples

This guide provides complete, real-world implementation examples for integrating the Padmission Tickets Plugin into your Laravel/Filament application.

## Table of Contents

- [Basic Setup](#basic-setup)
- [Panel Provider Configuration](#panel-provider-configuration)
- [Notification Configuration](#notification-configuration)
- [Model Customization](#model-customization)
- [Service Integration](#service-integration)
- [Testing Examples](#testing-examples)
- [Advanced Use Cases](#advanced-use-cases)

## Basic Setup

### 1. Installation & Configuration

```bash
# Install the package
composer require padmission/tickets

# Publish configuration (optional)
php artisan vendor:publish --tag="padmission-tickets-config"

# Run migrations
php artisan migrate

# Seed default data
php artisan padmission-tickets:seed
```

### 2. Basic Panel Configuration

```php
<?php
// app/Providers/Filament/AdminPanelProvider.php

use Filament\Panel;
use Filament\PanelProvider;
use Padmission\Tickets\TicketPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->plugins([
                TicketPlugin::make()
                    ->registerResources()
                    ->registerWidgets(),
            ]);
    }
}
```

## Panel Provider Configuration

### Customer Support Portal

```php
<?php
// app/Providers/Filament/SupportPanelProvider.php

use Filament\Panel;
use Filament\PanelProvider;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\TicketPlugin;

class SupportPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('support')
            ->path('support')
            ->colors([
                'primary' => '#3b82f6',
            ])
            ->plugins([
                TicketPlugin::make()
                    ->registerResources()
                    ->registerWidgets()
                    ->showChatWidget()
                    ->notificationConfiguration(
                        NotificationConfiguration::make()
                            ->onTicketCreated(function ($context) {
                                // New tickets always notify support team
                                $context->notifySupporter();
                            })
                            ->onTicketActivity(function ($context) {
                                // User messages notify support, support replies notify users
                                $context->whenUserTriggered(['notify_user' => false, 'notify_supporter' => true])
                                        ->whenSupporterTriggered(['notify_user' => true, 'notify_supporter' => false]);
                            })
                            ->onTicketClosed(function ($context) {
                                // Always notify the user when ticket is closed
                                $context->notifyUser();
                            })
                    ),
            ]);
    }
}
```

### Multi-Tenant Customer Portal

```php
<?php
// app/Providers/Filament/CustomerPanelProvider.php

use Filament\Panel;
use Filament\PanelProvider;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\TicketPlugin;

class CustomerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('customer')
            ->path('portal')
            ->tenant(Company::class)
            ->tenantRoutePrefix('company')
            ->plugins([
                TicketPlugin::make()
                    ->registerResources()
                    ->escalationLevel('customer')
                    ->notificationConfiguration(
                        NotificationConfiguration::make()
                            ->onTicketCreated(function ($context, $ticket, $actor) {
                                // Customers creating tickets - notify both for confirmation
                                if ($ticket->priority?->display_name === 'Urgent') {
                                    $context->notifyBoth(); // Urgent tickets notify everyone immediately
                                } else {
                                    $context->notifyUser(); // Standard tickets just confirm to user
                                }
                            })
                            ->onTicketActivity(function ($context, $ticket, $actor) {
                                // Only notify customers of support team replies
                                $context->whenSupporterTriggered(['notify_user' => true, 'notify_supporter' => false]);
                            })
                    ),
            ]);
    }
}
```

## Notification Configuration

### Business Hours & Priority-Based Notifications

```php
NotificationConfiguration::make()
    ->onTicketCreated(function ($context, $ticket, $actor) {
        $isBusinessHours = now()->between('09:00', '17:00') && now()->isWeekday();
        $isUrgent = $ticket->priority?->display_name === 'Urgent';
        $isVip = $ticket->submitter?->hasRole('vip');
        
        if ($isUrgent || $isVip) {
            // Urgent/VIP tickets always notify everyone
            $context->notifyBoth();
        } elseif ($isBusinessHours) {
            // Business hours - notify support team
            $context->notifySupporter();
        } else {
            // After hours - just confirm receipt to user
            $context->notifyUser();
        }
    })
    ->onTicketActivity(function ($context, $ticket, $actor) {
        // Smart activity notifications based on context
        $context->when($actor?->hasRole('admin'), function ($ctx) {
            // Admin replies always notify users
            $ctx->whenSupporterTriggered(['notify_user' => true, 'notify_supporter' => false]);
        })->when($ticket->assignee_id === $actor?->id, function ($ctx) {
            // Assigned support agent replies notify user
            $ctx->whenSupporterTriggered(['notify_user' => true, 'notify_supporter' => false]);
        })->otherwise(function ($ctx) {
            // Default behavior for other scenarios
            $ctx->whenUserTriggered(['notify_user' => false, 'notify_supporter' => true])
                ->whenSupporterTriggered(['notify_user' => true, 'notify_supporter' => false]);
        });
    })
```

### Environment-Specific Configuration

```php
NotificationConfiguration::make()
    ->onTicketCreated(function ($context) {
        $context->inEnvironment('production', function ($ctx) {
            $ctx->notifyBoth();
        })->inEnvironment('staging', function ($ctx) {
            $ctx->notifySupporter(); // Only notify testers in staging
        })->inEnvironment('local', function ($ctx) {
            $ctx->notifyNone(); // Don't spam in development
        });
    })
    ->onTicketActivity(function ($context) {
        $context->unless(app()->environment('testing'), function ($ctx) {
            // Normal notification logic for non-test environments
            $ctx->whenUserTriggered(['notify_user' => false, 'notify_supporter' => true])
                ->whenSupporterTriggered(['notify_user' => true, 'notify_supporter' => false]);
        });
    })
```

## Model Customization

### Custom User Model with Notification Preferences

```php
<?php
// app/Models/User.php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Models\Contracts\CanBeAssignedTickets;
use Padmission\Tickets\Models\Contracts\HasTicketDisplayName;

class User extends Authenticatable implements CanBeAssignedTickets, HasTicketDisplayName
{
    protected $fillable = [
        'name', 'email', 'password', 'notification_preference'
    ];

    protected $casts = [
        'notification_preference' => NotificationStrategy::class,
    ];

    public function ticketNotificationStrategy(): NotificationStrategy
    {
        return $this->notification_preference ?? NotificationStrategy::Debounced;
    }

    public function getNameForTickets(): string
    {
        return $this->name ?? $this->email;
    }

    public function canBeAssignedTickets(): bool
    {
        return $this->hasRole(['support', 'admin']);
    }

    // Relationships
    public function assignedTickets()
    {
        return $this->hasMany(config('padmission-tickets.models.Padmission\Tickets\Models\Ticket'), 'assignee_id');
    }

    public function submittedTickets()
    {
        return $this->hasMany(config('padmission-tickets.models.Padmission\Tickets\Models\Ticket'), 'submitter_id');
    }
}
```

### Custom Ticket Model with Business Logic

```php
<?php
// app/Models/Ticket.php

use Padmission\Tickets\Models\Ticket as BaseTicket;

class Ticket extends BaseTicket
{
    protected $casts = [
        'data' => 'array',
        'turn' => \Padmission\Tickets\Enums\Turn::class,
        'submitter_data' => \Padmission\Tickets\ValueObjects\SubmitterData::class,
        'closed_at' => 'datetime',
        'escalated_at' => 'datetime', // Custom field
    ];

    // Custom business logic
    public function escalate(): void
    {
        if ($this->escalated_at) {
            return; // Already escalated
        }

        $this->update([
            'escalated_at' => now(),
            'priority_id' => $this->getUrgentPriorityId(),
        ]);

        $this->addTicketActivity(
            \Padmission\Tickets\Enums\ActivityType::StatusChanged,
            'Ticket escalated to urgent priority',
            \Padmission\Tickets\Enums\ActivitySender::System
        );

        // Fire custom event
        event(new \App\Events\TicketEscalatedEvent($this));
    }

    protected function getUrgentPriorityId(): int
    {
        return \Padmission\Tickets\Models\TicketPriority::where('display_name', 'Urgent')->first()->id;
    }

    // Custom scopes
    public function scopeEscalated($query)
    {
        return $query->whereNotNull('escalated_at');
    }

    public function scopeOverdue($query)
    {
        return $query->where('created_at', '<', now()->subHours(24))
                    ->whereNull('closed_at');
    }
}
```

### Update Configuration to Use Custom Models

```php
<?php
// config/padmission-tickets.php

return [
    'models' => [
        \Illuminate\Contracts\Auth\Authenticatable::class => \App\Models\User::class,
        \Padmission\Tickets\Models\Ticket::class => \App\Models\Ticket::class,
        // ... other models
    ],
    
    // Custom notification job
    'jobs' => [
        \Padmission\Tickets\Jobs\NotificationJob::class => \App\Jobs\CustomNotificationJob::class,
    ],
    
    // Tenant support
    'tenancy' => [
        'enabled' => true,
        'tenancy_model' => \App\Models\Company::class,
    ],
];
```

## Service Integration

### Custom Notification Job with Slack Integration

```php
<?php
// app/Jobs/CustomNotificationJob.php

use Illuminate\Support\Facades\Http;
use Padmission\Tickets\Jobs\NotificationJob;

class CustomNotificationJob extends NotificationJob
{
    public function handle(): void
    {
        // Run the parent notification logic
        parent::handle();

        // Add custom Slack notification for urgent tickets
        if ($this->shouldSendSlackNotification()) {
            $this->sendSlackNotification();
        }
    }

    protected function shouldSendSlackNotification(): bool
    {
        return $this->ticket->priority?->display_name === 'Urgent'
            && config('services.slack.webhook_url');
    }

    protected function sendSlackNotification(): void
    {
        Http::post(config('services.slack.webhook_url'), [
            'text' => "🚨 Urgent Ticket Created",
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Urgent Ticket #{$this->ticket->id}*\n" .
                                 "Subject: {$this->ticket->subject}\n" .
                                 "Submitter: {$this->ticket->submitter?->name}\n" .
                                 "Priority: {$this->ticket->priority?->display_name}"
                    ]
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => ['type' => 'plain_text', 'text' => 'View Ticket'],
                            'url' => route('filament.support.resources.tickets.view', $this->ticket)
                        ]
                    ]
                ]
            ]
        ]);
    }
}
```

### Custom Activity Service with Analytics

```php
<?php
// app/Services/TicketAnalyticsService.php

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Enums\ActivityType;

class TicketAnalyticsService
{
    public function getResponseTimeMetrics(): array
    {
        return [
            'average_first_response' => $this->calculateAverageFirstResponse(),
            'average_resolution_time' => $this->calculateAverageResolutionTime(),
            'response_time_by_priority' => $this->getResponseTimeByPriority(),
        ];
    }

    protected function calculateAverageFirstResponse(): float
    {
        return Ticket::query()
            ->with(['ticketActivities' => function ($query) {
                $query->where('type', ActivityType::MessageAdded)
                      ->where('sender', \Padmission\Tickets\Enums\ActivitySender::Supporter)
                      ->orderBy('created_at');
            }])
            ->get()
            ->map(function ($ticket) {
                $firstResponse = $ticket->ticketActivities->first();
                return $firstResponse 
                    ? $firstResponse->created_at->diffInMinutes($ticket->created_at)
                    : null;
            })
            ->filter()
            ->average();
    }

    protected function calculateAverageResolutionTime(): float
    {
        return Ticket::whereNotNull('closed_at')
            ->get()
            ->map(fn ($ticket) => $ticket->closed_at->diffInHours($ticket->created_at))
            ->average();
    }

    protected function getResponseTimeByPriority(): array
    {
        return Ticket::with('priority')
            ->get()
            ->groupBy('priority.display_name')
            ->map(function ($tickets) {
                return $tickets->map(function ($ticket) {
                    $firstResponse = $ticket->ticketActivities()
                        ->where('type', ActivityType::MessageAdded)
                        ->where('sender', \Padmission\Tickets\Enums\ActivitySender::Supporter)
                        ->first();
                    
                    return $firstResponse 
                        ? $firstResponse->created_at->diffInMinutes($ticket->created_at)
                        : null;
                })->filter()->average();
            });
    }
}
```

## Testing Examples

### Feature Test for Custom Business Logic

```php
<?php
// tests/Feature/TicketEscalationTest.php

use App\Events\TicketEscalatedEvent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Enums\ActivityType;

class TicketEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new TicketPrioritySeeder)->run();
    }

    test('ticket can be escalated to urgent priority', function () {
        Event::fake();
        
        $ticket = Ticket::factory()->create([
            'priority_id' => 1, // Normal priority
            'escalated_at' => null,
        ]);

        $ticket->escalate();

        expect($ticket->refresh())
            ->escalated_at->not->toBeNull()
            ->priority->display_name->toBe('Urgent');

        // Verify activity was logged
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'type' => ActivityType::StatusChanged,
            'message' => 'Ticket escalated to urgent priority',
        ]);

        // Verify event was fired
        Event::assertDispatched(TicketEscalatedEvent::class);
    });

    test('ticket cannot be escalated twice', function () {
        $ticket = Ticket::factory()->create([
            'escalated_at' => now()->subHour(),
        ]);

        $originalEscalationTime = $ticket->escalated_at;
        
        $ticket->escalate();

        expect($ticket->refresh()->escalated_at->toDateTimeString())
            ->toBe($originalEscalationTime->toDateTimeString());
    });
}
```

### Unit Test for Custom Notification Logic

```php
<?php
// tests/Unit/CustomNotificationJobTest.php

use App\Jobs\CustomNotificationJob;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;

class CustomNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new TicketPrioritySeeder)->run();
        
        config(['services.slack.webhook_url' => 'https://hooks.slack.com/test']);
    }

    test('urgent tickets send slack notifications', function () {
        Http::fake();
        
        $urgentPriority = \Padmission\Tickets\Models\TicketPriority::where('display_name', 'Urgent')->first();
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority_id' => $urgentPriority->id,
            'subject' => 'Critical system failure',
        ]);

        $job = new CustomNotificationJob($ticket, $user, 'created');
        $job->handle();

        Http::assertSent(function ($request) use ($ticket) {
            return $request->url() === config('services.slack.webhook_url')
                && str_contains($request->body(), "Urgent Ticket #{$ticket->id}")
                && str_contains($request->body(), 'Critical system failure');
        });
    });

    test('normal priority tickets do not send slack notifications', function () {
        Http::fake();
        
        $normalPriority = \Padmission\Tickets\Models\TicketPriority::where('display_name', 'Normal')->first();
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority_id' => $normalPriority->id,
        ]);

        $job = new CustomNotificationJob($ticket, $user, 'created');
        $job->handle();

        Http::assertNothingSent();
    });
}
```

### Integration Test for Panel-Specific Configuration

```php
<?php
// tests/Feature/PanelSpecificNotificationTest.php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class PanelSpecificNotificationTest extends TestCase
{
    use RefreshDatabase;

    test('support panel has different notification rules than customer panel', function () {
        Notification::fake();

        // Configure support panel
        $supportPanel = Filament::getPanel('support');
        $supportPanel->plugin(
            TicketPlugin::make()->notificationConfiguration(
                NotificationConfiguration::make()
                    ->onTicketCreated(function ($context) {
                        $context->notifyBoth(); // Support panel notifies everyone
                    })
            )
        );

        // Configure customer panel  
        $customerPanel = Filament::getPanel('customer');
        $customerPanel->plugin(
            TicketPlugin::make()->notificationConfiguration(
                NotificationConfiguration::make()
                    ->onTicketCreated(function ($context) {
                        $context->notifyUser(); // Customer panel only confirms to user
                    })
            )
        );

        $user = User::factory()->create();
        $supporter = User::factory()->create();

        // Test support panel behavior
        Filament::setCurrentPanel($supportPanel);
        $supportTicket = Ticket::factory()->create([
            'submitter_id' => $user->id,
            'assignee_id' => $supporter->id,
        ]);

        // Test customer panel behavior
        Filament::setCurrentPanel($customerPanel);
        $customerTicket = Ticket::factory()->create([
            'submitter_id' => $user->id,
            'assignee_id' => $supporter->id,
        ]);

        // Verify different notification behaviors
        // (Add specific notification assertions based on your implementation)
    });
}
```

## Advanced Use Cases

### Auto-Assignment with Load Balancing

```php
<?php
// app/Strategies/LoadBalancedAssignmentStrategy.php

use Illuminate\Database\Eloquent\Builder;
use Padmission\Tickets\AssignmentStrategies\AssignmentStrategy;
use Padmission\Tickets\Models\Ticket;

class LoadBalancedAssignmentStrategy implements AssignmentStrategy
{
    public function assign($ticket): void
    {
        $assignee = $this->findLeastBusySupporter($ticket);
        
        if ($assignee) {
            $ticket->assignee_id = $assignee->id;
        }
    }

    protected function findLeastBusySupporter($ticket)
    {
        return User::whereHas('roles', function (Builder $query) {
                $query->where('name', 'support');
            })
            ->withCount(['assignedTickets' => function (Builder $query) {
                $query->whereNull('closed_at'); // Only count open tickets
            }])
            ->orderBy('assigned_tickets_count')
            ->first();
    }
}

// Use in panel provider:
TicketPlugin::make()
    ->assignmentStrategy(new LoadBalancedAssignmentStrategy())
```

### SLA Monitoring with Automatic Escalation

```php
<?php
// app/Console/Commands/MonitorSlaCommand.php

use App\Models\Ticket;
use Illuminate\Console\Command;
use Padmission\Tickets\Models\TicketPriority;

class MonitorSlaCommand extends Command
{
    protected $signature = 'tickets:monitor-sla';
    protected $description = 'Monitor SLA compliance and auto-escalate overdue tickets';

    public function handle()
    {
        $slaThresholds = [
            'Critical' => 2, // 2 hours
            'High' => 8,     // 8 hours  
            'Medium' => 24,  // 24 hours
            'Low' => 72,     // 72 hours
        ];

        foreach ($slaThresholds as $priority => $hours) {
            $overdueTickets = Ticket::whereHas('priority', function ($query) use ($priority) {
                    $query->where('display_name', $priority);
                })
                ->whereNull('closed_at')
                ->where('created_at', '<', now()->subHours($hours))
                ->whereNull('escalated_at')
                ->get();

            foreach ($overdueTickets as $ticket) {
                $ticket->escalate();
                $this->info("Escalated overdue {$priority} priority ticket #{$ticket->id}");
            }
        }

        $this->info('SLA monitoring completed');
    }
}

// Schedule in app/Console/Kernel.php:
protected function schedule(Schedule $schedule)
{
    $schedule->command('tickets:monitor-sla')->hourly();
}
```

### Custom Filament Widget with Real-time Updates

```php
<?php
// app/Filament/Widgets/LiveTicketStatsWidget.php

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;
use Padmission\Tickets\Models\Ticket;

class LiveTicketStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static bool $isLazy = false;

    #[On('ticket-created')]
    #[On('ticket-closed')]
    public function refresh(): void
    {
        // Force widget refresh when tickets change
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Open Tickets', Ticket::open()->count())
                ->description('Currently open tickets')
                ->descriptionIcon('heroicon-o-ticket')
                ->color('warning'),
                
            Stat::make('Avg Response Time', $this->getAverageResponseTime())
                ->description('In hours')
                ->descriptionIcon('heroicon-o-clock')
                ->color('success'),
                
            Stat::make('Tickets Today', Ticket::whereDate('created_at', today())->count())
                ->description('Created today')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('info'),
                
            Stat::make('Escalated', Ticket::escalated()->count())
                ->description('Require attention')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }

    protected function getAverageResponseTime(): string
    {
        $avgMinutes = app(\App\Services\TicketAnalyticsService::class)
            ->getResponseTimeMetrics()['average_first_response'] ?? 0;
            
        return number_format($avgMinutes / 60, 1);
    }
}
```

### Email Customization with Company Branding

```php
<?php
// app/Services/CustomEmailStyleService.php

use Padmission\Tickets\Services\EmailStyleService;

class CustomEmailStyleService extends EmailStyleService
{
    public function getEmailStyles(): string
    {
        $company = auth()->user()?->company ?? tenant();
        
        return "
            <style>
                .email-container {
                    font-family: {$this->getFontFamily($company)};
                    background-color: {$this->getBackgroundColor($company)};
                }
                .header {
                    background-color: {$this->getPrimaryColor($company)};
                    color: white;
                    padding: 20px;
                }
                .ticket-info {
                    background-color: #f8f9fa;
                    border-left: 4px solid {$this->getPrimaryColor($company)};
                    padding: 15px;
                    margin: 20px 0;
                }
                .activity-item {
                    border-bottom: 1px solid #dee2e6;
                    padding: 10px 0;
                }
                .footer {
                    font-size: 12px;
                    color: #6c757d;
                    text-align: center;
                    margin-top: 30px;
                }
            </style>
        ";
    }

    protected function getPrimaryColor($company): string
    {
        return $company?->brand_color ?? '#3b82f6';
    }

    protected function getFontFamily($company): string
    {
        return $company?->font_family ?? 'Arial, sans-serif';
    }

    protected function getBackgroundColor($company): string
    {
        return $company?->email_background ?? '#ffffff';
    }
}

// Register in service provider:
$this->app->bind(
    \Padmission\Tickets\Services\EmailStyleService::class,
    \App\Services\CustomEmailStyleService::class
);
```

This implementation guide provides production-ready examples that you can adapt to your specific needs. Each example follows Laravel and Filament best practices while demonstrating the flexibility and power of the Padmission Tickets Plugin.
