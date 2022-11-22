<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;

class SlackTicketAssignedNotification extends Notification
{
    use Queueable;

    private $user;
    private $ticket;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(User $user, $ticket)
    {
        $this->user = $user;
        $this->ticket = $ticket;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['slack'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }

    public function toSlack($notifiable)
   {
      $url = route('admin.ticketshow', $this->ticket->ticket_id);
      $content = 'Hello ' . $this->user->firstname .
                 ', le ticket n° ' . $this->ticket->ticket_id .
                 ' vous a été assigné. Priorité : ' . ($this->ticket->priority ? $this->ticket->priority : 'NON DEFINIE') . '.' .
                 ' Cliquez sur ce lien pour voir le ticket : ' . $url
                 ;
      return (new SlackMessage)
           ->content($content)
      ;
   }
}
