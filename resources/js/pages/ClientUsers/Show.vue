<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

// shadcn-vue
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {
  Card, CardContent, CardDescription, CardHeader, CardTitle,
} from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'

import { ArrowLeft, Pencil } from 'lucide-vue-next'
import clientUsersLink from '@/routes/clients/users'

type UserRole = 'client' | 'registrar' | 'super_admin'

type UserRow = {
  id: number
  name: string
  email: string
  role: UserRole
  created_at: string | null
}

const props = defineProps<{
  user: { data: UserRow } | UserRow
}>()

const user = computed<UserRow>(() => {
  return (props.user as any).data ? (props.user as any).data : (props.user as UserRow)
})

const openEdit = ref(false)

const form = useForm({
  name: user.value.name,
  email: user.value.email,
  password: '',
  password_confirmation: '',
  role: 'client' as UserRole, // enforce client for this module
})

function openEditDialog() {
  form.name = user.value.name
  form.email = user.value.email
  form.password = ''
  form.password_confirmation = ''
  form.role = 'client'
  openEdit.value = true
}

function submitUpdate() {
  form.put(clientUsersLink.update.url({ user: user.value.id }), {
    onSuccess: () => {
      openEdit.value = false
      form.password = ''
      form.password_confirmation = ''
    },
  })
}

const displayRole = computed(() => user.value.role.replace('_', ' '))

const initials = computed(() => {
  const parts = (user.value.name || '').trim().split(/\s+/).slice(0, 2)
  return parts.map(p => p[0]?.toUpperCase()).join('') || 'U'
})

const formattedCreatedAt = computed(() => {
  if (!user.value.created_at) return '-'
  // If your backend sends ISO string, this will display nicely.
  const d = new Date(user.value.created_at)
  if (Number.isNaN(d.getTime())) return user.value.created_at
  return d.toLocaleString()
})
</script>

<template>
  <Head title="View Client User" />

  <AppLayout>
    <div class="p-6 space-y-6">
      <!-- Top bar -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              class="px-2"
              @click="router.get(clientUsersLink.index.url())"
            >
              <ArrowLeft class="h-4 w-4 mr-1" />
              Back
            </Button>

            <Separator orientation="vertical" class="h-5" />

            <div class="text-sm text-muted-foreground">
              Client Users
              <span class="mx-1">/</span>
              <span class="text-foreground font-medium">View</span>
            </div>
          </div>

          <h1 class="text-2xl font-semibold leading-tight">User Details</h1>
          <p class="text-sm text-muted-foreground">
            Review user information and update details when needed.
          </p>
        </div>

        <div class="flex gap-2">
          <Button variant="outline" @click="router.get(clientUsersLink.index.url())">
            Back to list
          </Button>
          <Button @click="openEditDialog">
            <Pencil class="h-4 w-4 mr-2" />
            Edit
          </Button>
        </div>
      </div>

      <!-- Profile + Details -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <!-- Left: profile card -->
        <Card class="lg:col-span-1">
          <CardHeader>
            <CardTitle>Profile</CardTitle>
            <CardDescription>Basic identity overview</CardDescription>
          </CardHeader>

          <CardContent class="space-y-4">
            <div class="flex items-center gap-4">
              <div class="h-12 w-12 rounded-full border flex items-center justify-center font-semibold">
                {{ initials }}
              </div>

              <div class="min-w-0">
                <div class="font-medium truncate">{{ user.name }}</div>
                <div class="text-sm text-muted-foreground truncate">{{ user.email }}</div>
              </div>
            </div>

            <Separator />

            <div class="flex items-center justify-between">
              <div class="text-sm text-muted-foreground">Role</div>
              <Badge variant="secondary" class="capitalize">
                {{ displayRole }}
              </Badge>
            </div>

            <div class="flex items-center justify-between">
              <div class="text-sm text-muted-foreground">User ID</div>
              <div class="text-sm font-medium">#{{ user.id }}</div>
            </div>
          </CardContent>
        </Card>

        <!-- Right: details card -->
        <Card class="lg:col-span-2">
          <CardHeader>
            <CardTitle>Details</CardTitle>
            <CardDescription>Account information</CardDescription>
          </CardHeader>

          <CardContent class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div class="space-y-1">
                <div class="text-xs text-muted-foreground">Full name</div>
                <div class="rounded-md border px-3 py-2 text-sm">{{ user.name }}</div>
              </div>

              <div class="space-y-1">
                <div class="text-xs text-muted-foreground">Email address</div>
                <div class="rounded-md border px-3 py-2 text-sm">{{ user.email }}</div>
              </div>

              <div class="space-y-1">
                <div class="text-xs text-muted-foreground">Role</div>
                <div class="rounded-md border px-3 py-2 text-sm capitalize">{{ displayRole }}</div>
              </div>

              <div class="space-y-1">
                <div class="text-xs text-muted-foreground">Created at</div>
                <div class="rounded-md border px-3 py-2 text-sm">{{ formattedCreatedAt }}</div>
              </div>
            </div>

            <div class="rounded-lg border p-4">
              <div class="text-sm font-medium">Tip</div>
              <p class="text-sm text-muted-foreground mt-1">
                Leave password fields blank if you don’t want to change the password.
              </p>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Edit dialog -->
      <Dialog v-model:open="openEdit">
        <DialogContent class="sm:max-w-[560px]">
          <DialogHeader>
            <DialogTitle>Edit User</DialogTitle>
            <DialogDescription>
              Update name/email. Password is optional when updating.
            </DialogDescription>
          </DialogHeader>

          <div class="space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
            </div>

            <Separator />

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div class="space-y-1">
                <label class="text-sm">New Password</label>
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
            </div>
          </div>

          <DialogFooter class="gap-2">
            <Button variant="outline" @click="openEdit = false">Cancel</Button>
            <Button :disabled="form.processing" @click="submitUpdate">
              Save changes
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>