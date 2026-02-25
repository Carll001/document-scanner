<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '../ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '../ui/dialog';
import { Input } from '../ui/input';
import Select from '../ui/select/Select.vue';
import SelectContent from '../ui/select/SelectContent.vue';
import SelectItem from '../ui/select/SelectItem.vue';
import SelectTrigger from '../ui/select/SelectTrigger.vue';
import SelectValue from '../ui/select/SelectValue.vue';
import clients from '@/routes/clients';
import { toast } from 'vue-sonner';

const closeModal = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
});

const submit = () => {
    form.post(clients.users.store().url, {
        onSuccess: () => {
            closeModal.value = false;
            form.reset();
            toast.success('Created succesfully!')
        },
    });
};

const handleClose = () => {
    form.reset();
    form.clearErrors();
};
</script>

<template>
    <Dialog v-model:open="closeModal">
        <DialogTrigger>
            <Button>Create</Button>
        </DialogTrigger>
        <DialogContent @interact-outside="handleClose">
            <DialogHeader>
                <DialogTitle>Create Client User</DialogTitle>
                <DialogDescription>
                    Fill in the details to create a new user.
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-4">
                <section>
                    <div class="space-y-1">
                        <label for="name" class="text-sm font-medium">Name</label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Full Name"
                            :aria-invalid="!!form.errors.name"
                        />
                        <p v-if="form.errors.name" class="text-sm text-red-500">
                            {{ form.errors.name }}
                        </p>
                    </div>
                </section>
                <section>
                    <div class="space-y-1">
                        <label for="email" class="text-sm font-medium">Email</label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="Email"
                            :aria-invalid="!!form.errors.email"
                        />
                        <p v-if="form.errors.email" class="text-sm text-red-500">
                            {{ form.errors.email }}
                        </p>
                    </div>
                </section>
                <section>
                    <div class="space-y-1">
                        <label for="password" class="text-sm font-medium">Password</label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            placeholder="Password"
                            :aria-invalid="!!form.errors.password"
                        />
                        <p v-if="form.errors.password" class="text-sm text-red-500">
                            {{ form.errors.password }}
                        </p>
                    </div>
                </section>
                <section>
                    <div class="space-y-1">
                        <label for="password_confirmation" class="text-sm font-medium">Confirm Password</label>
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            placeholder="Confirm Password"
                            :aria-invalid="!!form.errors.password_confirmation"
                        />
                        <p v-if="form.errors.password_confirmation" class="text-sm text-red-500">
                            {{ form.errors.password_confirmation }}
                        </p>
                    </div>
                </section>
                <section>
                    <div class="space-y-1">
                        <label for="role" class="text-sm font-medium">Role</label>
                        <Select v-model="form.role">
                            <SelectTrigger id="role" :aria-invalid="!!form.errors.role">
                                <SelectValue placeholder="Select role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="client">
                                    Client
                                </SelectItem>
                                <SelectItem value="registrar">
                                    Registrar
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.role" class="text-sm text-red-500">
                            {{ form.errors.role }}
                        </p>
                    </div>
                </section>

                <DialogFooter>
                    <DialogClose>
                        <Button type="button" variant="outline" @click="handleClose">Close</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing? 'Creating..' : 'Create' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
