<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import usersRoutes from '@/routes/clients/users';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import Button from '../ui/button/Button.vue';
import { User } from '@/types';
import EditDialog from './EditDialog.vue';
import DeleteDialog from './DeleteDialog.vue';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { UserRoundMinusIcon } from 'lucide-vue-next';
import ViewDialog from './ViewDialog.vue';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';

const props = defineProps<{
    users: User[];
    filters: {
        search: string;
        per_page: number;
        role: string;
    };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
}>();

const selectedUser = ref<User | null>(null);
const userToDelete = ref<User | null>(null);
const userToView = ref<User | null>(null);

const goToPage = (pageNum: number) => {
    const query: Record<string, string | number> = {
        search: props.filters.search,
        per_page: props.filters.per_page,
        page: pageNum,
    };

    if (props.filters.role) {
        query.role = props.filters.role;
    }

    router.get(
        usersRoutes.index.url({
            query,
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'filters'],
        },
    );
};
</script>

<template>
    <div class="flex min-h-screen flex-col gap-4 p-4">
        <!-- Table area -->
        <div class="flex-1 overflow-auto max-h-[550px]">
            <Table class="w-full rounded-lg">
                <TableHeader>
                    <TableRow>
                        <TableHead>#</TableHead>
                        <TableHead>Full Name</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead class="flex justify-end pr-20"
                            >Actions</TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(user, index) in users" :key="user.id">
                        <TableCell>{{
                            index + props.pagination.from
                        }}</TableCell>
                        <TableCell>{{ user.name }}</TableCell>
                        <TableCell>{{ user.email }}</TableCell>
                        <TableCell class="capitalize">{{
                            user.role
                        }}</TableCell>
                        <TableCell class="flex justify-end space-x-2">
                            <Button variant="outline" size="sm" @click="userToView = user"
                                >View</Button
                            >
                            <Button variant="secondary" size="sm" @click="selectedUser = user"
                                >Edit</Button
                            >
                            <Button
                                size="sm"
                                variant="destructive"
                                @click="userToDelete = user"
                            >
                                Delete
                            </Button>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="users.length === 0">
                        <TableCell colspan="5">
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <UserRoundMinusIcon />
                                    </EmptyMedia>
                                    <EmptyTitle>No Users Found</EmptyTitle>
                                    <EmptyDescription>
                                        There are no records to display at the
                                        moment.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Sticky Pagination Footer -->
        <div
            class="mt-2 flex w-full justify-end gap-4 border-gray-200 p-4"
        >
            <Pagination
                :items-per-page="props.pagination.per_page"
                :total="props.pagination.total"
                :page="props.pagination.current_page"
            >
                <PaginationContent>
                    <PaginationPrevious
                        :disabled="props.pagination.current_page === 1"
                        @click="goToPage(props.pagination.current_page - 1)"
                    />
                    <PaginationItem
                        v-for="p in props.pagination.last_page"
                        :key="p"
                        :value="p"
                        :is-active="p === props.pagination.current_page"
                        @click="goToPage(p)"
                    >
                        {{ p }}
                    </PaginationItem>
                    <PaginationNext
                        :disabled="
                            props.pagination.current_page ===
                            props.pagination.last_page
                        "
                        @click="goToPage(props.pagination.current_page + 1)"
                    />
                </PaginationContent>
            </Pagination>
        </div>

        <!-- Dialogs -->
        <EditDialog
            v-if="selectedUser"
            :user-id="selectedUser.id"
            :user-name="selectedUser.name"
            :user-email="selectedUser.email"
            :user-role="selectedUser.role"
            @close="selectedUser = null"
        />
        <DeleteDialog
            v-if="userToDelete"
            :user-id="userToDelete.id"
            :user-name="userToDelete.name"
            @close="userToDelete = null"
        />
        <ViewDialog
            v-if="userToView"
            :user-id="userToView.id"
            :user-name="userToView.name"
            :user-email="userToView.email"
            :user-role="userToView.role"
            @close="userToView = null"
        />
    </div>
</template>
