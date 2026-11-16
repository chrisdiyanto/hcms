<?php

namespace App\Domain\Workflow\Contracts;

/**
 * Objek yang bisa diajukan lewat workflow (mis. ESS change request).
 * Modul pemilik subjek yang menerapkan efek bisnis saat disetujui/ditolak;
 * mesin workflow tidak menyentuh tabel modul lain secara langsung.
 */
interface WorkflowSubject
{
    public function title(int $id): string;

    public function url(int $id): string;

    /** @return array<int, array{label: string, old?: ?string, new?: ?string, value?: ?string}> */
    public function summary(int $id): array;

    public function onApproved(int $id, object $instance): void;

    public function onRejected(int $id, object $instance): void;

    public function onCancelled(int $id, object $instance): void;
}
