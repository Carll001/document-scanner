<script setup lang="ts">
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Client } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    client: Client,
}>();

</script>

<template>
<Head :title=" client.name ?? 'Client'" />
    <AppLayout>
        <div class="flex flex-col flex-1 gap-4 p-4">
            name: {{ client.name }}
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableCell>File</TableCell>
                        <TableCell>Year</TableCell>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        <TableCell>asdhgajhdas.pdf</TableCell>
                        <TableCell>2026</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <pre>{{ props }}</pre>
    </AppLayout>
</template>