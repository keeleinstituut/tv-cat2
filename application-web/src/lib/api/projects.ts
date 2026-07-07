export type DataResponse<T, A = {}> = {
  data: T
} & A

export type DataPaginationResponse<T> = {
  data: T
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    from: number
    last_page: number
    path: string
    per_page: number
    to: number
    total: number
    count: number

    links: {
      url: string | null
      label: string
      active: boolean
    }[]
  }
}