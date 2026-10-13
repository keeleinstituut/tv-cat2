<?php

namespace App\Console\Commands;

use App\Jobs\DetectRepetitionsJob;
use App\Models\Job;
use App\Models\Segment;
use App\Models\TranslationMemorySegment;
use App\Services\FiltersClient;
use App\Support\SegmentText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Moves jobs that were created with the XLIFF based file conversion over to catto-filters: the source file is read
 * again, and the work done so far (target, confirmed, locked, pretranslation data) is carried over to the new
 * segments whose source text is identical. Matching segments keep their id, so links from translation memory
 * entries stay valid.
 */
class ReimportJobs extends Command
{
    protected $signature = 'app:reimport-jobs
        {--job=* : Only these job ids (default: every job without catto-filters segment ids)}
        {--tm : Also bring the text of translation memory entries into the segment text form}
        {--dry-run : Report what would change and change nothing}';

    protected $description = 'Re-read the source files of existing jobs with catto-filters and carry the translations over';

    public function handle(FiltersClient $filters): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $jobs = Job::query()
            ->when($this->option('job'), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->when(!$this->option('job'), fn ($q) => $q->whereHas('segments', fn ($s) => $s->whereNull('filter_segment_id')))
            ->with('project')
            ->get();

        $rows = [];
        $failed = 0;
        foreach ($jobs as $job) {
            try {
                $rows[] = $this->reimport($job, $filters, $dryRun);
            } catch (Throwable $e) {
                $failed++;
                $this->error("Job {$job->id}: {$e->getMessage()}");
                $rows[] = [$job->id, '-', '-', '-', '-', '-', '-', 'FAILED'];
            }
        }

        $this->table(['job', 'old', 'new', 'matched', 'inserted', 'deleted', 'targets lost', 'result'], $rows);

        if ($this->option('tm')) {
            $this->normalizeTranslationMemories($dryRun);
        }

        $this->info($dryRun ? 'Dry run: nothing was changed.' : 'Done.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function reimport(Job $job, FiltersClient $filters, bool $dryRun): array
    {
        $file = $job->sourceFileCollection()->first();
        if (!$file) {
            throw new \RuntimeException('the job has no source file');
        }

        $extracted = $filters->extract($file, $job->project->source_locale, $job->target_locale);

        DB::beginTransaction();
        try {
            $row = $this->carryOver($job, array_values($extracted));
            $dryRun ? DB::rollBack() : DB::commit();

            return $row;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /** @param array<int, array{id: string, text: string}> $extracted */
    private function carryOver(Job $job, array $extracted): array
    {
        $old = Segment::where('job_id', $job->id)->orderBy('position')->get();

        // Old segments by source text, in document order: the n-th repetition matches the n-th old one.
        $bySource = [];
        foreach ($old as $segment) {
            $bySource[SegmentText::normalize($segment->source)][] = $segment;
        }

        $matched = 0;
        $now = now();
        $inserts = [];
        foreach ($extracted as $position => $new) {
            $match = isset($bySource[$new['text']]) ? array_shift($bySource[$new['text']]) : null;
            if ($match) {
                $matched++;
                $match->forceFill([
                    'position' => $position,
                    'filter_segment_id' => $new['id'],
                    'source' => $new['text'],
                    'target' => SegmentText::normalize($match->target),
                    'repetition_group' => null,
                ])->save();
            } else {
                $inserts[] = [
                    'id' => Str::uuid()->toString(), 'job_id' => $job->id, 'source' => $new['text'],
                    'position' => $position, 'filter_segment_id' => $new['id'],
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }

        $leftover = collect($bySource)->flatten(1);
        $targetsLost = $leftover->filter(fn ($s) => $s->target !== null && $s->target !== '')->count();
        foreach ($leftover->pluck('id')->chunk(1000) as $ids) {
            TranslationMemorySegment::whereIn('segment_id', $ids)->update(['segment_id' => null]);
            Segment::whereIn('id', $ids)->delete();
        }

        foreach (array_chunk($inserts, 500) as $chunk) {
            Segment::insert($chunk);
        }

        $job->forceFill(['status' => Job::STATUS_READY, 'error' => null])->save();
        DetectRepetitionsJob::dispatchSync($job);

        return [$job->id, $old->count(), count($extracted), $matched, count($inserts), $leftover->count(), $targetsLost, 'ok'];
    }

    private function normalizeTranslationMemories(bool $dryRun): void
    {
        $changed = 0;
        $fields = ['source', 'target', 'source_context_before', 'source_context_after', 'target_context_before', 'target_context_after'];

        TranslationMemorySegment::query()->lazyById(1000)->each(function (TranslationMemorySegment $entry) use (&$changed, $dryRun, $fields) {
            $new = [];
            foreach ($fields as $field) {
                $normalized = SegmentText::normalize($entry->$field);
                if ($normalized !== $entry->$field) {
                    $new[$field] = $normalized;
                }
            }
            if ($new) {
                $changed++;
                if (!$dryRun) {
                    $entry->forceFill($new)->saveQuietly();
                }
            }
        });

        $this->info('Translation memory entries ' . ($dryRun ? 'that would change' : 'changed') . ": $changed");
    }
}
