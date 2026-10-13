import apiClient from '../clients/apiClient'
import type { DataResponse } from './projects'

export type EkilexDataset = {
  id: string
  name: string
}

export const getEkilexDatasets = async () => {
  return (await apiClient.get<DataResponse<EkilexDataset[]>>('/api/ekilex/datasets')).data
}
