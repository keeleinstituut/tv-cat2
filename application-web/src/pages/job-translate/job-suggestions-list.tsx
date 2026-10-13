import { Skeleton } from "@/components/ui/skeleton"
import { Table,TableBody, TableCell, TableRow } from "@/components/ui/table"
import type { Suggestion } from "@/lib/api/suggestions"
import { diffWords } from "diff"
import { map } from "lodash"
import { useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import { plainText } from "@/lib/segment-text"
import SegmentEditInput from "./job-segment-edit-input"
import SuggestionBadge from "./suggestion-badge"

// The text a person reads, so the diff does not show tags or entities
const stripTags = (html: string) => plainText(html)

type SuggestionsListProps = {
  data: Suggestion[]
  query?: string
  onSelect?: (data: Suggestion) => void
  isLoading: boolean
  emptyText: string
}

const SuggestionsList = (props: SuggestionsListProps) => {
  const { t, i18n } = useTranslation()
  const { data, query, onSelect, isLoading, emptyText } = props
  const formatDateTime = (iso: string) =>
    new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
  const [selectedSuggestion, setSelectedSuggestion] = useState<Suggestion | null>(null)

  const sourceDiff = useMemo(() => {
    if (!query || !selectedSuggestion) return null
    const parts = diffWords(stripTags(query), stripTags(selectedSuggestion.source))
    return parts.some((part) => part.added || part.removed) ? parts : null
  }, [query, selectedSuggestion])

  useEffect(() => {
    setSelectedSuggestion(null)
  }, [data])

  return (
    <div className="flex flex-col flex-1">
      <div className="flex-1 overflow-y-auto no-scrollbar [contain:strict]">
        {isLoading && Array.from({ length: 8 }).map((_, i) => (
          <div key={i} className="flex flex-row border-b text-xs">
            <span className="w-8 pt-2 flex justify-center"><Skeleton className="h-4 w-4" /></span>
            <div className="flex-1 p-2"><Skeleton className="h-4 w-full" /></div>
            <span className="w-10 p-2"><Skeleton className="h-4 w-full" /></span>
            <div className="flex-1 p-2"><Skeleton className="h-4 w-full" /></div>
          </div>
        ))}
        {!isLoading && data.length === 0 && (
          <div className="h-full flex items-center justify-center">
            <span className="text-muted-foreground text-sm">{emptyText}</span>
          </div>
        )}
        {!isLoading && map(data, (suggestion, i) => (
          <div
            key={i}
            className="flex flex-row border-b text-xs"
            onClick={(e) => {
              if (e.detail == 1) {
                setSelectedSuggestion(suggestion)
              } else if (e.detail == 2 && onSelect) {
                onSelect(suggestion)
              }
            }}
          >
            <span className="w-8 pt-2 flex justify-center">{i + 1}</span>
            <SegmentEditInput readOnly value={suggestion.source} className="flex-1 p-2 whitespace-break-spaces" />
            <SuggestionBadge providerType={suggestion.provider.type} score={suggestion.score} />
            <SegmentEditInput readOnly value={suggestion.target} className="flex-1 p-2 whitespace-break-spaces" />
          </div>
        ))}
      </div>
      {!!selectedSuggestion && (
        <Table className="border-t">
          <TableBody>
            <TableRow>
              <TableCell>{t('jobTranslate.suggestionProviderLabel')}: </TableCell>
              <TableCell>{selectedSuggestion?.provider.type}</TableCell>
            </TableRow>
            <TableRow>
              <TableCell>{t('jobTranslate.suggestionNameLabel')}: </TableCell>
              <TableCell className="whitespace-break-spaces">{selectedSuggestion?.provider.name}</TableCell>
            </TableRow>
            {selectedSuggestion?.provider.type == 'TB' && (
              <>
                {([
                  ['suggestionDatasetLabel', selectedSuggestion.meta?.dataset],
                  ['suggestionDefinitionLabel', selectedSuggestion.meta?.definition],
                  ['suggestionNoteLabel', selectedSuggestion.meta?.note],
                  ['suggestionSentenceLabel', selectedSuggestion.meta?.sentence],
                ] as const).map(([label, value]) => !!value && (
                  <TableRow key={label}>
                    <TableCell>{t(`jobTranslate.${label}`)}: </TableCell>
                    <TableCell className="whitespace-break-spaces">{value}</TableCell>
                  </TableRow>
                ))}
              </>
            )}
            {selectedSuggestion?.provider.type == 'TM' && (
              <>
                {!!sourceDiff && (
                  <TableRow>
                    <TableCell>{t('jobTranslate.suggestionDiffLabel')}: </TableCell>
                    <TableCell className="whitespace-break-spaces">
                      {map(sourceDiff, (part, i) => (
                        <span
                          key={i}
                          className={part.added ? 'bg-emerald-200 dark:bg-emerald-900' : part.removed ? 'bg-red-200 dark:bg-red-900 line-through' : undefined}
                        >
                          {part.value}
                        </span>
                      ))}
                    </TableCell>
                  </TableRow>
                )}
                <TableRow>
                  <TableCell>{t('jobTranslate.suggestionSourceContextBeforeLabel')}: </TableCell>
                  <TableCell className="whitespace-break-spaces">
                    <SegmentEditInput readOnly value={selectedSuggestion?.meta?.source_context_before} />
                  </TableCell>
                </TableRow>
                <TableRow>
                  <TableCell>{t('jobTranslate.suggestionSourceContextAfterLabel')}: </TableCell>
                  <TableCell className="whitespace-break-spaces">
                    <SegmentEditInput readOnly value={selectedSuggestion?.meta?.source_context_after} />
                  </TableCell>
                </TableRow>
                {!!selectedSuggestion.created_at && (
                  <TableRow>
                    <TableCell>{t('jobTranslate.suggestionCreatedLabel')}: </TableCell>
                    <TableCell>{formatDateTime(selectedSuggestion.created_at)}</TableCell>
                  </TableRow>
                )}
                {!!selectedSuggestion.updated_at && (
                  <TableRow>
                    <TableCell>{t('jobTranslate.suggestionUpdatedLabel')}: </TableCell>
                    <TableCell>{formatDateTime(selectedSuggestion.updated_at)}</TableCell>
                  </TableRow>
                )}
              </>
            )}
          </TableBody>
        </Table>
      )}
    </div>
  )
}

export default SuggestionsList