<?php
/*
 * ============================================================
 * BUAT APA : Satu jenis notifikasi untuk semua kejadian shipment (status berubah, ETA
 *            diubah, exception dilaporkan/selesai). Isinya dibedakan oleh 'kind'.
 * SALURAN  : selalu "database" (notifikasi in-app: lonceng + halaman Notifications).
 *            Email ikut terkirim bila NOTIFY_BY_EMAIL=true (fitur lanjutan, lihat config).
 * ISI DB   : kind, level (info|success|warning|danger), title, message, link relatif ke
 *            shipment, dan id/nomor shipment.
 * ============================================================
 */

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShipmentNotification extends Notification
{
    public function __construct(
        public readonly string $kind,
        public readonly string $level,
        public readonly string $title,
        public readonly string $message,
        public readonly int $shipmentId,
        public readonly string $shipmentNumber,
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('cargotrace.notifications.mail') && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'level' => $this->level,
            'title' => $this->title,
            'message' => $this->message,
            'url' => route('shipments.show', $this->shipmentId, false), // link relatif: /shipments/5
            'shipment_id' => $this->shipmentId,
            'shipment_number' => $this->shipmentNumber,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('CargoTrace: '.$this->title)
            ->line($this->message)
            ->action('Open shipment', route('shipments.show', $this->shipmentId));
    }
}
