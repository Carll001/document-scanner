<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import CreateDialog from '@/components/ClientUser/CreateDialog.vue';
import Table from '@/components/ClientUser/Table.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { User, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import usersRoutes from '@/routes/clients/users';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manage Users',
    },
];

const props = defineProps<{
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search: string;
        per_page: number;
        role: string;
    };
}>();

const searchQuery = ref(props.filters.search);
const roleFilter = ref(props.filters.role || '');

const getQueryParams = (
    page = 1,
    search: string | number = searchQuery.value,
    role: string = roleFilter.value,
) => {
    const query: Record<string, string | number> = {
        search: String(search ?? ''),
        per_page: props.filters.per_page,
        page,
    };

    if (role === 'client' || role === 'registrar') {
        query.role = role;
    }

    return query;
};

watch(
    () => props.filters.search,
    (search) => {
        searchQuery.value = search;
    },
);

watch(
    () => props.filters.role,
    (role) => {
        roleFilter.value = role || '';
    },
);

watch(searchQuery, (value) => {
    if (value === props.filters.search) return;
    debouncedHandleSearch(value);
});

const debouncedHandleSearch = useDebounceFn((value: string | number) => {
    router.get(
        usersRoutes.index.url({
            query: getQueryParams(1, value),
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'filters'],
        },
    );
});

watch(roleFilter, (value) => {
    const currentRole = props.filters.role || '';
    if (value === currentRole) return;

    router.get(
        usersRoutes.index.url({
            query: getQueryParams(1, searchQuery.value, value),
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'filters'],
        },
    );
});



</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="ManageUser" />

        <div class="flex flex-1 flex-col gap-4 p-4">
            <section>

                <div>
                    <h1>User Management</h1>
                </div>
            </section>
        
            <section>
                <div class="flex justify-end gap-2">

                    <!-- Right Side -->
                    <div class="relative w-64">
                        <Search
                            class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search..."
                            class="pr-8"
                        />
                    </div>
                    <div>
                    <Select v-model="roleFilter">
                        <SelectTrigger class="w-[180px]">
                            <SelectValue placeholder="Filter Role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Roles</SelectLabel>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="registrar">Registrar</SelectItem>
                                <SelectItem value="client">Client</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
                 <CreateDialog />
                </div>
            </section>
            <section>
                <div>
                    <Table
                        :users="props.users.data"
                        :pagination="props.users"
                        :filters="props.filters"
                    />
                </div>
            </section>
        </div>
    </AppLayout>
</template>
