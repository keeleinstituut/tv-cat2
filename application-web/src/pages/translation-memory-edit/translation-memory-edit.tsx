import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb"
import { Button } from "@/components/ui/button"
import { useConfirm } from "@/components/confirm-provider"
import { Input } from "@/components/ui/input"
import { Separator } from "@/components/ui/separator"
import { getTranslationMemory } from "@/lib/api/translation-memories"
import { deleteTranslationMemorySegment, getTranslationMemorySegments, putTranslationMemorySegment, replaceTranslationMemorySegments, type TranslationMemorySegment } from "@/lib/api/translation-memory-segments"
import { useInfiniteQuery, useMutation, useQuery } from "@tanstack/react-query"
import { useDebounce } from "@uidotdev/usehooks"
import { Bold, ChevronLeft, ChevronRight, Info, Italic, Replace, Subscript, Superscript, Underline } from "lucide-react"
import { omitBy } from "lodash"
import { useCallback, useEffect, useMemo, useReducer, useRef, useState } from "react"
import { useForm, useWatch } from "react-hook-form"
import { useParams } from "react-router"
import { useTranslation } from "react-i18next"
import { Tabs, TabsContent } from "@/components/ui/tabs"
import * as TabsPrimitive from "@radix-ui/react-tabs"
import TranslationMemorySegmentsList from "./translation-memory-segments-list"
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group"
import SegmentEditInput from "../job-translate/job-segment-edit-input"

const filterFormDefaultValues = {
  source: '',
  target: '',
}

type Action = {
  type: 'SET_CURRENT_SEGMENT' | 'EDIT_SEGMENT'
  data: any
}

type State = {
  currentSegment: TranslationMemorySegment | null
  edited: { [key: string]: TranslationMemorySegment }
}

const reducerInitialState: State = {
  currentSegment: null,
  edited: {},
}

const reducer = (state: State, action: Action): State => {
  switch (action.type) {
    case 'SET_CURRENT_SEGMENT':
      return { ...state, currentSegment: action.data }

    case 'EDIT_SEGMENT':
      const segmentId = action.data.id
      return {
        ...state,
        edited: {
          ...state.edited,
          [segmentId]: {
            ...state.edited[segmentId],
            ...action.data,
          },
        },
      }

    default:
      return state
  }
}

