<script setup lang="ts">

import {computed, ref} from "vue";
import {SourceMetadataResponse} from "@/types";
import axios, {AxiosResponse} from "axios";
import routes from "@/routes";

const emit = defineEmits<{ (e: 'createNewFeed'): void }>();

type NewFeedType = 'custom' | 'subscription';

const newFeedType = ref<NewFeedType>('custom');
const errorMessage = ref<string>('');
const name = ref<string>('');
const url = ref<string>('');

const resetForm = () => {
    name.value = '';
    url.value = '';
}

const isLoading = ref<boolean>(false);
const hasError = ref<boolean>(false);

const createSubscription = () => {
    isLoading.value = true;
    hasError.value = false;

    axios.post(routes.api.createSubscription, {
        name: name.value,
        url: url.value,
    })
        .then(() => {
            isLoading.value = false;
            resetForm();

            emit('createNewFeed');
        })
        .catch((error) => {
            isLoading.value = false;
            hasError.value = true;

            errorMessage.value = error.response.data.message ?? error.response.data.error;
        })
};

const createCustomFeed = () => {

    axios.post(routes.api.createCustomFeed, {
        name: name.value,
    })
        .then(() => {
            isLoading.value = false;
            resetForm();

            emit('createNewFeed');
        })
        .catch((error) => {
            isLoading.value = false;
            hasError.value = true;

            errorMessage.value = error.response.data.message ?? error.response.data.error;
        })
}

const createNewFeed = () => {
    if (newFeedType.value == 'custom') {
        createCustomFeed();
    } else if (newFeedType.value == 'subscription') {
        createSubscription();
    }
}
</script>

<template>
    <form @submit.prevent="createNewFeed">
        <div v-if="hasError" class="rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor"
                         aria-hidden="true">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">There was an error processing your URL</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <ul role="list" class="list-disc space-y-1 pl-5">
                            <li>{{ errorMessage }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <h3 class="text-base font-semibold leading-6 text-gray-900">Create new feed</h3>

        <div class="mt-6 space-y-6 sm:flex sm:items-center sm:space-x-10 sm:space-y-0">
            <div class="flex items-center">
                <input id="feed-type-custom"
                       name="custom"
                       type="radio"
                       :checked="newFeedType === 'custom'"
                       @click="newFeedType = 'custom'"
                       class="relative size-4 appearance-none rounded-full border border-gray-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white checked:border-indigo-600 checked:bg-indigo-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:border-gray-300 disabled:bg-gray-100 disabled:before:bg-gray-400 forced-colors:appearance-auto forced-colors:before:hidden [&:not(:checked)]:before:hidden" />
                <label for="feed-type-custom" class="ml-2 block text-sm/6 font-medium text-gray-900">
                    Custom
                </label>
                <p class="w-4"></p>
                <input id="feed-type-subscription"
                       name="subscription"
                       type="radio"
                       :checked="newFeedType === 'subscription'"
                       @click="newFeedType = 'subscription'"
                       class="relative size-4 appearance-none rounded-full border border-gray-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white checked:border-indigo-600 checked:bg-indigo-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:border-gray-300 disabled:bg-gray-100 disabled:before:bg-gray-400 forced-colors:appearance-auto forced-colors:before:hidden [&:not(:checked)]:before:hidden" />
                <label for="feed-type-subscription" class="ml-2 block text-sm/6 font-medium text-gray-900">
                    Subscription
                </label>
            </div>
        </div>

        <div class="relative mt-2 rounded-md shadow-sm">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                     class="w-5 h-5 text-gray-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </div>

            <input required name="name" id="name"
                   v-model="name"
                   class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                   placeholder="Name">
        </div>
        <div v-if="newFeedType == 'subscription'" class="relative mt-2 rounded-md shadow-sm">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                     class="w-5 h-5 text-gray-400">
                    <path
                        d="M12.232 4.232a2.5 2.5 0 0 1 3.536 3.536l-1.225 1.224a.75.75 0 0 0 1.061 1.06l1.224-1.224a4 4 0 0 0-5.656-5.656l-3 3a4 4 0 0 0 .225 5.865.75.75 0 0 0 .977-1.138 2.5 2.5 0 0 1-.142-3.667l3-3Z"/>
                    <path
                        d="M11.603 7.963a.75.75 0 0 0-.977 1.138 2.5 2.5 0 0 1 .142 3.667l-3 3a2.5 2.5 0 0 1-3.536-3.536l1.225-1.224a.75.75 0 0 0-1.061-1.06l-1.224 1.224a4 4 0 1 0 5.656 5.656l3-3a4 4 0 0 0-.225-5.865Z"/>
                </svg>

            </div>

            <input required name="url" id="url"
                   v-model="url"
                   class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                   placeholder="URL (YouTube channel URL)">
        </div>

        <div class="mt-5">
            <button type="submit"
                    :disabled="isLoading"
                    class="disabled:bg-gray-500 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
            >
                Add
                <span :hidden="!isLoading">
                    <svg aria-hidden="true"
                         class="ml-3 w-4 h-4 text-gray-200 animate-spin dark:text-gray-600 fill-white"
                         viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z"
                            fill="currentColor"/>
                        <path
                            d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z"
                            fill="currentFill"/>
                    </svg>
                </span>
            </button>
        </div>
    </form>
</template>
