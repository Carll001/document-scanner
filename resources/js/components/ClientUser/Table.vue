<script setup lang="ts">
import { ref, computed } from 'vue';
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
import ViewDialog from './ViewDialog.vue';

import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';

import { UserRoundMinusIcon } from 'lucide-vue-next';

import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
    PaginationEllipsis,
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
        usersRoutes.index.url({ query }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'filters', 'pagination'],
        },
    );
};

const paginationPages = computed<(number | string)[]>(() => {
    const current = props.pagination.current_page;
    const last = props.pagination.last_page;
    const delta = 1; // show 1 page before/after current

    const range: number[] = [];
    const rangeWithDots: (number | string)[] = [];

    // Step 1: always include first, last, and pages around current
    for (let i = 1; i <= last; i++) {
        if (
            i === 1 || // first page
            i === last || // last page
            (i >= current - delta && i <= current + delta) // around current
        ) {
            range.push(i);
        }
    }

    // Step 2: insert ellipsis for gaps > 1
    let previous: number | null = null;
    for (const page of range) {
        if (previous !== null) {
            if (page - previous === 2) {
                rangeWithDots.push(previous + 1); // missing single page
            } else if (page - previous > 2) {
                rangeWithDots.push('...'); // gap >1 → ellipsis
            }
        }
        rangeWithDots.push(page);
        previous = page;
    }

    return rangeWithDots;
});
</script>

<template>
    <div class="flex h-[77svh] flex-1 flex-col gap-2 rounded-lg border p-2">
        <Table class="w-full">
            <TableHeader>
                <TableRow>
                    <TableHead class="w-16">#</TableHead>
                    <TableHead>Full Name</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Role</TableHead>
                    <TableHead class="pr-6 text-right"> Actions </TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow
                    v-for="(user, index) in users"
                    :key="user.id"
                    class="transition-colors hover:bg-muted/50"
                >
                    <TableCell>
                        {{ index + props.pagination.from }}
                    </TableCell>

                    <TableCell class="font-medium">
                        {{ user.name }}
                    </TableCell>

                    <TableCell class="text-muted-foreground">
                        {{ user.email }}
                    </TableCell>

                    <TableCell class="capitalize">
                        {{ user.role }}
                    </TableCell>

                    <TableCell class="space-x-2 text-right">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="userToView = user"
                        >
                            View
                        </Button>

                        <Button
                            variant="secondary"
                            size="sm"
                            @click="selectedUser = user"
                        >
                            Edit
                        </Button>

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
                    <TableCell colspan="5" class="py-10">
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <UserRoundMinusIcon />
                                </EmptyMedia>
                                <EmptyTitle> No Users Found </EmptyTitle>
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
        <div v-if="props.pagination.last_page > 1" class="mt-6 mt-auto ml-auto">
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

                    <template
                        v-for="(page, index) in paginationPages"
                        :key="index"
                    >
                        <PaginationEllipsis v-if="page === '...'" />

                        <PaginationItem
                            v-else
                            :value="page as number"
                            :is-active="page === props.pagination.current_page"
                            @click="goToPage(page as number)"
                        >
                            {{ page }}
                        </PaginationItem>
                    </template>

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
</template>
