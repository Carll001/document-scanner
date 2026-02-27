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
import { Input } from '../ui/input';
import Button from '../ui/button/Button.vue';
import Select from '../ui/select/Select.vue';
import SelectContent from '../ui/select/SelectContent.vue';
import SelectItem from '../ui/select/SelectItem.vue';
import SelectTrigger from '../ui/select/SelectTrigger.vue';
import SelectValue from '../ui/select/SelectValue.vue';
import clients from '@/routes/clients';
import { toast } from 'vue-sonner';
import InputError from '../InputError.vue';

interface Props {
    userId: string;
    userName: string;
    userEmail: string;
    userRole: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
}>();

const isOpen = ref(true);

const form = useForm({
    name: props.userName,
    email: props.userEmail,
    role: props.userRole,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(clients.users.update(props.userId).url, {
        onSuccess: () => {
            form.reset();
            toast.success('Updated successfully!');
            isOpen.value = false;
        },
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
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-lg font-semibold">Edit User</DialogTitle>
                <DialogDescription class="text-sm text-gray-500">
                    Update user information below.
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-5 py-3">
                <div class="space-y-1.5">
                    <label for="name" class="text-sm font-medium">Name</label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="Full Name"
                        class="w-full"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="space-y-1.5">
                    <label for="email" class="text-sm font-medium">Email</label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="Email"
                        class="w-full"
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="space-y-1.5">
                    <label for="password" class="text-sm font-medium">
                        Password
                        <span class="text-xs text-gray-500">(optional)</span>
                    </label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        placeholder="Leave empty to keep current password"
                        class="w-full"
                    />
                    <InputError :message="form.errors.password" />
                </div>
                <div class="space-y-1.5">
                    <label for="password_confirmation" class="text-sm font-medium">
                        Confirm Password
                        <span class="text-xs text-gray-500">(optional)</span>
                    </label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        placeholder="Confirm new password"
                        class="w-full"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>
                <div class="space-y-1.5">
                    <label for="role" class="text-sm font-medium">Role</label>
                    <Select v-model="form.role">
                        <SelectTrigger id="role" class="w-full">
                            <SelectValue placeholder="Select role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="client">Client</SelectItem>
                            <SelectItem value="registrar">Registrar</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <DialogFooter class="gap-2 pt-2">
                    <DialogClose asChild>
                        <Button type="button" variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button type="submit">Save</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>