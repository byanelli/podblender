<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{class?: string | Array<any> | Object}>();

const mergedClasses = computed(() => ["py-3", props.class ?? null]);
</script>

<template>
    <li :class="mergedClasses">
        <slot />
    </li>
</template>

<style scoped>

</style>
