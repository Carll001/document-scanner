<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import clients from '@/routes/clients';
import afs from '@/routes/afs';
import { dashboard } from '@/routes';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const props = defineProps<{
    totalClients: number;
    totalFiles: number;
    filesByStatus: Record<string, number>;
    recentFiles: Array<{
        id: number;
        company_name: string | null;
        original_name: string;
        status: string;
        created_at: string;
    }>;
}>();

const completedFiles = computed(() => props.filesByStatus.completed ?? 0);
const incompleteFiles = computed(() => props.filesByStatus.incomplete ?? 0);

const statusBadgeClass = (status: string) => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-700 border-green-200 dark:bg-green-500/20 dark:text-green-300 dark:border-green-500/30';
        case 'incomplete':
            return 'bg-yellow-100 text-yellow-700 border-yellow-200 dark:bg-yellow-500/20 dark:text-yellow-300 dark:border-yellow-500/30';
        case 'failed':
            return 'bg-red-100 text-red-700 border-red-200 dark:bg-red-500/20 dark:text-red-300 dark:border-red-500/30';
        default:
            return 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-500/20 dark:text-blue-300 dark:border-blue-500/30';
    }
};

const formatStatus = (status: string) =>
    status.charAt(0).toUpperCase() + status.slice(1);

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Link :href="clients.users.index.url()" class="block">
                    <Card class="h-full transition-colors hover:border-primary/50">
                        <CardHeader class="pb-2">
                            <CardDescription>Total Clients</CardDescription>
                            <CardTitle class="text-3xl">{{ totalClients }}</CardTitle>
                        </CardHeader>
                        <CardContent class="text-xs text-muted-foreground">
                            Click to view users list
                        </CardContent>
                    </Card>
                </Link>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Total Files</CardDescription>
                        <CardTitle class="text-3xl">{{ totalFiles }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-muted-foreground">
                        Completed + Incomplete
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Completed Files</CardDescription>
                        <CardTitle class="text-3xl">{{ completedFiles }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-muted-foreground">Count</CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Incomplete Files</CardDescription>
                        <CardTitle class="text-3xl">{{ incompleteFiles }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-xs text-muted-foreground">Count</CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
