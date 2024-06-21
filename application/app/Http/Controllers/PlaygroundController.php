<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Segment;
use App\Models\TranslationMemorySegment;

class PlaygroundController extends Controller
{
    public function test2(Request $request)
    {
        $segments = Segment::getModel()->orderBy('position', 'asc')->get();

        $segments->each(function ($segment) {
            echo $segment->source, PHP_EOL;
        });
    }

    public function test1(Request $request)
    {
        // $q = 'address & shipping';
        $q = $request->get('q');

        $result = DB::select("

        SELECT ts_rank_cd(to_tsvector('simple', P.source), to_tsquery('$q')) AS score
            ,P.id
            ,P.source
            ,P.target
        FROM    translation_memory_segments as P
        WHERE to_tsvector('simple', P.source) @@ to_tsquery('$q')
        ORDER BY score DESC;
        ");

        dump($result);

        return 'result';
    }

    public function tmContent(string $id, Request $request)
    {
        return TranslationMemorySegment::getModel()
            ->where('translation_memory_id', $id)
            ->paginate(1000);
    }
}
