<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import CreateDialog from '@/components/ClientUser/CreateDialog.vue';
import Table from '@/components/ClientUser/Table.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { User, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { route } from '@/lib/route';
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
    };
}>();

const searchQuery = ref(props.filters.search);

watch(
    () => props.filters.search,
    (search) => {
        searchQuery.value = search;
    },
);

const handleSearch = () => {
    router.get(
        route('clients.users.index'),
        {
            search: searchQuery.value,
            per_page: props.filters.per_page,
            page: 1,
        },
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
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="ManageUser" />

        <div class="flex flex-1 flex-col gap-4 p-4">
        
            <section>
                <div class="flex justify-end gap-2">
                   

                    <!-- Right Side -->
                    <div class="relative w-64">
                        <Search
                            class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            @input="handleSearch"
                            placeholder="Search..."
                            class="pr-8"
                        />
                    </div>
                    <div>
                    <Select>
                        <SelectTrigger class="w-[180px]">
                            <SelectValue placeholder="Filter Role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Roles</SelectLabel>
                                <SelectItem value="apple">Registrar</SelectItem>
                                <SelectItem value="banana">Client</SelectItem>
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
