<script setup lang="ts">
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import type { FileRow } from '@/types/paginated-response'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '../dialog'
import { Button } from '../button'
import { Input } from '../input'
import { Label } from '../label'
import { ScrollArea } from '../scroll-area'
import { save, regenerate } from '@/routes/afs'
import InputError from '@/components/InputError.vue'
import EditConfrimDialog from './EditConfrimDialog.vue'
import DialogClose from '../dialog/DialogClose.vue'
import { toast } from 'vue-sonner'

const props = defineProps<{ file: FileRow; open?: boolean }>()
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void
}>()

const open = computed({
    get: () => props.open ?? false,
    set: (value: boolean) => emit('update:open', value),
})

// The specific list of keys you want to show
const ALLOWED_KEYS = [
    "COMPANY NAME", "Company Name", "President’s Name", "Company TIN", "Company Address",
    "SEC Registration Date", "CASH", "CASH END", "CASHFLOWS", "CIB", "COGS", "COH",
    "Communication", "GROSS PROFIT", "Inventory", "Marketing", "NET INCOME",
    "NET SALES", "Net Sales", "OPERATING CASH", "OPEX", "Opex", "Outside Services",
    "PAYABLE TO SUPPLIERS", "PT Payable", "Purchases", "SHARE CAPITAL", "SHE",
    "Supplies", "TGAS", "TOTAL CURRENT ASSETS", "TOTAL LIAB AND SHE",
    "TRADE PAYABLE", "TRADE RECEIVABLES", "Tax", "Trade Payable", "Trade Receivables", "Travel"
]

// normalize header keys to compare “smartly”
const normalizeKey = (k: any) => {
    if (k === null || k === undefined) return ''
    return String(k).toUpperCase().replace(/[^A-Z0-9]/g, '')
}

const allowedNormalized = new Set(ALLOWED_KEYS.map(normalizeKey))

// Deep clone so we don’t mutate props
const clone = (obj: any) => JSON.parse(JSON.stringify(obj ?? {}))

const form = useForm({
    raw_data: clone(props.file.raw_data) || {},
    president_name: props.file.president_name ?? '',
})

watch(open, (v) => {
    if (v) {
        form.clearErrors()
        form.raw_data = clone(props.file.raw_data) || {}
        form.president_name = props.file.president_name ?? ''
    }
})

const filteredKeys = computed(() => {
    const data = form.raw_data ?? {}
    return Object.keys(data).filter((k) => allowedNormalized.has(normalizeKey(k)))
})

const handleSaveAndRegenerate = () => {
    form.put(save(props.file.id).url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.post(regenerate(props.file.id).url, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    open.value = false
                    toast.success('Updated successfully!')
                },
            })
        },
    })
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="sm:max-w-[700px] h-[90vh] flex flex-col">
      <DialogHeader>
        <DialogTitle>Edit File Data</DialogTitle>
      </DialogHeader>

      <ScrollArea class="flex-1 min-h-0 mt-4 pr-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 pb-6">
          <div
            v-for="key in filteredKeys"
            :key="key"
            class="flex flex-col gap-1.5"
            :class="{ 'md:col-span-2': key.toLowerCase().includes('address') }"
          >
            <Label :for="key" class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
              {{ key }}
            </Label>

            <Input
              :id="key"
              v-model="form.raw_data[key]"
              class="h-9 focus-visible:ring-primary"
              :disabled="form.processing"
            />

            <InputError :message="form.errors[`raw_data.${key}`]" />
          </div>
        </div>

        <div v-if="!filteredKeys.length" class="text-sm text-muted-foreground">
          No editable fields found in raw_data.
        </div>
      </ScrollArea>

      <DialogFooter class="pt-4 border-t flex gap-2 justify-end">
        <DialogClose>
          <Button variant="ghost">Cancel</Button>
        </DialogClose>

        <EditConfrimDialog
          :disabled="form.processing"
          :trigger-text="form.processing ? 'Working...' : 'Save and Regenerate'"
          title="Are you sure you want to save and regenerate?"
          description="This will save your updates and regenerate the document."
          @confirm="handleSaveAndRegenerate"
        />
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
