<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>404 — Page Not Found | School DMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/css/tailwind.output.css') }}" />
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
    <script src="{{ asset('assets/js/init-alpine.js') }}"></script>
</head>

<body>
    <div class="flex items-center min-h-screen p-6 bg-gray-50 dark:bg-gray-900">
        <div class="flex flex-col items-center mx-auto text-center">

            <!-- 404 graphic -->
            <div class="flex items-center justify-center w-32 h-32 mb-6 bg-purple-100 rounded-full dark:bg-gray-800">
                <svg class="w-16 h-16 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>

            <!-- Number -->
            <h1 class="text-8xl font-extrabold text-purple-600 dark:text-purple-400 leading-none">404</h1>

            <!-- Heading -->
            <h2 class="mt-4 text-2xl font-bold text-gray-700 dark:text-gray-200">
                Page not found
            </h2>

            <!-- Description -->
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 max-w-sm">
                The page you're looking for doesn't exist or may have been moved.
                Try going back to the dashboard.
            </p>

            <!-- Actions -->
            <div class="flex gap-3 mt-8">
                @auth
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/dashboard' }}"
                    class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 transition-colors duration-150">
                    ← Go back
                </a>
                <a href="/dashboard"
                    class="px-5 py-2.5 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition-colors duration-150 focus:outline-none focus:shadow-outline-purple">
                    Go to Dashboard
                </a>
                @else
                <a href="{{ route('login') }}"
                    class="px-5 py-2.5 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition-colors duration-150 focus:outline-none focus:shadow-outline-purple">
                    Go to Login
                </a>
                @endauth
            </div>

            <!-- Branding -->
            <p class="mt-10 text-xs text-gray-400 dark:text-gray-600">School DMS &mdash; Document Management System</p>
        </div>
    </div>
</body>

</html>
