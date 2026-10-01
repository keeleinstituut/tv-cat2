import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb"
import { Button } from "@/components/ui/button"
import { Separator } from "@/components/ui/separator"
import { getJob } from "@/lib/api/jobs"
import { useInfiniteQuery, useMutation, useQuery } from "@tanstack/react-query"
import { useParams } from "react-router"
import { Bold, Italic, Underline, Subscript, Superscript, Cat, TextSearch, SlidersHorizontal } from "lucide-react"
import { Tooltip, TooltipTrigger, TooltipContent } from "@/components/ui/tooltip"
import { Input } from "@/components/ui/input"
import { getSegments, putSegment, type Segment } from "@/lib/api/segments"
import { useCallback, useEffect, useMemo, useReducer, useRef, useState } from "react"
import { omitBy } from "lodash"
import { useDebounce } from "@uidotdev/usehooks"
import { useForm, useWatch } from "react-hook-form"
import { Tabs, TabsContent } from "@/components/ui/tabs"
import * as TabsPrimitive from "@radix-ui/react-tabs"
import { getJobSuggestions } from "@/lib/api/suggestions"
import { useTranslation } from "react-i18next"
import JobSegmentsList from "./job-segments-list"
import SuggestionsList from "./job-suggestions-list"
import JobTranslateFilterPanel, { type AdvancedFilterState, advancedFilterDefaultState } from "./job-translate-filter-panel"

const filterFormDefaultValues = {
  source: '',
  target: '',
}

const concordanceSearchFormDefaultValues = {
  source: '',
}


type Action = {
  type: 'SET_CURRENT_SEGMENT' | 'EDIT_SEGMENT'
  data: any
}

type State = {
  currentSegment: null | Segment

  edited: {
    [key: string]: Segment
  },
}

const reducerInitialState: State = {
  currentSegment: null,
  edited: {}
}

const reducer = (state: State, action: Action): State => {
  console.log(action)
  switch (action.type) {
    case 'SET_CURRENT_SEGMENT':
      return {
        ...state,
        currentSegment: action.data.segment,
      }

    case 'EDIT_SEGMENT':
      const segmentId = action.data.id

      return {
        ...state,
        edited: {
          ...state.edited,
          [segmentId]: {
            ...state.edited[segmentId],
            ...action.data,
          }
        }
      }

    default:
      return state
  }
}

