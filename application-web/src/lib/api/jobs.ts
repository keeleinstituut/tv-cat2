import apiClient from '../clients/apiClient'
import type { DataResponse, Project } from './projects'

export type Job = {
  id: string
  project_id: string
  target_locale: string
  status: string
  confirmed: string
  created_at: string
  updated_at: string

  project: Project
  source_file: File
  xliff_file: File
}

export type File = {
  file_name: string
  mime_type: string
  size: number
}

export const getJob = async ({ queryKey }: { queryKey: any[] }) => {
  const [_, jobId ] = queryKey
  return (await apiClient.get<DataResponse<Job>>(`/api/jobs/${jobId}`)).data
}