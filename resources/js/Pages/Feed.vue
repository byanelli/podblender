<script setup lang="ts">

import {Head, router} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AddClipForm from "@/Components/AddClipForm.vue";
import events from "@/events";
import {Feed} from "@/types";

const props = defineProps<{feed: Feed}>();

const reloadFeed = () => router.reload({only: ['feed']});

events.finishedProcessingClip(props.feed.id).listen(reloadFeed);

console.log(props.feed);

</script>

<template>

    <Head :title="feed.name" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{feed.name}}</h2>
        </template>


        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <AddClipForm :feed-id="feed.id" @add-clip="reloadFeed"/>

            <ul role="list" class="divide-y divide-gray-100">
                <li v-for="clip in feed.audio_clips" :key="clip.id" class="flex items-center justify-between gap-x-6 py-5">
                    <div class="min-w-0">
                        <div class="flex items-start gap-x-3">
                            <p class="text-sm font-semibold leading-6 text-gray-900">{{clip.title}}</p>
                            <p v-if="clip.processing" class="rounded-md whitespace-nowrap mt-0.5 px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset text-yellow-800 bg-yellow-50 ring-yellow-600/20">
                                Processing
                            </p>
                            <p v-else class="rounded-md whitespace-nowrap mt-0.5 px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset text-green-700 bg-green-50 ring-green-600/20">
                                Processed
                            </p>
                            <p class="rounded-md whitespace-nowrap mt-0.5 px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset text-gray-600 bg-gray-50 ring-gray-500/10">
                                {{clip.audio_source.platform_type.name}}
                            </p>
                        </div>
                        <div class="mt-1 flex items-center gap-x-2 text-xs leading-5 text-gray-500">
                            <p class="truncate">from {{clip.audio_source.name}}</p>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>

</template>
