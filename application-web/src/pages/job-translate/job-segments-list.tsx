import VirtualizedList from "@/components/pudinad/virtualized-list"
import type { DataPaginationResponse } from "@/lib/api/projects"
import type { Segment } from "@/lib/api/segments"
import type { InfiniteData, UseInfiniteQueryResult } from "@tanstack/react-query"
import type { VirtualItem, Virtualizer } from "@tanstack/react-virtual"
import { last } from "lodash"
import { Repeat, Repeat1 } from "lucide-react"
import { useCallback, useEffect, useState, type JSX } from "react"
import { useTranslation } from "react-i18next"
import SegmentEditInput from "./job-segment-edit-input"
import SuggestionBadge from "./suggestion-badge"
import classNames from "classnames"



interface ButtonItemProps {
  className?: string
  disabled?: boolean
  active?: boolean
  onClick?: () => void
  activeIcon: JSX.Element,
  inactiveIcon: JSX.Element,
}

function ButtonItem(props: ButtonItemProps) {
  const { className, disabled, active, onClick, activeIcon, inactiveIcon } = props

  return (
    <button
      className={classNames(
        "flex items-center justify-center w-[32px] self-stretch text-muted-foreground",
        {'hover:text-foreground': !disabled},
        className,
      )}
      onClick={onClick}
    >
      {active ? activeIcon : inactiveIcon}
    </button>
  )
}


type JobSegmentsListProps = {
  segmentsQuery: UseInfiniteQueryResult<InfiniteData<DataPaginationResponse<Segment[]>, unknown>, Error>
  editedSegments: {
    [key: string]: Segment
  }
  currentSegmentId: string | null
  onSegmentClick: (segment: Segment) => void
  onSegmentChange: (segment: Segment, value: Partial<Pick<Segment, "target" | "confirmed">>) => void
  showHiddenChars: boolean
  isOverwriteMode: boolean
}

const JobSegmentsList = (props: JobSegmentsListProps) => {
  const { t } = useTranslation()
  const { segmentsQuery, editedSegments, currentSegmentId, onSegmentClick, onSegmentChange, showHiddenChars, isOverwriteMode } = props

  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set())
  const [anchorId, setAnchorId] = useState<string | null>(null)
  const [isVirtualAllSelected, setIsVirtualAllSelected] = useState(false)

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'A') {
        e.preventDefault()
        setIsVirtualAllSelected(true)
      }
    }
    document.addEventListener('keydown', handler)
    return () => document.removeEventListener('keydown', handler)
  }, [])

  const allRows = segmentsQuery.data ? segmentsQuery.data.pages.flatMap((page) => page.data) : []

  const onRenderedVirtualItemsChange = useCallback((virtualItems: VirtualItem[]) => {
    // const firstItem = first(virtualItems)
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

  const handleRowClick = useCallback((segment: Segment, e: React.MouseEvent) => {
    const isCtrl = e.ctrlKey || e.metaKey
    const isShift = e.shiftKey

    if (!isCtrl && !isShift) {
      onSegmentClick(segment)
      setSelectedIds(new Set([segment.id]))
      setAnchorId(segment.id)
      setIsVirtualAllSelected(false)
    } else if (isCtrl) {
      setSelectedIds(prev => {
        const next = new Set(prev)
        next.has(segment.id) ? next.delete(segment.id) : next.add(segment.id)
        return next
      })
      setAnchorId(segment.id)
    } else if (isShift) {
      const effectiveAnchor = anchorId ?? segment.id
      const anchorIdx = allRows.findIndex(s => s.id === effectiveAnchor)
      const clickedIdx = allRows.findIndex(s => s.id === segment.id)
      const [start, end] = anchorIdx <= clickedIdx ? [anchorIdx, clickedIdx] : [clickedIdx, anchorIdx]
      setSelectedIds(new Set(allRows.slice(start, end + 1).map(s => s.id)))
    }
  }, [allRows, anchorId, onSegmentClick])

  const renderSegmentRow = useCallback((virtualRow: VirtualItem, virtualizer: Virtualizer<any, any>) => {
    const isLoaderRow = virtualRow.index > allRows.length - 1
    const segment = allRows[virtualRow.index]
    const isActive = !isLoaderRow && segment.id === currentSegmentId
    const isSelected = !isLoaderRow && (isVirtualAllSelected || selectedIds.has(segment.id))

    return (
      <div
        key={virtualRow.key}
        data-index={virtualRow.index}
        ref={virtualizer.measureElement}
      >
        <div
          className={classNames("flex border-b cursor-pointer select-none", {
            "bg-blue-50 dark:bg-blue-950/40": isActive,
            "bg-accent": !isActive && isSelected,
          })}
          onClick={(e) => !isLoaderRow && handleRowClick(segment, e)}
          onMouseDown={(e) => { if (e.shiftKey) e.preventDefault() }}
        >
          {isLoaderRow
            ? segmentsQuery.hasNextPage
              ? t('jobTranslate.segmentsListLoadingMore')
              : t('jobTranslate.segmentsListNothingMoreToLoad')
            : (
              <>
                <span className="w-[70px] font-medium p-2">{segment.position}</span>
                <SegmentEditInput
                  readOnly
                  value={segment.source}
                  className="basis-1/2 p-2 grow-0 shrink-1 text-sm"
                  showHiddenChars={showHiddenChars}
                />
                <SegmentEditInput
                  originalSegment={segment.target}
                  editedSegment={editedSegments[segment.id]?.target}
                  className="basis-1/2 grow-0 shrink-1 text-sm"
                  onChange={(value) => onSegmentChange(segment, { target: value })}
                  showHiddenChars={showHiddenChars}
                  isOverwriteMode={isOverwriteMode}
                />
                <span className="flex self-stretch w-[40px] text-xs mx-1">
                  <SuggestionBadge providerType={segment.pretranslate_suggestion_provider_type} score={segment.pretranslate_suggestion_score} className="h-full" />
                </span>
                <ButtonItem
                  disabled
                  active={!!segment.repetition_group}
                  inactiveIcon={<span className="w-[16px]" />}
                  activeIcon={
                    segment.repetition_group === segment.id ? (
                      <Repeat1 size={16} className="text-orange-400" />
                    ) : (
                      <Repeat size={16} />
                    )
                  }
                />
              </>
            )}
        </div>
      </div>
    )
  }, [allRows, currentSegmentId, selectedIds, isVirtualAllSelected, handleRowClick, showHiddenChars, isOverwriteMode])

  const totalCount = segmentsQuery.data?.pages[0]?.meta.total

  return (
    <div className="flex flex-col flex-1 min-h-0">
      {isVirtualAllSelected && totalCount != null && (
        <div className="shrink-0 bg-blue-50 dark:bg-blue-950/40 border-b px-4 py-2 text-sm text-center text-blue-700 dark:text-blue-300">
          {t('jobTranslate.allSegmentsSelected', { count: totalCount })}
        </div>
      )}
      <VirtualizedList
        className="flex-1 no-scrollbar overflow-y-auto [contain:strict]"
        count={segmentsQuery.hasNextPage ? allRows.length + 1 : allRows.length}
        renderItem={renderSegmentRow}
        onRenderedVirtualItemsChange={onRenderedVirtualItemsChange}
        overscan={5}
      />
    </div>
  )
}

export default JobSegmentsList