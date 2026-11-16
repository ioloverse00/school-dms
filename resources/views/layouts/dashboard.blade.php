<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" :data-bs-theme="dark ? 'dark' : 'light'" x-data="data()" lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', auth()->user()->hasRole('admin') ? 'Admin Portal' : (auth()->user()->hasRole('registrar') ? 'Registrar Portal' : 'Student Portal')) · School DMS</title>
    @include('partials.portal-styles')
    <script src="{{ asset('assets/js/init-alpine.js') }}"></script>
    <script src="{{ asset('assets/vendor/alpine/alpine.min.js') }}" defer></script>
</head>
<body class="portal-app">
@php
    $portalUser = auth()->user();
    $portalRole = $portalUser->hasRole('admin') ? 'admin' : ($portalUser->hasRole('registrar') ? 'registrar' : 'student');
    $portalBrand = ucfirst($portalRole) . ' Portal';
    $portalOffice = $portalRole === 'student' ? 'Student Services' : 'Office of the Registrar';
    $portalHome = $portalRole . '.dashboard';
    $archivedList = request()->routeIs($portalRole . '.requests.index') && request()->boolean('archived');
    $portalNav = [
        ['label' => 'Dashboard', 'route' => $portalHome, 'pattern' => $portalHome, 'icon' => 'grid'],
        ['label' => $portalRole === 'student' ? 'My requests' : 'Document requests', 'route' => $portalRole . '.requests.index', 'pattern' => $portalRole . '.requests.*', 'icon' => 'file'],
    ];
    if ($portalRole === 'student') {
        $portalNav[1]['pattern'] = ['student.requests.index', 'student.requests.show'];
    }
    if ($portalRole !== 'student') {
        $portalNav[] = ['label' => 'Archive', 'route' => $portalRole . '.requests.index', 'pattern' => '', 'icon' => 'archive', 'params' => ['archived' => 1]];
    }
    if ($portalRole !== 'student') {
        $portalNav[] = ['label' => 'Reports & analytics', 'route' => $portalRole.'.reports', 'pattern' => $portalRole.'.reports', 'icon' => 'chart'];
    }
    $searchStudents = request()->routeIs('admin.students.index', 'registrar.students.index');
    $searchTarget = $portalRole . ($searchStudents ? '.students.index' : '.requests.index');
    $searchTerm = is_string(request()->query('q')) ? request()->query('q') : '';
    $searchFilters = $searchStudents ? ['student_type'] : (request()->routeIs('admin.requests.index', 'registrar.requests.index') ? ($archivedList ? ['archived', 'document_type_id'] : ['status', 'document_type_id']) : []);
    if (request()->routeIs('student.requests.index')) {
        $searchFilters = ['status', 'document_type_id', 'urgency'];
    }
    $unreadCount = $portalUser->unreadNotifications()->count();
    $notificationFilter = request()->query('notification_filter') === 'unread' ? 'unread' : 'all';
    $portalNotifications = $portalUser->notifications()->when($notificationFilter === 'unread', fn ($query) => $query->whereNull('read_at'))->latest()->limit(8)->get();
