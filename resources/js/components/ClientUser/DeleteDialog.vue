<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
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
import clients from '@/routes/clients';
import { toast } from 'vue-sonner';

interface Props {
    userId: string;
    userName: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
}>();

const isOpen = ref(true);

const form = useForm({});

const handleDelete = () => {
    form.delete(clients.users.destroy(props.userId).url, {
        onSuccess: () => {
            toast.success('User deleted successfully!');
            isOpen.value = false;
        },
        onError: () => {
            toast.error('Failed to delete user');
        }
    });
};

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
                <DialogTitle>Delete User</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete <strong>{{ userName }}</strong>? This action cannot be undone.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <DialogClose asChild>
                    <Button type="button" variant="outline">Cancel</Button>
                </DialogClose>
                <Button 
                    variant="destructive" 
                    @click="handleDelete"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Deleting...' : 'Delete' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
