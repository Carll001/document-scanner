<script setup lang="ts">
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Button } from '../button';
import { router } from '@inertiajs/vue3';
import afs from '@/routes/afs';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog'
import { ScrollArea } from '@/components/ui/scroll-area'
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '../tooltip';
import { FileRow, PaginatedResponse } from '@/types/paginated-response';

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

const goToPage = (page: number) => {
    const query: Record<string, string | number> = {
        page,
        search: props.filters?.search ?? '',
    }

    const status = props.filters?.status ?? 'all'
    query.status = status === 'completed' || status === 'incomplete' ? status : 'all'
    const document = props.filters?.document ?? 'all'
    query.document = document === 'with_document' || document === 'no_document' ? document : 'all'

    router.get(
        afs.index.url({ query }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['generatedFiles', 'filters'],
        },
    )
}
const printPdf = (file: FileRow) => {
  if (!file.path) return

  const url = `/storage/${file.path}` // must be the PDF path

  // Remove previous iframe if any
  const existing = document.getElementById('print-iframe')
  if (existing) existing.remove()

  const iframe = document.createElement('iframe')
  iframe.id = 'print-iframe'
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  iframe.src = url

  iframe.onload = () => {
    // give PDF viewer a moment to render (important!)
    setTimeout(() => {
      iframe.contentWindow?.focus()
      iframe.contentWindow?.print()
    }, 300)
  }

  document.body.appendChild(iframe)
}

const paginationPages = computed(() => {
    if (!props.generatedFiles) return []
    const { current_page, last_page } = props.generatedFiles
    const pages: (number | string)[] = []

    // Always show first page
    pages.push(1)

    // Show pages around current page
    const start = Math.max(2, current_page - 1)
    const end = Math.min(last_page - 1, current_page + 1)

    if (start > 2) pages.push('...')

    for (let i = start; i <= end; i++) {
        pages.push(i)
    }

    if (end < last_page - 1) pages.push('...')

    // Always show last page if more than 1
    if (last_page > 1) pages.push(last_page)

    return pages
})


const dialogOpen = ref(false)
const selected = ref<FileRow | null>(null)

const openMissing = (file: FileRow) => {
    selected.value = file
    dialogOpen.value = true
}

