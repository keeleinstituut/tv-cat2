import VirtualizedList from "@/components/pudinad/virtualized-list"
import type { DataPaginationResponse } from "@/lib/api/projects"
import type { TranslationMemorySegment } from "@/lib/api/translation-memory-segments"
import type { InfiniteData, UseInfiniteQueryResult } from "@tanstack/react-query"
import type { VirtualItem, Virtualizer } from "@tanstack/react-virtual"
import { last } from "lodash"
import { useCallback } from "react"
import SegmentEditInput from "../job-translate/job-segment-edit-input"

type TranslationMemorySegmentsListProps = {
  segmentsQuery: UseInfiniteQueryResult<InfiniteData<DataPaginationResponse<TranslationMemorySegment[]>, unknown>, Error>
  editedSegments: { [key: string]: TranslationMemorySegment }
  onSegmentClick: (segment: TranslationMemorySegment) => void
  onSegmentChange: (segment: TranslationMemorySegment, value: string) => void
  activeSegmentId?: string | null
}

const TranslationMemorySegmentsList = (props: TranslationMemorySegmentsListProps) => {
  const { segmentsQuery, editedSegments, onSegmentClick, onSegmentChange, activeSegmentId } = props

  const allRows = segmentsQuery.data ? segmentsQuery.data.pages.flatMap((page) => page.data) : []
  const activeSegmentIndex = activeSegmentId ? allRows.findIndex(s => s.id === activeSegmentId) : null

  const onRenderedVirtualItemsChange = useCallback((virtualItems: VirtualItem[]) => {
    const lastItem = last(virtualItems)

    if (!lastItem) {
      return
    }

    const isOverIndex = lastItem.index >= allRows.length - 200
    const hasNextPage = segmentsQuery.hasNextPage
    const isNotFetching = !segmentsQuery.isFetchingNextPage

    if (isOverIndex && hasNextPage && isNotFetching) {
      segmentsQuery.fetchNextPage({ cancelRefetch: false })
    }
  }, [segmentsQuery])

  const renderSegmentRow = useCallback((virtualRow: VirtualItem, virtualizer: Virtualizer<any, any>) => {
    const isLoaderRow = virtualRow.index > allRows.length - 1
    const segment = allRows[virtualRow.index]

    return (
      <div
        key={virtualRow.key}
        data-index={virtualRow.index}
        ref={virtualizer.measureElement}
      >
        <div className={`flex border-b ${!isLoaderRow && segment.id === activeSegmentId ? 'bg-muted/50' : ''}`}>
          {isLoaderRow
            ? segmentsQuery.hasNextPage
              ? 'Loading more...'
              : 'Nothing more to load'
            : (
              <>
                <span className="w-[60px] font-medium p-2">{virtualRow.index + 1}</span>
                <SegmentEditInput
                  readOnly
                  value={segment.source}
                  className="basis-1/2 p-2 grow-0 shrink-1 text-sm"
                />
                <SegmentEditInput
                  originalSegment={segment.target}
                  editedSegment={editedSegments[segment.id]?.target}
                  className="basis-1/2 grow-0 shrink-1 text-sm"
                  onClick={() => onSegmentClick(segment)}
                  onChange={(value) => onSegmentChange(segment, value)}
                />
              </>
            )}
        </div>
      </div>
    )
  }, [allRows, editedSegments, onSegmentClick, onSegmentChange])

  return (
    <VirtualizedList
      className="flex-1 no-scrollbar overflow-y-auto [contain:strict]"
      count={segmentsQuery.hasNextPage ? allRows.length + 1 : allRows.length}
      renderItem={renderSegmentRow}
      onRenderedVirtualItemsChange={onRenderedVirtualItemsChange}
      overscan={5}
      scrollToIndex={activeSegmentIndex}
    />
  )
}

export default TranslationMemorySegmentsList
