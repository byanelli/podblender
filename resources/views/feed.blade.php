@use(App\Http\Routes\Web)

@php
    /** @var \App\Models\Feed $feed */
@endphp

<x-layouts.main :title="$feed->name" currentTab="Feeds">
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <form x-data x-validate action="{{Web::confirmAddClipToFeed($feed)}}" method="get">
                <h3 class="text-base font-semibold leading-6 text-gray-900">Add audio clip</h3>
                <div class="relative mt-2 rounded-md shadow-sm">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z" />
                            <path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z" />
                        </svg>
                    </div>
                    <input required name="url" id="url" class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="https://www.youtube.com/watch?v=9ntPxdWAWq8">
                </div>
                <div class="mt-5">
                    <button onclick="" type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">Change plan</button>
                </div>
            </form>
        </div>
    </div>
    <ul role="list" class="divide-y divide-gray-100">
        @foreach($feed->audioClips as $clip)
            <li class="flex items-center justify-between gap-x-6 py-5">
                <div class="min-w-0">
                    <div class="flex items-start gap-x-3">
                        <p class="text-sm font-semibold leading-6 text-gray-900">{{$clip->title}}</p>
                        @if ($clip->processing)
                            <p class="rounded-md whitespace-nowrap mt-0.5 px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset text-yellow-800 bg-yellow-50 ring-yellow-600/20">
                                Processing
                            </p>
                        @else
                            <p class="rounded-md whitespace-nowrap mt-0.5 px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset text-green-700 bg-green-50 ring-green-600/20">
                                Processed
                            </p>
                        @endif
                        <p class="rounded-md whitespace-nowrap mt-0.5 px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset text-gray-600 bg-gray-50 ring-gray-500/10">
                            {{$clip->platform->name}}
                        </p>
                    </div>
                    <div class="mt-1 flex items-center gap-x-2 text-xs leading-5 text-gray-500">
                        <p class="truncate">from {{$clip->audioSource->name}}</p>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
</x-layouts.main>
