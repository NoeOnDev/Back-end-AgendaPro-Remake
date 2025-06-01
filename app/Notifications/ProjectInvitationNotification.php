<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $project;
    protected $invitedBy;
    protected $token;

    public function __construct(Project $project, User $invitedBy, string $token)
    {
        $this->project = $project;
        $this->invitedBy = $invitedBy;
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $acceptUrl = "{$frontendUrl}/invitations/{$this->token}/accept";
        $rejectUrl = "{$frontendUrl}/invitations/{$this->token}/reject";

        return (new MailMessage)
            ->subject("Invitación al proyecto: {$this->project->name}")
            ->greeting('¡Hola!')
            ->line("{$this->invitedBy->name} te ha invitado a unirte al proyecto \"{$this->project->name}\".")
            ->line("Descripción del proyecto: {$this->project->description}")
            ->action('Aceptar Invitación', $acceptUrl)
            ->line('Si no deseas unirte a este proyecto, puedes ignorar este email.')
            ->line('Esta invitación expirará en 7 días.')
            ->salutation('Saludos, El equipo de Agenda Pro');
    }
}
