import { isNil, omitBy } from 'lodash'
import apiClient from '../clients/apiClient'
import type { DataPaginationResponse, DataResponse } from './projects'

export type TranslationMemorySegment = {
  id: string
  translation_memory_id: string
  source: string
  target: string
  source_context_before: string | null
  source_context_after: string | null
  target_context_before: string | null
  target_context_after: string | null
  created_at: string
  updated_at: string
}

export type TranslationMemorySegmentPutPayload = {
  id: string
  body: { target: string }
}

export const getTranslationMemorySegments = async ({ queryKey, pageParam }: { queryKey: any[], pageParam?: number }) => {
  const [_, params] = queryKey
  return (await apiClient.get<DataPaginationResponse<TranslationMemorySegment[]>>('/api/translation-memory-segments', {
    params: omitBy({
      ...params,
      page: params.page || pageParam,
    }, isNil)
  })).data
}

export const putTranslationMemorySegment = async (payload: TranslationMemorySegmentPutPayload) => {
  return (await apiClient.put<DataResponse<TranslationMemorySegment>>(`/api/translation-memory-segments/${payload.id}`, payload.body)).data
}

export type TranslationMemorySegmentReplacePayload = {
  translation_memory_id: string
  source?: string
  target: string
  replace_target: string
}

export const replaceTranslationMemorySegments = async (payload: TranslationMemorySegmentReplacePayload) => {
  return (await apiClient.put<{ count: number }>('/api/translation-memory-segments/replace', payload)).data
}
