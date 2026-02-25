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

interface Props {
    userId: number;
    userName: string;
    userEmail: string;
    userRole: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
}>();

const isOpen = ref(true);

// Emit close event when dialog closes
watch(isOpen, (newVal) => {
    if (!newVal) {
        emit('close');
    }
});
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>View User</DialogTitle>
                <DialogDescription>
                    User information details.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div>
                    <p class="text-sm text-gray-500">Name</p>
                    <p class="text-lg font-medium">{{ userName }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Email</p>
                    <p class="text-lg">{{ userEmail }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Role</p>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="userRole === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'">
                        {{ userRole }}
                    </span>
                </div>
            </div>

            <DialogFooter>
                <DialogClose asChild>
                    <Button type="button">Close</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
