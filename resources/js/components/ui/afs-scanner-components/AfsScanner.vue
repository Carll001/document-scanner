<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogTrigger } from '../dialog';
import afs from '@/routes/afs';
import { Button } from '../button';
import { Label } from '../label';
import { Input } from '../input';
import { ref } from 'vue';
import { Spinner } from '../spinner';

const closeScanner = ref(false);

const props = defineProps<{
    csvData?: Record<string, any>[],
    stats?: {
        complete: number,
        incomplete: number
    }
}>()

const form = useForm({
    file: null as File | null
})

const submit = () => {
    form.post(afs.parse().url, {
        forceFormData: true, // important for file uploads
        onSuccess: () => {
            console.log('Uploaded successfully')
            closeScanner.value = false
        }
    })
}

</script>
<template>
    <Dialog v-model:open="closeScanner">
        <DialogTrigger as-child>
            <Button>Import</Button>
        </DialogTrigger>
        <DialogContent>
            <div class="flex flex-col flex-1 gap-4 p-4">
                <Label for="file">Input CSV file</Label>

                <Input id="file" type="file" accept=".csv"
                    @change="(e: any) => form.file = (e.target as HTMLInputElement).files?.[0] ?? null" />

                <div v-if="form.errors.file" class="text-red-500 text-sm">
                    {{ form.errors.file }}
                </div>

                <Button class="bg-blue-500 text-white px-4 py-2 rounded flex gap-4" @click="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" class="h-4 w-4" /> {{ form.processing ? 'Uploading...' : 'Upload' }}
                </Button>

                
            <div v-if="form.progress">
                Processing please wait...
            </div>
            </div>
        </DialogContent>
    </Dialog>
</template>