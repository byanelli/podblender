@use(App\Http\Routes\Web)

@php
    /** @var \App\Models\Feed $feed */
@endphp

<x-layouts.main :title="$feed->name" currentTab="Feeds">
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <x-addClipForm state="form" :feed="$feed"/>
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
                            {{$clip->platform_type->name}}
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
