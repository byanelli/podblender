@use(App\Http\Routes\Web)

@php
    $tabs = [
        'Feeds' => Web::home(),
    ];
@endphp

<x-layouts.shell :title="$title">
    <div class="min-h-full">
        <nav class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex">
                        <div class="flex flex-shrink-0 items-center">
                            <img class="block h-8 w-auto lg:hidden" src="https://tailwindui.com/img/logos/mark.svg?color=indigo&shade=600" alt="Your Company">
                            <img class="hidden h-8 w-auto lg:block" src="https://tailwindui.com/img/logos/mark.svg?color=indigo&shade=600" alt="Your Company">
                        </div>
                        <div class="hidden sm:-my-px sm:ml-6 sm:flex sm:space-x-8">
                            @foreach($tabs as $tab => $url)
                                @if ($tab == $currentTab)
                                    <a href="{{$url}}" class="border-indigo-500 text-gray-900 inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium" aria-current="page">
                                        {{$tab}}
                                    </a>
                                @else
                                    <a href="{{$url}}" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium">
                                        {{$tab}}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-4 sm:mt-0 sm:ml-6 sm:flex sm:items-center">
                        <a type="button" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            href="{{Web::logout()}}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                <path fill-rule="evenodd" d="M17 4.25A2.25 2.25 0 0 0 14.75 2h-5.5A2.25 2.25 0 0 0 7 4.25v2a.75.75 0 0 0 1.5 0v-2a.75.75 0 0 1 .75-.75h5.5a.75.75 0 0 1 .75.75v11.5a.75.75 0 0 1-.75.75h-5.5a.75.75 0 0 1-.75-.75v-2a.75.75 0 0 0-1.5 0v2A2.25 2.25 0 0 0 9.25 18h5.5A2.25 2.25 0 0 0 17 15.75V4.25Z" clip-rule="evenodd" />
                                <path fill-rule="evenodd" d="M14 10a.75.75 0 0 0-.75-.75H3.704l1.048-.943a.75.75 0 1 0-1.004-1.114l-2.5 2.25a.75.75 0 0 0 0 1.114l2.5 2.25a.75.75 0 1 0 1.004-1.114l-1.048-.943h9.546A.75.75 0 0 0 14 10Z" clip-rule="evenodd" />
                            </svg>

                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <div class="py-10">
            <header>
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <h1 class="text-3xl font-bold leading-tight tracking-tight text-gray-900">{{$title}}</h1>
                </div>
            </header>
            <main>
                <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {{$slot}}
                </div>
            </main>
        </div>
    </div>
</x-layouts.shell>