const JobTranslatePage = () => {
  const { t } = useTranslation()
  const { job_id } = useParams()
  const [state, dispatch] = useReducer(reducer, reducerInitialState)
  const [isFilterPanelOpen, setIsFilterPanelOpen] = useState(false)
  const [advancedFilters, setAdvancedFilters] = useState<AdvancedFilterState>(advancedFilterDefaultState)
  const [activeRightTab, setActiveRightTab] = useState('cat')
  const [showHiddenChars] = useState(false)
  const [isOverwriteMode] = useState(false)

  const concordanceSearchForm = useForm({
    defaultValues: concordanceSearchFormDefaultValues,
  })

  const concordanceSearchFormValues = useDebounce(useWatch({ control: concordanceSearchForm.control }), 300)

  const filterForm = useForm({
    defaultValues: filterFormDefaultValues,
  })

  const filterFormValues = useDebounce(useWatch({ control: filterForm.control }), 300)

  const activeAdvancedFilterCount = useMemo(() => {
    const s = Object.values(advancedFilters.segmentStatus).filter(Boolean).length
    const p = Object.values(advancedFilters.pretranslated).filter(Boolean).length
    const o = advancedFilters.sort !== 'none' ? 1 : 0
    return s + p + o
  }, [advancedFilters])

  const segmentsQueryParam = useMemo(() => {
    const fn = (value: any) => value === "" || value === undefined

    const advancedParams: Record<string, any> = {}
    const { segmentStatus, pretranslated, sort } = advancedFilters
    if (segmentStatus.empty) advancedParams.filter_empty = 1
    if (segmentStatus.not_empty) advancedParams.filter_not_empty = 1
    if (segmentStatus.first_repetition) advancedParams.filter_first_repetition = 1
    if (pretranslated.score_101) advancedParams.filter_score_101 = 1
    if (pretranslated.score_100) advancedParams.filter_score_100 = 1
    if (pretranslated.score_99) advancedParams.filter_score_99 = 1
    if (pretranslated.fuzzy) advancedParams.filter_fuzzy = 1
    if (pretranslated.tm) advancedParams.filter_tm = 1
    if (pretranslated.nt) advancedParams.filter_nt = 1
    if (pretranslated.mt) advancedParams.filter_mt = 1
    if (pretranslated.no_match) advancedParams.filter_no_match = 1
    if (sort !== 'none') advancedParams.sort = sort

    return omitBy({
      job_id,
      source: filterFormValues.source,
      target: filterFormValues.target,
      per_page: 500,
      ...advancedParams,
    }, fn)
  }, [job_id, filterFormValues.source, filterFormValues.target, advancedFilters])

  const suggestionsQueryParam = useMemo(() => {
    const fn = (value: any) => value === "" || value === null || value === undefined

    return omitBy({
      segment_id: state.currentSegment?.id,
    }, fn)
  }, [state.currentSegment])

  const concordanceSearchSuggestionsQueryParams = useMemo(() => {
    return {
      q: concordanceSearchFormValues.source,
      providers: ['tm']
    }
  }, [concordanceSearchFormValues])

  const jobQuery = useQuery({
    queryKey: ['job', job_id],
    queryFn: getJob
  })

  const suggestionsQuery = useQuery({
    queryKey: ['job_suggestions', job_id, suggestionsQueryParam],
    queryFn: getJobSuggestions,
    enabled: !!state.currentSegment,
  })

  const concordanceSearchSuggestionsQuery = useQuery({
    queryKey: ['job_suggestions', job_id, concordanceSearchSuggestionsQueryParams],
    queryFn: getJobSuggestions,
    enabled: !!concordanceSearchFormValues.source,
  })

  const debounceTimersRef = useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map())

  useEffect(() => {
    return () => { debounceTimersRef.current.forEach(clearTimeout) }
  }, [])

  const updateSegmentMutation = useMutation({
    mutationFn: putSegment,
    onSuccess: () => {
      segmentsQuery.refetch()
    }
  })

  const handleSegmentChange = useCallback((segment: Segment, data: Partial<Pick<Segment, "target" | "confirmed">>) => {
    dispatch({ type: 'EDIT_SEGMENT', data: { id: segment.id, ...data } })

    const existing = debounceTimersRef.current.get(segment.id)
    if (existing) clearTimeout(existing)

    const timer = setTimeout(() => {
      updateSegmentMutation.mutate({ id: segment.id, body: { ...data } })
      debounceTimersRef.current.delete(segment.id)
    }, 800)

    debounceTimersRef.current.set(segment.id, timer)
  }, [updateSegmentMutation])

  const segmentsQuery = useInfiniteQuery({
    queryKey: ['segments', segmentsQueryParam],
    queryFn: getSegments,
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

  const selectedSegmentMeta = useMemo(() => {
    if (!state.currentSegment) {
      return null
    }

    return {
      source_char_count: state.currentSegment?.source?.length || 0,
      target_char_count: state.edited[state.currentSegment?.id]?.target?.length || state.currentSegment?.target?.length || 0,
    }
  }, [state.currentSegment, segmentsQuery])

  return (
    <div className="h-screen flex flex-col">
      <header className="flex h-16 shrink-0 items-center gap-2 border-b transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-16">
        <div className="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
          <Separator
            orientation="vertical"
            className="mx-2 data-[orientation=vertical]:h-4"
          />
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem className="hidden md:block">
                <BreadcrumbLink href="#">
                  {jobQuery.data?.data.project.name}
                </BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator className="hidden md:block" />
              <BreadcrumbItem>
                <BreadcrumbPage>{jobQuery.data?.data.xliff_file?.file_name}</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
      </header>

      <div className="flex flex-1">
        <div className="flex-4 flex flex-col">
          <div className="flex shrink-0 py-3 items-center border-b transition-[width,height] ease-linear">
            <div className="flex flex-wrap w-full items-center px-4 lg:px-6">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button size="icon" variant="ghost" onMouseDown={(e) => e.preventDefault()} onClick={() => document.execCommand('bold')}>
                    <Bold />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t('jobTranslate.toolbarBold')}</TooltipContent>
              </Tooltip>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button size="icon" variant="ghost" onMouseDown={(e) => e.preventDefault()} onClick={() => document.execCommand('italic')}>
                    <Italic />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t('jobTranslate.toolbarItalic')}</TooltipContent>
              </Tooltip>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button size="icon" variant="ghost" onMouseDown={(e) => e.preventDefault()} onClick={() => document.execCommand('underline')}>
                    <Underline />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t('jobTranslate.toolbarUnderline')}</TooltipContent>
              </Tooltip>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button size="icon" variant="ghost" onMouseDown={(e) => e.preventDefault()} onClick={() => document.execCommand('subscript')}>
                    <Subscript />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t('jobTranslate.toolbarSubscript')}</TooltipContent>
              </Tooltip>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button size="icon" variant="ghost" onMouseDown={(e) => e.preventDefault()} onClick={() => document.execCommand('superscript')}>
                    <Superscript />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t('jobTranslate.toolbarSuperscript')}</TooltipContent>
              </Tooltip>

            </div>
          </div>

          <div className="flex w-full items-center gap-1 p-4 lg:gap-2 lg:px-6 border-b">
            <Button
              variant={isFilterPanelOpen || activeAdvancedFilterCount > 0 ? "secondary" : "ghost"}
              size="icon"
              className="relative ml-auto"
              onClick={() => setIsFilterPanelOpen(v => !v)}
            >
              <SlidersHorizontal />
              {activeAdvancedFilterCount > 0 && (
                <span className="absolute -top-1 -right-1 size-4 rounded-full bg-primary text-primary-foreground text-[10px] flex items-center justify-center">
                  {activeAdvancedFilterCount}
                </span>
              )}
            </Button>
            <Input placeholder={t('jobTranslate.filterBarSourcePlaceholder')} {...filterForm.register('source')} />
            <Input placeholder={t('jobTranslate.filterBarTargetPlaceholder')} {...filterForm.register('target')} />
            <Button variant="ghost" onClick={() => {
              filterForm.reset(filterFormDefaultValues)
              setAdvancedFilters(advancedFilterDefaultState)
            }}>
              {t('jobTranslate.filterBarClear')}
            </Button>
          </div>

          {isFilterPanelOpen && (
            <JobTranslateFilterPanel
              filters={advancedFilters}
              onFiltersChange={setAdvancedFilters}
            />
          )}

          <JobSegmentsList
            editedSegments={state.edited}
            segmentsQuery={segmentsQuery}
            currentSegmentId={state.currentSegment?.id ?? null}
            onSegmentClick={(segment) => dispatch({
              type: 'SET_CURRENT_SEGMENT',
              data: { segment },
            })}
            onSegmentChange={handleSegmentChange}
            showHiddenChars={showHiddenChars}
            isOverwriteMode={isOverwriteMode}
          />

          <div className="flex border-t">
            <div className="float-left flex px-2 py-4 text-sm gap-2 text-ellipsis whitespace-nowrap overflow-hidden w-fit">
              <span className="text-blue-400 font-medium">{t('jobTranslate.statusActiveLabel')}</span>
              {!state.currentSegment ? (
                <span>{t('jobTranslate.statusClickOnSegment')}</span>
              ) : (
                <>
                  <span>{t('jobTranslate.statusCharsLabel')}</span>
                  <span>{selectedSegmentMeta?.source_char_count} / {selectedSegmentMeta?.target_char_count}</span>
                </>
              )}
            </div>
          </div>
        </div>

        <Tabs value={activeRightTab} onValueChange={setActiveRightTab} className="flex-2 border-l flex flex-row gap-0">
          <TabsContent value="cat">
            <div className="h-full flex flex-col">
              <span className="font-bold p-2 border-b">{t('jobTranslate.rightPanelCatTab')}</span>

              {!state.currentSegment ? (
                <div className="flex flex-1 items-center justify-center">
                  <span>{t('jobTranslate.rightPanelSelectSegment')}</span>
                </div>
              ) : (
                <SuggestionsList
                  data={suggestionsQuery.data?.data || []}
                  onSelect={(suggestion) => {
                    if (state.currentSegment) {
                      handleSegmentChange(state.currentSegment, { target: suggestion.target })
                    }
                  }}
                />
              )}
            </div>
          </TabsContent>
          <TabsContent value="text_search">
            <div className="h-full flex flex-col">
              <span className="font-bold p-2 border-b">{t('jobTranslate.rightPanelConcordanceSearch')}</span>
              <div className="p-2">
                <Input
                  placeholder={t('jobTranslate.rightPanelSearchPlaceholder')}
                  {...concordanceSearchForm.register('source')}
                />
              </div>
              {!concordanceSearchSuggestionsQuery.data ? (
                <div className="flex flex-1 items-center justify-center">
                  <span>{t('jobTranslate.rightPanelEnterTermsToSearch')}</span>
                </div>
              ) : (
                <SuggestionsList data={concordanceSearchSuggestionsQuery.data.data} />
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
                value="cat"
              >
                <Cat />
              </TabsPrimitive.Trigger>
              <TabsPrimitive.Trigger
                data-slot="tabs-trigger"
                className="data-[state=active]:bg-background dark:data-[state=active]:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring dark:data-[state=active]:border-input dark:data-[state=active]:bg-input/30 text-foreground dark:text-muted-foreground inline-flex size-16 items-center justify-center gap-1.5 border border-transparent text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:shadow-sm [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
                value="text_search"
              >
                <TextSearch />
              </TabsPrimitive.Trigger>
            </div>
          </TabsPrimitive.List>
        </Tabs>
      </div>

    </div>
  )
}

export default JobTranslatePage


