@use(App\Http\Routes\Web)
@use(App\Enums\Platform)

@php
/** @var string $url */
/** @var \App\Models\Feed $feed */
/** @var \App\YoutubeDownloader\Metadata $metadata */

$features = [
    'URL' => $url,
    'Platform' => Platform::YouTube->name,
    'Title' => $metadata->title,
    'Author' => $metadata->channel,
    'Description' => $metadata->description,
];
@endphp


<x-layouts.main title="Add clip to {{$feed->name}}" currentTab="Feeds">
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="">
                <dl class="divide-y divide-gray-100">
                    @foreach($features as $feature => $description)
                        <div class="px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                            <dt class="text-sm font-medium leading-6 text-gray-900">{{$feature}}</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">{{$description}}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <form action="{{Web::addClipToFeed($feed)}}" method="post">
                @csrf
                <input type="hidden" name="id" value="{{$metadata->id}}"/>
                <div class="mt-5">
                    <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">Add clip</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.main>
