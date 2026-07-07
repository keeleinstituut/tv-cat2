import DOMPurify, { type Config as DOMPurifyConfig } from "dompurify"
import { useCallback, useEffect, useRef } from "react"

const SANITIZE_CONFIG: DOMPurifyConfig = {
  ALLOWED_TAGS: ['b', 'strong', 'i', 'em', 'u', 's', 'del', 'ins', 'sup', 'sub', 'br', 'span'],
  ALLOWED_ATTR: [],
}

const sanitize = (html: string) => DOMPurify.sanitize(html, SANITIZE_CONFIG) as string

type SegmentEditInputProps =
  | {
      readOnly: true
      value: string | null | undefined
      className?: string
    }
  | {
      readOnly?: false
      className?: string
      originalSegment: string
      editedSegment: string | null
      onClick?: () => void
      onChange?: (value: string) => void
    }

const SegmentEditInputEditable = (props: {
  className?: string
  originalSegment: string
  editedSegment: string | null
  onClick?: () => void
  onChange?: (value: string) => void
}) => {
  const { originalSegment, editedSegment, className, onClick, onChange } = props

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

  return (
    <div
      ref={ref}
      contentEditable
      suppressContentEditableWarning
      className={`p-2 outline-none min-h-8 ${className ?? ''}`}
      onInput={handleInput}
      onClick={handleClick}
    />
  )
}

const SegmentEditInput = (props: SegmentEditInputProps) => {
  if (props.readOnly) {
    return (
      <span
        className={props.className}
        dangerouslySetInnerHTML={{ __html: sanitize(props.value ?? '') }}
      />
    )
  }
  return <SegmentEditInputEditable {...props} />
}

export default SegmentEditInput
