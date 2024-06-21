<?php

namespace App\Jobs;

use Exception;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

use Matecat\XliffParser\XliffParser;

use App\Models\Job;
use App\Models\Segment;


class XliffToSegmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Job $jobModel;

    /**
     * Create a new job instance.
     */
    public function __construct(Job $jobModel)
    {
        $this->jobModel = $jobModel;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $xliffFile = $this->jobModel->xliffFileCollection->first();
        $xliffStream = $xliffFile->stream();
        $xliffContent = stream_get_contents($xliffStream);

        $parser = new XliffParser();
        $xliff = $parser->xliffToArray($xliffContent);

        $this->jobModel->xliff_parsed = $xliff;
        $this->jobModel->save();

        $segments = collect();

        foreach ($xliff['files'] as $file) {
            if (!array_key_exists('trans-units', $file)) {
                continue;
            }

//            $this->jobModel->file_name = $file['attr']['original'];
//            $this->jobModel->save();

            foreach ($file['trans-units'] as $trans_unit) {

                foreach ($trans_unit['seg-source'] as $position => $seg_source) {
                    $segment = [
                        'id' => Str::uuid()->toString(),
                        'job_id' => $this->jobModel->id,
                        'source' => $seg_source['raw-content'],
                        'xliff_internal_id' => $trans_unit[ 'attr' ][ 'id' ],
                        'xliff_mrk_id' => $seg_source[ 'mid' ],
                    ];
                    $segments->push($segment);
                }
            }
        }

        $segments = $segments->map(function ($segment, $i) {
            return [
                'position' => $i,
                ...$segment,
            ];
        });
    

        Segment::insert($segments->toArray());
    }

    protected function _extractSegments( $fid, $file_info ) {
        $xliffFile = $this->jobModel->xliffFileCollection->first();
        $xliffStream = $xliffFile->stream();
        $xliffContent = stream_get_contents($xliffStream);

        $xliff_file_content = $xliffContent;
//        $xliff_file_content = $this->getXliffFileContent( $file_info[ 'path_cached_xliff' ] );
//        $mimeType           = $file_info[ 'mime_type' ];
//        $jobsMetadataDao    = new \Jobs\MetadataDao();
//
//        // create Structure for multiple files
//        $this->projectStructure[ 'segments' ]->offsetSet( $fid, new ArrayObject( [] ) );
//        $this->projectStructure[ 'segments-original-data' ]->offsetSet( $fid, new ArrayObject( [] ) );
//        $this->projectStructure[ 'file-part-id' ]->offsetSet( $fid, new ArrayObject( [] ) );
//        $this->projectStructure[ 'segments-meta-data' ]->offsetSet( $fid, new ArrayObject( [] ) );

        $xliffParser = new XliffParser();

        try {
            $xliff     = $xliffParser->xliffToArray( $xliff_file_content );
//            $xliffInfo = XliffProprietaryDetect::getInfoByStringData( $xliff_file_content );
        } catch ( Exception $e ) {
            throw new Exception( $file_info[ 'original_filename' ], $e->getCode(), $e );
        }

//        // Checking that parsing went well
//        if ( isset( $xliff[ 'parser-errors' ] ) or !isset( $xliff[ 'files' ] ) ) {
//            $this->_log( "Xliff Import: Error parsing. " . join( "\n", $xliff[ 'parser-errors' ] ) );
//            throw new Exception( $file_info[ 'original_filename' ], -4 );
//        }

        //needed to check if a file has only one segment
        //for correctness: we could have more tag files in the xliff
        $_fileCounter_Show_In_Cattool = 0;

        // Creating the Query
        foreach ( $xliff[ 'files' ] as $xliff_file ) {

//            // save x-jsont* datatype
//            if(isset( $xliff_file[ 'attr' ][ 'data-type' ] )){
//                $dataType = $xliff_file[ 'attr' ][ 'data-type' ];
//
//                if (strpos($dataType, 'x-jsont' ) !== false) {
//                    $this->metadataDao->insert( $this->projectStructure[ 'id_project' ], $fid, 'data-type', $dataType );
//                }
//            }

            if ( !array_key_exists( 'trans-units', $xliff_file ) ) {
                continue;
            }

            // files-part
            if ( isset( $xliff_file[ 'attr' ][ 'original' ] ) ) {
                $filesPartsStruct          = new FilesPartsStruct();
                $filesPartsStruct->id_file = $fid;
                $filesPartsStruct->key     = 'original';
                $filesPartsStruct->value   = $xliff_file[ 'attr' ][ 'original' ];

                $filePartsId = ( new FilesPartsDao() )->insert( $filesPartsStruct );

                // save `custom` meta data
                if(isset($xliff_file[ 'attr' ][ 'custom' ]) and !empty($xliff_file[ 'attr' ][ 'custom' ])){
                    $this->metadataDao->bulkInsert( $this->projectStructure[ 'id_project' ], $fid, $xliff_file[ 'attr' ][ 'custom' ], $filePartsId );
                }
            }

            foreach ( $xliff_file[ 'trans-units' ] as $xliff_trans_unit ) {

                //initialize flag
                $show_in_cattool = 1;

                if ( !isset( $xliff_trans_unit[ 'attr' ][ 'translate' ] ) ) {
                    $xliff_trans_unit[ 'attr' ][ 'translate' ] = 'yes';
                }

                if ( $xliff_trans_unit[ 'attr' ][ 'translate' ] == "no" ) {
                    //No segments to translate
                    //don't increment global counter '$this->fileCounter_Show_In_Cattool'
                    $show_in_cattool = 0;
                } else {

                    $this->_manageAlternativeTranslations( $xliff_trans_unit, $xliff_file[ 'attr' ], $xliffInfo );

                    $trans_unit_reference = self::sanitizedUnitId( $xliff_trans_unit[ 'attr' ][ 'id' ], $fid );

                    // check if there is original data
                    $segmentOriginalData = [];
                    $dataRefMap          = [];

                    if ( isset( $xliff_trans_unit[ 'original-data' ] ) and !empty( $xliff_trans_unit[ 'original-data' ] ) ) {
                        $segmentOriginalData = $xliff_trans_unit[ 'original-data' ];
                        foreach ( $segmentOriginalData as $datum ) {
                            if ( isset( $datum[ 'attr' ][ 'id' ] ) ) {
                                $dataRefMap[ $datum[ 'attr' ][ 'id' ] ] = $datum[ 'raw-content' ];
                            }
                        }
                    }

                    // If the XLIFF is already segmented (has <seg-source>)
                    if ( isset( $xliff_trans_unit[ 'seg-source' ] ) ) {
                        foreach ( $xliff_trans_unit[ 'seg-source' ] as $position => $seg_source ) {

                            //rest flag because if the first mrk of the seg-source is not translatable the rest of
                            //mrk in the list will not be too!!!
                            $show_in_cattool = 1;

                            $wordCount = CatUtils::segment_raw_word_count( $seg_source[ 'raw-content' ], $this->projectStructure[ 'source_language' ], $this->filter );

                            //init tags
                            $seg_source[ 'mrk-ext-prec-tags' ] = '';
                            $seg_source[ 'mrk-ext-succ-tags' ] = '';

                            if ( empty( $wordCount ) ) {
                                $show_in_cattool = 0;
                            } else {

                                $extract_external                  = $this->_strip_external( $seg_source[ 'raw-content' ], $xliffInfo );
                                $seg_source[ 'mrk-ext-prec-tags' ] = $extract_external[ 'prec' ];
                                $seg_source[ 'mrk-ext-succ-tags' ] = $extract_external[ 'succ' ];
                                $seg_source[ 'raw-content' ]       = $extract_external[ 'seg' ];

                                if ( isset( $xliff_trans_unit[ 'seg-target' ][ $position ][ 'raw-content' ] ) ) {

                                    if ( $this->features->filter( 'populatePreTranslations', true ) ) {

                                        $target_extract_external = $this->_strip_external( $xliff_trans_unit[ 'seg-target' ][ $position ][ 'raw-content' ], $xliffInfo );

                                        //
                                        // -----------------------------------------------
                                        // NOTE 2020-06-16
                                        // -----------------------------------------------
                                        //
                                        // before calling html_entity_decode function we convert
                                        // all unicode entities with no corresponding HTML entity
                                        //
                                        $extract_external[ 'seg' ]        = CatUtils::restoreUnicodeEntitesToOriginalValues( $extract_external[ 'seg' ] );
                                        $target_extract_external[ 'seg' ] = CatUtils::restoreUnicodeEntitesToOriginalValues( $target_extract_external[ 'seg' ] );

                                        // we don't want THE CONTENT OF TARGET TAG IF PRESENT and EQUAL TO SOURCE???
                                        // AND IF IT IS ONLY A CHAR? like "*" ?
                                        // we can't distinguish if it is translated or not
                                        // this means that we lose the tags id inside the target if different from source
                                        $src = CatUtils::trimAndStripFromAnHtmlEntityDecoded( $extract_external[ 'seg' ] );
                                        $trg = CatUtils::trimAndStripFromAnHtmlEntityDecoded( $target_extract_external[ 'seg' ] );

                                        if ( $this->__isTranslated( $src, $trg, $xliff_trans_unit ) && !is_numeric( $src ) && !empty( $trg ) ) { //treat 0,1,2.. as translated content!

                                            $target = $this->filter->fromRawXliffToLayer0( $target_extract_external[ 'seg' ] );

                                            //add an empty string to avoid casting to int: 0001 -> 1
                                            //useful for idiom internal xliff id
                                            if ( !$this->projectStructure[ 'translations' ]->offsetExists( $trans_unit_reference ) ) {
                                                $this->projectStructure[ 'translations' ]->offsetSet( $trans_unit_reference, new ArrayObject() );
                                            }

                                            /**
                                             * Trans-Unit
                                             * @see http://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html#trans-unit
                                             */
                                            $this->projectStructure[ 'translations' ][ $trans_unit_reference ]->offsetSet(
                                                $seg_source[ 'mid' ],
                                                new ArrayObject( [ 2 => $target, 4 => $xliff_trans_unit ] )
                                            );

                                            //seg-source and target translation can have different mrk id
                                            //override the seg-source surrounding mrk-id with them of target
                                            $seg_source[ 'mrk-ext-prec-tags' ] = $target_extract_external[ 'prec' ];
                                            $seg_source[ 'mrk-ext-succ-tags' ] = $target_extract_external[ 'succ' ];

                                        }
                                    }
                                }
                            }

                            //
                            // -------------------------------------
                            // START SEGMENTS META
                            // -------------------------------------
                            //

                            $metadataStruct = new Segments_SegmentMetadataStruct();

                            // check if there is sizeRestriction
                            if ( isset( $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] ) and $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] > 0 ) {
                                $metadataStruct->meta_key   = 'sizeRestriction';
                                $metadataStruct->meta_value = $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ];
                            }

                            $this->projectStructure[ 'segments-meta-data' ][ $fid ]->append( $metadataStruct );

                            //
                            // -------------------------------------
                            // END SEGMENTS META
                            // -------------------------------------
                            //

                            //
                            // -------------------------------------
                            // START SEGMENTS ORIGINAL DATA
                            // -------------------------------------
                            //

                            // if its empty pass create a Segments_SegmentOriginalDataStruct with no data
                            $segmentOriginalDataStructMap = ( !empty( $dataRefMap ) ) ? [ 'map' => $dataRefMap ] : [];
                            $segmentOriginalDataStruct    = new Segments_SegmentOriginalDataStruct( $segmentOriginalDataStructMap );
                            $this->projectStructure[ 'segments-original-data' ][ $fid ]->append( $segmentOriginalDataStruct );

                            //
                            // -------------------------------------
                            // END SEGMENTS ORIGINAL DATA
                            // -------------------------------------
                            //

                            $sizeRestriction = null;
                            if ( isset( $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] ) and $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] > 0 ) {
                                $sizeRestriction = $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ];
                            }

                            $segmentHash = $this->createSegmentHash( $seg_source[ 'raw-content' ], $dataRefMap, $sizeRestriction );

                            // segment struct
                            $segStruct = new Segments_SegmentStruct( [
                                'id_file'                 => $fid,
                                'id_file_part'            => ( isset( $filePartsId ) ) ? $filePartsId : null,
                                'id_project'              => $this->projectStructure[ 'id_project' ],
                                'internal_id'             => $xliff_trans_unit[ 'attr' ][ 'id' ],
                                'xliff_mrk_id'            => $seg_source[ 'mid' ],
                                'xliff_ext_prec_tags'     => $seg_source[ 'ext-prec-tags' ],
                                'xliff_mrk_ext_prec_tags' => $seg_source[ 'mrk-ext-prec-tags' ],
                                'segment'                 => $this->filter->fromRawXliffToLayer0( $seg_source[ 'raw-content' ] ),
                                'segment_hash'            => $segmentHash,
                                'xliff_mrk_ext_succ_tags' => $seg_source[ 'mrk-ext-succ-tags' ],
                                'xliff_ext_succ_tags'     => $seg_source[ 'ext-succ-tags' ],
                                'raw_word_count'          => $wordCount,
                                'show_in_cattool'         => $show_in_cattool
                            ] );

                            $this->projectStructure[ 'segments' ][ $fid ]->append( $segStruct );

                            //increment counter for word count
                            $this->files_word_count += $wordCount;

                            //increment the counter for not empty segments
                            $_fileCounter_Show_In_Cattool += $show_in_cattool;

                        } // end foreach seg-source

                        try {
                            $this->__addNotesToProjectStructure( $xliff_trans_unit, $fid );
                            $this->__addTUnitContextsToProjectStructure( $xliff_trans_unit, $fid );
                        } catch ( \Exception $exception ) {
                            throw new Exception( $exception->getMessage(), -1 );
                        }

                    } else {

                        $wordCount = CatUtils::segment_raw_word_count( $xliff_trans_unit[ 'source' ][ 'raw-content' ], $this->projectStructure[ 'source_language' ], $this->filter );

                        $prec_tags = null;
                        $succ_tags = null;
                        if ( empty( $wordCount ) ) {
                            $show_in_cattool = 0;
                        } else {
                            $extract_external                              = $this->_strip_external( $xliff_trans_unit[ 'source' ][ 'raw-content' ], $xliffInfo );
                            $prec_tags                                     = empty( $extract_external[ 'prec' ] ) ? null : $extract_external[ 'prec' ];
                            $succ_tags                                     = empty( $extract_external[ 'succ' ] ) ? null : $extract_external[ 'succ' ];
                            $xliff_trans_unit[ 'source' ][ 'raw-content' ] = $extract_external[ 'seg' ];

                            if ( isset( $xliff_trans_unit[ 'target' ][ 'raw-content' ] ) ) {

                                $target_extract_external = $this->_strip_external( $xliff_trans_unit[ 'target' ][ 'raw-content' ], $xliffInfo );

                                if ( $this->__isTranslated( $xliff_trans_unit[ 'source' ][ 'raw-content' ], $target_extract_external[ 'seg' ], $xliff_trans_unit ) ) {

                                    $target = $this->filter->fromRawXliffToLayer0( $target_extract_external[ 'seg' ] );

                                    //add an empty string to avoid casting to int: 0001 -> 1
                                    //useful for idiom internal xliff id
                                    if ( !$this->projectStructure[ 'translations' ]->offsetExists( $trans_unit_reference ) ) {
                                        $this->projectStructure[ 'translations' ]->offsetSet( $trans_unit_reference, new ArrayObject() );
                                    }

                                    /**
                                     * Trans-Unit
                                     * @see http://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html#trans-unit
                                     */
                                    $this->projectStructure[ 'translations' ][ $trans_unit_reference ]->append(
                                        new ArrayObject( [ 2 => $target, 4 => $xliff_trans_unit ] )
                                    );
                                }
                            }
                        }

                        try {
                            $this->__addNotesToProjectStructure( $xliff_trans_unit, $fid );
                            $this->__addTUnitContextsToProjectStructure( $xliff_trans_unit, $fid );
                        } catch ( \Exception $exception ) {
                            throw new Exception( $exception->getMessage(), -1 );
                        }

                        $segmentHash = md5( $xliff_trans_unit[ 'source' ][ 'raw-content' ] );


                        //
                        // -------------------------------------
                        // START SEGMENTS META
                        // -------------------------------------
                        //

                        $metadataStruct = new Segments_SegmentMetadataStruct();

                        // check if there is sizeRestriction
                        if ( isset( $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] ) and $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] > 0 ) {
                            $metadataStruct->meta_key   = 'sizeRestriction';
                            $metadataStruct->meta_value = $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ];
                        }

                        $this->projectStructure[ 'segments-meta-data' ][ $fid ]->append( $metadataStruct );

                        //
                        // -------------------------------------
                        // END SEGMENTS META
                        // -------------------------------------
                        //


                        // segment original data
                        if ( !empty( $segmentOriginalData ) ) {

                            $dataRefReplacer           = new \Matecat\XliffParser\XliffUtils\DataRefReplacer( $segmentOriginalData );
                            $segmentOriginalDataStruct = new Segments_SegmentOriginalDataStruct( [
                                'data'             => $segmentOriginalData,
                                'replaced_segment' => $dataRefReplacer->replace( $this->filter->fromRawXliffToLayer0( $xliff_trans_unit[ 'source' ][ 'raw-content' ] ) ),
                            ] );

                            $this->projectStructure[ 'segments-original-data' ][ $fid ]->append( $segmentOriginalDataStruct );
                        }

                        $sizeRestriction = null;
                        if ( isset( $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] ) and $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ] > 0 ) {
                            $sizeRestriction = $xliff_trans_unit[ 'attr' ][ 'sizeRestriction' ];
                        }

                        $segmentHash = $this->createSegmentHash( $xliff_trans_unit[ 'source' ][ 'raw-content' ], $segmentOriginalData, $sizeRestriction );

                        $segStruct = new Segments_SegmentStruct( [
                            'id_file'             => $fid,
                            'id_file_part'        => ( isset( $filePartsId ) ) ? $filePartsId : null,
                            'id_project'          => $this->projectStructure[ 'id_project' ],
                            'internal_id'         => $xliff_trans_unit[ 'attr' ][ 'id' ],
                            'xliff_ext_prec_tags' => ( !is_null( $prec_tags ) ? $prec_tags : null ),
                            'segment'             => $this->filter->fromRawXliffToLayer0( $xliff_trans_unit[ 'source' ][ 'raw-content' ] ),
                            'segment_hash'        => $segmentHash,
                            'xliff_ext_succ_tags' => ( !is_null( $succ_tags ) ? $succ_tags : null ),
                            'raw_word_count'      => $wordCount,
                            'show_in_cattool'     => $show_in_cattool
                        ] );

                        $this->projectStructure[ 'segments' ][ $fid ]->append( $segStruct );

                        //increment counter for word count
                        $this->files_word_count += $wordCount;

                        //increment the counter for not empty segments
                        $_fileCounter_Show_In_Cattool += $show_in_cattool;
                    }
                }
            }

            $this->total_segments += count( $xliff_file[ 'trans-units' ] );

        }

        // *NOTE*: PHP>=5.3 throws UnexpectedValueException, but PHP 5.2 throws ErrorException
        //use generic
        if ( count( $this->projectStructure[ 'segments' ][ $fid ] ) == 0 || $_fileCounter_Show_In_Cattool == 0 ) {
            $this->_log( "Segment import - no segments found in {$file_info[ 'original_filename' ]}\n" );
            throw new Exception( $file_info[ 'original_filename' ], -1 );
        } else {
            //increment global counter
            $this->show_in_cattool_segs_counter += $_fileCounter_Show_In_Cattool;
        }

    }
}
