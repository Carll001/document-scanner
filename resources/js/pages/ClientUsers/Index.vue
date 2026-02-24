<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { type BreadcrumbItem } from '@/types';
import { toast, Toaster } from 'vue-sonner';
import clientUsersLink from '@/routes/clients/users';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Search, Users } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users Management',
        href: '',
    },
];

type UserRole = 'client' | 'registrar';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: UserRole | 'super_admin'; // safe if old data exists
    created_at: string | null;
};

const props = defineProps<{
    filters: { search: string; per_page: number };
    users: {
        data: { data: UserRow[] }; // UserResource::collection(...) returns { data: [] }
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
}>();

// ✅ FIX: users.data is already { data: [...] } so don't do .data.data
const rows = computed<UserRow[]>(() => props.users?.data?.data ?? []);

const search = ref(props.filters.search ?? '');
const perPage = ref<number>(props.filters.per_page ?? 10);

watch([search, perPage], () => {
    router.get(
        clientUsersLink.index.url({
            query: { search: search.value, per_page: perPage.value },
        }),
        {},
        { preserveState: true, replace: true },
    );
});

const open = ref(false);
const editing = ref<UserRow | null>(null);
const deleteDialogOpen = ref(false);
const userToDelete = ref<UserRow | null>(null);
const showDialogOpen = ref(false);
const selectedUser = ref<UserRow | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'client' as UserRole,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.role = 'client';
    open.value = true;
}

function openEdit(u: UserRow) {
    editing.value = u;
    form.name = u.name;
    form.email = u.email;
    form.password = '';
    form.password_confirmation = '';
    form.role = (u.role === 'super_admin' ? 'registrar' : u.role) as UserRole;
    open.value = true;
}

function submit() {
    if (!editing.value) {
        form.post(clientUsersLink.store.url(), {
            onSuccess: () => {
                open.value = false;
                form.reset();
                toast.success('User created successfully!');
            },
        });
        return;
    }

    form.put(clientUsersLink.update.url({ user: editing.value.id }), {
        onSuccess: () => {
            open.value = false;
            form.reset();
            toast.success('User updated successfully!');
        },
    });
}

function destroyUser(u: UserRow) {
    userToDelete.value = u;
    deleteDialogOpen.value = true;
}

function confirmDelete() {
    if (!userToDelete.value) return;

    router.delete(
        clientUsersLink.destroy.url({ user: userToDelete.value.id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                deleteDialogOpen.value = false;
                userToDelete.value = null;
                toast.success('User deleted successfully!');
            },
        },
    );
}

function viewUser(u: UserRow) {
    selectedUser.value = u;
    showDialogOpen.value = true;
}

const flash = computed(() => (usePage().props as any).flash);
</script>

