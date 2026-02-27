export type FileRow = {
    id: string
    company_name: string | null
    president_name: string | null
    original_name: string | null
    path: string | null
    status: string
    created_at?: string
    missing_fields?: string[] | null
    filled_fields?: string[] | null
    raw_data?: string[] | null
}

export type PaginatedResponse = {
    data: FileRow[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
}