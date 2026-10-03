<?php
namespace App\Mail;

use App\Models\WorkerBadge;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkerBadgeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $badge;
    public $pdf;

    public function __construct(WorkerBadge $badge, $pdf)
    {
        $this->badge = $badge;
        $this->pdf = $pdf;
    }

    public function build()
    {
        $email = $this->subject('Your Worker Badge - ' . $this->badge->badge_number)
            ->view('emails.worker-badge')
            ->with([
                'workerName' => $this->badge->worker->full_name,
                'badgeNumber' => $this->badge->badge_number,
                'contractNumber' => $this->badge->contract->contract_number,
                'validUntil' => $this->badge->valid_until->format('d M Y'),
                'qrCodeUrl' => route('public.verify-badge', ['code' => $this->badge->qr_code]),
            ]);

        // Attach PDF badge
        $email->attachData($this->pdf->output(), 'worker-badge-' . $this->badge->badge_number . '.pdf', [
            'mime' => 'application/pdf',
        ]);

        // Attach QR code image for mobile wallets
        if ($this->badge->qr_code) {
            // Could also attach QR code image
        }

        return $email;
    }
}