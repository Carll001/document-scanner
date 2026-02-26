<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import usersRoutes from '@/routes/clients/users'
import EditDialog from '@/components/ClientUser/EditDialog.vue'

import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'

import { Mail, Pencil, IdCard, Shield, ArrowLeft } from 'lucide-vue-next'

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

const editingUser = ref<UserRow | null>(null)

const u = computed<UserRow>(() => {
  return (props.user as any).data ? (props.user as any).data : (props.user as UserRow)
})

const roleLabel = computed(() => {
  const r = (u.value.role || '').toLowerCase()
  return (r === 'super_admin' ? 'registrar' : r).replace('_', ' ')
})

const roleBadgeClass = computed(() => {
  const r = (u.value.role || '').toLowerCase()
  if (r === 'super_admin') return 'bg-purple-500/10 text-purple-700 ring-purple-600/20'
  if (r === 'registrar') return 'bg-amber-500/10 text-amber-700 ring-amber-600/20'
  return 'bg-blue-500/10 text-blue-700 ring-blue-600/20'
})

const avatarClass = computed(() => {
  const r = (u.value.role || '').toLowerCase()
  if (r === 'super_admin') return 'bg-purple-500/10 ring-purple-600/20'
  if (r === 'registrar') return 'bg-amber-500/10 ring-amber-600/20'
  return 'bg-blue-500/10 ring-blue-600/20'
})

const initials = computed(() => {
  const parts = (u.value.name || '').trim().split(/\s+/).filter(Boolean)
  const a = parts[0]?.[0] ?? ''
  const b = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : ''
  return (a + b).toUpperCase()
})

// ✅ DATE ONLY (no time)
const formattedDate = computed(() => {
  const raw = u.value.created_at
  if (!raw) return '-'

  const d = new Date(raw)
  if (Number.isNaN(d.getTime())) return '-'

  // example output: February 26, 2026
  return d.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
})

const goBack = () => router.visit(usersRoutes.index.url())
const goEdit = () => {
  editingUser.value = { ...u.value }
}
</script>

<template>
  <Head title="View Client User" />

  <AppLayout>
    <div class="flex flex-col gap-4 space-y-4 p-4">
      <section class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold tracking-tight">User Details</h1>
          <p class="text-muted-foreground">View user details and information.</p>
        </div>

        <div class="flex items-center gap-2">
          <Button variant="outline" class="gap-2" @click="goBack">
            <ArrowLeft class="h-4 w-4" />
            Back
          </Button>
          <Button class="gap-2" @click="goEdit">
            <Pencil class="h-4 w-4" />
            Edit
          </Button>
        </div>
      </section>

      <div class="rounded-2xl border bg-gradient-to-br from-muted/60 via-background to-muted/30">
        <div class="px-6 py-6">
          <div class="flex items-start gap-4">
            <div
              class="flex h-14 w-14 items-center justify-center rounded-2xl ring-1 font-semibold text-lg"
              :class="avatarClass"
            >
              {{ initials }}
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                  <h2 class="text-xl font-semibold leading-tight">
                    {{ u.name }}
                  </h2>
                  <p class="text-sm text-muted-foreground mt-1">Client user profile</p>
                </div>

                <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1 w-fit"
                  :class="roleBadgeClass"
                >
                  {{ roleLabel }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div class="rounded-2xl border bg-background p-4 transition hover:shadow-sm">
          <div class="flex items-start gap-3">
            <div class="rounded-xl border bg-muted/40 p-2">
              <Mail class="h-4 w-4 text-muted-foreground" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-medium text-muted-foreground">Email</p>
              <p class="mt-1 text-sm break-all font-medium">
                {{ u.email }}
              </p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="rounded-2xl border bg-muted/15 p-4">
            <div class="flex items-start gap-3">
              <div class="rounded-xl border bg-muted/40 p-2">
                <Shield class="h-4 w-4 text-muted-foreground" />
              </div>
              <div>
                <p class="text-xs font-medium text-muted-foreground">Role</p>
                <p class="mt-2 text-sm font-semibold capitalize">
                  {{ roleLabel }}
                </p>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border bg-muted/15 p-4">
            <div class="flex items-start gap-3">
              <div class="rounded-xl border bg-muted/40 p-2">
                <IdCard class="h-4 w-4 text-muted-foreground" />
              </div>
              <div>
                <p class="text-xs font-medium text-muted-foreground">User ID</p>
                <p class="mt-2 text-sm font-semibold">#{{ u.id }}</p>
              </div>
            </div>
          </div>
        </div>

        <Separator />

        <div class="rounded-2xl border bg-muted/10 p-4">
          <p class="text-xs font-medium text-muted-foreground">Created At</p>
          <p class="mt-2 text-sm font-semibold">
            {{ formattedDate }}
          </p>
        </div>
      </div>
    </div>

    <EditDialog
      v-if="editingUser"
      :user-id="editingUser.id"
      :user-name="editingUser.name"
      :user-email="editingUser.email"
      :user-role="editingUser.role"
      @close="editingUser = null"
    />
  </AppLayout>
</template>
