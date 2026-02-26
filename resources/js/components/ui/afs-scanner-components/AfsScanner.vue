<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Dialog, DialogContent, DialogTrigger } from '../dialog'
import afs from '@/routes/afs'
import { Button } from '../button'
import { Label } from '../label'
import { Input } from '../input'
import { ref, watch } from 'vue'

const open = ref(false)

const selectedName = ref('')
const selectedSize = ref('')

const form = useForm({
  file: null as File | null,
})

const humanSize = (bytes: number) => {
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let i = 0
  while (size >= 1024 && i < units.length - 1) {
    size /= 1024
    i++
  }
  return `${size.toFixed(i === 0 ? 0 : 1)} ${units[i]}`
}

const onFileChange = (e: Event) => {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0] ?? null

  form.file = file

  selectedName.value = file?.name ?? ''
  selectedSize.value = file ? humanSize(file.size) : ''
}

const resetUi = () => {
  form.reset('file')
  form.clearErrors()
  selectedName.value = ''
  selectedSize.value = ''
}

watch(open, (v) => {
  // when dialog closes, reset UI
  if (!v) resetUi()
})

const submit = () => {
  if (!form.file) return

  form.post(afs.parse().url, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      open.value = false
    },
  })
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <Button>Scan</Button>
    </DialogTrigger>

    <DialogContent>
      <div class="flex flex-col gap-4 p-4">
        <div class="space-y-1">
          <Label for="file">Input CSV file</Label>
          <Input
            id="file"
            type="file"
            accept=".csv"
            :disabled="form.processing"
            @change="onFileChange"
          />
        </div>

        <!-- Selected file preview -->
        <div v-if="selectedName" class="text-sm text-muted-foreground">
          Selected:
          <span class="font-medium text-foreground">{{ selectedName }}</span>
          <span v-if="selectedSize"> — {{ selectedSize }}</span>
        </div>

        <div v-if="form.errors.file" class="text-red-500 text-sm">
          {{ form.errors.file }}
        </div>

        <Button
          class="bg-blue-500 text-white px-4 py-2 rounded"
          @click="submit"
          :disabled="form.processing || !form.file"
        >
          {{ form.processing ? 'Uploading / Processing...' : 'Upload' }}
        </Button>

        <!-- Processing indicator -->
        <div v-if="form.processing" class="text-sm text-muted-foreground">
          Processing please wait...
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>