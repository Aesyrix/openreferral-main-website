<?php

namespace App\Enums;

enum NotificationType: string
{
    case NEW_LEAD = 'new_lead';
    case LEAD_UPDATE = 'lead_update';
    case FEEDBACK = 'feedback';
    case AGENT_SIGNUP = 'agent_signup';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::NEW_LEAD => 'New Lead',
            self::LEAD_UPDATE => 'Lead Update',
            self::FEEDBACK => 'Agent Feedback',
            self::AGENT_SIGNUP => 'Agent Signup',
            self::SYSTEM => 'System',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::NEW_LEAD => '🎯',
            self::LEAD_UPDATE => '🔄',
            self::FEEDBACK => '💬',
            self::AGENT_SIGNUP => '🙋',
            self::SYSTEM => '🔔',
        };
    }

    /**
     * The `data.type` value sent in the FCM payload, which the mobile app
     * deep-links on. Deliberately more explicit than the in-app enum value —
     * these are the keys the app team documented.
     */
    public function pushAction(): string
    {
        return match ($this) {
            self::NEW_LEAD => 'lead_assigned',
            self::LEAD_UPDATE => 'lead_status_updated',
            self::FEEDBACK => 'lead_feedback',
            self::AGENT_SIGNUP => 'agent_signup',
            self::SYSTEM => 'system',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NEW_LEAD => 'badge-blue',
            self::LEAD_UPDATE => 'badge-amber',
            self::FEEDBACK => 'badge-violet',
            self::AGENT_SIGNUP => 'badge-teal',
            self::SYSTEM => 'badge-slate',
        };
    }
}
