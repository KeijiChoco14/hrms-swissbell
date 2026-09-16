<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification
{
    use Queueable;

    public $task;

    /**
     * Create a new notification instance.
     */
    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', \App\Channels\FonnteChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->task->project_id 
            ? route('projects.show', $this->task->project_id) 
            : route('tasks.index');

        return (new MailMessage)
                    ->subject('Tugas Baru: ' . $this->task->title)
                    ->greeting('Halo ' . ($notifiable->name ?? 'Karyawan') . ',')
                    ->line('Anda telah ditugaskan pada sebuah task baru.')
                    ->line('**Judul Task:** ' . $this->task->title)
                    ->line('**Project:** ' . ($this->task->project->name ?? 'Task Mandiri (Tanpa Project)'))
                    ->line('**Prioritas:** ' . ($this->task->priority->value ?? $this->task->priority))
                    ->action('Lihat Detail Task', $url)
                    ->line('Terima kasih telah menggunakan sistem manajemen kami!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'title' => $this->task->title,
            'project_name' => $this->task->project->name ?? 'No Project',
            'message' => 'You have been assigned to a new task: ' . $this->task->title,
            'action_url' => $this->task->project_id ? route('projects.show', $this->task->project_id) : route('tasks.index'),
        ];
    }

    /**
     * Get the Fonnte representation of the notification.
     */
    public function toFonnte(object $notifiable): string
    {
        $url = $this->task->project_id 
            ? route('projects.show', $this->task->project_id) 
            : route('tasks.index');

        $greeting = 'Halo *' . ($notifiable->name ?? 'Karyawan') . "*,\n\n";
        $body = "Anda telah ditugaskan pada sebuah task baru.\n\n";
        $body .= "📌 *Judul Task:* " . $this->task->title . "\n";
        $body .= "📂 *Project:* " . ($this->task->project->name ?? 'Task Mandiri (Tanpa Project)') . "\n";
        
        $priority = $this->task->priority->value ?? $this->task->priority;
        $body .= "⚡ *Prioritas:* " . $priority . "\n\n";
        
        $body .= "Klik link berikut untuk melihat detail:\n";
        $body .= $url . "\n\n";
        $body .= "Terima kasih!";

        return $greeting . $body;
    }
}
