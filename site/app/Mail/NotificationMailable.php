<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;


class NotificationMailable extends Mailable
{
    use Queueable, SerializesModels;

    private $data;
    private $template;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Plantlog報告')->from('hidetobara@gmail.com')
            ->text($this->template)->with($this->data);
    }

    public function setStoppedSensor($sensor_id, $time)
    {
        $this->template = 'mail.stopped_sensor';
        $this->data = ['sensor_id' => $sensor_id, 'last_time' => $time];
        return $this;
    }
}
