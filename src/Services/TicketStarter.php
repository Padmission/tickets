<?php

namespace Padmission\Tickets\Services;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Actions\GetDefaultPriorityForPanel;
use Padmission\Tickets\Actions\GetDefaultStatusForPanel;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\TicketPlugin;
use Ramsey\Uuid\Uuid;

/*
 * A supporter starts a ticket from the ticket list, for someone in their
 * organization who asked some other way, or as a question of their own for
 * the team they escalate to.
 */
class TicketStarter
{
    /**
     * Support owes the work, so it waits on support, as "Send as update"
     * leaves a ticket. Without an assignee, auto-assignment picks one.
     *
     * @param  list<UploadedFile>  $attachments
     */
    public function openFor(Model $requester, string $subject, string $message, int|string|null $assigneeId = null, array $attachments = []): Ticket
    {
        $panelId = Filament::getCurrentOrDefaultPanel()->getId();
        $status = resolve(GetDefaultStatusForPanel::class)($panelId);
        $priority = resolve(GetDefaultPriorityForPanel::class)($panelId);

        return DB::transaction(function () use ($requester, $subject, $message, $assigneeId, $attachments, $panelId, $status, $priority): Ticket {
            $ticket = TicketPlugin::resolveModelClass(Ticket::class)::create([
                'panel' => $panelId,
                'source_panel' => $panelId,
                'subject' => $subject,
                'submitter_id' => $requester->getKey(),
                'assignee_id' => $assigneeId,
                'turn' => Turn::Supporter,
                'status_id' => $status->getKey(),
                'priority_id' => $priority->getKey(),
            ]);

            $ticket->addTicketActivity(ActivityType::OpenedFor, ActivitySender::System, data: ['requester' => $requester->getKey()]);

            $this->writeMessage($ticket, ActivitySender::Supporter, $message, $attachments);

            return $ticket;
        });
    }

    /**
     * @param  list<UploadedFile>  $attachments
     */
    public function ask(string $targetPanelId, string $subject, string $message, array $attachments = []): Ticket
    {
        $status = resolve(GetDefaultStatusForPanel::class)($targetPanelId);
        $priority = resolve(GetDefaultPriorityForPanel::class)($targetPanelId);

        return DB::transaction(function () use ($targetPanelId, $subject, $message, $attachments, $status, $priority): Ticket {
            $ticket = TicketPlugin::resolveModelClass(Ticket::class)::create([
                'panel' => $targetPanelId,
                'source_panel' => Filament::getCurrentOrDefaultPanel()->getId(),
                'subject' => $subject,
                'submitter_id' => Filament::auth()->id(),
                'turn' => Turn::Supporter,
                'status_id' => $status->getKey(),
                'priority_id' => $priority->getKey(),
            ]);

            $ticket->addTicketActivity(ActivityType::AskedDirectly, ActivitySender::System);

            $this->writeMessage($ticket, ActivitySender::User, $message, $attachments);

            return $ticket;
        });
    }

    /**
     * @param  list<UploadedFile>  $attachments
     */
    protected function writeMessage(Ticket $ticket, ActivitySender $sender, string $message, array $attachments): TicketActivity
    {
        $activity = $ticket->ticketActivities()->create([
            'type' => ActivityType::Message,
            'sender' => $sender,
            'content' => $message,
        ]);

        foreach ($attachments as $file) {
            $filename = $file->getClientOriginalName();
            $filepath = 'tickets/'.$ticket->getKey().'/'.Uuid::uuid4()->toString().'_'.$filename;

            Storage::disk(config('padmission-tickets.attachments.disk'))->put($filepath, $file->get());

            TicketPlugin::resolveModelClass(TicketAttachment::class)::create([
                'ticket_id' => $ticket->getKey(),
                'activity_id' => $activity->getKey(),
                'created_by' => Filament::auth()->id(),
                'filename' => $filename,
                'filepath' => $filepath,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        return $activity;
    }
}
