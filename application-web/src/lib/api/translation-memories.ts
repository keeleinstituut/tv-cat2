import apiClient from '../clients/apiClient'
import type { DataResponse } from './projects'


export type TranslationMemory = {
  id: string
  name: string
  source_locale: string
  target_locale: string
  created_at: string
  updated_at: string
  segment_count?: number
}

export type TranslationMemorySingleFetchAdditionalAttributes = {
  segment_count: number
}

export const getTranslationMemory = async ({ queryKey }: { queryKey: any[] }) => {
  const [_, translationMemoryId ] = queryKey
  return (await apiClient.get<DataResponse<TranslationMemory, TranslationMemorySingleFetchAdditionalAttributes>>(`/api/translation-memories/${translationMemoryId}`)).data
}
