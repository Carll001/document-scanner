<script setup lang="ts">
import { ref } from 'vue';
import Button from '../button/Button.vue';
import Dialog from '../dialog/Dialog.vue';
import DialogClose from '../dialog/DialogClose.vue';
import DialogContent from '../dialog/DialogContent.vue';
import DialogDescription from '../dialog/DialogDescription.vue';
import DialogFooter from '../dialog/DialogFooter.vue';
import DialogHeader from '../dialog/DialogHeader.vue';
import DialogTitle from '../dialog/DialogTitle.vue';
import DialogTrigger from '../dialog/DialogTrigger.vue';

const props = withDefaults(defineProps<{
    triggerText?: string
    title?: string
    description?: string
    confirmText?: string
    cancelText?: string
    disabled?: boolean
}>(), {
    triggerText: 'Save and Regenerate',
    title: 'Are you sure you want to continue?',
    description: "This action will save the changes and regenerate the document.",
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    disabled: false,
})

const emit = defineEmits<{
    (e: 'confirm'): void
}>()

const open = ref(false)

const onConfirm = () => {
    emit('confirm')
    open.value = false
}

</script>

<template>
    <div>
        <Dialog v-model:open="open">
            <DialogTrigger asChild>
                <Button :disabled="props.disabled" class="bg-blue-700 hover:bg-blue-800 text-white">
                    {{ props.triggerText }}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{ props.title }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ props.description }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose>
                        <Button variant="ghost" size="sm">{{ props.cancelText }}</Button>
                    </DialogClose>
                    <Button size="sm" @click="onConfirm">{{ props.confirmText }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
