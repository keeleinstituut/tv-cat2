import { Table, TableBody, TableCell, TableRow } from "@/components/ui/table"
import type { Suggestion } from "@/lib/api/suggestions"
import { map } from "lodash"
import { useEffect, useState } from "react"
import { useTranslation } from "react-i18next"
import SegmentEditInput from "./job-segment-edit-input"
import SuggestionBadge from "./suggestion-badge"

const SuggestionsList = (props: { data: Suggestion[], onSelect?: (data: Suggestion) => void }) => {
  const { t } = useTranslation()
  const { data, onSelect } = props
  const [selectedSuggestion, setSelectedSuggestion] = useState<Suggestion | null>(null)

  useEffect(() => {
    setSelectedSuggestion(null)
  }, [data])

  return (
    <div className="flex flex-col flex-1">
      <div className="flex-1 overflow-y-auto no-scrollbar [contain:strict]">
        {map(data, (suggestion, i) => (
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
            {selectedSuggestion?.provider.type == 'TM' && (
              <>
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
              </>
            )}
          </TableBody>
        </Table>
      )}
    </div>
  )
}

export default SuggestionsList