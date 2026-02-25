<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCaption, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import clientData from '@/routes/clientData';
import { Client } from '@/types';
import { Head, router } from '@inertiajs/vue3';


const props = defineProps<{
    client: Client[],
}>();

</script>

<template>

    <Head title="Client Data" />
    <AppLayout>
        <div class="flex flex-col flex-1 gap-4 p-4">
            <Table>
                <TableCaption>A list of your recent invoices.</TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[100px]">
                            Client Name
                        </TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead class="text-right">
                            Action
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(client, index) in props.client">
                        <TableCell class="font-medium">
                            {{ client.name }}
                        </TableCell>
                        <TableCell class="">
                            {{ client.email }}
                        </TableCell>
                        <TableCell class="space-x-2 text-right">
                            <!-- <Button @click="router.visit(clientData.show(client.id).url)">View</Button> -->
                            <Button>Edit</Button>
                            <Button>Delete</Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
                <TableFooter>
                    <TableRow>
                        <TableCell colspan="2">
                            Total
                        </TableCell>
                        <TableCell class="text-right">
                            $2,500.00
                        </TableCell>
                    </TableRow>
                </TableFooter>
            </Table>
        </div>
    </AppLayout>
</template>