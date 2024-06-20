<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import routes from "@/routes";
import AddClipForm from "@/Components/AddClipForm.vue";

type User = {
    feeds: Array<Feed>,
}

type Feed = {
    id: number,
    uuid: string,
    name: string,
    description: string,
}

const title: string = 'Feeds';

defineProps<{user: User}>()
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{title}}</h2>
        </template>

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-md bg-white shadow">
                <ul role="list" class="divide-y divide-gray-200">
                    <li v-for="feed in user.feeds" :key="feed.id" class="bg-white px-4 py-5 sm:px-6">
                        <div class="-ml-4 -mt-4 flex flex-wrap items-center justify-between sm:flex-nowrap">
                            <div class="ml-4 mt-4">
                                <div class="flex items-center">
                                    <div class="ml-4">
                                        <h3 class="text-base font-semibold leading-6 text-gray-900">
                                            <Link :href="routes.feed(feed.id)">{{feed.name}}</Link>
                                        </h3>
                                        <p class="text-sm text-gray-500">{{feed.description}}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="ml-4 mt-4 flex flex-shrink-0 space-x-2">
                                <Link :href="routes.feed(feed.id)" as="button" class="relative inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                        <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                                        <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" />
                                    </svg>
                                    <span>&nbsp;View</span>
                                </Link>
                                <a type="button" class="relative inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                      :href="routes.rss(feed.uuid)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                        <path d="M3.75 3a.75.75 0 0 0-.75.75v.5c0 .414.336.75.75.75H4c6.075 0 11 4.925 11 11v.25c0 .414.336.75.75.75h.5a.75.75 0 0 0 .75-.75V16C17 8.82 11.18 3 4 3h-.25Z" />
                                        <path d="M3 8.75A.75.75 0 0 1 3.75 8H4a8 8 0 0 1 8 8v.25a.75.75 0 0 1-.75.75h-.5a.75.75 0 0 1-.75-.75V16a6 6 0 0 0-6-6h-.25A.75.75 0 0 1 3 9.25v-.5ZM7 15a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z" />
                                    </svg>

                                    <span>&nbsp;RSS Feed</span>
                                </a>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
