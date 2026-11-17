<x-guest-layout>
    @if(session('status'))<div class="portal-auth-status" role="status">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div style="margin-bottom:20px">
            <label for="email" class="form-label">Student ID or email</label>
            <input id="email" name="email" type="text" class="form-control-custom spark-field form-control" value="{{ old('email') }}" required autofocus autocomplete="username" @error('email') aria-invalid="true" @enderror>
        </div>
        <div style="margin-bottom:18px">
            <label for="password" class="form-label">Password</label>
            <div x-data="{ showPassword: false }" style="position:relative">
                <input id="password" name="password" type="password" :type="showPassword ? 'text' : 'password'" class="form-control-custom spark-field form-control" style="padding-right:48px" required autocomplete="current-password" @error('password') aria-invalid="true" @enderror>
                <button type="button" class="portal-password-toggle" @click="showPassword = !showPassword" :title="showPassword ? 'Hide password' : 'Show password'" title="Show password" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword.toString()" aria-label="Show password" aria-pressed="false" aria-controls="password">
                    <i class="bi" :class="showPassword ? 'bi-eye' : 'bi-eye-slash'" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        @if($errors->any())
            <div class="portal-auth-errors" role="alert" style="padding:10px 12px;font-size:12px;margin-bottom:16px">
                {{ $errors->first() }}
            </div>
        @endif
        <div style="display:flex;justify-content:flex-end;margin-bottom:24px">
            @if(Route::has('password.request'))<a href="{{ route('password.request') }}" class="portal-inline-link">Forgot password?</a>@endif
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Sign in</button>
    </form>
</x-guest-layout>
