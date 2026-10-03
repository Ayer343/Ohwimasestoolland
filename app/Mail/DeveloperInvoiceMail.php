<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeveloperInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $mailData;

    public function __construct($mailData)
    {
        $this->mailData = $mailData;
    }

    public function build()
    {
        return $this->subject('Invoice #' . $this->mailData['invoice_number'] . ' - Payment Required')
                    ->view('emails.developer.invoice')
                    ->with($this->mailData);
    }
}