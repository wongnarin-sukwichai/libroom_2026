<?php

namespace App\Mail;

use App\Models\BookingGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCancelledUnconfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BookingGroup $group) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'แจ้งยกเลิกการจอง — ไม่ได้รับการยืนยันจากเจ้าหน้าที่',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking_cancelled_unconfirmed',
        );
    }
}
