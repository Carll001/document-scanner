<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'

// shadcn-vue
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import {
  Table,
  TableHeader,
  TableRow,
  TableHead,
  TableBody,
  TableCell,
} from '@/components/ui/table'

import { ChevronLeft, ChevronRight, Search, History as HistoryIcon } from 'lucide-vue-next'

type HistoryRow = {
  id: number
  field: string
  old_value: string | null
  new_value: string | null
  created_at: string
  user?: { id?: number; name: string } | null
}

type PaginatedResponse<T> = {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

const props = defineProps<{
  histories: PaginatedResponse<HistoryRow>
  filters?: {
    search?: string
  }
}>()

const search = ref(props.filters?.search ?? '')

const showingText = computed(() => {
  const from = props.histories.from ?? 0
  const to = props.histories.to ?? 0
  const total = props.histories.total ?? 0
  if (!total) return 'No results'
  return `Showing ${from} to ${to} of ${total} total history logs`
})

const goToPage = (page: number) => {
  router.get(
    '/histories',
    { search: search.value, page },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

const onSearch = useDebounceFn(() => {
  router.get(
    '/histories',
    { search: search.value, page: 1 },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}, 350)

watch(search, () => onSearch())

const prevDisabled = computed(() => props.histories.current_page <= 1)
const nextDisabled = computed(() => props.histories.current_page >= props.histories.last_page)

const clampText = (val: string | null) => {
  if (!val) return '—'
  const t = String(val)
  if (t.length <= 160) return t
  return t.slice(0, 160) + '…'
}

const formatDate = (iso: string) => {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleString()
}
</script>

<template>
  <AppLayout>
    <Head title="History Logs" />

    <div class="p-6 space-y-6 max-w-6xl mx-auto">
      
      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="flex items-center gap-2">
            <HistoryIcon class="h-6 w-6" />
            <h1 class="text-2xl font-bold tracking-tight">History Logs</h1>
          </div>
          <p class="text-sm text-muted-foreground">
            Audit trail ng lahat ng pagbabago.
          </p>
        </div>

        <!-- Search -->
        <div class="w-full sm:w-[360px] relative">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            v-model="search"
            class="pl-9"
            placeholder="Search field / value..."
          />
        </div>
      </div>

      <Separator />

      <!-- Table -->
      <div class="rounded-lg border bg-background overflow-hidden">
        <div class="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>User</TableHead>
                <TableHead>Field</TableHead>
                <TableHead>Old Value</TableHead>
                <TableHead>New Value</TableHead>
                <TableHead>Date</TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              <TableRow
                v-for="h in props.histories.data"
                :key="h.id"
                class="hover:bg-muted/40 transition"
              >
                <TableCell class="font-medium">
                  {{ h.user?.name ?? 'System' }}
                </TableCell>

                <TableCell>
                  <Badge variant="secondary" class="text-xs">
                    {{ h.field }}
                  </Badge>
                </TableCell>

                <TableCell class="max-w-[300px]">
                  <span class="text-muted-foreground line-clamp-2 break-words">
                    {{ clampText(h.old_value) }}
                  </span>
                </TableCell>

                <TableCell class="max-w-[300px]">
                  <span class="line-clamp-2 break-words">
                    {{ clampText(h.new_value) }}
                  </span>
                </TableCell>

                <TableCell class="text-xs text-muted-foreground whitespace-nowrap">
                  {{ formatDate(h.created_at) }}
                </TableCell>
              </TableRow>

              <TableRow v-if="props.histories.data.length === 0">
                <TableCell colspan="5" class="text-center py-10 text-muted-foreground">
                  Walang history logs pa.
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <p class="text-sm text-muted-foreground">
          {{ showingText }}
        </p>

        <div class="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            :disabled="prevDisabled"
            @click="goToPage(props.histories.current_page - 1)"
          >
            <ChevronLeft class="h-4 w-4 mr-1" />
            Prev
          </Button>

          <Badge variant="secondary" class="text-xs">
            Page {{ props.histories.current_page }} of {{ props.histories.last_page }}
          </Badge>

          <Button
            variant="outline"
            size="sm"
            :disabled="nextDisabled"
            @click="goToPage(props.histories.current_page + 1)"
          >
            Next
            <ChevronRight class="h-4 w-4 ml-1" />
          </Button>
        </div>
      </div>

    </div>
  </AppLayout>
</template>