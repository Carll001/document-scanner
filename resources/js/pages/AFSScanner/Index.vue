<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AfsScanner from '@/components/ui/afs-scanner-components/AfsScanner.vue'
import { Button } from '@/components/ui/button'
import afs from '@/routes/afs'
import { Input } from '@/components/ui/input'
import AfsTable from '@/components/ui/afs-scanner-components/AfsTable.vue'

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
}>()

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

      <div class="flex items-center justify-between">
        <Input class="max-w-xs" placeholder="Search..."/>

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
        <AfsTable :generated-files="props.generatedFiles" :csv-data="props.csvData" :stats="props.stats"/>
      </div>      

    </div>
  </AppLayout>
</template>