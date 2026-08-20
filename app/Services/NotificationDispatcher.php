<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\NotificationType;
use App\Mail\AgentAccountStatusMail;
use App\Mail\AgentApprovedMail;
use App\Mail\AgentAwaitingApprovalMail;
use App\Mail\AgentRejectedMail;
use App\Mail\LeadFeedbackMail;
use App\Models\Lead;
use App\Models\LeadFeedback;
use App\Models\Notification;
use App\Models\User;
use App\Support\Mailer;
use Illuminate\Support\Collection;

/**
 * The notification engine described in BACKEND_GUIDE §5.
 *
 * One `notifications` row per recipient (the in-app list), an FCM push for
 * recipients who allow push, and a queued email for recipients who allow email.
 * Every method here maps to a numbered event in that table.
 */
class NotificationDispatcher
{
    public function __construct(
        private FcmService $fcm,
        private Mailer $mailer,
    ) {}

    // ------------------------------------------------------------ Event #1

    /** New agent signs up → all staff + admin (in-app + push + email). */
    public function agentSignedUp(User $agent): void
    {
        $recipients = $this->staffAndAdmins();

        $this->fanOut(
            $recipients,
            NotificationType::AGENT_SIGNUP,
            'New agent awaiting approval',
            "{$agent->name} ({$agent->email}) signed up and is pending approval.",
            actor: $agent,
        );

        foreach ($recipients as $recipient) {
            $this->mailer->sendToUser($recipient, new AgentAwaitingApprovalMail($agent, $recipient), 'agent_signup');
        }
    }

    // ------------------------------------------------------------ Event #2

    /** Agent approved → that agent (in-app + push + email). */
    public function agentApproved(User $agent, ?User $actor = null): void
    {
        $this->fanOut(
            collect([$agent]),
            NotificationType::SYSTEM,
            "You're approved",
            'Your agent account has been approved. You can now receive leads.',
            actor: $actor,
        );

        $this->mailer->sendToUser($agent, new AgentApprovedMail($agent), 'agent_approved', force: true);
    }

    /** Agent rejected → that agent (in-app + push + email). */
    public function agentRejected(User $agent, ?string $reason = null, ?User $actor = null): void
    {
        $this->fanOut(
            collect([$agent]),
            NotificationType::SYSTEM,
            'Application not approved',
            $reason
                ? "Your agent application was not approved. Reason: {$reason}"
                : 'Your agent application was not approved.',
            actor: $actor,
        );

        $this->mailer->sendToUser($agent, new AgentRejectedMail($agent, $reason), 'agent_rejected', force: true);
    }

    // ------------------------------------------------------------ Event #3

    /** Lead assigned → that agent (in-app + push, no email). */
    public function leadAssigned(Lead $lead, User $agent, ?User $actor = null): void
    {
        $this->fanOut(
            collect([$agent]),
            NotificationType::NEW_LEAD,
            'New lead assigned',
            "{$lead->reference_id} · {$lead->name} ({$lead->intent?->label()}) in {$lead->location}.",
            lead: $lead,
            actor: $actor,
        );
    }

    // -------------------------------------------------------- Events #4/#5/#6

    /**
     * Agent accepted / rejected / commented on a lead → all staff + admin
     * (in-app + push + email).
     */
    public function agentResponded(Lead $lead, LeadFeedback $feedback, User $agent): void
    {
        $recipients = $this->staffAndAdmins();

        $verb = $feedback->accepted() ? 'accepted' : 'rejected';
        $detail = $feedback->accepted()
            ? ''
            : ' Reason: '.($feedback->reasonLabel() ?? 'not given').'.';

        if ($feedback->comments) {
            $detail .= ' Comments: "'.\Illuminate\Support\Str::limit($feedback->comments, 160).'"';
        }

        $this->fanOut(
            $recipients,
            NotificationType::FEEDBACK,
            "Agent {$verb} lead {$lead->reference_id}",
            "{$agent->name} {$verb} {$lead->reference_id} ({$lead->name}).{$detail}",
            lead: $lead,
            actor: $agent,
        );

        foreach ($recipients as $recipient) {
            $this->mailer->sendToUser(
                $recipient,
                new LeadFeedbackMail($lead, $feedback, $agent, $recipient),
                'lead_feedback'
            );
        }
    }

