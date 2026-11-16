@if ($paginator->hasPages())
<div class="portal-pagination grid px-4 py-3 text-xs font-semibold tracking-wide text-gray-500 uppercase border-t dark:border-gray-700 bg-gray-50 sm:grid-cols-9 dark:text-gray-400 dark:bg-gray-800">
    <span class="flex items-center col-span-3">
        Showing {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }} of {{ $paginator->total() }}
    </span>
    <span class="col-span-2"></span>
    {{-- Pagination --}}
    <span class="flex col-span-4 mt-2 sm:mt-auto sm:justify-end">
        <nav aria-label="Table navigation">
            <ul class="pagination pagination-sm mb-0 inline-flex items-center">
                {{-- Previous --}}
                <li>
                    @if ($paginator->onFirstPage())
                        <button disabled class="px-3 py-1 rounded-md rounded-l-lg focus:outline-none focus:shadow-outline-purple opacity-50 cursor-not-allowed" aria-label="Previous">
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-1 rounded-md rounded-l-lg focus:outline-none focus:shadow-outline-purple" aria-label="Previous">
                    @endif
                        <svg aria-hidden="true" class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                            <path d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" fill-rule="evenodd"></path>
                        </svg>
                    @if ($paginator->onFirstPage())
                        </button>
                    @else
                        </a>
                    @endif
                </li>

                {{-- Page numbers --}}
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li><span class="px-3 py-1">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <li>
                                @if ($page == $paginator->currentPage())
                                    <button class="btn btn-primary px-3 py-1 text-white transition-colors duration-150 bg-purple-600 border border-r-0 border-purple-600 rounded-md focus:outline-none focus:shadow-outline-purple">{{ $page }}</button>
                                @else
                                    <a href="{{ $url }}" class="px-3 py-1 rounded-md focus:outline-none focus:shadow-outline-purple">{{ $page }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach

                {{-- Next --}}
                <li>
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-1 rounded-md rounded-r-lg focus:outline-none focus:shadow-outline-purple" aria-label="Next">
                    @else
                        <button disabled class="px-3 py-1 rounded-md rounded-r-lg focus:outline-none focus:shadow-outline-purple opacity-50 cursor-not-allowed" aria-label="Next">
                    @endif
                        <svg class="w-4 h-4 fill-current" aria-hidden="true" viewBox="0 0 20 20">
                            <path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" fill-rule="evenodd"></path>
                        </svg>
                    @if ($paginator->hasMorePages())
                        </a>
                    @else
                        </button>
                    @endif
                </li>
            </ul>
        </nav>
    </span>
</div>
@endif