@endphp
@include('partials.portal-icons')
<a href="#main-content" class="portal-skip">Skip to content</a>
<div class="portal-shell" :class="{ 'portal-menu-open': isSideMenuOpen, 'portal-sidebar-collapsed': isDesktopSidebarCollapsed }">
    <aside id="dashboard-sidebar" class="sidebar-wrapper dashboard-sidebar portal-sidebar" :class="{ 'is-open': isSideMenuOpen }"
        :role="isSideMenuOpen ? 'dialog' : 'complementary'" :aria-modal="isSideMenuOpen ? 'true' : null" aria-label="Main navigation"
        :inert="desktopViewport && isDesktopSidebarCollapsed ? true : null"
        @keydown.escape.window="closeSideMenu" @keydown.tab="trapSideMenuFocus($event)" @resize.window="desktopViewport = window.innerWidth >= 768; if (desktopViewport && isSideMenuOpen) closeSideMenu()">
        <button type="button" class="portal-close" @click="closeSideMenu" aria-label="Close navigation" title="Close navigation"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6h12M13 12h8M9 18h12M8 9l-3 3 3 3"/></svg></button>
        <a class="sidebar-brand portal-brand" href="{{ route($portalHome) }}"><span class="portal-seal"><i class="bi bi-asterisk" aria-hidden="true"></i></span><span><strong>{{ $portalBrand }}</strong><small>SCHOOL DOCUMENT SERVICES</small></span></a>
        <div class="sidebar-menu-title portal-nav-label">Workspace</div>
        <nav aria-label="Workspace">
            @foreach($portalNav as $item)
                @php $active = isset($item['params']) ? $archivedList : (request()->routeIs(...(array) $item['pattern']) && ! ($item['icon'] === 'file' && $archivedList)); @endphp
                <a class="sidebar-menu-link portal-nav-item {{ $active ? 'is-active active' : '' }}" href="{{ route($item['route'], $item['params'] ?? []) }}" @if($active) aria-current="page" @endif>
                    <i class="bi {{ ['grid'=>'bi-grid-fill','file'=>'bi-file-earmark-text','users'=>'bi-people','archive'=>'bi-archive','chart'=>'bi-bar-chart-line','plus'=>'bi-plus-circle'][$item['icon']] ?? 'bi-circle' }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
        @if($portalRole !== 'student')
            <div class="sidebar-menu-title portal-nav-label">Administration</div>
            <nav aria-label="Account administration">
                <a class="sidebar-menu-link portal-nav-item {{ request()->routeIs($portalRole . '.students.*') ? 'is-active active' : '' }}" href="{{ route($portalRole . '.students.index') }}" @if(request()->routeIs($portalRole . '.students.*')) aria-current="page" @endif><i class="bi bi-people" aria-hidden="true"></i><span>Students</span></a>
                @if($portalRole === 'admin')
                <a class="sidebar-menu-link portal-nav-item {{ request()->routeIs('admin.registrars.*') ? 'is-active active' : '' }}" href="{{ route('admin.registrars.index') }}" @if(request()->routeIs('admin.registrars.*')) aria-current="page" @endif><i class="bi bi-people" aria-hidden="true"></i><span>Registrars</span></a>
                @endif
            </nav>
        @endif
        @if($portalRole === 'admin')
            <nav aria-label="Settings">
                @foreach([
                    'admin.settings.programs' => ['Programs', 'bi-mortarboard'],
                    'admin.settings.academic-calendar' => ['Academic calendar', 'bi-calendar3'],
                    'admin.settings.document-management.document-weights' => ['Document types', 'bi-files'],
                    'admin.settings.document-management.student-weights' => ['Priority settings', 'bi-sliders'],
                ] as $target => [$label, $icon])
                    <a class="sidebar-menu-link portal-nav-item portal-nav-secondary {{ (request()->routeIs($target) || ($target === 'admin.settings.document-management.student-weights' && request()->routeIs('admin.settings.student-types'))) ? 'is-active active' : '' }}" href="{{ route($target) }}" @if((request()->routeIs($target) || ($target === 'admin.settings.document-management.student-weights' && request()->routeIs('admin.settings.student-types')))) aria-current="page" @endif><i class="bi {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></a>
                @endforeach
            </nav>
        @endif
        @if($portalRole === 'registrar')
            <a class="sidebar-menu-link portal-nav-item portal-nav-secondary {{ request()->routeIs('registrar.academic-calendar') ? 'is-active active' : '' }}" href="{{ route('registrar.academic-calendar') }}" @if(request()->routeIs('registrar.academic-calendar')) aria-current="page" @endif><i class="bi bi-calendar3" aria-hidden="true"></i><span>Academic calendar</span></a>
        @endif
        <div class="portal-sidebar-footer"><strong>{{ $portalOffice }}</strong><span>Document Management System</span><span class="portal-sidebar-role">{{ ucfirst($portalRole) }} workspace</span></div>
    </aside>
    <div x-cloak x-show="isSideMenuOpen" class="portal-backdrop" @click="closeSideMenu" aria-hidden="true"></div>
    <div class="main-wrapper portal-workspace" :inert="isSideMenuOpen ? true : null">
        <header class="navbar-custom portal-topbar">
            <button id="sidebar-toggle" type="button" class="portal-menu-toggle" @click="toggleNavigation" aria-label="Toggle sidebar" aria-controls="dashboard-sidebar" :aria-expanded="(desktopViewport ? !isDesktopSidebarCollapsed : isSideMenuOpen) ? 'true' : 'false'"><i class="bi bi-list" aria-hidden="true"></i></button>
            <form method="GET" action="{{ route($searchTarget) }}" role="search" class="portal-search">
                @foreach($searchFilters as $filter)
                    @if(is_string(request()->query($filter)))<input type="hidden" name="{{ $filter }}" value="{{ request()->query($filter) }}">@endif
                @endforeach
                <label class="visually-hidden" for="globalSearch">{{ $searchStudents ? 'Search all students' : 'Search all accessible requests' }}</label>
                <input class="form-control-custom spark-field form-control" type="search" name="q" id="globalSearch" maxlength="200" value="{{ $searchTerm }}" placeholder="{{ $searchStudents ? 'Search students…' : 'Search requests…' }}">
                <button type="submit" class="btn btn-outline-secondary">Search</button>
                @if($searchTerm !== '')<a class="portal-search-clear" href="{{ route($searchTarget, array_filter(request()->only($searchFilters), 'is_string')) }}">Clear</a>@endif
                @error('q')<span class="portal-field-error">{{ $message }}</span>@enderror
            </form>
            <div class="navbar-actions portal-top-actions">
                <time id="portal-navbar-clock" class="portal-navbar-clock" title="Philippine time (Asia/Manila)" datetime="{{ now('Asia/Manila')->toIso8601String() }}">{{ now('Asia/Manila')->format('h:i:s A | m-d-Y') }}</time>
                <button type="button" class="navbar-action-btn portal-icon-button" @click="toggleTheme" aria-label="Toggle color mode" :aria-pressed="dark ? 'true' : 'false'"><i class="bi bi-moon-stars" aria-hidden="true"></i></button>
                <div class="portal-dropdown-anchor" @keydown.escape="closeNotificationsMenu">
                    <button type="button" class="navbar-action-btn portal-icon-button" @click="toggleNotificationsMenu" aria-label="Notifications" aria-controls="portal-notifications" :aria-expanded="isNotificationsMenuOpen ? 'true' : 'false'"><i class="bi bi-bell" aria-hidden="true"></i>@if($unreadCount > 0)<span class="portal-notification-dot">{{ $unreadCount }}</span>@endif</button>
                    <div x-cloak x-show="isNotificationsMenuOpen" @click.away="closeNotificationsMenu" id="portal-notifications" x-transition.opacity.duration.150ms class="portal-dropdown portal-notifications">
                        <div class="portal-dropdown-heading"><strong>Notifications</strong></div>
