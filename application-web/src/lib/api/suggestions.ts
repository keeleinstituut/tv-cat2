import { isNil, omitBy } from 'lodash'
import apiClient from '../clients/apiClient'
import type { DataResponse } from './projects'

export type Suggestion = {
  provider: {
    type: string
    name: string
  },
  source: string
  target: string
  score: number | null
  meta?: {
    source_context_before?: string
    source_context_after?: string
    target_context_before?: string
    target_context_after?: string
  }
}

export const getJobSuggestions = async ({ queryKey }: { queryKey: any[] }) => {
  const [_, job_id, params] = queryKey
  return (await apiClient.get<DataResponse<Suggestion[]>>(`/api/jobs/${job_id}/suggestions`, {
    params: omitBy(params, isNil)
  })).data
}
