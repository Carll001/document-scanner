<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import AfsScanner from '@/components/ui/afs-scanner-components/AfsScanner.vue'
import { Button } from '@/components/ui/button'
import afs from '@/routes/afs'
import { Input } from '@/components/ui/input'
import AfsTable from '@/components/ui/afs-scanner-components/AfsTable.vue'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

type FileRow = {
  id: number
  company_name: string | null
  original_name: string | null
  path: string | null
  status: 'generated' | 'incomplete' | string
  created_at?: string
  missing_fields?: string[] | null
}

type PaginatedResponse = {
  data: FileRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

const props = defineProps<{
  generatedFiles?: PaginatedResponse
  csvData?: any[]
  stats?: {
    complete: number
    incomplete: number
  }
  filters?: {
    search: string
    status: string
    document: string
  }
}>()

const searchQuery = ref(props.filters?.search ?? '')
const statusFilter = ref(props.filters?.status ?? 'all')
const documentFilter = ref(props.filters?.document ?? 'all')

const getQueryParams = (page = 1) => {
  const query: Record<string, string | number> = {
    page,
    search: searchQuery.value,
  }

  if (statusFilter.value === 'completed' || statusFilter.value === 'incomplete') {
    query.status = statusFilter.value
  } else {
    query.status = 'all'
  }

  if (documentFilter.value === 'with_document' || documentFilter.value === 'no_document') {
    query.document = documentFilter.value
  } else {
    query.document = 'all'
  }

  return query
}

watch(
  () => props.filters?.search,
  (value) => {
    searchQuery.value = value ?? ''
  },
)

watch(
  () => props.filters?.status,
  (value) => {
    statusFilter.value = value ?? 'all'
  },
)

watch(
  () => props.filters?.document,
  (value) => {
    documentFilter.value = value ?? 'all'
  },
)

const fetchWithFilters = () => {
  router.get(
    afs.index.url({
      query: getQueryParams(1),
    }),
    {},
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['generatedFiles', 'filters'],
    },
  )
}

const debouncedSearch = useDebounceFn(() => {
  fetchWithFilters()
}, 350)

watch(searchQuery, (value) => {
  if (value === (props.filters?.search ?? '')) return
  debouncedSearch()
})

watch(statusFilter, (value) => {
  if (value === (props.filters?.status ?? 'all')) return
  fetchWithFilters()
})

watch(documentFilter, (value) => {
  if (value === (props.filters?.document ?? 'all')) return
  fetchWithFilters()
})

/* --------------------------
   CSV Upload
--------------------------- */

const viewTemplatePlaceholder = () => {
  router.get(afs.placeholder().url)
}

/* --------------------------
   Missing Dialog
--------------------------- */

</script>

<template>
  <Head title="AFS SCANNER" />

  <AppLayout>
    <div class="p-4 space-y-6">

      <!-- =======================
           TOP ACTIONS
      ======================== -->

      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <Input
            v-model="searchQuery"
            class="w-56"
            placeholder="Search company, file, status..."
          />
          <Select v-model="statusFilter">
            <SelectTrigger class="w-40">
              <SelectValue placeholder="Filter status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Status</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="incomplete">Incomplete</SelectItem>
            </SelectContent>
          </Select>
          <Select v-model="documentFilter">
            <SelectTrigger class="w-56">
              <SelectValue placeholder="Filter document" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Documents</SelectItem>
              <SelectItem value="with_document">With Document</SelectItem>
              <SelectItem value="no_document">No Document Generated</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="flex gap-2">
          <AfsScanner />
          <Button>Print all completed</Button>
        </div>
      </div>

      <!-- =======================
           STATS
      ======================== -->

      <div v-if="stats" class="flex gap-6 text-sm">
        <p class="text-green-700 font-semibold">
          Completed: {{ stats.complete }}
        </p>
        <p class="text-red-700 font-semibold">
          Incomplete: {{ stats.incomplete }}
        </p>
      </div>

      <!-- =======================
           TABLE
      ======================== -->

      <div class="rounded-lg border">
        <AfsTable
          :generated-files="props.generatedFiles"
          :csv-data="props.csvData"
          :stats="props.stats"
          :filters="props.filters"
        />
      </div>      

    </div>
  </AppLayout>
</template>