    // ------------------------------------------------------------ Event #7

    /** Lead marked converted → that agent (in-app + push, no email). */
    public function leadConverted(Lead $lead, User $agent, ?User $actor = null): void
    {
        $this->fanOut(
            collect([$agent]),
            NotificationType::LEAD_UPDATE,
            'Lead marked converted',
            "Congratulations — {$lead->reference_id} ({$lead->name}) was marked converted.",
            lead: $lead,
            actor: $actor,
        );
    }

    /**
     * The agent moved their own lead through the pipeline — the mirror of #7:
     * staff and admin are the ones who need to know.
     */
    public function agentUpdatedLeadStatus(Lead $lead, User $agent, LeadStatus $to, ?string $comments = null): void
    {
        $detail = filled($comments)
            ? ' Comments: "'.\Illuminate\Support\Str::limit($comments, 160).'"'
            : '';

        $this->fanOut(
            $this->staffAndAdmins(),
            NotificationType::LEAD_UPDATE,
            "Lead {$lead->reference_id} marked {$to->label()}",
            "{$agent->name} moved {$lead->reference_id} ({$lead->name}) to {$to->label()}.{$detail}",
            lead: $lead,
            actor: $agent,
        );
    }

    /** Any other status change staff make on an agent's lead. */
    public function leadStatusChanged(Lead $lead, User $agent, string $status, ?User $actor = null): void
    {
        $this->fanOut(
            collect([$agent]),
            NotificationType::LEAD_UPDATE,
            'Lead status updated',
            "{$lead->reference_id} ({$lead->name}) is now marked \"{$status}\".",
            lead: $lead,
            actor: $actor,
        );
    }

    // ------------------------------------------------------------ Event #8

    /**
     * Agent suspended / restored / removed → that agent (email; in-app too,
     * except for removal where the account no longer exists).
     */
    public function agentAccountStatusChanged(User $agent, string $state, ?User $actor = null, ?string $reason = null): void
    {
        $copy = match ($state) {
            'suspended' => ['Account suspended', 'Your agent account has been suspended. Contact support for details.'],
            'restored' => ['Account reactivated', 'Your agent account has been reactivated. You can receive leads again.'],
            default => ['Account removed', 'Your agent account has been removed from Open Referral.'],
        };

        if ($state !== 'removed') {
            $this->fanOut(collect([$agent]), NotificationType::SYSTEM, $copy[0], $copy[1], actor: $actor, push: $state === 'restored');
        }

        $this->mailer->sendToUser(
            $agent,
            new AgentAccountStatusMail($agent, $state, $reason),
            'agent_'.$state,
            force: true
        );
    }

    // --------------------------------------------------------------- Internals

    /** @return Collection<int,User> */
    public function staffAndAdmins(): Collection
    {
        return User::query()->staffAndAdmins()->active()->get();
    }

    /**
     * Write one in-app row per recipient, then push to those who allow push.
     *
     * @param  Collection<int,User>  $recipients
     */
    public function fanOut(
        Collection $recipients,
        NotificationType $type,
        string $title,
        string $body,
        ?Lead $lead = null,
        ?User $actor = null,
        bool $push = true,
    ): void {
        if ($recipients->isEmpty()) {
            return;
        }

        $title = \Illuminate\Support\Str::limit($title, 185);
        $body = \Illuminate\Support\Str::limit($body, 495);

        foreach ($recipients as $recipient) {
            $notification = Notification::create([
                'user_id' => $recipient->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'lead_id' => $lead?->id,
                'actor_id' => $actor?->id,
                'created_at' => now(),
            ]);

            if (! $push || ! $recipient->wantsPush()) {
                continue;
            }

            // Pushed per recipient rather than as one multicast, because `id`
            // is that recipient's own notification row — the app uses it to
            // mark the notification read when the push is tapped.
            $this->fcm->pushToUsers([$recipient], $title, $body, [
                'id' => $notification->id,
                'type' => $type->value,
                'lead_id' => $lead?->id,
                'title' => $title,
                'body' => $body,
                'reference_id' => $lead?->reference_id,
            ]);
        }
    }
}
