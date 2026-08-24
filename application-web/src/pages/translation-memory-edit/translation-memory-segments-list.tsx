import VirtualizedList from "@/components/pudinad/virtualized-list"
import { Skeleton } from "@/components/ui/skeleton"
import type { DataPaginationResponse } from "@/lib/api/projects"
import type { TranslationMemorySegment } from "@/lib/api/translation-memory-segments"
import type { InfiniteData, UseInfiniteQueryResult } from "@tanstack/react-query"
import type { VirtualItem, Virtualizer } from "@tanstack/react-virtual"
import classNames from "classnames"
import { last } from "lodash"
import { Trash2 } from "lucide-react"
import { useCallback } from "react"
import { useTranslation } from "react-i18next"
import SegmentEditInput from "../job-translate/job-segment-edit-input"

type TranslationMemorySegmentsListProps = {
  segmentsQuery: UseInfiniteQueryResult<InfiniteData<DataPaginationResponse<TranslationMemorySegment[]>, unknown>, Error>
  editedSegments: { [key: string]: TranslationMemorySegment }
  onSegmentClick: (segment: TranslationMemorySegment) => void
  onSegmentChange: (segment: TranslationMemorySegment, field: 'source' | 'target', value: string) => void
  onSegmentDelete: (segment: TranslationMemorySegment) => void
  activeSegmentId?: string | null
  hasActiveFilter: boolean
}

const TranslationMemorySegmentsList = (props: TranslationMemorySegmentsListProps) => {
  const { segmentsQuery, editedSegments, onSegmentClick, onSegmentChange, onSegmentDelete, activeSegmentId, hasActiveFilter } = props
  const { t } = useTranslation()

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
              ? t('translationMemoryEdit.loadingMore')
              : t('translationMemoryEdit.nothingMoreToLoad')
            : (
              <>
                <span className="w-[60px] font-medium p-2">{virtualRow.index + 1}</span>
                <SegmentEditInput
                  originalSegment={segment.source}
                  editedSegment={editedSegments[segment.id]?.source}
                  className="basis-1/2 grow-0 shrink-1 text-sm"
                  onClick={() => onSegmentClick(segment)}
                  onChange={(value) => onSegmentChange(segment, 'source', value)}
                />
                <SegmentEditInput
                  originalSegment={segment.target}
                  editedSegment={editedSegments[segment.id]?.target}
                  className="basis-1/2 grow-0 shrink-1 text-sm"
                  onClick={() => onSegmentClick(segment)}
                  onChange={(value) => onSegmentChange(segment, 'target', value)}
                />
                <button
                  className={classNames(
                    "flex items-center justify-center w-[32px] self-stretch text-muted-foreground hover:text-destructive cursor-pointer",
                  )}
                  title={t('translationMemoryEdit.deleteSegment')}
                  onClick={() => onSegmentDelete(segment)}
                >
                  <Trash2 size={16} />
                </button>
              </>
            )}
        </div>
      </div>
    )
  }, [allRows, editedSegments, onSegmentClick, onSegmentChange, onSegmentDelete, t])

  if (segmentsQuery.isLoading) {
    return (
      <div className="flex-1 no-scrollbar overflow-y-auto [contain:strict]">
        {Array.from({ length: 15 }).map((_, i) => (
          <div key={i} className="flex border-b">
            <span className="w-[60px] p-2"><Skeleton className="h-4 w-6" /></span>
            <div className="basis-1/2 grow-0 shrink-1 p-2"><Skeleton className="h-4 w-full" /></div>
            <div className="basis-1/2 grow-0 shrink-1 p-2"><Skeleton className="h-4 w-full" /></div>
            <span className="w-[32px] self-stretch" />
          </div>
        ))}
      </div>
    )
  }

  if (allRows.length === 0) {
    return (
      <div className="flex-1 flex items-center justify-center">
        <span className="text-muted-foreground text-sm">
          {t(hasActiveFilter ? 'translationMemoryEdit.noSegmentsMatchFilter' : 'translationMemoryEdit.noSegmentsFound')}
        </span>
      </div>
    )
  }

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
