<!-- <script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '../ui/dialog'
import Button from '../ui/button/Button.vue'
import { Mail, Pencil, IdCard, Shield } from 'lucide-vue-next'

interface Props {
  userId: number
  userName: string
  userEmail: string
  userRole: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  close: []
  edit: [id: number]
}>()

const isOpen = ref(true)

watch(isOpen, (val) => {
  if (!val) emit('close')
})

const initials = computed(() => {
  const parts = (props.userName || '').trim().split(/\s+/).filter(Boolean)
  const a = parts[0]?.[0] ?? ''
  const b = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : ''
  return (a + b).toUpperCase()
})

const roleLabel = computed(() => {
  const r = (props.userRole || '').toLowerCase()
  if (r === 'super_admin') return 'Super Admin'
  if (r === 'admin') return 'Admin'
  if (r === 'registrar') return 'Registrar'
  if (r === 'client') return 'Client'
  return props.userRole
})

const roleBadgeClass = computed(() => {
  const r = (props.userRole || '').toLowerCase()
  if (r === 'super_admin') return 'bg-purple-500/10 text-purple-700 ring-purple-600/20'
  if (r === 'admin') return 'bg-red-500/10 text-red-700 ring-red-600/20'
  if (r === 'registrar') return 'bg-amber-500/10 text-amber-700 ring-amber-600/20'
  return 'bg-blue-500/10 text-blue-700 ring-blue-600/20'
})

const avatarClass = computed(() => {
  const r = (props.userRole || '').toLowerCase()
  if (r === 'super_admin') return 'bg-purple-500/10 ring-purple-600/20'
  if (r === 'admin') return 'bg-red-500/10 ring-red-600/20'
  if (r === 'registrar') return 'bg-amber-500/10 ring-amber-600/20'
  return 'bg-blue-500/10 ring-blue-600/20'
})

const handleEdit = () => emit('edit', props.userId)
</script>

<template>
  <Dialog v-model:open="isOpen">
    <DialogContent class="sm:max-w-xl p-0 overflow-hidden">
     
      <div class="border-b bg-gradient-to-br from-muted/60 via-background to-muted/30">
        <div class="px-6 pt-6 pb-6">
          <DialogHeader>
            <div class="flex items-start gap-4">
             
              <div
                class="flex h-14 w-14 items-center justify-center rounded-2xl ring-1 font-semibold text-lg"
                :class="avatarClass"
              >
                {{ initials }}
              </div>

              
              <div class="flex-1">
                <DialogTitle class="text-xl">
                  {{ userName }}
                </DialogTitle>

                <div class="mt-1 flex items-center justify-between gap-3">
                  <DialogDescription class="text-sm">
                    View the user profile information.
                  </DialogDescription>

                  <Button
                    size="sm"
                    variant="outline"
                    class="h-8 rounded-full gap-2"
                    type="button"
                    @click="handleEdit"
                  >
                    <Pencil class="h-4 w-4" />
                    Edit
                  </Button>
                </div>
              </div>
            </div>
          </DialogHeader>
        </div>
      </div>

      
      <div class="px-6 py-6 space-y-4">
      
        <div class="rounded-2xl border bg-background p-4 transition hover:shadow-sm">
          <div class="flex items-start gap-3">
            <div class="rounded-xl border bg-muted/40 p-2">
              <Mail class="h-4 w-4 text-muted-foreground" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-medium text-muted-foreground">Email</p>
              <p class="mt-1 text-sm break-all font-medium">
                {{ userEmail }}
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
                <span
                  class="mt-2 inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1"
                  :class="roleBadgeClass"
                >
                  {{ roleLabel }}
                </span>
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
                <p class="mt-2 text-sm font-semibold">
                  #{{ userId }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

     
      <DialogFooter class="px-6 py-4 border-t bg-muted/10">
        <DialogClose asChild>
          <Button type="button" variant="outline" class="rounded-full w-full sm:w-auto">
            Close
          </Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template> -->