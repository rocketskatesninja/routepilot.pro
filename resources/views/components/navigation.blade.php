@auth
    @if(auth()->user()->role === 'admin')
        <li>
            <a href="{{ route('reports.index') }}" class="nav-link">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h3m4 4v6a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v2"></path>
                </svg>
                Reports
            </a>
        </li>
    @endif
@endauth 