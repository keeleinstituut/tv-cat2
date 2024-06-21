<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\JobDownloadRequest;
use App\Jobs\SegmentsToXliffJob;
use App\Jobs\XliffToOriginalJob;
use App\Models\Job;
use Spatie\MediaLibrary\Support\MediaStream;

class DownloadController extends Controller
{
    public function download(JobDownloadRequest $request) {
        $params = collect($request->validated());
        $jobIds = $params->get('job_ids');
        $type = $params->get('type');

        $jobs = Job::getModel()->whereIn('id', $jobIds)->get();

        $files = $jobs->map(function ($job) use ($type) {
            switch ($type) {
                case 'source':
                    return $job->sourceFileCollection->first();
                case 'source_xliff':
                    return $job->xliffFileCollection->first();
                case 'target_xliff':
                    SegmentsToXliffJob::dispatchSync($job);
                    return $job->targetXliffFileCollection->first();
                case 'target':
                    SegmentsToXliffJob::dispatchSync($job);
                    XliffToOriginalJob::dispatchSync($job);
                    return $job->targetFileCollection->first();
            }
        });

        if ($files->count() > 1) {
            return MediaStream::create('files.zip')->addMedia($files);
        } else {
            return $files->first()->toResponse($request);
        }
    }
}