</script>
<template>
    <div class="flex flex-col flex-1 h-[76svh] p-2 gap-2">
        <Table class="text-">
            <TableHeader>
                <TableRow>
                    <TableHead>#</TableHead>
                    <TableHead>Company</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead class="text-center">Field</TableHead>
                    <TableHead class="text-center">Missing</TableHead>
                    <TableHead>Document</TableHead>
                    <TableHead class="text-right">Action</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow v-for="(file, index) in (generatedFiles?.data ?? [])" :key="file.id" >
                    <TableCell class="">
                        {{ (generatedFiles?.from ?? 0) + index }}
                    </TableCell>

                    <TableCell class="font-medium max-w-2xs truncate">
                        <TooltipProvider :delay-duration="700">
                            <Tooltip>
                                <TooltipTrigger>
                                    <p class="max-w-xs truncate">{{ file.company_name ?? 'Unknown Company' }}</p>
                                </TooltipTrigger>
                                <TooltipContent >
                                    <p>{{ file.company_name ?? 'Unknown Company' }}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                    </TableCell>

                    <TableCell>
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold capitalize"
                            :class="file.status === 'completed'
                                ? 'bg-green-100 text-green-700'
                                : 'bg-red-100 text-red-700'">
                            {{ file.status }}
                        </span>
                    </TableCell>

                    <TableCell class="text-center">
                        <span class="font-semibold" :class="file.status === 'incomplete'
                            ? 'text-green-700'
                            : 'text-muted-foreground'">
                            {{ file.filled_fields?.length ?? 0 }}
                        </span>
                    </TableCell>
                    <TableCell class="text-center">
                        <span class="font-semibold" :class="file.status === 'incomplete'
                            ? 'text-red-700'
                            : 'text-muted-foreground'">
                            {{ file.missing_fields?.length ?? 0 }}
                        </span>
                    </TableCell>

                    <TableCell>
                        <div v-if="file.path" class="max-w-sm truncate">
                            <a :href="`/storage/${file.path}`" target="_blank" class="text-blue-600 underline ">
                                {{ file.original_name }}
                            </a>
                        </div>
                        <div v-else>
                            <span class="text-muted-foreground text-xs">No document generated because of missing
                                fields</span>
                        </div>
                    </TableCell>

                    <TableCell class="text-right space-x-2">
                        <Button size="sm" @click="printPdf(file)" v-if="file.status === 'completed'">
                            Print
                        </Button>
                        <Button  variant="outline" size="sm"  class="border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white"
                            @click="openMissing(file)">
                            Field Summary
                        </Button>

                        <!-- <span v-else class="text-muted-foreground text-sm">—</span> -->
                    </TableCell>
                </TableRow>

                <TableRow v-if="!(generatedFiles?.data?.length)">
                    <TableCell colspan="7" class="text-center text-muted-foreground py-6">
                        No records yet.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <div v-if="generatedFiles && generatedFiles.last_page > 1" class="mt-6 mt-auto ml-auto">
            <Pagination :items-per-page="generatedFiles.per_page" :total="generatedFiles.total">
                <PaginationContent>
                    <PaginationPrevious
                        @click="generatedFiles!.current_page > 1 && goToPage(generatedFiles!.current_page - 1)"
                        :disabled="generatedFiles.current_page === 1" />
                    <template v-for="(page, index) in paginationPages" :key="index">
                        <PaginationEllipsis v-if="page === '...'" />
                        <PaginationItem v-else :value="page as number" :is-active="page === generatedFiles.current_page"
                            @click="goToPage(page as number)">
                            {{ page }}
                        </PaginationItem>
                    </template>
                    <PaginationNext
                        @click="generatedFiles!.current_page < generatedFiles!.last_page && goToPage(generatedFiles!.current_page + 1)"
                        :disabled="generatedFiles.current_page === generatedFiles.last_page" />
                </PaginationContent>
            </Pagination>
        </div>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    Fields Summary — {{ selected?.company_name ?? 'Unknown Company' }}
                </DialogTitle>
                <DialogDescription>
                    Overview of filled and missing fields for this record.
                </DialogDescription>
            </DialogHeader>

            <div class="grid grid-cols-2 gap-6 mt-6">
                <!-- Filled Fields -->
                <div>
                    <h3 class="text-sm font-semibold text-green-700 mb-3">
                        Filled Fields ({{ selected?.filled_fields?.length ?? 0 }})
                    </h3>
                    <ScrollArea v-if="selected?.filled_fields?.length" class="h-[40vh] border rounded-lg p-3">
                        <ul class="list-disc ml-5 space-y-1">
                            <li v-for="(field, idx) in selected.filled_fields" :key="`filled-${idx}`"
                                class="text-green-700 font-medium text-sm">
                                {{ field }}
                            </li>
                        </ul>
                    </ScrollArea>
                    <div v-else class="text-sm text-muted-foreground p-3 border rounded-lg bg-muted/50">
                        No fields filled.
                    </div>
                </div>

                <!-- Missing Fields -->
                <div>
                    <h3 class="text-sm font-semibold text-red-700 mb-3">
                        Missing Fields ({{ selected?.missing_fields?.length ?? 0 }})
                    </h3>
                    <ScrollArea v-if="selected?.missing_fields?.length" class="h-[40vh] border rounded-lg p-3">
                        <ul class="list-disc ml-5 space-y-1">
                            <li v-for="(field, idx) in selected.missing_fields" :key="`missing-${idx}`"
                                class="text-red-700 font-medium text-sm">
                                {{ field }}
                            </li>
                        </ul>
                    </ScrollArea>
                    <div v-else class="text-sm text-muted-foreground p-3 border rounded-lg bg-muted/50">
                        No missing fields recorded.
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <Button variant="secondary" @click="dialogOpen = false">
                    Close
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- <pre>{{ props }}</pre> -->
</template>
