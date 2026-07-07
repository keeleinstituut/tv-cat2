import { isNil, omitBy } from 'lodash'
import apiClient from '../clients/apiClient'
import type { DataResponse } from './projects'

export const getAuthUser = async ({ queryKey }: { queryKey: any[] }) => {
  const [_, params] = queryKey
  return (await apiClient.get<DataResponse<any>>(`/api/auth/user`, {
    params: omitBy(params, isNil)
  })).data
}
