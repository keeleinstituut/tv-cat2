import { Checkbox } from "@/components/ui/checkbox"
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible"
import { ChevronDown, ChevronRight } from "lucide-react"
import { useState } from "react"
import { useTranslation } from "react-i18next"

export type SegmentStatusFilter = {
  empty: boolean
  not_empty: boolean
  first_repetition: boolean
}

export type PretranslatedFilter = {
  score_101: boolean
  score_100: boolean
  score_99: boolean
  fuzzy: boolean
  tm: boolean
  nt: boolean
  mt: boolean
  no_match: boolean
}

export type SortKey =
  | 'none'
  | 'source_asc'
  | 'source_desc'
  | 'shortest'
  | 'longest'
  | 'match_asc'
  | 'match_desc'

export type AdvancedFilterState = {
  segmentStatus: SegmentStatusFilter
  pretranslated: PretranslatedFilter
  sort: SortKey
}

export const advancedFilterDefaultState: AdvancedFilterState = {
  segmentStatus: { empty: false, not_empty: false, first_repetition: false },
  pretranslated: { score_101: false, score_100: false, score_99: false, fuzzy: false, tm: false, nt: false, mt: false, no_match: false },
  sort: 'none',
}

type FilterPanelProps = {
  filters: AdvancedFilterState
  onFiltersChange: (filters: AdvancedFilterState) => void
}

interface CheckboxItemProps {
  disabled?: boolean
  label: string
  checked?: boolean
  onCheckedChange?: () => void
}

function CheckboxItem(props: CheckboxItemProps) {
  const { disabled, label, checked, onCheckedChange } = props

  if (disabled) {
    return (
      <div className="flex items-center gap-1.5 opacity-40 cursor-not-allowed select-none">
        <Checkbox disabled />
        <span className="text-xs">{label}</span>
      </div>
    )
  }

  return (
    <label className="flex items-center gap-1.5 cursor-pointer">
      <Checkbox
        checked={checked}
        onCheckedChange={onCheckedChange}
      />
      <span className="text-xs">{label}</span>
    </label>
  )
}

interface RadioButtonItemProps {
  disabled?: boolean
  label: string
  checked?: boolean
  onCheckedChange?: () => void
}

function RadioButtonItem(props: RadioButtonItemProps) {
  const { disabled, label, checked, onCheckedChange, } = props

  if (disabled) {
    return (
      <label className="flex items-center gap-1.5 opacity-40 cursor-not-allowed select-none">
        <input type="radio" disabled />
        <span className="text-xs">{label}</span>
      </label>
    )
  }

  return (
    <label className="flex items-center gap-1.5 cursor-pointer">
      <input
        type="radio"
        checked={checked}
        onChange={onCheckedChange}
        className="accent-primary"
      />
      <span className="text-xs">{label}</span>
    </label>
  )
}

function SectionHeader({ label, count, open }: { label: string; count: number; open: boolean }) {
  const backgroundColor = count === 0 ? 'bg-muted text-muted-foreground' : 'bg-primary text-primary-foreground'

  return (
    <div className="flex items-center gap-2 py-1.5 px-4 text-sm font-medium cursor-pointer select-none">
      {open ? <ChevronDown className="size-3.5 shrink-0" /> : <ChevronRight className="size-3.5 shrink-0" />}
      <span>{label}</span>
      <span className={`size-4 rounded-full ${backgroundColor} text-[10px] flex items-center justify-center font-normal`}>
        {count}
      </span>
    </div>
  )
}

