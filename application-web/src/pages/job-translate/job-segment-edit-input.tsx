import DOMPurify, { type Config as DOMPurifyConfig } from "dompurify"
import { useCallback, useEffect, useRef } from "react"

const SANITIZE_CONFIG: DOMPurifyConfig = {
  ALLOWED_TAGS: ['b', 'strong', 'i', 'em', 'u', 's', 'del', 'ins', 'sup', 'sub', 'br', 'span'],
  ALLOWED_ATTR: [],
}

const sanitize = (html: string) => DOMPurify.sanitize(html, SANITIZE_CONFIG) as string

const processHiddenChars = (html: string): string => {
  let result = '', inTag = false
  for (const ch of html) {
    if (ch === '<') { inTag = true; result += ch }
    else if (ch === '>') { inTag = false; result += ch }
    else if (!inTag && ch === ' ') result += '<span class="text-muted-foreground/60 select-none">·</span>'
    else result += ch
  }
  return result
}

type SegmentEditInputProps =
  | {
      readOnly: true
      value: string | null | undefined
      className?: string
      showHiddenChars?: boolean
    }
  | {
      readOnly?: false
      className?: string
      originalSegment: string
      editedSegment: string | null
      onClick?: () => void
      onChange?: (value: string) => void
      showHiddenChars?: boolean
      isOverwriteMode?: boolean
    }

const SegmentEditInputEditable = (props: {
  className?: string
  originalSegment: string
  editedSegment: string | null
  onClick?: () => void
  onChange?: (value: string) => void
  showHiddenChars?: boolean
  isOverwriteMode?: boolean
}) => {
  const { originalSegment, editedSegment, className, onClick, onChange, showHiddenChars, isOverwriteMode } = props

  const ref = useRef<HTMLDivElement>(null)
  // Tracks the last value we set or emitted to avoid overwriting the cursor position
  const lastValueRef = useRef<string | null>(null)

  useEffect(() => {
    if (!ref.current) return
    const value = editedSegment ?? originalSegment
    if (value !== lastValueRef.current) {
      ref.current.innerHTML = sanitize(value)
      lastValueRef.current = value
    }
  }, [editedSegment, originalSegment])

  const handleInput = useCallback(() => {
    if (!ref.current) return
    const html = sanitize(ref.current.innerHTML)
    lastValueRef.current = html
    if (onChange) onChange(html)
  }, [onChange])

  const handleClick = useCallback(() => {
    if (onClick) onClick()
  }, [onClick])

  const handleKeyDown = useCallback((e: React.KeyboardEvent<HTMLDivElement>) => {
    if (!isOverwriteMode || e.key.length !== 1 || e.ctrlKey || e.metaKey || e.altKey) return
    const sel = window.getSelection()
    if (!sel || !sel.rangeCount) return
    const range = sel.getRangeAt(0)
    if (!range.collapsed) return
    const { startContainer, startOffset } = range
    if (startContainer.nodeType !== Node.TEXT_NODE) return
    const textNode = startContainer as Text
    if (startOffset >= textNode.length) return
    e.preventDefault()
    const newRange = document.createRange()
    newRange.setStart(textNode, startOffset)
    newRange.setEnd(textNode, startOffset + 1)
    sel.removeAllRanges()
    sel.addRange(newRange)
    document.execCommand('insertText', false, e.key)
    handleInput()
  }, [isOverwriteMode, handleInput])

  const editableClassName = [
    'p-2 outline-none min-h-8',
    className ?? '',
    showHiddenChars ? 'underline decoration-dotted decoration-muted-foreground/30 underline-offset-2' : '',
    isOverwriteMode ? 'caret-orange-500' : '',
  ].filter(Boolean).join(' ')

  return (
    <div
      ref={ref}
      contentEditable
      suppressContentEditableWarning
      className={editableClassName}
      onInput={handleInput}
      onClick={handleClick}
      onKeyDown={handleKeyDown}
    />
  )
}

const SegmentEditInput = (props: SegmentEditInputProps) => {
  if (props.readOnly) {
    const html = sanitize(props.value ?? '')
    return (
      <span
        className={props.className}
        dangerouslySetInnerHTML={{ __html: props.showHiddenChars ? processHiddenChars(html) : html }}
      />
    )
  }
  return <SegmentEditInputEditable {...props} />
}

export default SegmentEditInput
