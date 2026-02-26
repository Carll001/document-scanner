<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import type { FileRow } from '@/types/paginated-response'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger } from '../dialog'
import { Button } from '../button'
import { Input } from '../input'
import { Label } from '../label'
import { ScrollArea } from '../scroll-area'
import { save, regenerate } from '@/routes/afs'

const props = defineProps<{ file: FileRow }>()

const open = ref(false)

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

// Deep clone so we don’t mutate props
const clone = (obj: any) => JSON.parse(JSON.stringify(obj ?? {}))

const form = useForm({
  raw_data: clone(props.file.raw_data),
})

// reset data every time dialog opens (so it's always fresh)
watch(open, (v) => {
  if (v) {
    form.clearErrors()
    form.raw_data = clone(props.file.raw_data)
  }
})

const filteredKeys = computed(() => {
  const data = form.raw_data ?? {}
  return Object.keys(data).filter((k) => ALLOWED_KEYS.includes(k))
})

const handleSave = () => {
  form.put(save(props.file.id).url, {
    preserveScroll: true,
    preserveState: true,
  })
}

const handleSaveAndRegenerate = () => {
  // Save first, then regenerate on success
  form.put(save(props.file.id).url, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      form.post(regenerate(props.file.id).url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          open.value = false
        },
      })
    },
  })
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <Button size="sm" variant="outline">Edit Financials</Button>
    </DialogTrigger>

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

            <div v-if="form.errors[`raw_data.${key as any}`]" class="text-xs text-red-500">
              {{ form.errors[`raw_data.${key as any}`] }}
            </div>
          </div>
        </div>

        <div v-if="!filteredKeys.length" class="text-sm text-muted-foreground">
          No editable fields found in raw_data.
        </div>
      </ScrollArea>

      <DialogFooter class="pt-4 border-t flex gap-2 justify-end">
        <Button type="button" variant="ghost" @click="open = false" :disabled="form.processing">
          Cancel
        </Button>

        <Button type="button" @click="handleSave" :disabled="form.processing">
          {{ form.processing ? 'Saving...' : 'Save' }}
        </Button>

        <Button
          type="button"
          class="bg-blue-700 hover:bg-blue-800 text-white"
          @click="handleSaveAndRegenerate"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Working...' : 'Save and Regenerate' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>