<template>
    <Head title="Client Users" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 space-y-4 p-4">
            <section>
                <h1 class="text-2xl font-bold tracking-tight">Client Users</h1>
                <p class="text-muted-foreground">
                    Manage client users who can access the dashboard.
                </p>
            </section>

            <section>
                <div class="flex justify-end gap-3">
                    <Button @click="openCreate">+ Add User</Button>
                    <div class="relative md:max-w-sm">
                        <Input
                            v-model="search"
                            placeholder="Search..."
                            class="pr-10"
                        />
                         <Search
                            class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                    </div>
                </div>
            </section>
            <div v-if="flash?.success" class="text-sm">
                {{ flash.success }}
            </div>

            <div class="">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>#</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-for="(u, index) in rows" :key="u.id">
                            <TableCell>
                                {{ index + 1 }}
                            </TableCell>
                            <TableCell class="font-medium">{{
                                u.name
                            }}</TableCell>
                            <TableCell>{{ u.email }}</TableCell>
                            <TableCell class="capitalize">
                                {{
                                    (u.role === 'super_admin'
                                        ? 'registrar'
                                        : u.role
                                    ).replace('_', ' ')
                                }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        @click="viewUser(u)"
                                        >View</Button
                                    >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEdit(u)"
                                        >Edit</Button
                                    >
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        @click="destroyUser(u)"
                                        >Delete</Button
                                    >
                                </div>
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="rows.length === 0">
                            <TableCell
                                colspan="5"
                                class="py-10 text-center text-sm text-muted-foreground"
                            >
                                <Empty>
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <Users />
                                        </EmptyMedia>
                                        <EmptyTitle>No Users Yet</EmptyTitle>
                                        <EmptyDescription>
                                            You haven't created any users yet.
                                            Get started by creating your first
                                            user.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div
                class="flex items-center justify-between text-sm text-muted-foreground"
            >
                <div>
                    Showing {{ users.from ?? 0 }} - {{ users.to ?? 0 }} of
                    {{ users.total }}
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="users.current_page <= 1"
                        @click="
                            router.get(
                                clientUsersLink.index.url({
                                    query: {
                                        search,
                                        per_page: perPage,
                                        page: users.current_page - 1,
                                    },
                                }),
                                {},
                                { preserveState: true, replace: true },
                            )
                        "
                    >
                        Prev
                    </Button>
                    <span
                        >Page {{ users.current_page }} /
                        {{ users.last_page }}</span
                    >
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="users.current_page >= users.last_page"
                        @click="
                            router.get(
                                clientUsersLink.index.url({
                                    query: {
                                        search,
                                        per_page: perPage,
                                        page: users.current_page + 1,
                                    },
                                }),
                                {},
                                { preserveState: true, replace: true },
                            )
                        "
                    >
                        Next
                    </Button>
                </div>
            </div>

            <Dialog v-model:open="open">
                <DialogContent class="sm:max-w-[520px]">
                    <DialogHeader>
                        <DialogTitle>{{
                            editing ? 'Edit User' : 'Create User'
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                editing
                                    ? 'Password is optional when updating.'
                                    : 'Password is required when creating.'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-3">
                        <div class="space-y-1">
                            <label class="text-sm">Name</label>
                            <Input
                                v-model="form.name"
                                placeholder="Full name"
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm">Email</label>
                            <Input v-model="form.email" placeholder="Email" />
                            <p
                                v-if="form.errors.email"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm">Password</label>
                            <Input
                                v-model="form.password"
                                type="password"
                                placeholder="Min 8 chars"
                            />
                            <p
                                v-if="form.errors.password"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm">Confirm Password</label>
                            <Input
                                v-model="form.password_confirmation"
                                type="password"
                                placeholder="Confirm password"
                            />
                            <p
                                v-if="form.errors.password_confirmation"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.password_confirmation }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm">Role</label>
                            <Select v-model="form.role">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="client"
                                        >Client</SelectItem
                                    >
                                    <SelectItem value="registrar"
                                        >Registrar</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <p
                                v-if="form.errors.role"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.role }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="open = false"
                            >Cancel</Button
                        >
                        <Button :disabled="form.processing" @click="submit">
                            {{ editing ? 'Update' : 'Create' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="deleteDialogOpen">
                <DialogContent class="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Delete User</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete
                            <span class="font-semibold">{{
                                userToDelete?.name
                            }}</span
                            >? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter class="gap-2">
                        <Button
                            variant="outline"
                            @click="deleteDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" @click="confirmDelete">
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="showDialogOpen">
                <DialogContent class="sm:max-w-[525px]">
                    <DialogHeader>
                        <DialogTitle>{{ selectedUser?.name }}</DialogTitle>
                        <DialogDescription>
                            User details and information
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-muted-foreground">
                                Name
                            </p>
                            <p class="text-sm">{{ selectedUser?.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <p class="text-sm font-medium text-muted-foreground">
                                Email
                            </p>
                            <p class="text-sm">{{ selectedUser?.email }}</p>
                        </div>

                        <div class="space-y-2">
                            <p class="text-sm font-medium text-muted-foreground">
                                Role
                            </p>
                            <p class="capitalize text-sm">
                                {{
                                    (selectedUser?.role === 'super_admin'
                                        ? 'registrar'
                                        : selectedUser?.role
                                    )?.replace('_', ' ')
                                }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <p class="text-sm font-medium text-muted-foreground">
                                Created At
                            </p>
                            <p class="text-sm">{{ selectedUser?.created_at }}</p>
                        </div>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="showDialogOpen = false">
                            Close
                        </Button>
                        <Button @click="openEdit(selectedUser!)">Edit</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