const JobTranslateFilterPanel = ({ filters, onFiltersChange }: FilterPanelProps) => {
  const { t } = useTranslation()
  const [statusOpen, setStatusOpen] = useState(false)
  const [pretranslatedOpen, setPretranslatedOpen] = useState(false)
  const [sortOpen, setSortOpen] = useState(false)

  const toggleStatus = (key: keyof SegmentStatusFilter) => {
    onFiltersChange({
      ...filters,
      segmentStatus: { ...filters.segmentStatus, [key]: !filters.segmentStatus[key] },
    })
  }

  const togglePretranslated = (key: keyof PretranslatedFilter) => {
    onFiltersChange({
      ...filters,
      pretranslated: { ...filters.pretranslated, [key]: !filters.pretranslated[key] },
    })
  }

  const setSort = (sort: SortKey) => {
    onFiltersChange({ ...filters, sort })
  }

  const statusCount = Object.values(filters.segmentStatus).filter(Boolean).length
  const pretranslatedCount = Object.values(filters.pretranslated).filter(Boolean).length
  const sortCount = filters.sort !== 'none' ? 1 : 0

  return (
    <div className="border-b bg-background overflow-y-auto max-h-[60vh]">
      {/* Segment status */}
      <Collapsible open={statusOpen} onOpenChange={setStatusOpen}>
        <CollapsibleTrigger asChild>
          <div>
            <SectionHeader label={t('jobTranslate.filterSegmentStatusHeader')} count={statusCount} open={statusOpen} />
          </div>
        </CollapsibleTrigger>
        <CollapsibleContent>
          <div className="flex flex-wrap px-4 pb-3 gap-x-6 gap-y-1.5">
            <div className="w-[150px] flex flex-col">
              <CheckboxItem label={t('jobTranslate.filterStatusEmpty')} checked={filters.segmentStatus.empty} onCheckedChange={() => toggleStatus('empty')} />
              <CheckboxItem label={t('jobTranslate.filterStatusNotEmpty')} checked={filters.segmentStatus.not_empty} onCheckedChange={() => toggleStatus('not_empty')} />
            </div>
            <div className="w-[150px] flex flex-col">
              <CheckboxItem label={t('jobTranslate.filterStatusFirstRepetition')} checked={filters.segmentStatus.first_repetition} onCheckedChange={() => toggleStatus('first_repetition')} />
            </div>
          </div>
        </CollapsibleContent>
      </Collapsible>

      {/* Pretranslated from */}
      <Collapsible open={pretranslatedOpen} onOpenChange={setPretranslatedOpen}>
        <CollapsibleTrigger asChild>
          <div>
            <SectionHeader label={t('jobTranslate.filterPretranslatedHeader')} count={pretranslatedCount} open={pretranslatedOpen} />
          </div>
        </CollapsibleTrigger>
        <CollapsibleContent>
          <div className="px-4 pb-3 flex flex-wrap gap-x-6 gap-y-1.5">
            <CheckboxItem label={t('jobTranslate.filterScore101')} checked={filters.pretranslated['score_101']} onCheckedChange={() => togglePretranslated('score_101')} />
            <CheckboxItem label={t('jobTranslate.filterScore100')} checked={filters.pretranslated['score_100']} onCheckedChange={() => togglePretranslated('score_100')} />
            <CheckboxItem label={t('jobTranslate.filterScore99')} checked={filters.pretranslated['score_99']} onCheckedChange={() => togglePretranslated('score_99')} />
            <CheckboxItem label={t('jobTranslate.filterFuzzy')} checked={filters.pretranslated['fuzzy']} onCheckedChange={() => togglePretranslated('fuzzy')} />
            {/* <CheckboxItem label={t('jobTranslate.filterTm')} checked={filters.pretranslated['tm']} onCheckedChange={() => togglePretranslated('tm')} /> */}
            {/* <CheckboxItem label={t('jobTranslate.filterNt')} checked={filters.pretranslated['nt']} onCheckedChange={() => togglePretranslated('nt')} /> */}
            {/* <CheckboxItem label={t('jobTranslate.filterMt')} checked={filters.pretranslated['mt']} onCheckedChange={() => togglePretranslated('mt')} /> */}
            <CheckboxItem label={t('jobTranslate.filterNoMatch')} checked={filters.pretranslated['no_match']} onCheckedChange={() => togglePretranslated('no_match')} />
          </div>
        </CollapsibleContent>
      </Collapsible>

      {/* Sort */}
      <Collapsible open={sortOpen} onOpenChange={setSortOpen}>
        <CollapsibleTrigger asChild>
          <div>
            <SectionHeader label={t('jobTranslate.filterSortHeader')} count={sortCount} open={sortOpen} />
          </div>
        </CollapsibleTrigger>
        <CollapsibleContent>
          <div className="flex flex-wrap px-4 pb-3 gap-x-6 gap-y-1.5">
            <div className="w-[150px] flex flex-col">
              <RadioButtonItem label={t('jobTranslate.filterSortNone')} checked={filters.sort === 'none'} onCheckedChange={() => setSort('none')} />
            </div>
            <div className="w-[150px] flex flex-col">
              <RadioButtonItem label={t('jobTranslate.filterSortSourceAsc')} checked={filters.sort === 'source_asc'} onCheckedChange={() => setSort('source_asc')} />
              <RadioButtonItem label={t('jobTranslate.filterSortSourceDesc')} checked={filters.sort === 'source_desc'} onCheckedChange={() => setSort('source_desc')} />
            </div>
            <div className="w-[150px] flex flex-col">
              <RadioButtonItem label={t('jobTranslate.filterSortShortest')} checked={filters.sort === 'shortest'} onCheckedChange={() => setSort('shortest')} />
              <RadioButtonItem label={t('jobTranslate.filterSortLongest')} checked={filters.sort === 'longest'} onCheckedChange={() => setSort('longest')} />
            </div>
            <div className="w-[150px] flex flex-col">
              <RadioButtonItem label={t('jobTranslate.filterSortMatchDesc')} checked={filters.sort === 'match_desc'} onCheckedChange={() => setSort('match_desc')} />
              <RadioButtonItem label={t('jobTranslate.filterSortMatchAsc')} checked={filters.sort === 'match_asc'} onCheckedChange={() => setSort('match_asc')} />
            </div>
          </div>
        </CollapsibleContent>
      </Collapsible>
    </div>
  )
}

export default JobTranslateFilterPanel
