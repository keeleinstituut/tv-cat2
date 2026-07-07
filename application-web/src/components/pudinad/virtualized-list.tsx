import { useVirtualizer, Virtualizer, type VirtualItem } from "@tanstack/react-virtual"
import { useEffect, useRef, type JSX } from "react"

type VirtualizedListProps = {
  className?: string
  count: number
  renderItem: (virtualItem: VirtualItem, virtualizer: Virtualizer<HTMLDivElement, Element>) => JSX.Element
  onRenderedVirtualItemsChange?: (virtualItems: VirtualItem[]) => void
  overscan?: number
  scrollToIndex?: number | null
}

const VirtualizedList = (props: VirtualizedListProps) => {
  const { className, count, renderItem, onRenderedVirtualItemsChange, overscan, scrollToIndex } = props

  const virtualizerRef = useRef<HTMLDivElement>(null)
  const virtualizer = useVirtualizer({
    count,
    getScrollElement: () => virtualizerRef.current,
    estimateSize: () => 100,
    overscan,
  })

  const virtualItems = virtualizer.getVirtualItems()
  const isMounted = useRef(false)

  useEffect(() => {
    if (onRenderedVirtualItemsChange) {
      onRenderedVirtualItemsChange(virtualItems)
    }
  }, [virtualItems, onRenderedVirtualItemsChange])

  useEffect(() => {
    if (!isMounted.current) {
      isMounted.current = true
      return
    }
    if (scrollToIndex != null && scrollToIndex >= 0) {
      virtualizer.scrollToIndex(scrollToIndex)
    }
  }, [scrollToIndex])

  return (
    <div
      ref={virtualizerRef}
      className={className}
    >
      <div
        className="w-full relative"
        style={{
          height: virtualizer.getTotalSize(),
        }}
      >
        <div
          className="absolute top-0 left-0 w-full"
          style={{
            transform: `translateY(${virtualItems[0]?.start ?? 0}px)`,
          }}
        >
          {virtualItems.map(virtualRow => {
            return renderItem(virtualRow, virtualizer)
          })}
        </div>
      </div>
    </div>
  )
}

export default VirtualizedList