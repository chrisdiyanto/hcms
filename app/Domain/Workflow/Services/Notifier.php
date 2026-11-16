<?php

namespace App\Domain\Workflow\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Notifikasi in-app (tb_notification, tampil di header Sximo).
 * Email lewat antrean menyusul (notification_templates belum dibuat).
 */
final class Notifier
{
    public function send(int $userId, string $title, string $note, string $url, string $icon = 'fa fa-tasks'): void
    {
        DB::table('tb_notification')->insert([
            'userid' => $userId,
            'url' => Str::limit($url, 255, ''),
            'title' => Str::limit($title, 255, ''),
            'note' => Str::limit($note, 1000, ''),
            'created' => now(),
            'icon' => $icon,
            'is_read' => '0',
            'postedBy' => (int) (auth()->id() ?? 0),
        ]);
    }
}
