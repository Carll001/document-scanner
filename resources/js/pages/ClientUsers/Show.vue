    <script setup lang="ts">
    import AfsTable from '@/components/ui/afs-scanner-components/AfsTable.vue';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
    import AppLayout from '@/layouts/AppLayout.vue';
    import { User } from '@/types';
import { PaginatedResponse } from '@/types/paginated-response';
    import { Head } from '@inertiajs/vue3';

    const props = defineProps<{
        user: { data: User }
        generatedFiles: PaginatedResponse
    }>();
</script>
    <template>

        <Head />
        <AppLayout>
            <div class="flex flex-col flex-1 gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ props.user.data.name }}</CardTitle>
                        <CardDescription>{{ props.user.data.email }}</CardDescription>
                    </CardHeader>
                </Card>

                <div class="">
                    <AfsTable :generated-files="generatedFiles"/>
                </div>
            </div>
            <pre>{{ props }}</pre>
        </AppLayout>
    </template>