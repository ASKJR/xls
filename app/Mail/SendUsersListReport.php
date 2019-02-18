<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendUsersListReport extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $fileUrl;

    public function __construct($url)
    {
        $this->fileUrl = $url;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $today = date('Y-m-d');
        $this->subject('Daily users report')
            ->from('mycompany@compnay.com');
        return $this->view('emails.users-report')
            ->attach(storage_path("app/public/users-report/users-$today.xls"), [
                'as' => "users-report-$today.xls"
            ]);
    }
}
