<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, Link, router} from '@inertiajs/vue3';
import routes from "@/routes";
import AddSubscriptionForm from "@/AppComponents/AddSubscriptionForm.vue";
import Panel from "@/Components/Panel.vue";
import PanelList from "@/Components/PanelList.vue";
import PanelListItem from "@/Components/PanelListItem.vue";
import axios from "axios";
import {ref} from "vue";
import ErrorPanel from "@/AppComponents/ErrorPanel.vue";

type User = {
    feeds: Array<Feed>,
}

type Feed = {
    id: number,
    uuid: string,
    name: string,
    description: string,
    subscription_id: number|null,
    audio_clips_count: number,
}

const title: string = 'Feeds';

defineProps<{user: User}>()

const errorMessage = ref<string>('');
const isLoading = ref<boolean>(false);

const reloadUser = () => router.reload({only: ['user']});

const deleteFeed = (feed: Feed) => {
    if (!confirm(`Are you sure you want to delete ${feed.name}?`)) { return; }

    errorMessage.value = '';
    isLoading.value = true;

    axios.delete(routes.api.deleteFeed(feed.id))
        .then(() => {
            reloadUser();

            isLoading.value = false;
        })
        .catch((error) => {
            isLoading.value = false;

            errorMessage.value = error.response.data.message ?? error.response.data.error;
        });
};

</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
<!--        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{title}}</h2>
        </template>-->

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            <ErrorPanel v-if="errorMessage != ''"
                        :message="errorMessage"
                        operation="deleting your feed" />

            <Panel>
                <AddSubscriptionForm @createNewFeed="reloadUser" />
            </Panel>

            <PanelList>
                <PanelListItem
                    v-for="feed in user.feeds"
                    :key="feed.id"
                    :class="'flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-y-2 sm:gap-x-6'"
                >
                    <!-- First Div: Metadata -->
                    <div class="min-w-0">
                        <Link :href="routes.feed(feed.id)" class="flex items-start gap-x-3">
                            <p class="text-sm font-semibold leading-6 text-gray-900">{{ feed.name }}</p>
                        </Link>
                        <div class="mt-1 flex items-center gap-x-2 text-xs/5 text-gray-500">
                            <p class="whitespace-nowrap">{{ feed.subscription_id == null ? 'Custom' : 'Subscription' }}</p>
                            <svg viewBox="0 0 2 2" class="size-0.5 fill-current">
                                <circle cx="1" cy="1" r="1" />
                            </svg>
                            <p class="whitespace-nowrap">{{ feed.audio_clips_count }} clips <!--todo: pluralize--></p>
                        </div>
                    </div>

                    <!-- Second Div: Buttons -->
                    <div class="flex flex-none items-center gap-x-4">
                        <a
                            target="_blank"
                            class="inline-flex items-center space-x-2 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                            :href="routes.rss(feed.uuid)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12.75 19.5v-.75a7.5 7.5 0 0 0-7.5-7.5H4.5m0-6.75h.75c7.87 0 14.25 6.38 14.25 14.25v.75M6 18.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"
                                />
                            </svg>
                            <span>RSS</span>
                        </a>
                        <button
                            @click="deleteFeed(feed)"
                            class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                            :disabled="isLoading"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="size-5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                                />
                            </svg>
                        </button>
                    </div>
                </PanelListItem>
                <PanelListItem v-show="user.feeds.length === 0">
                    No feeds to display.
                </PanelListItem>
            </PanelList>
        </div>
    </AuthenticatedLayout>
</template>