const TranslationMemoryEditPage = () => {
  const { t, i18n } = useTranslation()
  const { translation_memory_id } = useParams()
  const [state, dispatch] = useReducer(reducer, reducerInitialState)

  const [showReplace, setShowReplace] = useState(false)
  const [replaceWith, setReplaceWith] = useState('')
  const confirm = useConfirm()

  const filterForm = useForm({ defaultValues: filterFormDefaultValues })
  const filterFormValues = useDebounce(useWatch({ control: filterForm.control }), 300)

  const segmentsQueryParam = useMemo(() => {
    const fn = (value: any) => value === ""
    return omitBy({
      translation_memory_id,
      source: filterFormValues.source,
      target: filterFormValues.target,
      per_page: 500,
    }, fn)
  }, [translation_memory_id, filterFormValues.source, filterFormValues.target])

  const hasActiveFilter = Boolean(filterFormValues.source || filterFormValues.target)

  const translationMemoryQuery = useQuery({
    queryKey: ['translation-memory', translation_memory_id],
    queryFn: getTranslationMemory,
  })

  const segmentsQuery = useInfiniteQuery({
    queryKey: ['tm-segments', segmentsQueryParam],
    queryFn: getTranslationMemorySegments,
    refetchOnWindowFocus: false,
    getNextPageParam: (lastGroup) => {
      const nextPage = lastGroup.meta.current_page + 1
      if (nextPage > lastGroup.meta.last_page) {
        return null
      }
      return nextPage
    },
    initialPageParam: 1,
  })

  const debounceTimersRef = useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map())

  useEffect(() => {
    return () => { debounceTimersRef.current.forEach(clearTimeout) }
  }, [])

  const updateSegmentMutation = useMutation({
    mutationFn: putTranslationMemorySegment,
    onSuccess: () => {
      segmentsQuery.refetch()
    },
  })

  const replaceMutation = useMutation({
    mutationFn: replaceTranslationMemorySegments,
    onSuccess: () => {
      segmentsQuery.refetch()
    },
  })

  const deleteSegmentMutation = useMutation({
    mutationFn: deleteTranslationMemorySegment,
    onSuccess: () => {
      segmentsQuery.refetch()
    },
  })

  const allRows = segmentsQuery.data?.pages.flatMap(p => p.data) ?? []
  const currentMatchIndex = allRows.findIndex(s => s.id === state.currentSegment?.id)

  const handleNextMatch = useCallback(() => {
    if (!allRows.length) return
    const nextIndex = (currentMatchIndex + 1) % allRows.length
    dispatch({ type: 'SET_CURRENT_SEGMENT', data: allRows[nextIndex] })
  }, [allRows, currentMatchIndex])

  const handlePrevMatch = useCallback(() => {
    if (!allRows.length) return
    const prevIndex = (currentMatchIndex - 1 + allRows.length) % allRows.length
    dispatch({ type: 'SET_CURRENT_SEGMENT', data: allRows[prevIndex] })
  }, [allRows, currentMatchIndex])

  const handleReplace = useCallback(() => {
    if (!state.currentSegment || !filterFormValues.target) return
    const segment = state.currentSegment
    const currentTarget = state.edited[segment.id]?.target ?? segment.target
    const newTarget = currentTarget.replaceAll(filterFormValues.target, replaceWith)
    handleSegmentChange(segment, 'target', newTarget)
  }, [state.currentSegment, state.edited, filterFormValues.target, replaceWith])

  const handleReplaceAll = useCallback(() => {
    if (!filterFormValues.target || !translation_memory_id) return
    replaceMutation.mutate({
      translation_memory_id,
      source: filterFormValues.source,
      target: filterFormValues.target,
      replace_target: replaceWith,
    })
  }, [filterFormValues.target, translation_memory_id, replaceWith, replaceMutation])

  const handleSegmentChange = useCallback((segment: TranslationMemorySegment, field: 'source' | 'target', value: string) => {
    dispatch({ type: 'EDIT_SEGMENT', data: { id: segment.id, [field]: value } })

    const timerKey = `${segment.id}:${field}`
    const existing = debounceTimersRef.current.get(timerKey)
    if (existing) clearTimeout(existing)

    const timer = setTimeout(() => {
      updateSegmentMutation.mutate({ id: segment.id, body: { [field]: value } })
      debounceTimersRef.current.delete(timerKey)
    }, 800)

    debounceTimersRef.current.set(timerKey, timer)
  }, [updateSegmentMutation])

  const handleSegmentDelete = useCallback(async (segment: TranslationMemorySegment) => {
    const ok = await confirm({
      title: t('translationMemoryEdit.confirmDeleteTitle'),
      description: t('translationMemoryEdit.confirmDeleteDescription'),
      confirmText: t('translationMemoryEdit.confirmDelete'),
      variant: 'destructive',
      rememberKey: 'tm-segment-delete',
    })
    if (ok) deleteSegmentMutation.mutate(segment.id)
  }, [confirm, deleteSegmentMutation, t])

  const lastPage = (segmentsQuery.data?.pages || []).at(-1)

  const formatDateTime = useCallback((iso: string) =>
    new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)),
    [i18n.language])

  return (
    <div className="h-screen flex flex-col">
      <header className="flex h-16 shrink-0 items-center gap-2 border-b transition-[width,height] ease-linear">
        <div className="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
          <Separator orientation="vertical" className="mx-2 data-[orientation=vertical]:h-4" />
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem className="hidden md:block">
                <BreadcrumbLink target="_blank">
                  {translationMemoryQuery.data?.data.name ?? '...'}
                </BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator className="hidden md:block" />
              <BreadcrumbItem>
                <BreadcrumbPage>{t('translationMemoryEdit.breadcrumbEdit')}</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1 min-h-0">
        <div className="flex-4 flex flex-col min-h-0">

          <div className="flex h-16 shrink-0 items-center gap-2 border-b">
            <div className="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
              <Button variant="ghost" onMouseDown={(e) => e.preventDefault()} onClick={() => document.execCommand('bold')}>
                <Bold />
              </Button>
              <Button variant="ghost" onMouseDown={(e) => e.preventDefault()} onClick={() => document.execCommand('italic')}>
                <Italic />
              </Button>
              <Button variant="ghost" onMouseDown={(e) => e.preventDefault()} onClick={() => document.execCommand('underline')}>
                <Underline />
              </Button>
              <Button variant="ghost" onMouseDown={(e) => e.preventDefault()} onClick={() => document.execCommand('subscript')}>
                <Subscript />
              </Button>
              <Button variant="ghost" onMouseDown={(e) => e.preventDefault()} onClick={() => document.execCommand('superscript')}>
                <Superscript />
              </Button>
            </div>
          </div>

          <div className="flex flex-col border-b shrink-0">
            <div className="flex w-full items-start gap-1 p-4 lg:gap-2 lg:px-6">
              <Input placeholder={t('translationMemoryEdit.filterSourcePlaceholder')} {...filterForm.register('source')} />
              <div className="w-full">
                <InputGroup>
                  <InputGroupInput placeholder={t('translationMemoryEdit.filterTargetPlaceholder')} {...filterForm.register('target')} />
                  <InputGroupAddon align="inline-end">
                    <Button variant={showReplace ? 'secondary' : 'ghost'} size="icon" onClick={() => setShowReplace(v => !v)}>
                      <Replace />
                    </Button>
                  </InputGroupAddon>
                </InputGroup>
                {showReplace && (
                  <div className="flex w-full items-center gap-1 pt-4">
                    <Input
                      placeholder={t('translationMemoryEdit.replaceWithPlaceholder')}
                      value={replaceWith}
                      onChange={(e) => setReplaceWith(e.target.value)}
                    />
                    <Button variant="outline" size="sm" onClick={handleReplace} disabled={!filterFormValues.target}>
                      {t('translationMemoryEdit.replace')}
                    </Button>
                    <Button variant="outline" size="sm" onClick={handleReplaceAll} disabled={!filterFormValues.target || replaceMutation.isPending}>
                      {t('translationMemoryEdit.replaceAll')}
                    </Button>
                    <Button variant="ghost" size="icon" onClick={handlePrevMatch}><ChevronLeft /></Button>
                    <Button variant="ghost" size="icon" onClick={handleNextMatch}><ChevronRight /></Button>
                  </div>
                )}
              </div>
              <Button variant="ghost" onClick={() => {
                filterForm.reset(filterFormDefaultValues)
                setShowReplace(false)
                setReplaceWith('')
              }}>
                {t('translationMemoryEdit.clearFilter')}
              </Button>
            </div>

          </div>

          <TranslationMemorySegmentsList
            editedSegments={state.edited}
            segmentsQuery={segmentsQuery}
            onSegmentClick={(segment) => dispatch({ type: 'SET_CURRENT_SEGMENT', data: segment })}
            onSegmentChange={handleSegmentChange}
            onSegmentDelete={handleSegmentDelete}
            activeSegmentId={state.currentSegment?.id}
            hasActiveFilter={hasActiveFilter}
          />

          <div className="flex border-t shrink-0">
            <div className="flex px-2 py-4 text-sm gap-2 text-ellipsis whitespace-nowrap overflow-hidden">
              <span>{t('translationMemoryEdit.segmentsCount', { count: lastPage?.meta?.total ?? 0 })}</span>
            </div>
          </div>
        </div>

        <Tabs defaultValue="info" className="flex-2 border-l flex flex-row gap-0">
          <TabsContent value="info">
            <div className="h-full flex flex-col">
              <span className="font-bold p-2 border-b">{t('translationMemoryEdit.segmentInfo')}</span>

              {!state.currentSegment ? (
                <div className="flex flex-1 items-center justify-center">
                  <span>{t('translationMemoryEdit.selectSegment')}</span>
                </div>
              ) : (
                <div className="p-3 flex flex-col gap-3 text-sm overflow-y-auto">
                  <div>
                    <div className="text-muted-foreground text-xs mb-1">{t('translationMemoryEdit.fieldId')}</div>
                    <div className="font-mono text-xs break-all">{state.currentSegment.id}</div>
                  </div>
                  <div className="flex gap-4">
                    <div>
                      <div className="text-muted-foreground text-xs mb-1">{t('translationMemoryEdit.fieldSourceChars')}</div>
                      <div>{(state.edited[state.currentSegment.id]?.source ?? state.currentSegment.source).length}</div>
                    </div>
                    <div>
                      <div className="text-muted-foreground text-xs mb-1">{t('translationMemoryEdit.fieldTargetChars')}</div>
                      <div>{(state.edited[state.currentSegment.id]?.target ?? state.currentSegment.target).length}</div>
                    </div>
                  </div>
                  {state.currentSegment.source_context_before && (
                    <div>
                      <div className="text-muted-foreground text-xs mb-1">{t('translationMemoryEdit.fieldSourceContextBefore')}</div>
                      <div className="text-xs text-muted-foreground italic whitespace-break-spaces">
                        <SegmentEditInput readOnly value={state.currentSegment.source_context_before} />
                      </div>
                    </div>
                  )}
                  {state.currentSegment.source_context_after && (
                    <div>
                      <div className="text-muted-foreground text-xs mb-1">{t('translationMemoryEdit.fieldSourceContextAfter')}</div>
                      <div className="text-xs text-muted-foreground italic whitespace-break-spaces">
                        <SegmentEditInput readOnly value={state.currentSegment.source_context_after} />
                      </div>
                    </div>
                  )}
                  {state.currentSegment.target_context_before && (
                    <div>
                      <div className="text-muted-foreground text-xs mb-1">{t('translationMemoryEdit.fieldTargetContextBefore')}</div>
                      <div className="text-xs text-muted-foreground italic whitespace-break-spaces">
                        <SegmentEditInput readOnly value={state.currentSegment.target_context_before} />
                      </div>
                    </div>
                  )}
                  {state.currentSegment.target_context_after && (
                    <div>
                      <div className="text-muted-foreground text-xs mb-1">{t('translationMemoryEdit.fieldTargetContextAfter')}</div>
                      <div className="text-xs text-muted-foreground italic whitespace-break-spaces">
                        <SegmentEditInput readOnly value={state.currentSegment.target_context_after} />
                      </div>
                    </div>
                  )}
                  <div>
                    <div className="text-muted-foreground text-xs mb-1">{t('translationMemoryEdit.fieldCreated')}</div>
                    <div className="text-xs">{formatDateTime(state.currentSegment.created_at)}</div>
                  </div>
                  <div>
                    <div className="text-muted-foreground text-xs mb-1">{t('translationMemoryEdit.fieldUpdated')}</div>
                    <div className="text-xs">{formatDateTime(state.currentSegment.updated_at)}</div>
                  </div>
                </div>
              )}
            </div>
          </TabsContent>

          <TabsPrimitive.List
            data-slot="tabs-list"
            className="text-muted-foreground w-fit border-l"
          >
            <div className="flex flex-col">
              <TabsPrimitive.Trigger
                data-slot="tabs-trigger"
                className="data-[state=active]:bg-background dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground inline-flex size-16 items-center justify-center gap-1.5 border border-transparent text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:shadow-sm [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
                value="info"
              >
                <Info />
              </TabsPrimitive.Trigger>
            </div>
          </TabsPrimitive.List>
        </Tabs>
      </div>
    </div>
  )
}

export default TranslationMemoryEditPage
