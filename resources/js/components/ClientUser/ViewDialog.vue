<script setup lang="ts">
import { ref, watch } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '../ui/dialog';
import Button from '../ui/button/Button.vue';
import Label from '../ui/label/Label.vue';

interface Props {
    userId: number;
    userName: string;
    userEmail: string;
    userRole: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{ close: [] }>();

const isOpen = ref(true);

watch(isOpen, (newVal) => {
    if (!newVal) emit('close');
});

</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="w-full max-w-md p-6 rounded-xl shadow-lg">
            <!-- Header -->
            <DialogHeader class="pb-4 border-b border-gray-200">
                <DialogTitle class="text-xl font-semibold">User Information</DialogTitle>
                <DialogDescription class="text-sm text-gray-500">
                    Detailed information about this user.
                </DialogDescription>
            </DialogHeader>

            <!-- Content -->
            <div class="mt-6 space-y-5">
                <div>
                    <Label class="text-muted-foreground">Name</Label>
                    <p class="text-md font-medium">{{ userName }}</p>
                </div>

                <div>
                    <Label class="text-muted-foreground">Email</Label>
                    <p class="text-lg">{{ userEmail }}</p>
                </div>

                <div class="space-y-1">
                    <Label class="text-muted-foreground">Role</Label>
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-medium border bg-blue-300/10 border-gray-300">
                        {{ userRole }}
                    </span>
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="mt-6 flex justify-end">
                <DialogClose asChild>
                    <Button type="button" class="px-4 py-2">Close</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>