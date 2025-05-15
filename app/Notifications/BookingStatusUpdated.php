<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Booking;

class BookingStatusUpdated extends Notification
{
    use Queueable;

    public Booking $booking;
    public string $message;
    public string $subject;

    /**
     * Create a new notification instance.
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
        $this->setMessageAndSubject();
    }

    /**
     * Set the message and subject based on booking status.
     */
    protected function setMessageAndSubject(): void
    {
        switch ($this->booking->status) {
            case 'diterima':
                $this->subject = 'Booking Kost Diterima!';
                $this->message = "Booking Anda untuk kost {$this->booking->kost->nama_kost} telah diterima. Silakan lakukan pembayaran sesuai instruksi.";
                break;
            case 'ditolak':
                $this->subject = 'Booking Kost Ditolak';
                $this->message = "Mohon maaf, booking Anda untuk kost {$this->booking->kost->nama_kost} telah ditolak. Alasan: {$this->booking->catatan}. Silakan cari kost lain atau hubungi pemilik.";
                break;
            case 'batal':
                $this->subject = 'Booking Kost Dibatalkan';
                $this->message = "Booking Anda untuk kost {$this->booking->kost->nama_kost} telah dibatalkan.";
                break;
            default:
                $this->subject = 'Update Status Booking';
                $this->message = "Status booking Anda untuk kost {$this->booking->kost->nama_kost} telah diperbarui menjadi {$this->booking->status}.";
                break;
        }
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject($this->subject)
                    ->line($this->message)
                    ->action('Lihat Booking', url('/bookings/' . $this->booking->id_booking))
                    ->line('Terima kasih telah menggunakan aplikasi kami!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id_booking,
            'kost_nama' => $this->booking->kost->nama_kost,
            'status' => $this->booking->status,
            'message' => $this->message,
            'subject' => $this->subject,
        ];
    }
}
