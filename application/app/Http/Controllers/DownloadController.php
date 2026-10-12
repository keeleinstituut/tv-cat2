<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\JobDownloadRequest;
use App\Jobs\ExportXliffJob;
use App\Jobs\MergeSegmentsJob;
use App\Models\Job;
use Spatie\MediaLibrary\Support\MediaStream;

class DownloadController extends Controller
{
    public function download(JobDownloadRequest $request) {
        $params = collect($request->validated());
        $jobIds = $params->get('job_ids');
        $type = $params->get('type');

        $jobs = Job::getModel()->whereIn('id', $jobIds)->get();
        $jobs->each(fn ($job) => $this->authorize('view', $job));

        $files = $jobs->map(function ($job) use ($type) {
            switch ($type) {
                case 'source':
                    return $job->sourceFileCollection->first();
                case 'target':
                    MergeSegmentsJob::dispatchSync($job);
                    return $job->targetFileCollection->first();
                case 'xliff':
                    ExportXliffJob::dispatchSync($job);
                    return $job->xliffFileCollection->first();
            }
        });

        if ($files->count() > 1) {
            return MediaStream::create('files.zip')->addMedia($files);
        } else {
            return $files->first()->toResponse($request);
        }
    }
}
