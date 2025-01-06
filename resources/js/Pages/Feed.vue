<script setup lang="ts">

import {Head, router} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AddClipForm from "@/AppComponents/AddClipForm.vue";
import events from "@/events";
import {AudioClip, ClipMetadataResponse, Feed} from "@/types";
import moment from "moment/moment";
import axios, {AxiosResponse} from "axios";
import routes from "@/routes";
import {ref} from "vue";
import PanelList from "@/Components/PanelList.vue";
import PanelListItem from "@/Components/PanelListItem.vue";
import ErrorPanel from "@/AppComponents/ErrorPanel.vue";

const props = defineProps<{feed: Feed}>();

const reloadFeed = () => router.reload({only: ['feed']});

const errorMessage = ref<string>('');
const isLoading = ref<boolean>(false);

const deleteClip = (clip: AudioClip) => {
    if (!confirm(`Are you sure you want to delete ${clip.title}?`)) { return; }

    errorMessage.value = '';
    isLoading.value = true;

    axios.delete(routes.api.deleteClip(props.feed.id, clip.id))
        .then(() => {
            reloadFeed();

            isLoading.value = false;
        })
        .catch((error) => {
            isLoading.value = false;

            errorMessage.value = error.response.data.message ?? error.response.data.error;
        });
}

events.finishedProcessingClip(props.feed.id).listen(reloadFeed);

console.log(props.feed);

</script>

<template>

    <Head :title="feed.name" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{feed.name}}</h2>
        </template>

        <ErrorPanel v-if="errorMessage != ''"
                    :message="errorMessage"
                    operation="deleting your clip" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            <AddClipForm :feed-id="feed.id" @add-clip="reloadFeed"/>

            <PanelList>
                <PanelListItem v-for="clip in feed.audio_clips" :key="clip.id" :class="'flex items-center justify-between gap-x-6'">
                    <div class="min-w-0">
                        <div class="flex items-start gap-x-3">
                            <p class="text-sm font-semibold leading-6 text-gray-900">{{clip.title}}</p>
                            <p v-if="clip.processing_state.name == 'Processing'" class="rounded-md whitespace-nowrap mt-0.5 px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset text-yellow-800 bg-yellow-50 ring-yellow-600/20">
                                Processing
                            </p>
                            <p v-else-if="clip.processing_state.name == 'Processed'" class="rounded-md whitespace-nowrap mt-0.5 px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset text-green-700 bg-green-50 ring-green-600/20">
                                Processed
                            </p>
                            <p v-else-if="clip.processing_state.name == 'Unavailable'" class="rounded-md whitespace-nowrap mt-0.5 px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset text-gray-700 bg-green-50 ring-gray-600/20">
                                Unavailable
                            </p>
                            <p v-else>
                                Unknown (Error)
                            </p>
                            <p class="rounded-md whitespace-nowrap mt-0.5 px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset text-gray-600 bg-gray-50 ring-gray-500/10">
                                {{clip.audio_source.platform_type.name}}
                            </p>
                        </div>
                        <div class="mt-1 flex items-center gap-x-2 text-xs/5 text-gray-500">
                            <p class="whitespace-nowrap">
                                From {{clip.audio_source.name}}
                            </p>
                            <svg viewBox="0 0 2 2" class="size-0.5 fill-current">
                                <circle cx="1" cy="1" r="1" />
                            </svg>
                            <p class="whitespace-nowrap">
                                Published {{moment(clip.published_at).format('MMM Do YYYY')}}
                            </p>
                            <svg viewBox="0 0 2 2" class="size-0.5 fill-current">
                                <circle cx="1" cy="1" r="1" />
                            </svg>
                            <p class="whitespace-nowrap">
                                Added {{moment(clip.created_at).format('MMM Do YYYY')}}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-none items-center gap-x-4">
                        <button
                            @click="deleteClip(clip)"
                            class="hidden rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:block"
                            :disabled="isLoading"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </PanelListItem>
                <PanelListItem v-show="feed.audio_clips.length === 0">
                    No clips to display.
                </PanelListItem>
            </PanelList>
        </div>
    </AuthenticatedLayout>

</template>
