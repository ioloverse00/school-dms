<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login — School DMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/css/tailwind.output.css') }}" />
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
    <script src="{{ asset('assets/js/init-alpine.js') }}"></script>
    <style>
        .login-bg { background: linear-gradient(135deg, #5b21b6 0%, #7c3aed 60%, #8b5cf6 100%); }
        .orb { position: absolute; border-radius: 9999px; background: rgba(255,255,255,0.08); }
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900" style="min-height:100vh; display:flex; align-items:center; justify-content:center;">

    <div style="display:flex; width:100%; max-width:880px; min-height:500px; border-radius:1rem; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.12); margin:1.5rem;">

        {{-- Left — branding panel --}}
        <div class="login-bg" style="position:relative; width:42%; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:2.5rem 2rem; overflow:hidden;">
            <div class="orb" style="width:260px;height:260px;top:-80px;left:-80px;"></div>
            <div class="orb" style="width:180px;height:180px;bottom:-60px;right:-50px;"></div>
            <div class="orb" style="width:90px;height:90px;top:55%;left:8%;"></div>

            <div style="position:relative;z-index:1;text-align:center;color:#fff;">
                {{-- Icon --}}
                <div style="width:72px;height:72px;background:rgba(255,255,255,0.18);border-radius:9999px;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;">
                    <svg style="width:36px;height:36px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>

                <h1 style="font-size:1.6rem;font-weight:800;letter-spacing:-0.02em;margin-bottom:0.3rem;">School DMS</h1>
                <p style="font-size:0.8rem;color:rgba(255,255,255,0.75);margin-bottom:2rem;">Document Management System</p>

                <div style="border-top:1px solid rgba(255,255,255,0.2);padding-top:1.5rem;display:flex;flex-direction:column;gap:0.875rem;text-align:left;">
                    @foreach([
                        ['icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'label' => 'Submit document requests online'],
                        ['icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'label' => 'Track request status in real time'],
                        ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'label' => 'Priority-based processing queue'],
                    ] as $item)
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <div style="width:30px;height:30px;background:rgba(255,255,255,0.15);border-radius:0.5rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                            </svg>
                        </div>
                        <span style="font-size:0.78rem;color:rgba(255,255,255,0.85);">{{ $item['label'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Right — form panel --}}
        <div class="bg-white dark:bg-gray-800" style="flex:1;display:flex;align-items:center;justify-content:center;padding:2.5rem 2.25rem;">
            <div style="width:100%;max-width:340px;">

                <div style="margin-bottom:1.75rem;">
                    <h2 class="text-gray-800 dark:text-gray-200" style="font-size:1.5rem;font-weight:700;margin-bottom:0.3rem;">Welcome back</h2>
                    <p class="text-gray-500 dark:text-gray-400" style="font-size:0.875rem;">Sign in to your account to continue.</p>
                </div>

                @if(session('status'))
                <div class="p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">{{ session('status') }}</div>
                @endif

                @if($errors->any())
                <div class="p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    {{-- Student ID / Email --}}
                    <label class="block text-sm text-gray-700 dark:text-gray-400" style="margin-bottom:0.25rem;">
                        <span class="text-gray-700 dark:text-gray-400" style="font-weight:500;">Student ID</span>
                        <input id="email" name="email" type="text"
                            value="{{ old('email') }}" required autofocus autocomplete="username"
                            class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 dark:focus:shadow-outline-gray form-input @error('email') border-red-600 @enderror" />
                    </label>

                    {{-- Password --}}
                    <label class="block mt-4 text-sm text-gray-700 dark:text-gray-400" style="margin-bottom:0.25rem;">
                        <span class="text-gray-700 dark:text-gray-400" style="font-weight:500;">Password</span>
                        <input id="password" name="password" type="password"
                            required autocomplete="current-password"
                            class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 dark:focus:shadow-outline-gray form-input @error('password') border-red-600 @enderror" />
                    </label>

                    {{-- Forgot password --}}
                    <div class="flex mt-4" style="justify-content:flex-end;">
                        @if(Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                            class="text-sm text-purple-600 dark:text-purple-400 hover:underline" style="font-weight:500;">
                            Forgot password?
                        </a>
                        @endif
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                        class="flex items-center justify-center w-full mt-6 px-4 py-3 text-sm font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple">
                        Sign in
                    </button>
                </form>
            </div>
        </div>

    </div>
</body>
</html>
