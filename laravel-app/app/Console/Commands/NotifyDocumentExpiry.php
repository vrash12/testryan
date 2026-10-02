<?php

namespace App\Console\Commands;

use App\Actions\Notifications\BusinessNotifications;
use App\Models\ContractorDocument;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class NotifyDocumentExpiry extends Command
{
    protected $signature = 'nestcrafters:notify-document-expiry {--date= : Explicit current business date (Y-m-d); other dates are refused} {--staged : Send once per 30-day, 7-day, due-today and recently-expired stage}';

    protected $description = 'Record enabled insurance-document expiry notifications for eligible administrators';

    public function handle(BusinessNotifications $notifications): int
    {
        $value = $this->option('date');
        try {
            $date = $value ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : CarbonImmutable::today();
            if ($value && $date->format('Y-m-d') !== $value) {
                throw new \InvalidArgumentException('Invalid date.');
            }
        } catch (\Throwable) {
            $this->error('The business date must be a valid Y-m-d date.');

            return self::FAILURE;
        }
        if (! $date->isSameDay(CarbonImmutable::today())) {
            $this->error('Reminder recording requires the current configured business date.');

            return self::FAILURE;
        }
        $end = $date->addDays(30);
        $documents = ContractorDocument::query()
            ->with('owner')
            ->whereHas('owner', fn ($q) => $q->where('is_admin', 0)->whereDoesntHave('accountState', fn ($s) => $s->whereNotNull('archived_at')->orWhereNotNull('blocked_at')))
            ->where('archived', 0)
            ->whereRaw('LOWER(file_type) = ?', ['insurance'])
            ->whereBetween('expiry_date', [$this->option('staged') ? $date->subDays(30)->toDateString() : $date->toDateString(), $end->toDateString()])
            ->orderBy('expiry_date')
            ->get();

        foreach ($documents as $document) {
            if ($document->owner) {
                $days = (int) $date->diffInDays($document->expiry_date->startOfDay(), false);
                $stage = $this->option('staged') ? ($days < 0 ? -1 : ($days === 0 ? 0 : ($days <= 7 ? 7 : 30))) : null;
                $notifications->documentExpiring($document->owner, (int) $document->file_id, $document->expiry_date->toDateString(), $stage);
            }
        }

        $this->info('Processed '.$documents->count().' active insurance documents in the selected reminder window.');

        return self::SUCCESS;
    }
}
