<?php

namespace App\Notifications;

use App\Models\Testimonial;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class NewTestimonialSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    protected $testimonial;

    public function __construct(Testimonial $testimonial)
    {
        $this->testimonial = $testimonial;
        Log::info('NewTestimonialSubmitted notification instantiated', [
            'testimonial_id' => $testimonial->id
        ]);
    }

    public function via($notifiable): array
    {
        Log::info('via() method called for testimonial notification', [
            'notifiable_id' => $notifiable->id ?? null,
            'notifiable_type' => get_class($notifiable)
        ]);
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        Log::info('toArray() method called for testimonial notification', [
            'testimonial_id' => $this->testimonial->id,
            'notifiable_id' => $notifiable->id ?? null
        ]);
        
        $data = [
            'title' => 'New Testimonial Submitted',
            'message' => $this->testimonial->name . ' submitted a ' . $this->testimonial->rating . '-star testimonial',
            'icon' => 'fas fa-star text-warning',
            'category' => 'testimonial',
            'priority' => 1,
            'action_url' => route('admin.testimonials.show', $this->testimonial->id),
        ];
        
        Log::info('toArray() returning data', ['data' => $data]);
        
        return $data;
    }
}