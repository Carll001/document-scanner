<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

// shadcn-vue
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'

import clientUsersLink from '@/routes/clients/users' // wayfinder output

type UserRole = 'client' | 'registrar' | 'super_admin'

type UserRow = {
  id: number
  name: string
  email: string
  role: UserRole
  created_at: string | null
}

const props = defineProps<{
  filters: { search: string; per_page: number }
  users: {
    data: { data: UserRow[] } // UserResource::collection
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
}>()

const rows = computed(() => props.users.data.data)

const search = ref(props.filters.search ?? '')
const perPage = ref<number>(props.filters.per_page ?? 10)

watch([search, perPage], () => {
  router.get(
    clientUsersLink.index.url({ query: { search: search.value, per_page: perPage.value } }),
    {},
    { preserveState: true, replace: true }
  )
})

const open = ref(false)
const editing = ref<UserRow | null>(null)

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'client' as UserRole,
})

function openCreate() {
  editing.value = null
  form.reset()
  form.role = 'client'
  open.value = true
}

function openEdit(u: UserRow) {
  editing.value = u
  form.name = u.name
  form.email = u.email
  form.password = ''
  form.password_confirmation = ''
  form.role = u.role
  open.value = true
}

function submit() {
  if (!editing.value) {
    form.post(clientUsersLink.store.url(), {
      onSuccess: () => {
        open.value = false
        form.reset()
      },
    })
    return
  }

  form.put(clientUsersLink.update.url({ user: editing.value.id }), {
    onSuccess: () => {
      open.value = false
      form.reset()
    },
  })
}

function destroyUser(u: UserRow) {
  if (!confirm(`Delete ${u.name}?`)) return

  router.delete(clientUsersLink.destroy.url({ user: u.id }), {
    preserveScroll: true,
  })
}

function viewUser(u: UserRow) {
  // ✅ FIX: no wayfinder show yet, use direct url
  router.get(`/clients/users/${u.id}`)
}

const flash = computed(() => (usePage().props as any).flash)
</script>

<template>
  <Head title="Client Users" />

  <AppLayout>
    <div class="p-6 space-y-4">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold">Manage Client Users</h1>
          <p class="text-sm text-muted-foreground">Create/update client accounts and roles.</p>
        </div>

        <Button @click="openCreate">+ Add User</Button>
      </div>

      <div v-if="flash?.success" class="text-sm">
        {{ flash.success }}
      </div>

      <div class="flex flex-col md:flex-row gap-3 md:items-center">
        <Input v-model="search" placeholder="Search name/email..." class="md:max-w-sm" />

        <div class="flex items-center gap-2">
          <span class="text-sm text-muted-foreground">Per page</span>
          <Select v-model="perPage">
            <SelectTrigger class="w-[120px]">
              <SelectValue placeholder="Per page" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="5">5</SelectItem>
              <SelectItem :value="10">10</SelectItem>
              <SelectItem :value="15">15</SelectItem>
              <SelectItem :value="25">25</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div class="border rounded-lg overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Role</TableHead>
              <TableHead class="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>

          <TableBody>
            <TableRow v-for="u in rows" :key="u.id">
              <TableCell class="font-medium">{{ u.name }}</TableCell>
              <TableCell>{{ u.email }}</TableCell>
              <TableCell class="capitalize">{{ u.role.replace('_', ' ') }}</TableCell>
              <TableCell class="text-right space-x-2">
                <Button variant="secondary" size="sm" @click="viewUser(u)">View</Button>
                <Button variant="outline" size="sm" @click="openEdit(u)">Edit</Button>
                <Button variant="destructive" size="sm" @click="destroyUser(u)">Delete</Button>
              </TableCell>
            </TableRow>

            <TableRow v-if="rows.length === 0">
              <TableCell colspan="4" class="text-center text-sm text-muted-foreground py-10">
                No users found.
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <div class="flex items-center justify-between text-sm text-muted-foreground">
        <div>
          Showing {{ users.from ?? 0 }} - {{ users.to ?? 0 }} of {{ users.total }}
        </div>

        <div class="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            :disabled="users.current_page <= 1"
            @click="router.get(clientUsersLink.index.url({ query: { search, per_page: perPage, page: users.current_page - 1 } }), {}, { preserveState: true, replace: true })"
          >
            Prev
          </Button>
          <span>Page {{ users.current_page }} / {{ users.last_page }}</span>
          <Button
            variant="outline"
            size="sm"
            :disabled="users.current_page >= users.last_page"
            @click="router.get(clientUsersLink.index.url({ query: { search, per_page: perPage, page: users.current_page + 1 } }), {}, { preserveState: true, replace: true })"
          >
            Next
          </Button>
        </div>
      </div>

      <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-[520px]">
          <DialogHeader>
            <DialogTitle>{{ editing ? 'Edit User' : 'Create User' }}</DialogTitle>
            <DialogDescription>
              {{ editing ? 'Password is optional when updating.' : 'Password is required when creating.' }}
            </DialogDescription>
          </DialogHeader>

          <div class="space-y-3">
            <div class="space-y-1">
              <label class="text-sm">Name</label>
              <Input v-model="form.name" placeholder="Full name" />
              <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-1">
              <label class="text-sm">Email</label>
              <Input v-model="form.email" placeholder="Email" />
              <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
            </div>

            <div class="space-y-1">
              <label class="text-sm">Password</label>
              <Input v-model="form.password" type="password" placeholder="Min 8 chars" />
              <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
            </div>

            <div class="space-y-1">
              <label class="text-sm">Confirm Password</label>
              <Input v-model="form.password_confirmation" type="password" placeholder="Confirm password" />
              <p v-if="form.errors.password_confirmation" class="text-sm text-destructive">
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
                  <SelectItem value="client">Client</SelectItem>
                  <SelectItem value="registrar">Registrar</SelectItem>
                  <SelectItem value="super_admin">Super Admin</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.role" class="text-sm text-destructive">{{ form.errors.role }}</p>
            </div>
          </div>

          <DialogFooter class="gap-2">
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button :disabled="form.processing" @click="submit">
              {{ editing ? 'Update' : 'Create' }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>