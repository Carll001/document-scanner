<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
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
  status: 'completed' | 'incomplete' | 'generated' | string
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

/* ==========================
   Filters + Fetch
========================== */

const searchQuery = ref(props.filters?.search ?? '')
const statusFilter = ref(props.filters?.status ?? 'all')
const documentFilter = ref(props.filters?.document ?? 'all')

const getQueryParams = (page = 1) => {
  const query: Record<string, string | number> = {
    page,
    search: searchQuery.value,
  }

  query.status =
    statusFilter.value === 'completed' || statusFilter.value === 'incomplete'
      ? statusFilter.value
      : 'all'

  query.document =
    documentFilter.value === 'with_document' || documentFilter.value === 'no_document'
      ? documentFilter.value
      : 'all'

  return query
}

watch(
  () => props.filters?.search,
  (value) => (searchQuery.value = value ?? ''),
)
watch(
  () => props.filters?.status,
  (value) => (statusFilter.value = value ?? 'all'),
)
watch(
  () => props.filters?.document,
  (value) => (documentFilter.value = value ?? 'all'),
)

const fetchWithFilters = () => {
  router.get(
    afs.index.url({ query: getQueryParams(1) }),
    {},
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['generatedFiles', 'filters'],
    },
  )
}

const debouncedSearch = useDebounceFn(fetchWithFilters, 350)

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

const viewTemplatePlaceholder = () => {
  router.get(afs.placeholder().url)
}

/* ==========================
   Printing (Single + All)
========================== */

const isPrinting = ref(false)

const completedPrintableFiles = computed(() => {
  return (props.generatedFiles?.data ?? []).filter(
    (f) => f.status === 'completed' && !!f.path,
  )
})

const printPdfQueued = (file: FileRow) => {
  return new Promise<void>((resolve) => {
    if (!file.path) return resolve()

    const url = `/storage/${file.path}`

    const iframe = document.createElement('iframe')
    iframe.style.position = 'fixed'
    iframe.style.right = '0'
    iframe.style.bottom = '0'
    iframe.style.width = '0'
    iframe.style.height = '0'
    iframe.style.border = '0'
    iframe.src = url

    let done = false
    const finish = () => {
      if (done) return
      done = true
      window.removeEventListener('focus', onFocusBack, true)
      iframe.remove()
      resolve()
    }

    // When print dialog closes (print OR cancel), window often regains focus
    const onFocusBack = () => {
      // small delay so browser fully closes dialog
      setTimeout(() => finish(), 200)
    }

    iframe.onload = () => {
      const w = iframe.contentWindow
      if (!w) return finish()

      const onAfterPrint = () => {
        w.removeEventListener('afterprint', onAfterPrint)
        window.clearTimeout(fallbackTimer)
        finish()
      }

      w.addEventListener('afterprint', onAfterPrint)
      window.addEventListener('focus', onFocusBack, true)

      // Hard fallback no matter what
      const fallbackTimer = window.setTimeout(() => {
        w.removeEventListener('afterprint', onAfterPrint)
        finish()
      }, 5000)

      setTimeout(() => {
        try {
          w.focus()
          w.print()
        } catch {
          window.clearTimeout(fallbackTimer)
          finish()
        }
      }, 600)
    }

    document.body.appendChild(iframe)
  })
}

// Single print uses same stable function
const printSingle = async (file: FileRow) => {
  if (isPrinting.value) return
  isPrinting.value = true
  try {
    await printPdfQueued(file)
  } finally {
    isPrinting.value = false
  }
}

const printAllCompleted = async () => {
  if (isPrinting.value) return
  isPrinting.value = true
  try {
    for (const file of completedPrintableFiles.value) {
      await printPdfQueued(file)
    }
  } finally {
    isPrinting.value = false
  }
}
</script>

<template>

  <Head title="AFS SCANNER" />

  <AppLayout>
    <div class="p-4 space-y-6">
      <!-- TOP ACTIONS -->
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <Input v-model="searchQuery" class="w-56" placeholder="Search company, file, status..." />

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

          <Button :disabled="!completedPrintableFiles.length || isPrinting" @click="printAllCompleted">
            {{
              isPrinting
                ? 'Printing...'
                : `Print All Completed (${completedPrintableFiles.length})`
            }}
          </Button>
        </div>
      </div>

      <!-- STATS -->
      <div v-if="stats" class="flex gap-6 text-sm">
        <p class="text-green-700 font-semibold">Completed: {{ stats.complete }}</p>
        <p class="text-red-700 font-semibold">Incomplete: {{ stats.incomplete }}</p>
      </div>

      <!-- TABLE -->
      <div class="rounded-lg border">
        <!-- IMPORTANT: make sure AfsTable emits/uses printSingle if you want per-row printing from here -->
        <!-- If you want, pass printSingle as a prop and call it from the row Print button -->
        <AfsTable :generated-files="props.generatedFiles" :csv-data="props.csvData" :stats="props.stats"
          :filters="props.filters" :on-print="printSingle" />
      </div>
    </div>
  </AppLayout>
</template>