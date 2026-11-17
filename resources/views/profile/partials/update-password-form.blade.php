<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Use at least 8 characters with an uppercase letter, a lowercase letter, a number, and a special character.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6" data-password-validation data-check-url="{{ route('password.check') }}">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
            <div class="profile-password-field" x-data="{ visible: false }"><x-text-input id="update_password_current_password" name="current_password" type="password" x-bind:type="visible ? 'text' : 'password'" required class="mt-1 block w-full" autocomplete="current-password" /><button type="button" class="profile-password-toggle" @click="visible = !visible" :aria-label="visible ? 'Hide password' : 'Show password'" :title="visible ? 'Hide password' : 'Show password'" :aria-pressed="visible.toString()" aria-label="Show password" title="Show password" aria-controls="update_password_current_password"><i class="bi bi-eye-slash" :class="visible ? 'bi-eye' : 'bi-eye-slash'" aria-hidden="true"></i></button></div>
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            <p class="profile-live-error" data-current-feedback aria-live="polite"></p>
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <div class="profile-password-field" x-data="{ visible: false }"><x-text-input id="update_password_password" name="password" type="password" x-bind:type="visible ? 'text' : 'password'" required minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}" title="At least 8 characters including uppercase, lowercase, a number and a special character" class="mt-1 block w-full" autocomplete="new-password" /><button type="button" class="profile-password-toggle" @click="visible = !visible" :aria-label="visible ? 'Hide password' : 'Show password'" :title="visible ? 'Hide password' : 'Show password'" :aria-pressed="visible.toString()" aria-label="Show password" title="Show password" aria-controls="update_password_password"><i class="bi bi-eye-slash" :class="visible ? 'bi-eye' : 'bi-eye-slash'" aria-hidden="true"></i></button></div>
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            <p class="profile-live-error" data-strength-feedback aria-live="polite"></p>
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <div class="profile-password-field" x-data="{ visible: false }"><x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" x-bind:type="visible ? 'text' : 'password'" required class="mt-1 block w-full" autocomplete="new-password" /><button type="button" class="profile-password-toggle" @click="visible = !visible" :aria-label="visible ? 'Hide password' : 'Show password'" :title="visible ? 'Hide password' : 'Show password'" :aria-pressed="visible.toString()" aria-label="Show password" title="Show password" aria-controls="update_password_password_confirmation"><i class="bi bi-eye-slash" :class="visible ? 'bi-eye' : 'bi-eye-slash'" aria-hidden="true"></i></button></div>
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            <p class="profile-live-error" data-match-feedback aria-live="polite"></p>
        </div>

        <div class="profile-form-actions">
            <x-primary-button disabled>{{ __('Update Password') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 3000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