<div id="portal-notification-panel" data-endpoint="{{ route('notifications.panel') }}">@include('partials.notification-panel')</div>
                    </div>
                </div>
                <div class="portal-dropdown-anchor" @keydown.escape="closeProfileMenu">
                    <button type="button" class="navbar-profile-btn portal-account" @click="toggleProfileMenu" aria-label="Account" aria-controls="portal-account-menu" :aria-expanded="isProfileMenuOpen ? 'true' : 'false'"><span class="navbar-profile-img portal-avatar">{{ mb_strtoupper(mb_substr($portalUser->name, 0, 1)) }}</span><span class="portal-account-copy"><strong>{{ $portalUser->name }}</strong><small>{{ ucfirst($portalRole) }}</small></span></button>
                    <div x-cloak x-show="isProfileMenuOpen" @click.away="closeProfileMenu" id="portal-account-menu" x-transition.opacity.duration.150ms class="portal-dropdown portal-account-menu"><p class="portal-dropdown-heading">{{ $portalUser->name }}</p><a class="portal-profile-link" href="{{ route('profile.edit') }}">Profile &amp; account</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="portal-logout">Log out</button></form></div>
                </div>
            </div>
        </header>
        <main id="main-content" class="spark-page portal-content" tabindex="-1">@yield('content')</main>
        <footer class="footer-custom portal-footer"><span>School Document Management System</span><span>{{ $portalOffice }} · {{ ucfirst($portalRole) }} workspace</span>@include('partials.spark-credit')</footer>
    </div>
</div>
@include('partials.feedback-toasts')
@include('partials.confirm-action')
<script src="{{ asset('assets/js/spark-portal.js') }}" defer></script>
@stack('scripts')
</body></html>
