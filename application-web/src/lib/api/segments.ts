import { isNil, omitBy } from 'lodash'
import apiClient from '../clients/apiClient'
import type { DataPaginationResponse, DataResponse } from './projects'

export type Segment = {
  id: string
  job_id: string
  source: string
  target: string
  confirmed: boolean
  position: number
  created_at: string
  updated_at: string
  repetition_group: string
  pretranslate_suggestion_provider_type: string | null
  pretranslate_suggestion_score: number | null
}

export type SegmentPutPayload = {
  id: string
  body: {
    target?: string
    confirmed?: boolean
  }
}

export const getSegments = async ({ queryKey, pageParam }: { queryKey: any[], pageParam: any}) => {
  const [_, params] = queryKey
  return (await apiClient.get<DataPaginationResponse<Segment[]>>('/api/segments', {
    params: omitBy({
      ...params,
      page: params.page || pageParam
    }, isNil)
  })).data
}

export const putSegment = async (payload: SegmentPutPayload) => {
  return (await apiClient.put<DataResponse<Segment>>(`/api/segments/${payload.id}`, payload.body)).data
}
